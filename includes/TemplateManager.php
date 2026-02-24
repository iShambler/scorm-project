<?php
/**
 * TemplateManager — Gestión de plantillas SCORM
 * 
 * Permite listar, cargar, importar y exportar plantillas de estilo
 * para la generación de paquetes SCORM.
 */
namespace ScormConverter;

use ZipArchive;

class TemplateManager
{
    private string $templatesDir;
    private string $defaultTemplate = 'arelance-corporate';

    public function __construct(?string $templatesDir = null)
    {
        $this->templatesDir = $templatesDir ?? (defined('TEMPLATES_PATH') ? TEMPLATES_PATH : __DIR__ . '/../templates');
        if (!is_dir($this->templatesDir)) {
            @mkdir($this->templatesDir, 0755, true);
        }
    }

    /**
     * Lista todas las plantillas disponibles
     * @return array [{id, name, description, author, version, colors, preview_exists}]
     */
    public function listTemplates(): array
    {
        $templates = [];
        $dirs = glob($this->templatesDir . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $jsonPath = $dir . '/template.json';
            if (!file_exists($jsonPath)) continue;

            $data = json_decode(file_get_contents($jsonPath), true);
            if (!$data || empty($data['id'])) continue;

            $templates[] = [
                'id' => $data['id'],
                'name' => $data['name'] ?? $data['id'],
                'description' => $data['description'] ?? '',
                'author' => $data['author'] ?? 'Desconocido',
                'version' => $data['version'] ?? '1.0',
                'colors' => $data['colors'] ?? [],
                'fonts' => $data['fonts'] ?? [],
                'preview_exists' => file_exists($dir . '/preview.png') || file_exists($dir . '/preview.jpg'),
                'has_logo' => !empty($data['logo']) && file_exists($dir . '/' . $data['logo']),
                'has_bg' => !empty($data['portada_bg']) && file_exists($dir . '/' . $data['portada_bg']),
            ];
        }

        // Default primero
        usort($templates, function ($a, $b) {
            if ($a['id'] === 'arelance-corporate') return -1;
            if ($b['id'] === 'arelance-corporate') return 1;
            return strcmp($a['name'], $b['name']);
        });

        return $templates;
    }

    /**
     * Carga una plantilla completa por su ID
     * @return array|null Datos de la plantilla con CSS incluido
     */
    public function loadTemplate(string $id): ?array
    {
        $dir = $this->templatesDir . '/' . $this->sanitizeId($id);
        $jsonPath = $dir . '/template.json';
        $cssPath = $dir . '/styles.css';

        if (!file_exists($jsonPath) || !file_exists($cssPath)) {
            return null;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) return null;

        $data['css'] = file_get_contents($cssPath);
        $data['dir'] = $dir;

        return $data;
    }

