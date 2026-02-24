<?php
/**
 * API: Subir y analizar documento Word
 * POST /api/analyze.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/WordProcessor.php';
require_once __DIR__ . '/../includes/AIProcessor.php';

use ScormConverter\WordProcessor;
use ScormConverter\AIProcessor;

// Capturar errores PHP para no romper JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('max_execution_time', 600); // 10 min para documentos grandes
set_time_limit(600);
header('Content-Type: application/json; charset=utf-8');

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => 'Error PHP: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
});

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Método no permitido');
}

try {
    // Verificar archivo
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
        ];
        
        $errorCode = $_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errorMsg = $errorMessages[$errorCode] ?? 'Error desconocido al subir el archivo';
        
        jsonResponse(false, null, $errorMsg);
    }
    
    $file = $_FILES['document'];
    
    // Validar extensión
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        jsonResponse(false, null, 'Solo se permiten archivos .docx');
    }
    
    // Validar tamaño
    if ($file['size'] > MAX_FILE_SIZE) {
        jsonResponse(false, null, 'El archivo excede el tamaño máximo de 50MB');
    }
    
    // Mover archivo a carpeta temporal
    $tempId = generateUniqueId();
    $tempPath = UPLOADS_PATH . '/' . $tempId . '.docx';
    
    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        jsonResponse(false, null, 'Error al guardar el archivo');
    }
    
    // Procesar documento Word
    $wordProcessor = new WordProcessor($tempPath);
    $documentData = $wordProcessor->process();
    $structure = $wordProcessor->detectStructure();
    $codeBlocks = $wordProcessor->extractCodeBlocks();
    
    // Fase 4: Extraer imágenes del Word
    $wordImages = $wordProcessor->getImages();
    logError('DEBUG Word images found: ' . count($wordImages));
    
    // Verificar si la API key está configurada
    $useAI = CLAUDE_API_KEY !== 'tu-api-key-aqui' && !empty(CLAUDE_API_KEY);
    
    $analysisResult = null;
    
    if ($useAI) {
        try {
            $aiProcessor = new AIProcessor();
            
            // ============================================================
            // NUEVO FLUJO: Estructuración por unidades
            // 1. Detectar unidades (desde estructura del Word)
            // 2. Por cada UD → structureUnit() (la IA organiza en secciones+bloques)
            // 3. Por cada UD → generateQuestions()
            // ============================================================
            
            // Paso 1: Obtener unidades del Word con su contenido
            $outline = $wordProcessor->buildStructuredOutline();
            logError('DEBUG outline: ' . count($outline['units']) . ' units detected, module="' . $outline['module_title'] . '"');
            
            // ============================================================
            // SMART MERGING: Si el documento es pequeño, reducir UDs
            // Un documento de <5000 chars no necesita 6 unidades.
            // Regla: ~5000 chars mínimo por UD. Si no llega, fusionar.
            // ============================================================
            $totalChars = $documentData['char_count'] ?? strlen($documentData['text']);
            $detectedUnits = count($outline['units']);
            $maxUnits = max(1, floor($totalChars / 5000));
            
            if ($detectedUnits > 1 && $detectedUnits > $maxUnits) {
                logError('DEBUG smart merge: doc=' . $totalChars . ' chars, detected=' . $detectedUnits . ' UDs, max=' . $maxUnits . ' -> merging');
                
                // Si el doc es muy pequeño (<8000 chars), forzar 1 sola UD
                if ($totalChars < 8000) {
                    $maxUnits = 1;
                }
                
                // Fusionar todas las UDs detectadas en $maxUnits UDs
                $mergedUnits = [];
                $chunkSize = max(1, ceil($detectedUnits / $maxUnits));
                $chunks = array_chunk($outline['units'], $chunkSize);
                
                foreach ($chunks as $ci => $chunk) {
                    $mergedContent = [];
                    $mergedSections = [];
                    $titles = [];
                    
                    foreach ($chunk as $u) {
                        $titles[] = $u['title'];
                        $mergedContent = array_merge($mergedContent, $u['content'] ?? []);
                        $mergedSections = array_merge($mergedSections, $u['sections'] ?? []);
                    }
                    
                    // Si solo hay 1 UD final, usar título inteligente (NO concatenar todos)
                    if (count($chunks) === 1) {
                        // Prioridad: module_title > structure title > nombre archivo limpio
                        // NO usar $titles[0] porque suele ser "Introducción" u otro heading genérico
                        $mergedTitle = $outline['module_title']
                            ?: ($structure['title'] ?? '');
                        
                        // Si no hay título formal, usar nombre del archivo (limpio)
                        if (empty($mergedTitle) || mb_strlen($mergedTitle) < 5) {
                            $mergedTitle = pathinfo($file['name'], PATHINFO_FILENAME);
                            $mergedTitle = str_replace(['_', '-'], ' ', $mergedTitle);
                            // Capitalizar: "los ordenadores gaming" -> "Los ordenadores gaming"
                            $mergedTitle = mb_strtoupper(mb_substr($mergedTitle, 0, 1)) . mb_substr($mergedTitle, 1);
                        }
                    } else {
                        $mergedTitle = implode(' - ', $titles);
                    }
                    
                    $mergedUnits[] = [
                        'number' => $ci + 1,
                        'title' => $mergedTitle,
                        'sections' => $mergedSections,
                        'content' => $mergedContent
                    ];
                }
                
                $outline['units'] = $mergedUnits;
                logError('DEBUG smart merge: result=' . count($mergedUnits) . ' UDs');
            }
            
            // Si no detectó unidades en el outline, usar PROMPT_ANALYZE clásico
            if (empty($outline['units'])) {
                logError('DEBUG: No outline units, falling back to full PROMPT_ANALYZE');
                $analysisResult = $aiProcessor->analyzeDocument($documentData['text']);
            } else {
                // Construir resultado con estructura del Word + IA por unidad
                $moduleTitle = $outline['module_title'] ?: $structure['title'] ?: 'Módulo formativo';
                $moduleCode = 'MOD_01';
                if (preg_match('/M[OÓ]DULO\s*(\w+)/iu', $documentData['text'], $mc)) {
                    $moduleCode = 'MOD_' . strtoupper(trim($mc[1]));
                }
                
                $units = [];
                $totalHours = DEFAULT_HOURS;
                $hoursPerUnit = max(1, floor($totalHours / count($outline['units'])));
                
                foreach ($outline['units'] as $ou) {
                    $unitNumber = $ou['number'];
                    $unitTitle = $ou['title'];
                    
                    // Reconstruir el texto completo de esta UD (secciones + subsecciones)
                    $unitText = $wordProcessor->outlineToText(['module_title' => '', 'units' => [$ou]]);
                    
                    logError('DEBUG structureUnit UD' . $unitNumber . ': "' . mb_substr($unitTitle, 0, 40) . '" (' . strlen($unitText) . ' chars)');
                    
                    // Paso 2: IA estructura esta unidad
                    try {
                        $structured = $aiProcessor->structureUnit($unitTitle, $unitText);
                        
                        $unitData = [
                            'numero' => $unitNumber,
                            'titulo' => $unitTitle,
                            'duracion' => $hoursPerUnit,
                            'resumen' => $structured['resumen'] ?? '',
                            'objetivos' => $structured['objetivos'] ?? [],
                            'secciones' => $structured['secciones'] ?? [],
                            'conceptos_clave' => $structured['conceptos_clave'] ?? [],
                            '_enriched' => true,
                            '_structured' => true
                        ];
                    } catch (\Exception $suEx) {
                        logError('Error structureUnit UD' . $unitNumber . ': ' . $suEx->getMessage());
                        // Fallback: secciones básicas
                        $unitContent = implode("\n\n", $ou['content'] ?? []);
                        foreach ($ou['sections'] as $sec) {
                            $unitContent .= "\n\n" . implode("\n\n", $sec['content'] ?? []);
                            foreach ($sec['subsections'] as $sub) {
                                $unitContent .= "\n\n" . implode("\n\n", $sub['content'] ?? []);
                            }
                        }
                        $unitData = [
                            'numero' => $unitNumber,
                            'titulo' => $unitTitle,
                            'duracion' => $hoursPerUnit,
                            'resumen' => buildSmartSummary($unitContent, $unitTitle),
                            'objetivos' => extractObjectives($unitContent, $unitTitle),
                            'secciones' => buildSectionsFromContent($unitContent, $unitTitle),
                            'conceptos_clave' => generateBasicFlashcards($unitTitle, $unitContent),
                            'preguntas' => generateBasicQuestions($unitTitle)
                        ];
                    }
                    
                    // Paso 3: Generar preguntas
                    if (!isset($unitData['preguntas'])) {
                        try {
                            $questions = $aiProcessor->generateQuestions(
                                $unitTitle,
                                $unitText,
                                $unitData['conceptos_clave'] ?? []
                            );
                            $unitData['preguntas'] = $questions;
                        } catch (\Exception $qe) {
                            logError('Error preguntas UD' . $unitNumber . ': ' . $qe->getMessage());
                            $unitData['preguntas'] = generateBasicQuestions($unitTitle);
                        }
                    }
                    
                    // Añadir bloques de código
                    $unitData['codigo'] = $wordProcessor->extractCodeBlocksForContent($unitText);
                    
                    $units[] = $unitData;
                }
                
                $analysisResult = [
                    'modulo' => [
                        'codigo' => $moduleCode,
                        'titulo' => $moduleTitle,
                        'duracion_total' => $totalHours
                    ],
                    'unidades' => $units
                ];
            }
            
            // Generar preguntas para las UDs que vengan del PROMPT_ANALYZE clásico
            if ($analysisResult && empty($analysisResult['unidades'][0]['preguntas'] ?? null)) {
                foreach ($analysisResult['unidades'] as &$unit) {
                    if (!empty($unit['preguntas'])) continue;
                    try {
                        $ucontent = '';
                        foreach ($unit['secciones'] ?? [] as $sec) {
                            foreach ($sec['contenido_estructurado'] ?? [] as $b) {
                                $ucontent .= ($b['texto'] ?? '') . "\n";
                                $ucontent .= implode("\n", $b['items'] ?? []) . "\n";
                            }
                        }
                        $unit['preguntas'] = $aiProcessor->generateQuestions(
                            $unit['titulo'], $ucontent, $unit['conceptos_clave'] ?? []
                        );
                    } catch (\Exception $qe) {
                        $unit['preguntas'] = generateBasicQuestions($unit['titulo']);
                    }
                }
            }
            
        } catch (\Exception $e) {
            logError('Error en procesamiento IA: ' . $e->getMessage());
            $useAI = false;
        }
    }
    
    // Si no hay IA o falló, usar análisis básico
    if (!$analysisResult) {
        $analysisResult = buildBasicAnalysis($structure, $documentData, $codeBlocks, $wordProcessor);
    }
    
    // Añadir metadatos
    $analysisResult['metadata'] = [
        'temp_id' => $tempId,
        'file_name' => $file['name'],
        'file_size' => $file['size'],
        'word_count' => $documentData['word_count'],
        'ai_enabled' => $useAI,
        'word_images_count' => count($wordImages)
    ];
    
    // Fase 4: guardar imágenes del Word en temp para el generador
    if (!empty($wordImages)) {
        $imgDir = TEMP_PATH . '/' . $tempId . '_images';
        $savedImgs = $wordProcessor->saveImagesToDir($imgDir);
        $analysisResult['metadata']['word_images_dir'] = $imgDir;
        $analysisResult['metadata']['word_images'] = array_keys($savedImgs);
        logError('DEBUG Word images saved to: ' . $imgDir . ' (' . count($savedImgs) . ' files)');
    }
    
    // Limpiar archivo temporal antiguo (mantener el actual para generación)
    cleanupTempFiles();
    
    jsonResponse(true, $analysisResult, 'Documento analizado correctamente');
    
} catch (\Exception $e) {
    logError('Error en analyze.php: ' . $e->getMessage());
    jsonResponse(false, null, 'Error al procesar el documento: ' . $e->getMessage());
}

/**
 * Construye un análisis básico sin IA
 */
