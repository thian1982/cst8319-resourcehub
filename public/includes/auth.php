<?php
declare(strict_types=1);

// Model, repository and service classes in src/ are loaded on demand.
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (headers_sent()) { throw new RuntimeException('Cannot start session after output.'); }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('RESOURCEHUBSESSID');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}

function rh_pdo(): PDO {
    return require dirname(__DIR__, 2) . '/config/database.php';
}
function rh_csrf(): string {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf_token'];
}
function rh_csrf_valid($token): bool {
    return is_string($token) && hash_equals(rh_csrf(), $token);
}
function rh_current_user(): ?array {
    if (empty($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) { return null; }
    try {
        $stmt = rh_pdo()->prepare('SELECT user_id, full_name, email, role, account_status FROM users WHERE user_id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['account_status'] !== 'active' || !in_array($user['role'], ['user', 'admin'], true)) {
            unset($_SESSION['user_id'], $_SESSION['role']);
            return null;
        }
        $_SESSION['role'] = $user['role'];
        return $user;
    } catch (Throwable $e) {
        error_log('ResourceHub auth lookup failed: ' . $e->getMessage());
        return null; // fail closed
    }
}
function rh_h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