    /**
     * Genera el CSS final con variables inyectadas desde template.json
     */
    public function buildCSS(string $templateId): string
    {
        $tpl = $this->loadTemplate($templateId);
        if (!$tpl) {
            $tpl = $this->loadTemplate($this->defaultTemplate);
        }
        if (!$tpl) {
            throw new \Exception("No se pudo cargar ninguna plantilla");
        }

        $colors = $tpl['colors'] ?? [];
        $fonts = $tpl['fonts'] ?? [];
        $settings = $tpl['settings'] ?? [];

        // Google Fonts import
        $fontsUrl = $settings['google_fonts_url'] ?? '';
        $fontImport = !empty($fontsUrl) ? "@import url('{$fontsUrl}');\n" : '';

        // Variables CSS
        $vars = ":root{\n";
        $vars .= "  --primary:" . ($colors['primary'] ?? '#143554') . ";\n";
        $vars .= "  --secondary:" . ($colors['secondary'] ?? '#1a4a6e') . ";\n";
        $vars .= "  --accent:" . ($colors['accent'] ?? '#F05726') . ";\n";
        $vars .= "  --bg:" . ($colors['bg'] ?? '#f8f9fa') . ";\n";
        $vars .= "  --card:" . ($colors['card'] ?? '#ffffff') . ";\n";
        $vars .= "  --text:" . ($colors['text'] ?? '#333333') . ";\n";
        $vars .= "  --text-light:" . ($colors['text_light'] ?? '#666666') . ";\n";
        $vars .= "  --border:" . ($colors['border'] ?? '#e0e0e0') . ";\n";
        $vars .= "  --success:" . ($colors['success'] ?? '#22c55e') . ";\n";
        $vars .= "  --danger:" . ($colors['danger'] ?? '#ef4444') . ";\n";
        $vars .= "  --warning:" . ($colors['warning'] ?? '#f59e0b') . ";\n";
        $vars .= "  --radius:" . ($settings['radius'] ?? '12px') . ";\n";
        $vars .= "  --shadow:0 4px 20px rgba(0,0,0,.08);\n";
        $vars .= "  --font-heading:'" . ($fonts['heading'] ?? 'Poppins') . "';\n";
        $vars .= "  --font-body:'" . ($fonts['body'] ?? 'Inter') . "';\n";
        $vars .= "  --font-code:'" . ($fonts['code'] ?? 'Fira Code') . "';\n";
        $vars .= "}\n";

        return $fontImport . $vars . "\n" . ($tpl['css'] ?? '');
    }

    /**
     * Copia los assets de la plantilla al directorio del SCORM
     * @return array Lista de assets copiados
     */
    public function copyAssets(string $templateId, string $destDir): array
    {
        $tpl = $this->loadTemplate($templateId);
        if (!$tpl) return [];

        $copied = [];
        $assetsDir = ($tpl['dir'] ?? '') . '/assets';
        if (!is_dir($assetsDir)) return [];

        $destAssets = $destDir . '/img';
        if (!is_dir($destAssets)) @mkdir($destAssets, 0755, true);

        // Copiar logo si existe
        if (!empty($tpl['logo'])) {
            $logoSrc = $tpl['dir'] . '/' . $tpl['logo'];
            if (file_exists($logoSrc)) {
                $logoFile = basename($tpl['logo']);
                copy($logoSrc, $destAssets . '/' . $logoFile);
                $copied['logo'] = 'img/' . $logoFile;
            }
        }

        // Copiar fondo de portada si existe
        if (!empty($tpl['portada_bg'])) {
            $bgSrc = $tpl['dir'] . '/' . $tpl['portada_bg'];
            if (file_exists($bgSrc)) {
                $bgFile = basename($tpl['portada_bg']);
                copy($bgSrc, $destAssets . '/' . $bgFile);
                $copied['portada_bg'] = 'img/' . $bgFile;
            }
        }

        // Copiar fuentes locales
        $fontsDir = $assetsDir . '/fonts';
        if (is_dir($fontsDir)) {
            $destFonts = $destDir . '/css/fonts';
            @mkdir($destFonts, 0755, true);
            foreach (glob($fontsDir . '/*.{woff,woff2,ttf,otf}', GLOB_BRACE) as $font) {
                $fontFile = basename($font);
                copy($font, $destFonts . '/' . $fontFile);
                $copied['fonts'][] = 'css/fonts/' . $fontFile;
            }
        }

        return $copied;
    }

    /**
     * Importa una plantilla desde un archivo ZIP
     * @return array Resultado {success, id, message}
     */
    public function importTemplate(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['success' => false, 'id' => '', 'message' => 'No se pudo abrir el archivo ZIP'];
        }

