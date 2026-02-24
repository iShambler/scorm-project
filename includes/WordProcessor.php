<?php
/**
 * Procesador de documentos Word
 * Extrae texto y estructura de archivos .docx
 */

namespace ScormConverter;

use ZipArchive;
use DOMDocument;

class WordProcessor
{
    private string $filePath;
    private string $rawText = '';
    private array $paragraphs = [];
    private array $tables = [];
    private array $styles = [];
    private array $relationships = [];  // rId -> target path
    private array $images = [];         // extracted images: [{filename, data, mime, rId}]
    
    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Archivo no encontrado: {$filePath}");
        }
        
        $this->filePath = $filePath;
    }
    
    /**
     * Procesa el documento y extrae todo el contenido
     */
    public function process(): array
    {
        $this->extractContent();
        
        return [
            'text' => $this->rawText,
            'paragraphs' => $this->paragraphs,
            'tables' => $this->tables,
            'word_count' => str_word_count($this->rawText),
            'char_count' => strlen($this->rawText)
        ];
    }
    
    /**
     * Extrae el contenido del archivo .docx
     */
    private function extractContent(): void
    {
        $zip = new ZipArchive();
        
        if ($zip->open($this->filePath) !== true) {
            throw new \Exception("No se pudo abrir el archivo Word");
        }
        
        // Extraer documento principal
        $content = $zip->getFromName('word/document.xml');
        if ($content === false) {
            $zip->close();
            throw new \Exception("Archivo Word corrupto o formato no válido");
        }
        
        // Extraer estilos (para detectar títulos)
        $stylesContent = $zip->getFromName('word/styles.xml');
        if ($stylesContent !== false) {
            $this->parseStyles($stylesContent);
        }
        
        // Extraer relaciones para imágenes
        $relsContent = $zip->getFromName('word/_rels/document.xml.rels');
        if ($relsContent !== false) {
            $this->parseRelationships($relsContent);
        }
        
        // Extraer imágenes embebidas
        $this->extractImages($zip);
        
        $zip->close();
        
        // Parsear el contenido XML
        $this->parseDocumentXml($content);
    }
    
    /**
     * Parsea el XML del documento
     */
    private function parseDocumentXml(string $xml): void
    {
        // Limpiar el XML
        $xml = preg_replace('/xmlns[^=]*="[^"]*"/', '', $xml);
        $xml = preg_replace('/<[a-zA-Z]+:/', '<', $xml);
        $xml = preg_replace('/<\/[a-zA-Z]+:/', '</', $xml);
        
        $dom = new DOMDocument();
        @$dom->loadXML($xml);
        
        // Recopilar nodos tabla para detectarlos durante el recorrido
        $tableNodes = [];
        $tables = $dom->getElementsByTagName('tbl');
        foreach ($tables as $table) {
            $tableNodes[] = $table;
        }
        
        // Recorrer el body en orden para preservar la posición de tablas
        // respecto a los párrafos (importante para no perder info)
        $body = $dom->getElementsByTagName('body')->item(0);
        $currentText = [];
        $tableIndex = 0;
        
        if ($body) {
            foreach ($body->childNodes as $node) {
                $nodeName = $node->nodeName;
                
                if ($nodeName === 'p') {
                    $text = $this->extractTextFromNode($node);
                    $style = $this->detectParagraphStyle($node);
                    
                    if (!empty(trim($text))) {
                        $this->paragraphs[] = [
                            'text' => trim($text),
                            'style' => $style,
                            'is_heading' => $this->isHeading($style, $text),
                            'is_list' => $this->isList($node)
                        ];
                        $currentText[] = trim($text);
                    }
                } elseif ($nodeName === 'tbl') {
                    // Extraer tabla como datos estructurados
                    $tableData = $this->parseTable($node);
                    $this->tables[] = $tableData;
                    
                    // CRUCIAL: Convertir tabla a texto legible e incluirla
                    // en el flujo de párrafos para que la IA la vea y no se pierda
                    $tableText = $this->tableToText($tableData);
                    if (!empty($tableText)) {
                        $this->paragraphs[] = [
                            'text' => $tableText,
                            'style' => 'Table',
                            'is_heading' => false,
                            'is_list' => false
                        ];
                        $currentText[] = $tableText;
                    }
                }
            }
        } else {
            // Fallback: recorrido clásico si no hay body
            $paragraphs = $dom->getElementsByTagName('p');
            foreach ($paragraphs as $p) {
                $text = $this->extractTextFromNode($p);
                $style = $this->detectParagraphStyle($p);
                if (!empty(trim($text))) {
                    $this->paragraphs[] = [
                        'text' => trim($text),
                        'style' => $style,
                        'is_heading' => $this->isHeading($style, $text),
                        'is_list' => $this->isList($p)
                    ];
                    $currentText[] = trim($text);
                }
            }
            foreach ($tableNodes as $table) {
                $tableData = $this->parseTable($table);
                $this->tables[] = $tableData;
                $tableText = $this->tableToText($tableData);
                if (!empty($tableText)) {
                    $currentText[] = $tableText;
                }
            }
        }
        
        $this->rawText = implode("\n\n", $currentText);
    }

    /**
     * Convierte datos de tabla en texto legible para incluir en el contenido
     * Formato: "Tabla: Col1 | Col2 | Col3 / Fila1Val1 | Fila1Val2 | Fila1Val3 / ..."
     */
    private function tableToText(array $tableData): string
    {
        if (empty($tableData)) return '';
        
        $lines = [];
        foreach ($tableData as $ri => $row) {
            $cellTexts = array_map('trim', $row);
            // Filtrar filas completamente vacías
            if (empty(implode('', $cellTexts))) continue;
            
            if ($ri === 0) {
                // Primera fila como encabezado
                $lines[] = '[Tabla: ' . implode(' | ', $cellTexts) . ']';
            } else {
                $lines[] = implode(' | ', $cellTexts);
            }
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Extrae texto de un nodo XML
     */
    private function extractTextFromNode($node): string
    {
        $text = '';
        $textNodes = $node->getElementsByTagName('t');
        
        foreach ($textNodes as $t) {
            $text .= $t->nodeValue;
        }
        
        return $text;
    }
    
    /**
     * Detecta el estilo de un párrafo
     */
    private function detectParagraphStyle($paragraph): string
    {
        $pPr = $paragraph->getElementsByTagName('pPr');
        if ($pPr->length > 0) {
            $pStyle = $pPr->item(0)->getElementsByTagName('pStyle');
            if ($pStyle->length > 0) {
                return $pStyle->item(0)->getAttribute('val') ?? 'Normal';
            }
        }
        return 'Normal';
    }
    
    /**
     * Determina si es un encabezado
     */
    private function isHeading(string $style, string $text): bool
    {
        // Por estilo
        if (preg_match('/^(Heading|Título|Title|Heading\d)/i', $style)) {
            return true;
        }
        
        // Por patrón de texto
        if (preg_match('/^(UNIDAD\s+DID[ÁA]CTICA|M[OÓ]DULO|CAP[ÍI]TULO|SECCI[OÓ]N)/iu', $text)) {
            return true;
        }
        
        // Por formato (todo mayúsculas y corto)
        if (mb_strlen($text) < 100 && preg_match('/^[A-ZÁÉÍÓÚÑ\s\d\.\-:]+$/u', $text)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Determina si es un elemento de lista
     */
    private function isList($paragraph): bool
    {
        $numPr = $paragraph->getElementsByTagName('numPr');
        return $numPr->length > 0;
    }
    
    /**
     * Parsea una tabla
     */
    private function parseTable($table): array
    {
        $rows = $table->getElementsByTagName('tr');
        $tableData = [];
        
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('tc');
            $rowData = [];
            
            foreach ($cells as $cell) {
                $rowData[] = trim($this->extractTextFromNode($cell));
            }
            
            if (!empty(array_filter($rowData))) {
                $tableData[] = $rowData;
            }
        }
        
        return $tableData;
    }
    
    /**
     * Parsea los estilos del documento
     */
    private function parseStyles(string $xml): void
    {
        // Simplificado - extraer nombres de estilos
        preg_match_all('/styleId="([^"]+)"/', $xml, $matches);
        if (!empty($matches[1])) {
            $this->styles = $matches[1];
        }
    }
    
    /**
     * Obtiene el texto sin formato
     */
    public function getRawText(): string
    {
        return $this->rawText;
    }
    
    /**
     * Obtiene los párrafos estructurados
     */
    public function getParagraphs(): array
    {
        return $this->paragraphs;
    }
    
    /**
     * Obtiene las tablas
     */
    public function getTables(): array
    {
        return $this->tables;
    }
    
    /**
     * Detecta la estructura del documento
     * Soporta: UNIDAD DIDÁCTICA, MÓDULO, CAPÍTULO, y capítulos numerados (1. Título)
     */
    public function detectStructure(): array
    {
        $structure = [
            'title' => '',
            'units' => [],
            'chapters' => [],  // Capítulos/secciones del documento original
            'all_content' => [] // Todo el texto del documento
        ];
        
        $currentUnit = null;
        $currentChapter = null;
        
        foreach ($this->paragraphs as $para) {
            $text = $para['text'];
            
            // Acumular todo el contenido
            $structure['all_content'][] = $text;
            
            // Detectar título del módulo/documento
            if (empty($structure['title'])) {
                if (preg_match('/M[OÓ]DULO\s*\d*[:\s]*(.+)/iu', $text, $match)) {
                    $structure['title'] = trim($match[1]);
                    continue;
                }
                // Si el primer párrafo parece un título (heading o corto y sin punto final)
                if ($para['is_heading'] && mb_strlen($text) < 120 && !preg_match('/\.$/', $text)
                    && !preg_match('/^\d+\.\s/', $text)) {
                    $structure['title'] = trim($text);
                    continue;
                }
            }
            
            // Detectar unidad didáctica (formato formal)
            if (preg_match('/UNIDAD\s+DID[ÁA]CTICA\s+(\d+)[:\s]*([^\n]+)/iu', $text, $match)) {
                if ($currentUnit !== null) {
                    $structure['units'][] = $currentUnit;
                }
                $currentUnit = [
                    'number' => (int)$match[1],
                    'title' => trim($match[2]),
                    'content' => []
                ];
                continue;
            }
            
            // Detectar capítulos numerados: "1. Título", "2. Título", etc.
            if (preg_match('/^(\d{1,2})\.\s+([A-ZÁÉÍÓÚÑ].{3,80})$/u', $text, $match)) {
                // Verificar que no es un subpunto (1.1., 2.3., etc)
                if (!preg_match('/^\d+\.\d+/', $text)) {
                    if ($currentChapter !== null) {
                        $structure['chapters'][] = $currentChapter;
                    }
                    $currentChapter = [
                        'number' => (int)$match[1],
                        'title' => trim($match[2]),
                        'content' => []
                    ];
                    // También acumular en la unidad actual si existe
                    if ($currentUnit !== null) {
                        $currentUnit['content'][] = $text;
                    }
                    continue;
                }
            }
            
            // Agregar contenido a la unidad/capítulo actual
            if ($currentUnit !== null) {
                $currentUnit['content'][] = $text;
            }
            if ($currentChapter !== null) {
                $currentChapter['content'][] = $text;
            }
        }
        
        // Agregar última unidad y capítulo
        if ($currentUnit !== null) {
            $structure['units'][] = $currentUnit;
        }
        if ($currentChapter !== null) {
            $structure['chapters'][] = $currentChapter;
        }
        
        // Si no se detectaron UDs pero sí capítulos, usar capítulos como UDs
        if (empty($structure['units']) && !empty($structure['chapters'])) {
            $structure['units'] = $structure['chapters'];
        }
        
        return $structure;
    }
    
    /**
     * Extrae código fuente del documento
     * Busca bloques completos delimitados por líneas vacías
     */
    public function extractCodeBlocks(): array
    {
        $codeBlocks = [];
        
        // Detectar bloques de código completos entre líneas en blanco
        $patterns = [
            'python' => '/(?:^|\n\n)((?:(?:def|class|import|from|if|for|while|with|try|async)\s+.+\n)(?:(?:[ \t]+.+|\s*)\n)*)/m',
            'sql' => '/(?:^|\n\n)((?:SELECT|INSERT|UPDATE|DELETE|CREATE\s+TABLE|ALTER\s+TABLE)\b[\s\S]+?;)/mi',
            'javascript' => '/(?:^|\n\n)((?:(?:function|const|let|var|class|async)\s+.+\n)(?:(?:[ \t]+.+|\s*)\n)*)/m',
            'html' => '/(?:^|\n\n)(<(?:div|section|article|form|table|ul|ol|nav|header|footer|template)\b[\s\S]+?<\/(?:div|section|article|form|table|ul|ol|nav|header|footer|template)>)/mi',
        ];
        
        foreach ($patterns as $lang => $pattern) {
            preg_match_all($pattern, $this->rawText, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $code = trim($match[1] ?? $match[0]);
                // Solo incluir si tiene al menos 2 líneas y no es texto normal
                $lineCount = substr_count($code, "\n") + 1;
                if ($lineCount >= 2 && strlen($code) >= 40) {
                    $codeBlocks[] = [
                        'language' => $lang,
                        'code' => $code
                    ];
                }
            }
        }
        
        // Deduplicar bloques similares
        $unique = [];
        foreach ($codeBlocks as $block) {
            $key = md5($block['code']);
            if (!isset($unique[$key])) {
                $unique[$key] = $block;
            }
        }
        
        return array_slice(array_values($unique), 0, 20);
    }
    
    /**
     * Extrae código asociado a una unidad específica por su contenido
     */
    public function extractCodeBlocksForContent(string $content): array
    {
        $codeBlocks = [];
        
        $patterns = [
            'python' => '/(?:^|\n\n)((?:(?:def|class|import|from|if|for|while|with|try|async)\s+.+\n)(?:(?:[ \t]+.+|\s*)\n)*)/m',
            'sql' => '/(?:^|\n\n)((?:SELECT|INSERT|UPDATE|DELETE|CREATE\s+TABLE|ALTER\s+TABLE)\b[\s\S]+?;)/mi',
            'javascript' => '/(?:^|\n\n)((?:(?:function|const|let|var|class|async)\s+.+\n)(?:(?:[ \t]+.+|\s*)\n)*)/m',
        ];
        
        foreach ($patterns as $lang => $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $code = trim($match[1] ?? $match[0]);
                $lineCount = substr_count($code, "\n") + 1;
                if ($lineCount >= 2 && strlen($code) >= 40) {
                    $codeBlocks[] = [
                        'language' => $lang,
                        'code' => $code
                    ];
                }
            }
        }
        
        return $codeBlocks;
    }

    // =====================================================================
    //  IMAGE EXTRACTION (Fase 4)
    // =====================================================================

    /**
     * Parsea las relaciones del documento (rId -> file path)
     */
    private function parseRelationships(string $xml): void
    {
        $dom = new DOMDocument();
        @$dom->loadXML($xml);
        $rels = $dom->getElementsByTagName('Relationship');
        foreach ($rels as $rel) {
            $id = $rel->getAttribute('Id');
            $target = $rel->getAttribute('Target');
            $type = $rel->getAttribute('Type');
            if (strpos($type, '/image') !== false) {
                $this->relationships[$id] = $target;
            }
        }
    }

    /**
     * Extrae imágenes embebidas del .docx, filtrando:
     * - Thumbnails (<2KB)
     * - Imágenes de texto/diagramas (aspect ratio muy extremo o demasiado ancho)
     * - Imágenes pequeñas tipo iconos
     */
    private function extractImages(ZipArchive $zip): void
    {
        $validExts = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        $mimeMap = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'webp' => 'image/webp'
        ];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^word/media/(.+)\.(' . implode('|', $validExts) . ')$#i', $name, $m)) {
                $data = $zip->getFromIndex($i);
                if ($data === false || strlen($data) < 2000) continue; // ignorar < 2KB

                $ext = strtolower($m[2]);
                $filename = $m[1] . '.' . $ext;

                // Analizar dimensiones reales de la imagen
                $imgInfo = @getimagesizefromstring($data);
                if ($imgInfo === false) continue;

                $w = $imgInfo[0];
                $h = $imgInfo[1];

                // Filtrar iconos pequeños (< 100px en alguna dimensión)
                if ($w < 100 || $h < 100) continue;

                // Filtrar imágenes de texto: muy anchas respecto a su alto
                // (capturas de texto, tablas, diagramas tipo captura)
                $ratio = $w / max($h, 1);
                if ($ratio > 4) continue; // Ej: 1200x200 = ratio 6 -> es texto

                // Filtrar imágenes muy altas y estrechas (barras laterales, etc)
                if ($h / max($w, 1) > 4) continue;

                $rId = array_search('media/' . $filename, $this->relationships);
                $this->images[] = [
                    'filename' => $filename,
                    'data' => $data,
                    'mime' => $mimeMap[$ext] ?? 'image/png',
                    'rId' => $rId ?: null,
                    'size' => strlen($data),
                    'width' => $w,
                    'height' => $h,
                    'path_in_zip' => $name
                ];
            }
        }
    }

    /**
     * Devuelve las imágenes extraídas
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * Construye un outline estructurado jerárquico del documento.
     * Detecta: TEMA/UNIDAD (nivel 1), secciones numeradas X.Y (nivel 2),
     * subsecciones X.Y.Z o X.Y.a (nivel 3), y contenido bajo cada una.
     * Devuelve un array que representa fielmente la estructura del Word.
     */
    public function buildStructuredOutline(): array
    {
        $outline = [
            'module_title' => '',
            'units' => []
        ];

        $currentUnit = null;
        $currentSection = null;
        $currentSubsection = null;
        $foundFirstUnit = false;  // Para ignorar TOC
        $seenUnitNumbers = [];    // Para no duplicar UDs del TOC

        // Primer paso: buscar título del módulo y la línea "MÓDULO X"
        foreach ($this->paragraphs as $para) {
            $text = trim($para['text']);
            if (empty($text)) continue;
            
            // Detectar línea "MÓDULO II" o "MÓDULO 2"
            if (preg_match('/^M[OÓ]DULO\s+([IVXLCDM\d]+)\s*$/iu', $text)) {
                continue; // Solo la etiqueta, no es el título
            }
            // Detectar título largo del módulo (heading antes del primer TEMA)
            if (empty($outline['module_title']) && $para['is_heading'] && mb_strlen($text) > 15 && mb_strlen($text) < 200
                && !preg_match('/^TEMA\s/i', $text) && !preg_match('/^\d+\./', $text)
                && !preg_match('/^M[OÓ]DULO\s/i', $text)) {
                $outline['module_title'] = trim($text);
            }
            // Parar después de encontrar el primer TEMA
            if (preg_match('/^TEMA\s+\d/i', $text)) break;
        }

        // Segundo paso: construir estructura real (ignorando TOC)
        foreach ($this->paragraphs as $para) {
            $text = trim($para['text']);
            if (empty($text)) continue;

            // --- Detectar TEMA / UNIDAD DIDÁCTICA (nivel 1) ---
            if (preg_match('/^(?:TEMA|UNIDAD(?:\s+DID[AÁ]CTICA)?)\s*(\d+)\s*[:\-.]?\s*(.+)?/iu', $text, $m)) {
                $unitNum = (int)$m[1];
                $unitTitle = trim($m[2] ?? '');
                
                // Si ya vimos esta UD, es la repetición real (post-TOC) → reemplazar
                if (in_array($unitNum, $seenUnitNumbers)) {
                    // Flush anterior y empezar nueva con contenido real
                    $this->flushSubsection($currentSubsection, $currentSection);
                    $this->flushSection($currentSection, $currentUnit);
                    // Eliminar la UD del TOC del outline
                    $outline['units'] = array_filter($outline['units'], fn($u) => $u['number'] !== $unitNum);
                    $outline['units'] = array_values($outline['units']);
                    if ($currentUnit !== null && $currentUnit['number'] !== $unitNum) {
                        $this->flushUnit($currentUnit, $outline);
                    }
                } else {
                    $this->flushSubsection($currentSubsection, $currentSection);
                    $this->flushSection($currentSection, $currentUnit);
                    $this->flushUnit($currentUnit, $outline);
                }
                
                $seenUnitNumbers[] = $unitNum;
                $currentUnit = [
                    'number' => $unitNum,
                    'title' => $unitTitle,
                    'sections' => [],
                    'content' => []
                ];
                $currentSection = null;
                $currentSubsection = null;
                $foundFirstUnit = true;
                continue;
            }

            // Ignorar todo antes del primer TEMA real (= TOC, portada, etc)
            if (!$foundFirstUnit) continue;

            // --- Detectar subsección X.Y.Z o X.Y.a (nivel 3) --- ANTES que sección
            if (preg_match('/^(\d+)\.(\d+)\.\s*([a-z])\s*\.?\s+(.+)$/iu', $text, $m)) {
                $this->flushSubsection($currentSubsection, $currentSection);
                $currentSubsection = [
                    'id' => $m[1] . '.' . $m[2] . '.' . strtolower($m[3]),
                    'title' => trim($m[4]),
                    'content' => []
                ];
                continue;
            }
            // Variante: "4.3.a. Título" o "4.3.a Título"
            if (preg_match('/^(\d+)\.(\d+)\.([a-z])\.?\s+(.+)$/iu', $text, $m)) {
                $this->flushSubsection($currentSubsection, $currentSection);
                $currentSubsection = [
                    'id' => $m[1] . '.' . $m[2] . '.' . strtolower($m[3]),
                    'title' => trim($m[4]),
                    'content' => []
                ];
                continue;
            }

            // --- Detectar sección X.Y. (nivel 2) ---
            if (preg_match('/^(\d+)\.(\d+)\.?\s+(.+)$/u', $text, $m)) {
                // Verificar que no es subsection (X.Y.a) - ya manejado arriba
                if (!preg_match('/^\d+\.\d+\.[a-z]/i', $text)) {
                    $this->flushSubsection($currentSubsection, $currentSection);
                    $this->flushSection($currentSection, $currentUnit);
                    $currentSection = [
                        'id' => $m[1] . '.' . $m[2],
                        'title' => trim($m[3]),
                        'subsections' => [],
                        'content' => []
                    ];
                    $currentSubsection = null;
                    continue;
                }
            }

            // --- Acumular contenido en el nivel correcto ---
            if ($currentSubsection !== null) {
                $currentSubsection['content'][] = $text;
            } elseif ($currentSection !== null) {
                $currentSection['content'][] = $text;
            } elseif ($currentUnit !== null) {
                $currentUnit['content'][] = $text;
            }
        }

        // Flush final
        $this->flushSubsection($currentSubsection, $currentSection);
        $this->flushSection($currentSection, $currentUnit);
        $this->flushUnit($currentUnit, $outline);

        // Fallback: si no detectó UDs con este método, usar detectStructure
        if (empty($outline['units'])) {
            $structure = $this->detectStructure();
            foreach ($structure['units'] as $su) {
                $outline['units'][] = [
                    'number' => $su['number'],
                    'title' => $su['title'],
                    'sections' => [],
                    'content' => $su['content'] ?? []
                ];
            }
            if (empty($outline['module_title'])) {
                $outline['module_title'] = $structure['title'];
            }
        }

        return $outline;
    }

    private function flushSubsection(?array &$sub, ?array &$section): void
    {
        if ($sub !== null && $section !== null) {
            $section['subsections'][] = $sub;
        }
        $sub = null;
    }

    private function flushSection(?array &$section, ?array &$unit): void
    {
        if ($section !== null && $unit !== null) {
            $unit['sections'][] = $section;
        }
        $section = null;
    }

    private function flushUnit(?array &$unit, array &$outline): void
    {
        if ($unit !== null) {
            $outline['units'][] = $unit;
        }
        $unit = null;
    }

    /**
     * Genera un texto estructurado legible del outline para pasar a la IA.
     * Formato:
     *   TEMA 4: Título
     *     4.1. Sección
     *       [contenido de la sección...]
     *       4.1.a. Subsección
     *         [contenido de la subsección...]
     */
    public function outlineToText(array $outline): string
    {
        $lines = [];
        if (!empty($outline['module_title'])) {
            $lines[] = 'MÓDULO: ' . $outline['module_title'];
            $lines[] = '';
        }
        foreach ($outline['units'] as $unit) {
            $lines[] = '=== TEMA ' . $unit['number'] . ': ' . $unit['title'] . ' ===';
            // Contenido previo a secciones
            foreach ($unit['content'] as $c) {
                $lines[] = $c;
            }
            foreach ($unit['sections'] as $sec) {
                $lines[] = '';
                $lines[] = '--- ' . $sec['id'] . '. ' . $sec['title'] . ' ---';
                foreach ($sec['content'] as $c) {
                    $lines[] = $c;
                }
                foreach ($sec['subsections'] as $sub) {
                    $lines[] = '';
                    $lines[] = '  -- ' . $sub['id'] . '. ' . $sub['title'] . ' --';
                    foreach ($sub['content'] as $c) {
                        $lines[] = '  ' . $c;
                    }
                }
            }
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    /**
     * Guarda las imágenes extraídas en un directorio
     * @return array Mapa de filename => ruta guardada
     */
    public function saveImagesToDir(string $dir): array
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $saved = [];
        foreach ($this->images as $img) {
            $path = $dir . '/' . $img['filename'];
            file_put_contents($path, $img['data']);
            $saved[$img['filename']] = [
                'path' => $path,
                'mime' => $img['mime'],
                'size' => $img['size']
            ];
        }
        return $saved;
    }
}
