<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$activePublicPage = 'login';
$pageTitle = 'ResourceHub | Login';
$error = '';
$email = '';
if (rh_current_user() !== null) {
    header('Location: /', true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Cache-Control: no-store');
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if (!rh_csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || $password === '' || strlen($email) > 254) {
        $error = 'Enter a valid email address and password.';
    } else {
        try {
            $user = rh_auth()->login($email, $password);
            if ($user !== null) {
                header('Location: ' . ($user->isAdmin() ? '/admin/admin-dashboard.php' : '/'), true, 303);
                exit;
            }
            $error = 'Invalid credentials or inactive account.';
        } catch (Throwable $e) {
            error_log('ResourceHub login failed: ' . $e->getMessage());
            $error = 'Sign-in is temporarily unavailable. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Sign in to ResourceHub to manage equipment reservations.">
<title><?= rh_h($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/public.css">
</head>
<body class="public-page">
<?php require __DIR__ . '/includes/public-navbar.php'; ?>
<main class="container py-5">
<div class="row justify-content-center w-100 mx-auto"><div class="col-md-8 col-lg-5 col-xl-4">
<section class="login-card" aria-labelledby="loginHeading">
<div class="login-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></div>
<h1 id="loginHeading" class="h3 fw-bold text-center">Welcome Back</h1>
<p class="text-secondary text-center mb-4">Sign in to your ResourceHub account.</p>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= rh_h($error) ?></div><?php endif; ?>
<form method="post" action="/login.php">
<input type="hidden" name="csrf_token" value="<?= rh_h(rh_csrf()) ?>">
<div class="mb-3"><label for="email" class="form-label fw-semibold">Email Address</label>
<div class="input-group"><span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span><input type="email" class="form-control" id="email" name="email" value="<?= rh_h($email) ?>" autocomplete="username" placeholder="name@example.com" maxlength="254" required></div></div>
<div class="mb-3"><label for="password" class="form-label fw-semibold">Password</label>
<div class="input-group"><span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span><input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required><button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button></div></div>
<button type="submit" class="btn btn-primary w-100 py-2 mt-2"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button>
</form>
<hr class="my-4"><p class="small text-secondary text-center mb-0">Need an account? Contact your ResourceHub administrator. Public registration is not available.</p>
</section></div></div></main>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>document.getElementById('togglePassword').addEventListener('click', function () { const input = document.getElementById('password'); const show = input.type === 'password'; input.type = show ? 'text' : 'password'; this.setAttribute('aria-label', show ? 'Hide password' : 'Show password'); this.setAttribute('aria-pressed', String(show)); this.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye'; });</script>
</body></html>
