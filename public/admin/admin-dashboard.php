<?php require __DIR__ . '/_auth.php'; ?>
<?php
$pageTitle = 'ResourceHub | Admin Dashboard';
$stats = [];
$reservations = [];
$databaseError = false;

try {
    $pdo = rh_pdo();
    $counts = $pdo->query("SELECT
        (SELECT COUNT(*) FROM resources) AS total_resources,
        (SELECT COUNT(*) FROM reservations WHERE status = 'active') AS active_reservations,
        (SELECT COUNT(*) FROM users) AS registered_users,
        (SELECT COUNT(*) FROM resources WHERE status = 'maintenance') AS under_maintenance")->fetch(PDO::FETCH_ASSOC);

    $stats = [
        ['label' => 'Total Resources', 'value' => (int) $counts['total_resources'], 'icon' => 'bi-box-seam', 'detail' => 'Resources in inventory'],
        ['label' => 'Active Reservations', 'value' => (int) $counts['active_reservations'], 'icon' => 'bi-calendar-check', 'detail' => 'Reservations with active status'],
        ['label' => 'Registered Users', 'value' => (int) $counts['registered_users'], 'icon' => 'bi-people', 'detail' => 'All registered accounts'],
        ['label' => 'Under Maintenance', 'value' => (int) $counts['under_maintenance'], 'icon' => 'bi-tools', 'detail' => 'Resources in maintenance status'],
    ];

    $stmt = $pdo->query("SELECT res.reservation_id, u.full_name AS user_name,
        r.name AS resource_name, res.start_datetime, res.end_datetime, res.status
        FROM reservations res
        INNER JOIN users u ON u.user_id = res.user_id
        INNER JOIN resources r ON r.resource_id = res.resource_id
        ORDER BY res.created_at DESC, res.reservation_id DESC
        LIMIT 10");
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('ResourceHub admin dashboard database error: ' . $exception->getMessage());
    $databaseError = true;
    http_response_code(503);
}

function escapeHtml(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="ResourceHub administration dashboard.">
<title><?= escapeHtml($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-page">
<div class="admin-layout">
<?php $activeAdminPage = 'dashboard'; require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
<main class="admin-main">
<?php if ($databaseError): ?><div class="alert alert-danger" role="alert">Dashboard data is temporarily unavailable. Please try again later.</div><?php endif; ?>
   <div class="mb-4"><h1 class="fw-bold mb-1">Administrator Dashboard</h1><p class="text-secondary mb-0">Overview of ResourceHub operations — live database data.</p></div>
   <?php if (!$databaseError): ?>
   <section aria-label="Dashboard statistics" class="row g-3 mb-4">
    <?php foreach ($stats as $stat): ?>
    <div class="col-sm-6 col-xl-3"><div class="stat-card">
     <div class="d-flex justify-content-between align-items-start mb-3"><span class="text-secondary small fw-semibold"><?= escapeHtml($stat['label']) ?></span><span class="stat-icon"><i class="bi <?= escapeHtml($stat['icon']) ?>" aria-hidden="true"></i></span></div>
     <div class="stat-value"><?= (int)$stat['value'] ?></div><div class="text-secondary small"><?= escapeHtml($stat['detail']) ?></div>
    </div></div>
    <?php endforeach; ?>
   </section>
   <section class="panel mb-4" aria-labelledby="recentHeading">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><h2 id="recentHeading" class="h5 fw-bold mb-0">Recent Reservations</h2><span class="badge text-bg-light border">Latest 10 records</span></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
     <thead><tr><th scope="col">Booking ID</th><th scope="col">User</th><th scope="col">Resource</th><th scope="col">Start</th><th scope="col">End</th><th scope="col">Status</th></tr></thead>
     <tbody>
     <?php if (!$reservations): ?><tr><td colspan="6" class="text-center text-secondary py-4">No reservations found.</td></tr><?php endif; ?>
     <?php foreach ($reservations as $booking): ?>
      <?php $badgeClass = ['active'=>'text-bg-primary','completed'=>'text-bg-success','cancelled'=>'text-bg-secondary'][$booking['status']] ?? 'text-bg-light'; ?>
      <tr><td class="fw-semibold"><?= 'RH-' . (int) $booking['reservation_id'] ?></td><td><?= escapeHtml((string) $booking['user_name']) ?></td><td><?= escapeHtml((string) $booking['resource_name']) ?></td><td><?= escapeHtml((string) $booking['start_datetime']) ?></td><td><?= escapeHtml((string) $booking['end_datetime']) ?></td><td><span class="badge <?= $badgeClass ?>"><?= escapeHtml(ucfirst((string) $booking['status'])) ?></span></td></tr>
     <?php endforeach; ?>
     </tbody>
    </table></div>
   </section>
   <?php endif; ?>
   <section class="panel" aria-labelledby="actionsHeading">
     <h2 id="actionsHeading" class="h5 fw-bold mb-3">Administrative Modules</h2>
     <p class="text-secondary mb-3">Choose a module to manage accounts, resources, reservations, or maintenance.</p>
     <div class="row g-3">
      <?php foreach ([
       ['icon'=>'bi-people','label'=>'Users','url'=>'/admin/admin-users.php'],
       ['icon'=>'bi-box-seam','label'=>'Resources','url'=>'/admin/admin-resources.php'],
       ['icon'=>'bi-tags','label'=>'Categories','url'=>'/admin/admin-categories.php'],
       ['icon'=>'bi-calendar-check','label'=>'Reservations','url'=>'/admin/admin-reservations.php'],
       ['icon'=>'bi-tools','label'=>'Maintenance','url'=>'/admin/admin-maintenance.php']
      ] as $module): ?>
      <div class="col-sm-6 col-xl">
       <a class="d-block border rounded-3 p-3 h-100 text-center text-decoration-none text-dark" href="<?= escapeHtml($module['url']) ?>">
        <i class="bi <?= escapeHtml($module['icon']) ?> fs-3 text-primary" aria-hidden="true"></i>
        <div class="fw-semibold mt-2"><?= escapeHtml($module['label']) ?></div>
        <small class="text-primary">Open module <i class="bi bi-arrow-right" aria-hidden="true"></i></small>
       </a>
      </div>
      <?php endforeach; ?>
     </div>
    </section>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
