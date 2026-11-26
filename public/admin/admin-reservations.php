<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
$pageTitle = 'ResourceHub | Manage Reservations';
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function validDateTime(string $value): ?string {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    return $dt && $dt->format('Y-m-d\TH:i') === $value ? $dt->format('Y-m-d H:i:s') : null;
}
$error = '';
$notice = '';
$pdo = null;
try {
    $pdo = rh_pdo();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!rh_csrf_valid($_POST['csrf_token'] ?? null)) {
            throw new DomainException('Security token expired. Refresh the page and try again.');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            $resourceId = filter_var($_POST['resource_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            $start = validDateTime((string)($_POST['start_datetime'] ?? ''));
            $end = validDateTime((string)($_POST['end_datetime'] ?? ''));
            if (!$userId || !$resourceId || !$start || !$end || $start >= $end) {
                throw new DomainException('Select a user and resource, and provide a valid start time earlier than the end time.');
            }
            if ($start <= date('Y-m-d H:i:s')) {
                throw new DomainException('New reservations must start in the future.');
            }
            $pdo->beginTransaction();
            // Lock the resource row to serialize booking attempts for this resource.
            $resourceStmt = $pdo->prepare('SELECT status FROM resources WHERE resource_id = ? FOR UPDATE');
            $resourceStmt->execute([$resourceId]);
            $resource = $resourceStmt->fetch();
            if (!$resource || $resource['status'] !== 'available') {
                throw new DomainException('This resource is not available for new reservations.');
            }
            $userStmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND account_status = 'active' LIMIT 1");
            $userStmt->execute([$userId]);
            if (!$userStmt->fetch()) {
                throw new DomainException('Select an active user account.');
            }
            $overlap = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE resource_id = ? AND status = 'active' AND start_datetime < ? AND end_datetime > ?");
            $overlap->execute([$resourceId, $end, $start]);
            if ((int)$overlap->fetchColumn() > 0) {
                throw new DomainException('This resource already has an overlapping active reservation.');
            }
            $insert = $pdo->prepare("INSERT INTO reservations (user_id, resource_id, start_datetime, end_datetime, status) VALUES (?, ?, ?, ?, 'active')");
            $insert->execute([$userId, $resourceId, $start, $end]);
            $pdo->commit();
            header('Location: /admin/admin-reservations.php?result=created', true, 303);
            exit;
        } elseif ($action === 'cancel') {
            $id = filter_var($_POST['reservation_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if (!$id) { throw new DomainException('Invalid reservation.'); }
            $update = $pdo->prepare("UPDATE reservations SET status = 'cancelled', cancelled_at = NOW() WHERE reservation_id = ? AND status = 'active'");
            $update->execute([$id]);
            if ($update->rowCount() === 0) { throw new DomainException('Only active reservations can be cancelled.'); }
            header('Location: /admin/admin-reservations.php?result=cancelled', true, 303);
            exit;
        } else {
            throw new DomainException('Unsupported operation.');
        }
    }
} catch (DomainException $ex) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    $error = $ex->getMessage();
} catch (Throwable $ex) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('ResourceHub admin reservations error: ' . $ex->getMessage());
    $error = 'The reservation operation could not be completed. Please try again or contact the administrator.';
}
$rows = [];
$users = [];
$resources = [];
$counts = ['active'=>0, 'completed'=>0, 'cancelled'=>0];
try {
    if (!$pdo) { $pdo = rh_pdo(); }
    $rows = $pdo->query('SELECT r.reservation_id, r.user_id, r.resource_id, r.start_datetime, r.end_datetime, r.status, u.full_name AS user_name, x.name AS resource_name FROM reservations r JOIN users u ON u.user_id = r.user_id JOIN resources x ON x.resource_id = r.resource_id ORDER BY r.created_at DESC, r.reservation_id DESC')->fetchAll();
    $users = $pdo->query("SELECT user_id, full_name, email FROM users WHERE account_status = 'active' ORDER BY full_name")->fetchAll();
    $resources = $pdo->query("SELECT resource_id, name, location FROM resources WHERE status = 'available' ORDER BY name")->fetchAll();
    foreach ($rows as $r) { if (isset($counts[$r['status']])) { $counts[$r['status']]++; } }
} catch (Throwable $ex) {
    error_log('ResourceHub reservation listing failed: ' . $ex->getMessage());
    $error = 'Unable to load reservation data. Please try again later.';
}
if (($_GET['result'] ?? '') === 'created') { $notice = 'Reservation created successfully.'; }
if (($_GET['result'] ?? '') === 'cancelled') { $notice = 'Reservation cancelled successfully.'; }
$csrf = rh_csrf();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title><?= e($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="/assets/css/admin.css" rel="stylesheet"></head>
<body class="admin-page"><div class="admin-layout">
<?php $activeAdminPage = 'reservations'; require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
<main class="admin-main">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><div><h1 class="h2 fw-bold mb-1">Manage Reservations</h1><p class="text-secondary mb-0">Review and manage ResourceHub reservation records.</p></div><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus-lg me-1"></i>Create Reservation</button></div>
<?php if ($notice): ?><div class="alert alert-success" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
<div class="col-sm-6 col-xl-3"><div class="stat"><div class="text-secondary small">Total Reservations</div><div class="value"><?= count($rows) ?></div></div></div>
<div class="col-sm-6 col-xl-3"><div class="stat"><div class="text-secondary small">Active</div><div class="value text-primary"><?= $counts['active'] ?></div></div></div>
<div class="col-sm-6 col-xl-3"><div class="stat"><div class="text-secondary small">Completed</div><div class="value text-success"><?= $counts['completed'] ?></div></div></div>
<div class="col-sm-6 col-xl-3"><div class="stat"><div class="text-secondary small">Cancelled</div><div class="value text-secondary"><?= $counts['cancelled'] ?></div></div></div>
</div>
<section class="panel" aria-labelledby="listTitle"><div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-3"><h2 id="listTitle" class="h5 fw-bold mb-0">Reservation Directory</h2><span id="resultCount" class="small text-secondary" aria-live="polite"></span></div>
<div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label" for="search">Search reservations</label><input id="search" type="search" class="form-control" placeholder="ID, user, or resource"></div><div class="col-md-4"><label class="form-label" for="status">Reservation status</label><select id="status" class="form-select"><option value="">All Statuses</option><option value="active">Active</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div><div class="col-md-2 d-flex align-items-end"><button id="reset" type="button" class="btn btn-outline-secondary w-100">Reset Filters</button></div></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th scope="col">Reservation ID</th><th scope="col">User</th><th scope="col">Resource</th><th scope="col">Start</th><th scope="col">End</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): $s = $r['status']; $label = $s === 'active' ? (strtotime($r['start_datetime']) > time() ? 'Upcoming' : 'Active') : ucfirst($s); $badge = $s === 'active' ? 'primary' : ($s === 'completed' ? 'success' : 'secondary'); ?>
<tr class="reservation-row" data-search="<?= e(strtolower('RES-'.$r['reservation_id'].' '.$r['user_name'].' '.$r['resource_name'])) ?>" data-status="<?= e($s) ?>"><td class="fw-semibold">RES-<?= (int)$r['reservation_id'] ?></td><td><?= e($r['user_name']) ?></td><td><?= e($r['resource_name']) ?></td><td class="text-nowrap"><?= e($r['start_datetime']) ?></td><td class="text-nowrap"><?= e($r['end_datetime']) ?></td><td><span class="badge text-bg-<?= e($badge) ?>"><?= e($label) ?></span></td><td>
<?php if ($s === 'active'): ?><form method="post" onsubmit="return confirm('Cancel this reservation? This action cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="reservation_id" value="<?= (int)$r['reservation_id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button></form><?php else: ?><span class="text-secondary small">No actions</span><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><div id="noResults" class="alert alert-info d-none" role="status">No reservations match the selected filters.</div></section>
</main></div>
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h2 class="modal-title fs-5" id="createModalLabel">Create Reservation</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create">
<div class="mb-3"><label for="userId" class="form-label">Active User</label><select id="userId" name="user_id" class="form-select" required><option value="">Select a user</option><?php foreach ($users as $u): ?><option value="<?= (int)$u['user_id'] ?>"><?= e($u['full_name'].' ('.$u['email'].')') ?></option><?php endforeach; ?></select></div>
<div class="mb-3"><label for="resourceId" class="form-label">Available Resource</label><select id="resourceId" name="resource_id" class="form-select" required><option value="">Select a resource</option><?php foreach ($resources as $resource): ?><option value="<?= (int)$resource['resource_id'] ?>"><?= e($resource['name'].' — '.$resource['location']) ?></option><?php endforeach; ?></select></div>
<div class="mb-3"><label for="startDatetime" class="form-label">Start</label><input id="startDatetime" name="start_datetime" type="datetime-local" class="form-control" required></div><div class="mb-3"><label for="endDatetime" class="form-label">End</label><input id="endDatetime" name="end_datetime" type="datetime-local" class="form-control" required></div><p class="small text-secondary mb-0">Bookings must be in the future and cannot overlap an active reservation for the same resource.</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Create Reservation</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script>
const search=document.getElementById('search'),status=document.getElementById('status'),rows=document.querySelectorAll('.reservation-row');
function filterRows(){const q=search.value.trim().toLowerCase(),s=status.value;let visible=0;rows.forEach(row=>{const show=row.dataset.search.includes(q)&&(!s||row.dataset.status===s);row.classList.toggle('d-none',!show);if(show)visible++;});document.getElementById('resultCount').textContent=`${visible} of ${rows.length} reservations`;document.getElementById('noResults').classList.toggle('d-none',visible!==0);}
search.addEventListener('input',filterRows);status.addEventListener('change',filterRows);document.getElementById('reset').addEventListener('click',()=>{search.value='';status.value='';filterRows();});filterRows();
</script></body></html>