function buildBasicAnalysis(array $structure, array $documentData, array $codeBlocks, $wordProcessor = null): array
{
    $moduleTitle = $structure['title'] ?: 'Módulo formativo';
    
    // Detectar código del archivo
    $moduleCode = 'MOD_01';
    if (preg_match('/([A-Z]+_M\d+)/i', $moduleTitle, $match)) {
        $moduleCode = strtoupper($match[1]);
    }
    
    // Detectar horas
    $totalHours = DEFAULT_HOURS;
    if (preg_match('/(\d+)\s*h/i', $documentData['text'], $match)) {
        $totalHours = (int)$match[1];
    }
    
    $units = [];
    
    if (!empty($structure['units'])) {
        $hoursPerUnit = max(1, floor($totalHours / count($structure['units'])));
        
        foreach ($structure['units'] as $unit) {
            $unitContent = implode("\n\n", $unit['content'] ?? []);
            
            // Generar secciones reales a partir del contenido
            $secciones = buildSectionsFromContent($unitContent, $unit['title']);
            
            // Extraer código específico de esta unidad
            $unitCodeBlocks = $wordProcessor ? $wordProcessor->extractCodeBlocksForContent($unitContent) : [];
            
            // Generar resumen real (primeras frases significativas)
            $resumen = buildSmartSummary($unitContent, $unit['title']);
            
            $units[] = [
                'numero' => $unit['number'],
                'titulo' => cleanBasicTitle($unit['title']),
                'duracion' => $hoursPerUnit,
                'resumen' => $resumen,
                'objetivos' => extractObjectives($unitContent, $unit['title']),
                'conceptos_clave' => generateBasicFlashcards($unit['title'], $unitContent),
                'secciones' => $secciones,
                'preguntas' => generateBasicQuestions($unit['title']),
                'codigo' => $unitCodeBlocks
            ];
        }
    } else {
        // Crear una unidad por defecto
        $allContent = $documentData['text'];
        $units[] = [
            'numero' => 1,
            'titulo' => 'Contenido del módulo',
            'duracion' => $totalHours,
            'resumen' => buildSmartSummary($allContent, 'Contenido del módulo'),
            'objetivos' => extractObjectives($allContent, 'Contenido del módulo'),
            'conceptos_clave' => generateBasicFlashcards('Contenido', $allContent),
            'secciones' => buildSectionsFromContent($allContent, 'Contenido'),
            'preguntas' => generateBasicQuestions('Contenido'),
            'codigo' => $wordProcessor ? $wordProcessor->extractCodeBlocksForContent($allContent) : []
        ];
    }
    
    return [
        'modulo' => [
            'codigo' => $moduleCode,
            'titulo' => cleanBasicTitle($moduleTitle),
            'duracion_total' => $totalHours
        ],
        'unidades' => $units
    ];
}

