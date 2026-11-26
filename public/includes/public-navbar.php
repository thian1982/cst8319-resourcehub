<?php
require_once __DIR__ . '/auth.php';
$activePublicPage = $activePublicPage ?? '';
$navUser = rh_current_user();
?>
<nav class="navbar navbar-expand-lg navbar-dark public-navbar" aria-label="Main navigation">
 <div class="container">
  <a class="navbar-brand" href="/"><i class="bi bi-box-seam me-2" aria-hidden="true"></i>ResourceHub</a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNavigation">
   <ul class="navbar-nav mx-auto">
    <?php foreach ([['home','/','Home'],['resources','/resources.php','Browse Resources'],['reservations','/my-reservations.php','My Reservations'],['profile','/profile.php','My Profile'],['how','/#how-it-works','How It Works'],['about','/#about','About']] as $item): ?>
     <li class="nav-item"><a class="nav-link <?= $activePublicPage === $item[0] ? 'active' : '' ?>" <?= $activePublicPage === $item[0] ? 'aria-current="page"' : '' ?> href="<?= rh_h($item[1]) ?>"><?= rh_h($item[2]) ?></a></li>
    <?php endforeach; ?>
    <?php if ($navUser && $navUser['role'] === 'admin'): ?><li class="nav-item"><a class="nav-link" href="/admin/admin-dashboard.php">Admin Dashboard</a></li><?php endif; ?>
   </ul>
   <?php if ($navUser): ?>
    <form method="post" action="/logout.php" class="d-flex m-0"><input type="hidden" name="csrf_token" value="<?= rh_h(rh_csrf()) ?>"><button class="btn btn-outline-light public-login-link" type="submit"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Logout</button></form>
   <?php else: ?>
    <a class="btn <?= $activePublicPage === 'login' ? 'btn-light' : 'btn-outline-light' ?> public-login-link" href="/login.php" <?= $activePublicPage === 'login' ? 'aria-current="page"' : '' ?>><i class="bi bi-person me-1" aria-hidden="true"></i>Login</a>
   <?php endif; ?>
  </div>
 </div>
</nav>
