<?php
/**
 * API: Generar documento PDF del curso
 * POST /api/generate_pdf.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/PDFGenerator.php';

use ScormConverter\Auth;
use ScormConverter\PDFGenerator;

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
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        jsonResponse(false, null, 'Datos inválidos');
    }

    if (!isset($data['modulo']) || !isset($data['unidades'])) {
        jsonResponse(false, null, 'Faltan datos del módulo o unidades');
    }

    // Preparar configuración del módulo
    $moduleConfig = [
        'codigo'        => $data['modulo']['codigo'] ?? 'MOD_01',
        'titulo'        => $data['modulo']['titulo'] ?? 'Módulo formativo',
        'duracion_total' => $data['modulo']['duracion_total'] ?? DEFAULT_HOURS,
        'empresa'       => $data['modulo']['empresa'] ?? DEFAULT_COMPANY
    ];

    // Preparar unidades (sin imágenes Pexels — el PDF es solo texto)
    $units = [];
    foreach ($data['unidades'] as $unit) {
        $units[] = [
            'numero'            => $unit['numero'],
            'titulo'            => $unit['titulo'],
            'duracion'          => $unit['duracion'] ?? 6,
            'resumen'           => $unit['resumen'] ?? '',
            'objetivos'         => $unit['objetivos'] ?? [],
            'conceptos_clave'   => $unit['conceptos_clave'] ?? [],
            'secciones'         => $unit['secciones'] ?? [],
            'preguntas'         => $unit['preguntas'] ?? [],
            'conclusiones_ia'   => $unit['conclusiones_ia'] ?? []
        ];
    }

    $templateId = $data['template_id'] ?? 'arelance-corporate';
    $generator = new PDFGenerator($moduleConfig, $units, $templateId);
    $pdfPath = $generator->generate();

    if (!file_exists($pdfPath)) {
        jsonResponse(false, null, 'Error al generar el PDF');
    }

    // Mover a ubicación accesible
    $downloadId = generateUniqueId();
    $downloadPath = TEMP_PATH . '/download_' . $downloadId . '.pdf';
    rename($pdfPath, $downloadPath);

    jsonResponse(true, [
        'download_id' => $downloadId,
        'filename'    => $moduleConfig['codigo'] . '_Manual.pdf',
        'size'        => filesize($downloadPath)
    ], 'PDF generado correctamente');

} catch (\Exception $e) {
    logError('Error en generate_pdf.php: ' . $e->getMessage());
    jsonResponse(false, null, 'Error al generar el PDF: ' . $e->getMessage());
}
