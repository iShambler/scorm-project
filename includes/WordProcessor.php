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
        
        $paragraphs = $dom->getElementsByTagName('p');
        $currentText = [];
        
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
        
        // Extraer tablas
        $tables = $dom->getElementsByTagName('tbl');
        foreach ($tables as $table) {
            $this->tables[] = $this->parseTable($table);
        }
        
        $this->rawText = implode("\n\n", $currentText);
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
     * Extrae todas las imágenes embebidas del archivo .docx
     */
    private function extractImages(ZipArchive $zip): void
    {
        $validExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
        $mimeMap = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'
        ];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Las imágenes suelen estar en word/media/
            if (preg_match('#^word/media/(.+)\.(' . implode('|', $validExts) . ')$#i', $name, $m)) {
                $data = $zip->getFromIndex($i);
                if ($data !== false && strlen($data) > 500) { // ignorar thumbs tiny
                    $ext = strtolower($m[2]);
                    $filename = $m[1] . '.' . $ext;
                    // Buscar rId correspondiente
                    $rId = array_search('media/' . $filename, $this->relationships);
                    $this->images[] = [
                        'filename' => $filename,
                        'data' => $data,
                        'mime' => $mimeMap[$ext] ?? 'image/png',
                        'rId' => $rId ?: null,
                        'size' => strlen($data),
                        'path_in_zip' => $name
                    ];
                }
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
