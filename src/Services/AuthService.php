<?php
declare(strict_types=1);

// Login, logout and "who is signed in" checks. The session itself is started
// in public/includes/auth.php before this class is used.
class AuthService
{
    private UserRepository $users;

    public function __construct(PDO $pdo)
    {
        $this->users = new UserRepository($pdo);
    }

    // Returns the user on success, or null for a wrong email/password or an inactive account.
    // We give the same answer for both so the form does not reveal which emails exist.
    public function login(string $email, string $password): ?User
    {
        $user = $this->users->findByEmailForLogin($email);
        if ($user === null || !$user->isActive() || !$user->checkPassword($password)) {
            return null;
        }
        session_regenerate_id(true); // new session id after login
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['role'] = $user->getRole();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $user;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600, 'path' => $params['path'], 'domain' => $params['domain'],
                'secure' => $params['secure'], 'httponly' => $params['httponly'], 'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    // Looks the user up again on every request, so a deactivated account
    // or a role change takes effect right away.
    public function currentUser(): ?User
    {
        if (empty($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
            return null;
        }
        $user = $this->users->findById((int) $_SESSION['user_id']);
        if ($user === null || !$user->isActive()) {
            unset($_SESSION['user_id'], $_SESSION['role']);
            return null;
        }
        $_SESSION['role'] = $user->getRole();
        return $user;
    }

    // Stops the page with 403 unless an administrator is signed in.
    public function requireAdmin(): User
    {
        $user = $this->currentUser();
        if ($user === null || !$user->isAdmin()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store, private');
            exit('403 Forbidden: Administrator access required.');
        }
        return $user;
    }
}
