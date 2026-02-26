<?php
/**
 * Sistema de autenticación con roles
 * Almacén: MySQL (tabla `users`)
 */

namespace ScormConverter;

class Auth
{
    private \PDO $db;

    public function __construct()
    {
        $this->connect();
        $this->ensureTable();
        $this->ensureAdmin();
    }

    // ── Conexión ──

    private function connect(): void
    {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $this->db = new \PDO($dsn, DB_USER, DB_PASS, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * Crea la tabla users si no existe
     */
    private function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `username`      VARCHAR(100) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `role`          ENUM('admin','user') NOT NULL DEFAULT 'user',
                `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ── Sesión ──

    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login(string $username, string $password): ?array
    {
        $this->startSession();
        $user = $this->findByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        return $this->safeUser($user);
    }

    public function logout(): void
    {
        $this->startSession();
        session_destroy();
    }

    public function currentUser(): ?array
    {
        $this->startSession();
        if (empty($_SESSION['user_id'])) return null;
        $user = $this->findById((int)$_SESSION['user_id']);
        if (!$user) return null;
        $_SESSION['role'] = $user['role'];
        return $this->safeUser($user);
    }

    public function isLoggedIn(): bool
    {
        return $this->currentUser() !== null;
    }

    public function isAdmin(): bool
    {
        $user = $this->currentUser();
        return $user && $user['role'] === 'admin';
    }

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            if ($this->isApiRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'No autenticado']);
                exit;
            }
        }
    }

    public function requireAdmin(): void
    {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
    }

    // ── CRUD usuarios ──

    public function listUsers(): array
    {
        $stmt = $this->db->query("SELECT * FROM `users` ORDER BY `id` ASC");
        return array_map([$this, 'safeUser'], $stmt->fetchAll());
    }

    public function createUser(string $username, string $password, string $role = 'user'): ?array
    {
        $username = trim($username);
        if (empty($username) || empty($password)) return null;
        if ($this->findByUsername($username)) return null;

        $role = in_array($role, ['admin', 'user']) ? $role : 'user';
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->db->prepare("
            INSERT INTO `users` (`username`, `password_hash`, `role`) VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $hash, $role]);

        $user = $this->findById((int)$this->db->lastInsertId());
        return $user ? $this->safeUser($user) : null;
    }

    public function updateRole(int $id, string $newRole): ?array
    {
        if (!in_array($newRole, ['admin', 'user'])) return null;

        $stmt = $this->db->prepare("UPDATE `users` SET `role` = ? WHERE `id` = ?");
        $stmt->execute([$newRole, $id]);

        $user = $this->findById($id);
        return $user ? $this->safeUser($user) : null;
    }

    public function deleteUser(int $id): bool
    {
        // No permitir borrar el admin original (id=1)
        if ($id === 1) return false;

        $stmt = $this->db->prepare("DELETE FROM `users` WHERE `id` = ? AND `id` != 1");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // ── Internos ──

    private function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `users` WHERE `username` = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function safeUser(array $user): array
    {
        return [
            'id'         => (int)$user['id'],
            'username'   => $user['username'],
            'role'       => $user['role'],
            'created_at' => $user['created_at'] ?? ''
        ];
    }

    /**
     * Asegura que el usuario admin existe
     */
    private function ensureAdmin(): void
    {
        $admin = $this->findByUsername('admin');
        if (!$admin) {
            $hash = password_hash('Arelance2024K', PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("
                INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `created_at`)
                VALUES (1, 'admin', ?, 'admin', '2025-01-01 00:00:00')
            ");
            $stmt->execute([$hash]);
        }
    }

    private function isApiRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return strpos($accept, 'application/json') !== false
            || strpos($contentType, 'application/json') !== false
            || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
    }
}
