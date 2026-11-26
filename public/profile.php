<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$activePublicPage = 'profile';
$pageTitle = 'ResourceHub | My Profile';
header('Cache-Control: private, no-store, max-age=0');

$user = rh_current_user();
if ($user === null) {
    header('Location: /login.php', true, 303);
    exit;
}

$roleLabel = $user['role'] === 'admin' ? 'Administrator' : 'Regular User';
$stats = ['total' => 0, 'upcoming' => 0, 'completed' => 0];
$statsError = false;
try {
    $stmt = rh_pdo()->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = 'active' AND start_datetime > NOW() THEN 1 ELSE 0 END), 0) AS upcoming,
                COALESCE(SUM(CASE WHEN (status = 'completed' OR (status = 'active' AND end_datetime <= NOW())) THEN 1 ELSE 0 END), 0) AS completed
         FROM reservations
         WHERE user_id = :user_id"
    );
    $stmt->execute(['user_id' => (int) $user['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result === false) {
        throw new RuntimeException('Reservation summary query returned no row.');
    }
    foreach ($stats as $key => $_) {
        $stats[$key] = (int) $result[$key];
    }
} catch (Throwable $e) {
    error_log('ResourceHub profile reservation summary failed: ' . $e->getMessage());
    $statsError = true;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= rh_h($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/public.css">
</head>
<body class="public-page">
<?php require __DIR__ . '/includes/public-navbar.php'; ?>
<main class="container py-4 py-lg-5">
<nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/">Home</a></li><li class="breadcrumb-item active" aria-current="page">My Profile</li></ol></nav>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="fw-bold mb-1">My Profile</h1><p class="text-secondary mb-0">Account information and reservation overview.</p></div><span class="badge text-bg-success">Signed in</span></div>
<div class="row g-4">
<div class="col-lg-4"><section class="panel h-100">
<div class="d-flex flex-column align-items-center text-center"><div class="avatar mb-3"><i class="bi bi-person" aria-hidden="true"></i></div><h2 class="h4 fw-bold mb-1"><?= rh_h((string) $user['full_name']) ?></h2><p class="text-secondary mb-2"><?= rh_h($roleLabel) ?></p><span class="badge text-bg-light border">Active account</span></div>
<hr><p class="small text-secondary mb-2">Account management</p>
<button class="btn btn-outline-primary w-100 mb-2" type="button" disabled>Edit Profile (coming soon)</button>
<button class="btn btn-outline-secondary w-100" type="button" disabled>Change Password (coming soon)</button>
<p class="small text-secondary mt-3 mb-0">Account editing and password changes are not available yet.</p>
</section></div>
<div class="col-lg-8">
<section class="panel mb-4"><h2 class="h5 fw-bold mb-4">Account Information</h2><div class="row g-4">
<div class="col-md-6"><div class="info-label">Full name</div><div class="info-value"><?= rh_h((string) $user['full_name']) ?></div></div>
<div class="col-md-6"><div class="info-label">Email address</div><div class="info-value"><?= rh_h((string) $user['email']) ?></div></div>
<div class="col-md-6"><div class="info-label">Account role</div><div class="info-value"><?= rh_h($roleLabel) ?></div></div>
<div class="col-md-6"><div class="info-label">Account status</div><div class="info-value">Active</div></div>
</div></section>
<h2 class="h5 fw-bold mb-3">Reservation Overview</h2>
<?php if ($statsError): ?>
<div class="alert alert-warning" role="alert">Reservation statistics are temporarily unavailable. Please try again later.</div>
<?php else: ?>
<div class="row g-3">
<div class="col-sm-4"><div class="stat"><div class="text-secondary small">Total</div><strong><?= $stats['total'] ?></strong></div></div>
<div class="col-sm-4"><div class="stat"><div class="text-secondary small">Upcoming</div><strong><?= $stats['upcoming'] ?></strong></div></div>
<div class="col-sm-4"><div class="stat"><div class="text-secondary small">Completed</div><strong><?= $stats['completed'] ?></strong></div></div>
</div>
<p class="small text-secondary mt-2 mb-0">Upcoming counts active reservations with a future start time. Completed includes non-cancelled reservations that have ended.</p>
<?php endif; ?>
<a class="btn btn-primary mt-4" href="/my-reservations.php"><i class="bi bi-calendar-check me-2"></i>View My Reservations</a>
</div></div>
</main>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