        // Buscar template.json (puede estar en raíz o en una subcarpeta)
        $jsonContent = null;
        $jsonPrefix = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (basename($name) === 'template.json') {
                $jsonContent = $zip->getFromIndex($i);
                $jsonPrefix = dirname($name);
                if ($jsonPrefix === '.') $jsonPrefix = '';
                else $jsonPrefix .= '/';
                break;
            }
        }

        if (!$jsonContent) {
            $zip->close();
            return ['success' => false, 'id' => '', 'message' => 'El ZIP no contiene template.json'];
        }

        $data = json_decode($jsonContent, true);
        if (!$data || empty($data['id'])) {
            $zip->close();
            return ['success' => false, 'id' => '', 'message' => 'template.json inválido: falta el campo "id"'];
        }

        // Validar que tiene styles.css
        $hasCss = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (basename($zip->getNameIndex($i)) === 'styles.css') {
                $hasCss = true;
                break;
            }
        }
        if (!$hasCss) {
            $zip->close();
            return ['success' => false, 'id' => '', 'message' => 'El ZIP no contiene styles.css'];
        }

        $id = $this->sanitizeId($data['id']);
        $destDir = $this->templatesDir . '/' . $id;

        // Proteger plantilla default
        if ($id === 'arelance-corporate' && is_dir($destDir)) {
            $zip->close();
            return ['success' => false, 'id' => $id, 'message' => 'No se puede sobreescribir la plantilla por defecto'];
        }

        // Crear directorio y extraer
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        if (!is_dir($destDir . '/assets')) @mkdir($destDir . '/assets', 0755, true);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Quitar prefijo de carpeta si existe
            $relativeName = $jsonPrefix ? str_replace($jsonPrefix, '', $name) : $name;
            if (empty($relativeName) || substr($relativeName, -1) === '/') continue;

            // Seguridad: no permitir path traversal
            if (strpos($relativeName, '..') !== false) continue;

            $targetPath = $destDir . '/' . $relativeName;
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);

            file_put_contents($targetPath, $zip->getFromIndex($i));
        }

        $zip->close();

        return [
            'success' => true,
            'id' => $id,
            'message' => 'Plantilla "' . ($data['name'] ?? $id) . '" importada correctamente'
        ];
    }

    /**
     * Exporta una plantilla como ZIP descargable
     * @return string|null Ruta del ZIP generado
     */
    public function exportTemplate(string $id): ?string
    {
        $dir = $this->templatesDir . '/' . $this->sanitizeId($id);
        if (!is_dir($dir) || !file_exists($dir . '/template.json')) {
            return null;
        }

        $zipPath = (defined('TEMP_PATH') ? TEMP_PATH : sys_get_temp_dir()) . '/template_' . $id . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $this->addDirToZip($zip, $dir, $id);
        $zip->close();

        return $zipPath;
    }

    /**
     * Elimina una plantilla (no permite eliminar la default)
     */
    public function deleteTemplate(string $id): array
    {
        $id = $this->sanitizeId($id);
        if ($id === $this->defaultTemplate) {
            return ['success' => false, 'message' => 'No se puede eliminar la plantilla por defecto'];
        }

        $dir = $this->templatesDir . '/' . $id;
        if (!is_dir($dir)) {
            return ['success' => false, 'message' => 'Plantilla no encontrada'];
        }

        $this->deleteDir($dir);
        return ['success' => true, 'message' => 'Plantilla eliminada'];
    }

    /**
     * Obtiene la ruta de la imagen de preview de una plantilla
     */
    public function getPreviewPath(string $id): ?string
    {
        $dir = $this->templatesDir . '/' . $this->sanitizeId($id);
        foreach (['preview.png', 'preview.jpg', 'preview.jpeg', 'preview.webp'] as $file) {
            if (file_exists($dir . '/' . $file)) {
                return $dir . '/' . $file;
            }
        }
        return null;
    }

    /**
     * Devuelve el ID de la plantilla por defecto
     */
    public function getDefaultId(): string
    {
        return $this->defaultTemplate;
    }

    // ── HELPERS ──

    private function sanitizeId(string $id): string
    {
        return preg_replace('/[^a-z0-9\-_]/', '', strtolower($id));
    }

    private function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        $basePath = realpath($dir);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $prefix . '/' . str_replace('\\', '/', substr($filePath, strlen($basePath) + 1));
            $zip->addFile($filePath, $relativePath);
        }
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