function cleanBasicTitle(string $title): string
{
    $title = preg_replace('/\(\d+h?\)/i', '', $title);
    $title = trim($title);
    return mb_strtoupper(mb_substr($title, 0, 1)) . mb_strtolower(mb_substr($title, 1));
}

function generateBasicFlashcards(string $title, string $content): array
{
    $flashcards = [
        ['termino' => 'Objetivo principal', 'definicion' => 'Dominar los conceptos fundamentales de ' . strtolower($title) . ' para su aplicación profesional.'],
        ['termino' => 'Competencia clave', 'definicion' => 'Capacidad de aplicar los conocimientos adquiridos en situaciones reales del entorno laboral.']
    ];
    
    // Intentar extraer términos del contenido
    preg_match_all('/([A-Z][a-záéíóúñ]+(?:\s+[a-záéíóúñ]+){0,2}):\s*([^.]+\.)/u', $content, $matches, PREG_SET_ORDER);
    
    foreach (array_slice($matches, 0, 4) as $match) {
        $flashcards[] = [
            'termino' => trim($match[1]),
            'definicion' => trim($match[2])
        ];
    }
    
    return array_slice($flashcards, 0, 6);
}

function generateBasicQuestions(string $title): array
{
    return [
        [
            'pregunta' => '¿Cuál es el objetivo principal de esta unidad sobre ' . strtolower($title) . '?',
            'opciones' => [
                'Solo memorizar conceptos teóricos',
                'Adquirir competencias teórico-prácticas aplicables',
                'Revisar contenido de módulos anteriores',
                'Ninguna de las anteriores'
            ],
            'correcta' => 1,
            'explicacion' => 'El objetivo es adquirir competencias que puedan aplicarse en el entorno profesional.'
        ],
        [
            'pregunta' => 'Para dominar esta unidad se recomienda:',
            'opciones' => [
                'Leer el contenido una sola vez',
                'Memorizar sin comprender',
                'Estudiar y practicar regularmente',
                'Omitir los ejercicios prácticos'
            ],
            'correcta' => 2,
            'explicacion' => 'El aprendizaje efectivo requiere práctica constante y comprensión profunda.'
        ],
        [
            'pregunta' => 'Los conocimientos de esta unidad contribuyen a:',
            'opciones' => [
                'Obtener información aislada',
                'Desarrollar competencias profesionales integrales',
                'Cumplir requisitos formales únicamente',
                'Ninguna de las anteriores'
            ],
            'correcta' => 1,
            'explicacion' => 'El contenido está diseñado para desarrollar competencias aplicables profesionalmente.'
        ],
        [
            'pregunta' => '¿Qué enfoque metodológico es más adecuado para esta unidad?',
            'opciones' => [
                'Aprendizaje pasivo y memorístico',
                'Aprendizaje activo con práctica',
                'Lectura rápida sin reflexión',
                'Estudio sin aplicación práctica'
            ],
            'correcta' => 1,
            'explicacion' => 'El aprendizaje activo y la práctica son fundamentales para la adquisición de competencias.'
        ]
    ];
}

