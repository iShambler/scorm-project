<?php
/**
 * API: Generar paquete SCORM
 * POST /api/generate.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/SCORMGenerator.php';
require_once __DIR__ . '/../includes/ImageHelper.php';

use ScormConverter\Auth;
use ScormConverter\SCORMGenerator;
use ScormConverter\ImageHelper;

// Verificar autenticación
$auth = new Auth();
$auth->requireLogin();

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('max_execution_time', 300);
set_time_limit(300);
header('Content-Type: application/json; charset=utf-8');

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => 'Error PHP fatal: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
});

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Método no permitido');
}

try {
    // Obtener datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        jsonResponse(false, null, 'Datos inválidos');
    }
    
    // Validar estructura
    if (!isset($data['modulo']) || !isset($data['unidades'])) {
        jsonResponse(false, null, 'Faltan datos del módulo o unidades');
    }
    
    // Preparar configuración del módulo
    $moduleConfig = [
        'codigo' => $data['modulo']['codigo'] ?? 'MOD_01',
        'titulo' => $data['modulo']['titulo'] ?? 'Módulo formativo',
        'duracion_total' => $data['modulo']['duracion_total'] ?? DEFAULT_HOURS,
        'empresa' => $data['modulo']['empresa'] ?? DEFAULT_COMPANY
    ];
    
    // Preparar unidades
    $units = [];
    foreach ($data['unidades'] as $unit) {
        $units[] = [
            'numero' => $unit['numero'],
            'titulo' => $unit['titulo'],
            'duracion' => $unit['duracion'] ?? 6,
            'filename' => 'ud' . $unit['numero'] . '_' . slugify($unit['titulo']),
            'resumen' => $unit['resumen'] ?? '',
            'objetivos' => $unit['objetivos'] ?? [],
            'conceptos_clave' => $unit['conceptos_clave'] ?? [],
            'secciones' => $unit['secciones'] ?? [],
            'preguntas' => $unit['preguntas'] ?? [],
            'codigo' => $unit['codigo'] ?? [],
            '_enriched' => $unit['_enriched'] ?? false,
            'conclusiones_ia' => $unit['conclusiones_ia'] ?? []
        ];
    }
    
    // Fase 4: Recopilar imágenes
    $allImages = [];
    
    // 4a. Imágenes del Word: NO se usan como imágenes decorativas del SCORM.
    // Las imágenes del Word suelen ser capturas de texto, tablas o diagramas
    // cuyo contenido ya está extraído como texto en el XML del documento.
    // Usarlas como banners visuales queda mal y pierde contexto.
    // Solo se usarán imágenes fotográficas de Unsplash.
    logError('DEBUG generate: Word images SKIPPED (text/table captures, content already in text)');
    
    // 4b. Imágenes automáticas de Pexels (por keyword de cada sección)
    $imageHelper = new ImageHelper(defined('PEXELS_API_KEY') ? PEXELS_API_KEY : '');
    if ($imageHelper->isAvailable()) {
        foreach ($units as &$unit) {
            if (!empty($unit['secciones'])) {
                foreach ($unit['secciones'] as &$sec) {
                    $keyword = $sec['icono_keyword'] ?? '';
                    // Fallback: usar título de sección como keyword si no hay icono_keyword
                    if (empty($keyword) && !empty($sec['titulo'])) {
                        $keyword = $sec['titulo'];
                    }
                    if (!empty($keyword) && empty($sec['image'])) {
                        $img = $imageHelper->searchAndDownload($keyword);
                        if ($img) {
                            $allImages[] = $img;
                            $sec['image'] = $img['filename'];
                            $sec['image_credit'] = $img['credit'] ?? 'Unsplash';
                        }
                    }
                }
            }
        }
        unset($unit, $sec);
        logError('DEBUG generate: Unsplash images fetched, total images now: ' . count($allImages));
    }
    
    // Generar paquete SCORM con plantilla seleccionada
    $templateId = $data['template_id'] ?? 'arelance-corporate';
    $generator = new SCORMGenerator($moduleConfig, $units, $allImages, $templateId);
    $zipPath = $generator->generate();
    
    if (!file_exists($zipPath)) {
        jsonResponse(false, null, 'Error al generar el paquete SCORM');
    }
    
    // Mover a ubicación accesible y generar URL de descarga
    $downloadId = generateUniqueId();
    $downloadPath = TEMP_PATH . '/download_' . $downloadId . '.zip';
    rename($zipPath, $downloadPath);
    
    // Calcular estadísticas
    $totalFlashcards = 0;
    $totalQuestions = 0;
    $totalSections = 0;
    
    foreach ($units as $unit) {
        $totalFlashcards += count($unit['conceptos_clave']);
        $totalQuestions += count($unit['preguntas']);
        $totalSections += count($unit['secciones']);
    }
    
    jsonResponse(true, [
        'download_id' => $downloadId,
        'filename' => $moduleConfig['codigo'] . '_SCORM.zip',
        'size' => filesize($downloadPath),
        'stats' => [
            'units' => count($units),
            'flashcards' => $totalFlashcards,
            'questions' => $totalQuestions,
            'sections' => $totalSections
        ]
    ], 'Paquete SCORM generado correctamente');
    
} catch (\Exception $e) {
    logError('Error en generate.php: ' . $e->getMessage());
    jsonResponse(false, null, 'Error al generar el paquete: ' . $e->getMessage());
}
