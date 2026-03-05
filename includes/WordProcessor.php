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
    private array $imagePositions = []; // posición de cada imagen en el documento
    
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
        // Limpiar el XML: eliminar namespaces de tags y atributos
        $xml = preg_replace('/xmlns[^=]*="[^"]*"/', '', $xml);
        $xml = preg_replace('/<[a-zA-Z]+:/', '<', $xml);
        $xml = preg_replace('/<\/[a-zA-Z]+:/', '</', $xml);
        // Eliminar prefijos de atributos (r:embed → embed, w:val → val)
        $xml = preg_replace('/(\s)[a-zA-Z]+:([a-zA-Z]+)=/', '$1$2=', $xml);
        
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

                    // Detectar imágenes embebidas en este párrafo
                    $drawings = $node->getElementsByTagName('drawing');
                    if ($drawings->length > 0) {
                        foreach ($drawings as $drawing) {
                            $rId = $this->extractImageRId($drawing);
                            if ($rId && isset($this->relationships[$rId])) {
                                $filename = basename($this->relationships[$rId]);
                                $this->imagePositions[] = [
                                    'rId' => $rId,
                                    'paragraph_index' => count($this->paragraphs),
                                    'preceding_text' => trim($text),
                                    'filename' => $filename
                                ];
                                // Si el párrafo solo contiene imagen (sin texto), insertar placeholder
                                if (empty(trim($text))) {
                                    $placeholder = '[IMAGEN: ' . $filename . ']';
                                    $this->paragraphs[] = [
                                        'text' => $placeholder,
                                        'style' => 'ImagePlaceholder',
                                        'is_heading' => false,
                                        'is_list' => false,
                                        'image_rId' => $rId
                                    ];
                                    $currentText[] = $placeholder;
                                }
                            }
                        }
                    }

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

                $ratio = $w / max($h, 1);

                $rId = array_search('media/' . $filename, $this->relationships);
                $this->images[] = [
                    'filename' => $filename,
                    'data' => $data,
                    'mime' => $mimeMap[$ext] ?? 'image/png',
                    'rId' => $rId ?: null,
                    'size' => strlen($data),
                    'width' => $w,
                    'height' => $h,
                    'path_in_zip' => $name,
                    'aspect_ratio' => round($ratio, 2)
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
     * Construye un outline del documento dividiendo por TEMAs/UNIDADes.
     * Estrategia robusta:
     *   1. Detectar dónde acaba el TOC (índice) y empieza el contenido real
     *   2. Dividir solo por TEMA/UNIDAD (nivel 1)
     *   3. TODO el contenido entre TEMAs va a content[] sin parsear secciones
     *   4. La IA (structureUnit) se encarga de organizar en secciones
     * Esto garantiza que NUNCA se pierde contenido por mala detección de estructura.
     */
    public function buildStructuredOutline(): array
    {
        $outline = [
            'module_title' => '',
            'units' => []
        ];

        // ── PASO 1: Detectar título del módulo ──
        foreach ($this->paragraphs as $para) {
            $text = trim($para['text']);
            if (empty($text)) continue;

            if (preg_match('/^M[OÓ]DULO\s+([IVXLCDM\d]+)\s*$/iu', $text)) continue;

            if (empty($outline['module_title']) && $para['is_heading'] && mb_strlen($text) > 15 && mb_strlen($text) < 200
                && !preg_match('/^TEMA\s/i', $text) && !preg_match('/^\d+\./', $text)
                && !preg_match('/^M[OÓ]DULO\s/i', $text)) {
                $outline['module_title'] = trim($text);
            }
            if (preg_match('/^TEMA\s+\d/i', $text)) break;
        }

        // ── PASO 2: Encontrar dónde empieza el contenido real (después del TOC) ──
        $contentStartIdx = $this->detectContentStart();

        // ── PASO 3: Recoger TODOS los TEMAs y su contenido ──
        // Primero encontramos las posiciones de cada TEMA real (post-TOC)
        $temaPositions = []; // [{index, number, title}]
        for ($i = $contentStartIdx; $i < count($this->paragraphs); $i++) {
            $text = trim($this->paragraphs[$i]['text']);
            if (empty($text)) continue;

            if (preg_match('/^(?:TEMA|UNIDAD(?:\s+DID[AÁ]CTICA)?)\s*(\d+)\s*[:\-.]?\s*(.+)?/iu', $text, $m)) {
                $unitNum = (int)$m[1];
                $unitTitle = trim($m[2] ?? '');

                // Si ya existe un TEMA con este número, mantener el último (es el real, post-TOC)
                $temaPositions = array_filter($temaPositions, fn($t) => $t['number'] !== $unitNum);
                $temaPositions[] = [
                    'index' => $i,
                    'number' => $unitNum,
                    'title' => $unitTitle
                ];
            }
        }
        $temaPositions = array_values($temaPositions);

        // ── PASO 4: Recoger TODO el contenido entre cada TEMA y el siguiente ──
        foreach ($temaPositions as $ti => $tema) {
            $startIdx = $tema['index'] + 1; // Párrafo siguiente al título TEMA
            $endIdx = isset($temaPositions[$ti + 1])
                ? $temaPositions[$ti + 1]['index'] // Hasta el siguiente TEMA
                : count($this->paragraphs);        // O hasta el final

            $content = [];
            for ($i = $startIdx; $i < $endIdx; $i++) {
                $text = trim($this->paragraphs[$i]['text']);
                if (!empty($text)) {
                    $content[] = $text;
                }
            }

            $outline['units'][] = [
                'number' => $tema['number'],
                'title' => $tema['title'],
                'sections' => [],  // Vacío — la IA se encarga
                'content' => $content
            ];
        }

        // ── FALLBACK: si no detectó TEMAs, usar todo el contenido como 1 unidad ──
        if (empty($outline['units'])) {
            $structure = $this->detectStructure();
            if (!empty($structure['units'])) {
                foreach ($structure['units'] as $su) {
                    $outline['units'][] = [
                        'number' => $su['number'],
                        'title' => $su['title'],
                        'sections' => [],
                        'content' => $su['content'] ?? []
                    ];
                }
            } else {
                // Último recurso: todo el texto como 1 unidad
                $allContent = [];
                foreach ($this->paragraphs as $para) {
                    $text = trim($para['text']);
                    if (!empty($text)) $allContent[] = $text;
                }
                if (!empty($allContent)) {
                    $outline['units'][] = [
                        'number' => 1,
                        'title' => $outline['module_title'] ?: 'Contenido formativo',
                        'sections' => [],
                        'content' => $allContent
                    ];
                }
            }
            if (empty($outline['module_title'])) {
                $outline['module_title'] = $structure['title'] ?? '';
            }
        }

        logError('DEBUG buildOutline: ' . count($outline['units']) . ' units, contentStart=' . $contentStartIdx
            . ', temaPositions=' . count($temaPositions)
            . ', totalParagraphs=' . count($this->paragraphs));

        return $outline;
    }

    /**
     * Detecta dónde acaba el TOC (índice) y empieza el contenido real.
     * Heurística: el TOC son párrafos cortos numerados (tipo "3.1. Título...")
     * agrupados antes del contenido largo. El contenido real empieza cuando
     * encontramos un párrafo largo (>20 palabras) que NO es un título numerado.
     */
    private function detectContentStart(): int
    {
        $tocCandidates = 0;   // Cuántos párrafos cortos numerados hemos visto
        $lastTemaIdx = 0;     // Último TEMA visto

        for ($i = 0; $i < count($this->paragraphs); $i++) {
            $text = trim($this->paragraphs[$i]['text']);
            if (empty($text)) continue;

            // ¿Es un heading TEMA/UNIDAD?
            if (preg_match('/^(?:TEMA|UNIDAD(?:\s+DID[AÁ]CTICA)?)\s*\d+/iu', $text)) {
                $lastTemaIdx = $i;
                continue;
            }

            // ¿Es una entrada de TOC? (corta, numerada, sin punto final como frase)
            $isTocEntry = $this->isTocEntry($text);
            if ($isTocEntry) {
                $tocCandidates++;
                continue;
            }

            // Si hemos visto entradas de TOC y ahora encontramos contenido largo
            // después de un TEMA, es el inicio del contenido real
            $wordCount = str_word_count($text);
            if ($tocCandidates >= 3 && $wordCount > 20 && $lastTemaIdx > 0) {
                // El contenido real empieza desde el último TEMA que vimos
                return $lastTemaIdx;
            }

            // Si encontramos un párrafo largo antes de ver TOC, no hay TOC
            if ($tocCandidates === 0 && $wordCount > 30) {
                return 0;
            }
        }

        // Si no encontramos corte claro, empezar desde el principio
        return 0;
    }

    /**
     * Determina si un párrafo es una entrada de índice (TOC).
     * Entradas de TOC: cortas, numeradas, sin punto final de frase.
     */
    private function isTocEntry(string $text): bool
    {
        $wordCount = str_word_count($text);
        // Entrada numerada corta: "3.1. Introducción" o "3.4.a. Valoración previa"
        $isNumbered = preg_match('/^\d+\.\d+/', $text);
        $isShort = $wordCount < 15 && mb_strlen($text) < 120;
        // No es una frase real (no termina con punto tras palabra larga)
        $isNotSentence = !preg_match('/[a-záéíóúñ]{3,}\.\s*$/iu', $text);

        return $isNumbered && $isShort && $isNotSentence;
    }

    /**
     * Genera un texto legible del outline para pasar a la IA.
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
     * Extrae el rId de un nodo <drawing>.
     * Tras strip de namespaces: drawing > inline/anchor > graphic > graphicData > pic > blipFill > blip[@embed]
     */
    private function extractImageRId($drawingNode): ?string
    {
        $blips = $drawingNode->getElementsByTagName('blip');
        if ($blips->length > 0) {
            $blip = $blips->item(0);
            // El atributo puede ser "embed" o "r:embed" (el namespace stripping
            // solo afecta a tags, no a atributos con prefijo)
            $embed = $blip->getAttribute('embed');
            if (empty($embed)) {
                $embed = $blip->getAttribute('r:embed');
            }
            if (!empty($embed)) {
                return $embed;
            }
        }
        return null;
    }

    /**
     * Devuelve imágenes con su posición en el documento.
     * Solo incluye imágenes que pasaron los filtros de extractImages().
     */
    public function getImagePositions(): array
    {
        $imageByFilename = [];
        foreach ($this->images as $img) {
            $imageByFilename[$img['filename']] = $img;
        }

        $result = [];
        foreach ($this->imagePositions as $pos) {
            if (isset($imageByFilename[$pos['filename']])) {
                $result[] = array_merge($pos, [
                    'image' => $imageByFilename[$pos['filename']]
                ]);
            }
        }
        return $result;
    }

    /**
     * Guarda las imágenes extraídas en un directorio
     * @return array Mapa de filename => ruta guardada
     */
    public function getExtractedImages(): array
    {
        return array_map(function($img) {
            return ['filename' => $img['filename'], 'rId' => $img['rId'], 'size' => $img['size']];
        }, $this->images);
    }

    public function getRawImagePositions(): array
    {
        return $this->imagePositions;
    }

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

    /**
     * Inyecta texto extraído de imágenes como párrafos reales.
     * Reemplaza placeholders [IMAGEN: x] por el texto extraído vía Vision API.
     * También actualiza rawText para que la IA tenga contenido.
     */
    public function injectExtractedImageText(array $imageTexts): void
    {
        $injectedText = [];
        $usedFilenames = [];

        // 1. Reemplazar placeholders existentes [IMAGEN: filename]
        foreach ($this->paragraphs as &$para) {
            if (($para['style'] ?? '') === 'ImagePlaceholder') {
                if (preg_match('/\[IMAGEN:\s*([^\]]+)\]/', $para['text'], $m)) {
                    $filename = trim($m[1]);
                    if (isset($imageTexts[$filename]) && !empty($imageTexts[$filename])) {
                        $text = $imageTexts[$filename];
                        $para['text'] = $text;
                        $para['style'] = '';
                        $para['is_heading'] = false;
                        $para['is_list'] = false;
                        unset($para['image_rId']);
                        $injectedText[] = $text;
                        $usedFilenames[$filename] = true;
                    }
                }
            }
        }
        unset($para);

        // 2. Imágenes sin placeholder (no se detectó posición en XML pero sí se extrajo texto)
        foreach ($imageTexts as $filename => $text) {
            if (!isset($usedFilenames[$filename]) && !empty($text)) {
                $this->paragraphs[] = [
                    'text' => $text,
                    'style' => '',
                    'is_heading' => false,
                    'is_list' => false
                ];
                $injectedText[] = $text;
            }
        }

        // 3. Actualizar rawText con todo el texto inyectado
        if (!empty($injectedText)) {
            $this->rawText = trim($this->rawText . "\n\n" . implode("\n\n", $injectedText));
        }
    }

    /**
     * Comprueba si el documento es mayoritariamente imágenes (poco texto, muchas imágenes)
     */
    public function isImageHeavyDocument(): bool
    {
        $imageCount = count($this->images);
        if ($imageCount === 0) return false;

        $textLen = strlen($this->rawText);
        $textParas = 0;
        $imgParas = 0;

        foreach ($this->paragraphs as $para) {
            if (($para['style'] ?? '') === 'ImagePlaceholder') {
                $imgParas++;
            } elseif (!empty(trim($para['text']))) {
                $textParas++;
            }
        }

        // Documento mayoritariamente imágenes si:
        // 1. Hay imágenes y muy poco texto (<500 chars), o
        // 2. Hay más placeholders de imagen que párrafos de texto, o
        // 3. Hay muchas imágenes extraídas pero poco texto proporcional
        return ($imageCount > 0 && $textLen < 500)
            || ($imgParas > 0 && $imgParas >= $textParas)
            || ($imageCount >= 5 && $textLen < $imageCount * 200);
    }
}