/**
 * Construye secciones reales a partir del contenido de la unidad
 * Detecta subtítulos (líneas cortas seguidas de párrafos) para dividir en secciones
 */
function buildSectionsFromContent(string $content, string $unitTitle): array
{
    $secciones = [];
    $lines = preg_split('/\n+/', $content);
    
    $currentTitle = '';
    $currentContent = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Detectar subtítulos: líneas cortas que parecen encabezados
        $isHeading = false;
        if (mb_strlen($line) < 120) {
            // Patrón numérico: "1.1.", "2.3.", etc.
            if (preg_match('/^\d+\.\d+\.?\s+/', $line)) {
                $isHeading = true;
            }
            // Todo mayúsculas y corto
            elseif (mb_strlen($line) < 80 && preg_match('/^[A-ZÁÉÍÓÚÑ\s\d\.\-:]+$/', $line)) {
                $isHeading = true;
            }
        }
        
        if ($isHeading) {
            // Guardar sección anterior si tiene contenido
            if (!empty($currentContent)) {
                $secciones[] = [
                    'titulo' => $currentTitle ?: 'Introducción',
                    'contenido' => implode("\n\n", $currentContent)
                ];
            }
            // Limpiar título
            $currentTitle = preg_replace('/^\d+\.\d+\.?\s+/', '', $line);
            $currentContent = [];
        } else {
            $currentContent[] = $line;
        }
    }
    
    // Última sección
    if (!empty($currentContent)) {
        $secciones[] = [
            'titulo' => $currentTitle ?: 'Contenido',
            'contenido' => implode("\n\n", $currentContent)
        ];
    }
    
    // Si no se detectaron secciones, dividir contenido en bloques lógicos
    if (count($secciones) <= 1 && strlen($content) > 500) {
        $paragraphs = preg_split('/\n{2,}/', $content);
        $paragraphs = array_filter($paragraphs, function($p) { return strlen(trim($p)) > 50; });
        $paragraphs = array_values($paragraphs);
        
        $chunkSize = max(1, ceil(count($paragraphs) / 3));
        $chunks = array_chunk($paragraphs, $chunkSize);
        
        $sectionNames = ['Introducción y conceptos básicos', 'Desarrollo del contenido', 'Aplicación y profundización'];
        $secciones = [];
        foreach ($chunks as $i => $chunk) {
            $secciones[] = [
                'titulo' => $sectionNames[$i] ?? 'Sección ' . ($i + 1),
                'contenido' => implode("\n\n", $chunk)
            ];
        }
    }
    
    return $secciones ?: [['titulo' => 'Contenido', 'contenido' => $content]];
}

