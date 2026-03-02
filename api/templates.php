<?php
/**
 * API: Gestión de temas SCORM
 * GET    /api/templates.php                    → preset + temas del usuario (admin: todos)
 * POST   /api/templates.php?action=create      → crear tema (colores + logo)
 * DELETE /api/templates.php?id=X               → eliminar tema del usuario
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

$manager = new TemplateManager();
$method = $_SERVER['REQUEST_METHOD'];
$user = $auth->currentUser();
$db = $auth->getDb();

// ── GET: Listar preset + temas de usuario ──
if ($method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    // Preset arelance-corporate siempre presente
    $presets = $manager->listTemplates();

    // Temas del usuario (admin ve todos)
    $userThemes = $auth->isAdmin()
        ? $manager->getAllUserThemes($db)
        : $manager->getUserThemes($db, $user['id']);

    jsonResponse(true, [
        'presets' => $presets,
        'user_themes' => $userThemes,
        'default' => $manager->getDefaultId()
    ], 'OK');
}

// ── POST: Crear tema ──
if ($method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_GET['action'] ?? '';

    // ── Actualizar tema existente ──
    if ($action === 'update') {
        $themeId = (int)($_GET['id'] ?? 0);
        if ($themeId < 1) jsonResponse(false, null, 'Falta el ID del tema');

        $name = trim($_POST['name'] ?? '');
        $primary = trim($_POST['color_primary'] ?? '');
        $accent = trim($_POST['color_accent'] ?? '');
        if (empty($name) || empty($primary) || empty($accent)) {
            jsonResponse(false, null, 'Faltan datos obligatorios');
        }

        // Logo nuevo si se sube
        $logoFilename = null;
        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['logo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'svg'])) jsonResponse(false, null, 'Logo: solo PNG, JPG o SVG');
            if ($file['size'] > 2 * 1024 * 1024) jsonResponse(false, null, 'Logo: máximo 2 MB');

            $logosDir = (defined('UPLOAD_PATH') ? UPLOAD_PATH : __DIR__ . '/../uploads') . '/logos';
            if (!is_dir($logosDir)) @mkdir($logosDir, 0755, true);
            $logoFilename = 'logo_' . $user['id'] . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $logosDir . '/' . $logoFilename);
        }

        $updated = $manager->updateUserTheme($db, $themeId, $user['id'], $name, $primary, $accent, $logoFilename, $auth->isAdmin());
        if ($updated) {
            jsonResponse(true, $updated, 'Tema actualizado');
        }
        jsonResponse(false, null, 'No se pudo actualizar el tema');
    }

    if ($action !== 'create') {
        jsonResponse(false, null, 'Acción no válida');
    }

    $name = trim($_POST['name'] ?? '');
    $primary = trim($_POST['color_primary'] ?? '');
    $accent = trim($_POST['color_accent'] ?? '');

    if (empty($name) || empty($primary) || empty($accent)) {
        jsonResponse(false, null, 'Faltan datos: nombre, color primario y color acento son obligatorios');
    }

    // Procesar logo si se sube
    $logoFilename = null;
    if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'svg'])) {
            jsonResponse(false, null, 'Logo: solo PNG, JPG o SVG');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            jsonResponse(false, null, 'Logo: máximo 2 MB');
        }

        $logosDir = (defined('UPLOAD_PATH') ? UPLOAD_PATH : __DIR__ . '/../uploads') . '/logos';
        if (!is_dir($logosDir)) @mkdir($logosDir, 0755, true);

        $logoFilename = 'logo_' . $user['id'] . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $logosDir . '/' . $logoFilename);
    }

    $theme = $manager->createUserTheme($db, $user['id'], $name, $primary, $accent, $logoFilename);
    if (!$theme) {
        jsonResponse(false, null, 'Error al crear el tema. Verifica los datos.');
    }

    jsonResponse(true, $theme, 'Tema creado correctamente');
}

// ── DELETE: Eliminar tema ──
if ($method === 'DELETE') {
    header('Content-Type: application/json; charset=utf-8');

    $id = (int)($_GET['id'] ?? 0);
    if ($id < 1) {
        jsonResponse(false, null, 'Falta el ID del tema');
    }

    $deleted = $manager->deleteUserTheme($db, $id, $user['id'], $auth->isAdmin());
    if ($deleted) {
        jsonResponse(true, null, 'Tema eliminado');
    }
    jsonResponse(false, null, 'No se pudo eliminar el tema');
}

// Método no soportado
http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Método no permitido']);
