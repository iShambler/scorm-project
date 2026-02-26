<?php
/**
 * API: Autenticación y gestión de usuarios
 * POST /api/auth.php?action=login|logout|me|list|create|update_role|delete
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';

use ScormConverter\Auth;

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'login':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            jsonResponse(false, null, 'Usuario y contraseña requeridos');
        }

        $user = $auth->login($username, $password);
        if (!$user) {
            jsonResponse(false, null, 'Credenciales incorrectas');
        }
        jsonResponse(true, $user, 'Login correcto');
        break;

    case 'logout':
        $auth->logout();
        jsonResponse(true, null, 'Sesión cerrada');
        break;

    case 'me':
        $auth->startSession();
        $user = $auth->currentUser();
        if (!$user) {
            jsonResponse(false, null, 'No autenticado');
        }
        jsonResponse(true, $user);
        break;

    // ── Admin: listar usuarios ──
    case 'list':
        $auth->requireAdmin();
        jsonResponse(true, $auth->listUsers());
        break;

    // ── Admin: crear usuario ──
    case 'create':
        $auth->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'user';

        if (empty($username) || empty($password)) {
            jsonResponse(false, null, 'Usuario y contraseña requeridos');
        }
        if (mb_strlen($username) < 3) {
            jsonResponse(false, null, 'Usuario mínimo 3 caracteres');
        }
        if (strlen($password) < 4) {
            jsonResponse(false, null, 'Contraseña mínimo 4 caracteres');
        }

        $user = $auth->createUser($username, $password, $role);
        if (!$user) {
            jsonResponse(false, null, 'El usuario ya existe');
        }
        jsonResponse(true, $user, 'Usuario creado');
        break;

    // ── Admin: cambiar rol ──
    case 'update_role':
        $auth->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $role = $input['role'] ?? '';

        if ($id <= 0 || empty($role)) {
            jsonResponse(false, null, 'ID y rol requeridos');
        }

        $user = $auth->updateRole($id, $role);
        if (!$user) {
            jsonResponse(false, null, 'No se pudo actualizar el rol');
        }
        jsonResponse(true, $user, 'Rol actualizado');
        break;

    // ── Admin: eliminar usuario ──
    case 'delete':
        $auth->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            jsonResponse(false, null, 'ID requerido');
        }

        if ($auth->deleteUser($id)) {
            jsonResponse(true, null, 'Usuario eliminado');
        } else {
            jsonResponse(false, null, 'No se puede eliminar este usuario');
        }
        break;

    default:
        jsonResponse(false, null, 'Acción no válida');
}
