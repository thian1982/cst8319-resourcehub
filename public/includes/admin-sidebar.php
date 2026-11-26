<?php
/**
 * ResourceHub shared administrator sidebar.
 * Include inside a Bootstrap .row, before <main>.
 * Set $activeAdminPage to dashboard, users, resources, categories,
 * reservations, or maintenance before including this file.
 */
$adminNavigation = [
    'dashboard' => ['label' => 'Dashboard', 'href' => '/admin/admin-dashboard.php', 'icon' => 'bi-speedometer2'],
    'users' => ['label' => 'Manage Users', 'href' => '/admin/admin-users.php', 'icon' => 'bi-people'],
    'resources' => ['label' => 'Manage Resources', 'href' => '/admin/admin-resources.php', 'icon' => 'bi-boxes'],
    'categories' => ['label' => 'Manage Categories', 'href' => '/admin/admin-categories.php', 'icon' => 'bi-tags'],
    'reservations' => ['label' => 'Reservations', 'href' => '/admin/admin-reservations.php', 'icon' => 'bi-calendar-check'],
    'maintenance' => ['label' => 'Maintenance', 'href' => '/admin/admin-maintenance.php', 'icon' => 'bi-tools'],
];
$activeAdminPage = $activeAdminPage ?? '';
?>
<aside class="col-md-3 col-lg-2 admin-sidebar p-3" aria-label="Administrator navigation">
    <a class="admin-brand mb-4" href="/admin/admin-dashboard.php">
        <i class="bi bi-box-seam me-2" aria-hidden="true"></i>ResourceHub
    </a>
    <div class="small text-uppercase text-white-50 mb-2">Administration</div>
    <nav class="admin-nav" aria-label="Admin pages">
        <?php foreach ($adminNavigation as $key => $item): ?>
            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
               class="<?= $key === $activeAdminPage ? 'active' : '' ?>"
               <?= $key === $activeAdminPage ? 'aria-current="page"' : '' ?>>
                <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> me-2" aria-hidden="true"></i><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <hr class="border-light opacity-25">
    <a class="admin-home-link" href="/"><i class="bi bi-house me-2" aria-hidden="true"></i>Public Homepage</a>
</aside>
