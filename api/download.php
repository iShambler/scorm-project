<?php
/**
 * API: Descargar paquete SCORM o PDF
 * GET /api/download.php?id=xxx&format=zip|pdf
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';

// Verificar autenticación
$auth = new \ScormConverter\Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    die('No autenticado');
}

// Obtener ID de descarga
$downloadId = $_GET['id'] ?? '';

if (empty($downloadId) || !preg_match('/^scorm_[a-f0-9.]+$/i', $downloadId)) {
    http_response_code(400);
    die('ID de descarga inválido');
}

// Detectar formato (zip o pdf)
$format = $_GET['format'] ?? 'zip';
$ext = ($format === 'pdf') ? '.pdf' : '.zip';
$filePath = TEMP_PATH . '/download_' . $downloadId . $ext;

if (!file_exists($filePath)) {
    http_response_code(404);
    die('Archivo no encontrado o expirado');
}

// Obtener nombre del archivo desde query string o usar genérico
$defaultName = ($format === 'pdf') ? 'Manual.pdf' : 'SCORM_Package.zip';
$filename = $_GET['filename'] ?? $defaultName;
$filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $filename);

// Enviar archivo con Content-Type correcto
$mimeType = ($format === 'pdf') ? 'application/pdf' : 'application/zip';
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filePath);

// Eliminar archivo después de descarga
unlink($filePath);

exit;