/**
 * Genera un resumen inteligente basado en las primeras frases significativas
 */
function buildSmartSummary(string $content, string $title): string
{
    // Extraer primeras frases significativas (no títulos, no código)
    $sentences = preg_split('/(?<=[.!?])\s+/', $content);
    $summary = [];
    $totalLen = 0;
    
    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        // Saltar líneas cortas (títulos), código, y líneas de solo mayúsculas
        if (strlen($sentence) < 30) continue;
        if (preg_match('/^[A-ZÁÉÍÓÚÑ\s\d\.\-:]+$/', $sentence)) continue;
        if (preg_match('/^(def |class |import |from |SELECT |CREATE )/', $sentence)) continue;
        
        $summary[] = $sentence;
        $totalLen += strlen($sentence);
        
        if ($totalLen > 350 || count($summary) >= 3) break;
    }
    
    if (empty($summary)) {
        return 'Esta unidad aborda los contenidos fundamentales sobre ' . mb_strtolower($title) . '.';
    }
    
    return implode(' ', $summary);
}

/**
 * Extrae objetivos reales del contenido o genera unos coherentes
 */
function extractObjectives(string $content, string $title): array
{
    $objectives = [];
    
    // Buscar líneas que parezcan objetivos
    if (preg_match_all('/(?:comprender|dominar|conocer|aplicar|desarrollar|implementar|configurar|crear|diseñar|analizar|utilizar|gestionar)\s+[^.\n]{10,100}[.]/ui', $content, $matches)) {
        foreach (array_slice($matches[0], 0, 4) as $obj) {
            $obj = trim($obj);
            $objectives[] = mb_strtoupper(mb_substr($obj, 0, 1)) . mb_substr($obj, 1);
        }
    }
    
    // Si no encontró suficientes, generar basados en el título y contenido
    if (count($objectives) < 3) {
        $titleLower = mb_strtolower($title);
        $defaults = [
            'Comprender los fundamentos teóricos de ' . $titleLower,
            'Aplicar los conceptos en situaciones prácticas reales',
            'Desarrollar habilidades profesionales en ' . $titleLower,
        ];
        foreach ($defaults as $d) {
            if (count($objectives) >= 4) break;
            $objectives[] = $d;
        }
    }
    
    return array_slice($objectives, 0, 5);
}

