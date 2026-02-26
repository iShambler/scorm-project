<?php
/**
 * API: Gestión de plantillas SCORM
 * GET    /api/templates.php              → listar plantillas
 * GET    /api/templates.php?id=X         → obtener datos de una plantilla
 * GET    /api/templates.php?preview=X    → obtener imagen de preview
 * GET    /api/templates.php?export=X     → descargar plantilla como ZIP
 * POST   /api/templates.php?action=import  → importar plantilla (ZIP upload)
 * DELETE /api/templates.php?id=X         → eliminar plantilla
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';

$auth = new \ScormConverter\Auth();
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../includes/TemplateManager.php';

use ScormConverter\TemplateManager;

ini_set('display_errors', 1);
error_reporting(E_ALL);

$manager = new TemplateManager();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──
if ($method === 'GET') {
    // Preview image
    if (!empty($_GET['preview'])) {
        $path = $manager->getPreviewPath($_GET['preview']);
        if ($path) {
            $mime = mime_content_type($path) ?: 'image/png';
            header('Content-Type: ' . $mime);
            header('Cache-Control: public, max-age=86400');
            readfile($path);
            exit;
        }
        http_response_code(404);
        echo 'Preview no encontrada';
        exit;
    }

    // Export ZIP
    if (!empty($_GET['export'])) {
        $zipPath = $manager->exportTemplate($_GET['export']);
        if ($zipPath && file_exists($zipPath)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="template-' . $_GET['export'] . '.zip"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            @unlink($zipPath);
            exit;
        }
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Plantilla no encontrada']);
        exit;
    }

    // Get single template
    if (!empty($_GET['id'])) {
        header('Content-Type: application/json; charset=utf-8');
        $tpl = $manager->loadTemplate($_GET['id']);
        if ($tpl) {
            unset($tpl['css'], $tpl['dir']); // No enviar CSS completo al frontend
            jsonResponse(true, $tpl, 'OK');
        }
        jsonResponse(false, null, 'Plantilla no encontrada');
    }

    // List all
    header('Content-Type: application/json; charset=utf-8');
    $templates = $manager->listTemplates();
    jsonResponse(true, ['templates' => $templates, 'default' => $manager->getDefaultId()], 'OK');
}

// ── POST: Import ──
if ($method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (empty($_GET['action']) || $_GET['action'] !== 'import') {
        jsonResponse(false, null, 'Acción no válida. Usa ?action=import');
    }

    if (!isset($_FILES['template']) || $_FILES['template']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, null, 'No se recibió el archivo ZIP');
    }

    $file = $_FILES['template'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'zip') {
        jsonResponse(false, null, 'Solo se aceptan archivos .zip');
    }

    $result = $manager->importTemplate($file['tmp_name']);
    jsonResponse($result['success'], ['id' => $result['id']], $result['message']);
}

// ── DELETE ──
if ($method === 'DELETE') {
    header('Content-Type: application/json; charset=utf-8');

    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        jsonResponse(false, null, 'Falta el ID de la plantilla');
    }

    $result = $manager->deleteTemplate($id);
    jsonResponse($result['success'], null, $result['message']);
}

// Método no soportado
http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Método no permitido']);
