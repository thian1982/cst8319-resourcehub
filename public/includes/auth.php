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
function rh_auth(): AuthService {
    static $auth = null;
    if ($auth === null) { $auth = new AuthService(rh_pdo()); }
    return $auth;
}
// Pages still use arrays for the signed-in user, so this returns the user as an array.
function rh_current_user(): ?array {
    try {
        $user = rh_auth()->currentUser();
        return $user ? $user->toArray() : null;
    } catch (Throwable $e) {
        error_log('ResourceHub auth lookup failed: ' . $e->getMessage());
        return null; // fail closed
    }
}
function rh_h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