/**
 * Matching inteligente: asigna contenido del documento a cada UD de la IA
 * Estrategia:
 * 1. Si hay match directo por número (UDs formales) → usar ese
 * 2. Si hay capítulos → distribuir capítulos entre UDs por título/proximidad
 * 3. Fallback → dividir todo el texto equitativamente
 */
function matchUnitContents(array $aiUnits, array $structure): array
{
    $result = [];
    $matched = false;
    
    // Estrategia 1: Match directo por número de UD
    foreach ($aiUnits as $aiUnit) {
        $num = (int)$aiUnit['numero'];
        foreach ($structure['units'] as $su) {
            if ((int)$su['number'] === $num) {
                $content = implode("\n\n", $su['content'] ?? []);
                if (strlen($content) > 50) {
                    $result[$num] = $content;
                    $matched = true;
                }
            }
        }
    }
    
    // Si el match directo funcionó para todas las UDs, listo
    if ($matched && count($result) === count($aiUnits)) {
        logError('DEBUG matching: directo por número OK');
        return $result;
    }
    
    // Estrategia 2: Distribuir capítulos entre UDs por palabra clave del título
    $chapters = $structure['chapters'] ?? [];
    if (empty($chapters)) $chapters = $structure['units'] ?? [];
    
    if (!empty($chapters) && count($chapters) > count($aiUnits)) {
        logError('DEBUG matching: distribuyendo ' . count($chapters) . ' capítulos entre ' . count($aiUnits) . ' UDs');
        
        // Distribuir capítulos equitativamente entre UDs
        $chunkSize = max(1, ceil(count($chapters) / count($aiUnits)));
        $chunks = array_chunk($chapters, $chunkSize);
        
        foreach ($aiUnits as $idx => $aiUnit) {
            $num = (int)$aiUnit['numero'];
            if (isset($chunks[$idx])) {
                $contents = [];
                foreach ($chunks[$idx] as $ch) {
                    $contents[] = $ch['title'];
                    $contents = array_merge($contents, $ch['content'] ?? []);
                }
                $result[$num] = implode("\n\n", $contents);
            }
        }
        
        if (count($result) === count($aiUnits)) {
            return $result;
        }
    }
    
    // Estrategia 3: Fallback - dividir todo el texto equitativamente
    $allContent = $structure['all_content'] ?? [];
    if (!empty($allContent)) {
        logError('DEBUG matching: fallback dividiendo todo el texto (' . count($allContent) . ' párrafos)');
        
        // Filtrar párrafos significativos
        $meaningful = array_filter($allContent, function($p) {
            return strlen(trim($p)) > 20;
        });
        $meaningful = array_values($meaningful);
        
        $chunkSize = max(1, ceil(count($meaningful) / count($aiUnits)));
        $chunks = array_chunk($meaningful, $chunkSize);
        
        foreach ($aiUnits as $idx => $aiUnit) {
            $num = (int)$aiUnit['numero'];
            if (isset($chunks[$idx])) {
                $result[$num] = implode("\n\n", $chunks[$idx]);
            }
        }
    }
    
    return $result;
}

