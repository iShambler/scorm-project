<?php
/**
 * API: Descargar paquete SCORM
 * GET /api/download.php?id=xxx
 */

require_once __DIR__ . '/../config.php';

// Obtener ID de descarga
$downloadId = $_GET['id'] ?? '';

if (empty($downloadId) || !preg_match('/^scorm_[a-f0-9.]+$/i', $downloadId)) {
    http_response_code(400);
    die('ID de descarga inválido');
}

$filePath = TEMP_PATH . '/download_' . $downloadId . '.zip';

if (!file_exists($filePath)) {
    http_response_code(404);
    die('Archivo no encontrado o expirado');
}

// Obtener nombre del archivo desde query string o usar genérico
$filename = $_GET['filename'] ?? 'SCORM_Package.zip';
$filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $filename);

// Enviar archivo
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filePath);

// Eliminar archivo después de descarga
unlink($filePath);

exit;