/**
 * Enriquece las secciones generadas por IA con el contenido real del documento
 * Distribuye los párrafos del Word entre las secciones según proximidad temática
 */
function enrichSectionsWithRealContent(array $secciones, string $unitContent): array
{
    // Dividir el contenido real en párrafos significativos
    // El contenido viene unido con \n\n desde implode de paragraphs
    $paragraphs = preg_split('/\n\n+/', $unitContent);
    $paragraphs = array_filter($paragraphs, function($p) {
        $p = trim($p);
        if (strlen($p) < 20) return false;
        // Filtrar títulos puros (todo mayúsculas y cortos)
        if (strlen($p) < 80 && preg_match('/^[A-ZÁÉÍÓÚÑ\s\d\.\-:]+$/', $p)) return false;
        // Filtrar encabezados de unidad
        if (preg_match('/^UNIDAD\s+DID/i', $p)) return false;
        return true;
    });
    $paragraphs = array_values($paragraphs);
    
    if (empty($paragraphs)) return $secciones;
    
    $numSections = count($secciones);
    if ($numSections === 0) return $secciones;
    
    // Distribuir párrafos equitativamente entre secciones
    $chunkSize = max(1, ceil(count($paragraphs) / $numSections));
    $chunks = array_chunk($paragraphs, $chunkSize);
    
    foreach ($secciones as $i => &$seccion) {
        if (isset($chunks[$i]) && !empty($chunks[$i])) {
            $realContent = implode("\n\n", $chunks[$i]);
            $existing = trim($seccion['contenido'] ?? '');
            
            // Usar contenido real siempre que exista
            if (!empty($realContent)) {
                $seccion['contenido'] = $realContent;
            }
        }
    }
    
    // Si quedaron párrafos sin asignar, añadirlos a la última sección
    $assignedChunks = min($numSections, count($chunks));
    for ($j = $assignedChunks; $j < count($chunks); $j++) {
        if (isset($chunks[$j])) {
            $lastIdx = $numSections - 1;
            $secciones[$lastIdx]['contenido'] .= "\n\n" . implode("\n\n", $chunks[$j]);
        }
    }
    
    return $secciones;
}
