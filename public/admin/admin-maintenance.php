<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
$pageTitle = 'ResourceHub | Maintenance Management';
function mh($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirectMaintenance(string $message): void {
    $_SESSION['maintenance_flash'] = $message;
    header('Location: /admin/admin-maintenance.php', true, 303);
    exit;
}
$error = '';
try {
    $pdo = rh_pdo();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!rh_csrf_valid($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $error = 'Security verification failed. Refresh the page and try again.';
        } else {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'create') {
                $resourceId = filter_var($_POST['resource_id'] ?? null, FILTER_VALIDATE_INT);
                $note = trim((string)($_POST['note'] ?? ''));
                if (!$resourceId || $resourceId < 1 || $note === '' || mb_strlen($note) > 2000) {
                    $error = 'Select a resource and enter a maintenance note of 1–2000 characters.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare('SELECT resource_id, status FROM resources WHERE resource_id = ? FOR UPDATE');
                        $stmt->execute([$resourceId]);
                        $resource = $stmt->fetch();
                        if (!$resource || $resource['status'] === 'inactive') {
                            throw new DomainException('This resource is unavailable for maintenance updates.');
                        }
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE resource_id = ? AND status = 'active' AND end_datetime > NOW()");
                        $stmt->execute([$resourceId]);
                        if ((int)$stmt->fetchColumn() > 0 && $resource['status'] !== 'maintenance') {
                            throw new DomainException('This resource has active or upcoming bookings. Resolve those bookings before starting maintenance.');
                        }
                        $stmt = $pdo->prepare('INSERT INTO maintenance_notes (resource_id, created_by_user_id, note, started_at) VALUES (?, ?, ?, NOW())');
                        $stmt->execute([$resourceId, (int)$adminUser['user_id'], $note]);
                        $stmt = $pdo->prepare("UPDATE resources SET status = 'maintenance' WHERE resource_id = ?");
                        $stmt->execute([$resourceId]);
                        $pdo->commit();
                        redirectMaintenance('Maintenance record created successfully.');
                    } catch (Throwable $ex) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        throw $ex;
                    }
                }
            } elseif ($action === 'complete') {
                $noteId = filter_var($_POST['maintenance_note_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$noteId || $noteId < 1) {
                    $error = 'Invalid maintenance record.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare('SELECT resource_id FROM maintenance_notes WHERE maintenance_note_id = ?');
                        $stmt->execute([$noteId]);
                        $record = $stmt->fetch();
                        if (!$record) throw new DomainException('Maintenance record not found.');
                        $stmt = $pdo->prepare('SELECT resource_id, status FROM resources WHERE resource_id = ? FOR UPDATE');
                        $stmt->execute([(int)$record['resource_id']]);
                        if (!$stmt->fetch()) throw new DomainException('Resource not found.');
                        $stmt = $pdo->prepare('UPDATE maintenance_notes SET ended_at = NOW() WHERE maintenance_note_id = ? AND ended_at IS NULL');
                        $stmt->execute([$noteId]);
                        if ($stmt->rowCount() !== 1) throw new DomainException('This maintenance record has already been completed.');
                        $stmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_notes WHERE resource_id = ? AND ended_at IS NULL');
                        $stmt->execute([(int)$record['resource_id']]);
                        if ((int)$stmt->fetchColumn() === 0) {
                            $stmt = $pdo->prepare("UPDATE resources SET status = 'available' WHERE resource_id = ? AND status = 'maintenance'");
                            $stmt->execute([(int)$record['resource_id']]);
                        }
                        $pdo->commit();
                        redirectMaintenance('Maintenance record marked completed.');
                    } catch (Throwable $ex) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        throw $ex;
                    }
                }
            } else {
                $error = 'Unknown action.';
            }
        }
    }
    $records = $pdo->query('SELECT m.maintenance_note_id, m.resource_id, m.note, m.started_at, m.ended_at, r.name AS resource_name, u.full_name AS reported_by FROM maintenance_notes m JOIN resources r ON r.resource_id = m.resource_id JOIN users u ON u.user_id = m.created_by_user_id ORDER BY m.started_at DESC, m.maintenance_note_id DESC')->fetchAll();
    $resources = $pdo->query("SELECT resource_id, name, status FROM resources WHERE status <> 'inactive' ORDER BY name, resource_id")->fetchAll();
} catch (DomainException $ex) {
    $error = $ex->getMessage();
} catch (Throwable $ex) {
    error_log('ResourceHub maintenance: ' . $ex->getMessage());
    $error = 'Unable to complete the request. Please try again or check the server log.';
}
$records = $records ?? [];
$resources = $resources ?? [];
$openCount = count(array_filter($records, static fn($r) => $r['ended_at'] === null));
$completedCount = count($records) - $openCount;
$flash = $_SESSION['maintenance_flash'] ?? null;
unset($_SESSION['maintenance_flash']);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title><?= mh($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet"></head><body class="admin-page">
<div class="admin-layout">
<?php $activeAdminPage = 'maintenance'; require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
<main class="admin-main">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><p class="text-secondary mb-1">Administrator / Maintenance</p><h1 class="fw-bold h2">Maintenance Management</h1><p class="text-secondary mb-0">Track equipment issues and maintenance activity.</p></div><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenance"><i class="bi bi-plus-lg me-1"></i>Add Maintenance Record</button></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= mh($error) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="alert alert-success" role="status"><?= mh($flash) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
<?php foreach ([['All Records',count($records),'bi-clipboard-data'],['Open Maintenance',$openCount,'bi-tools'],['Completed',$completedCount,'bi-check-circle']] as $stat): ?>
<div class="col-sm-6 col-xl-4"><div class="stat h-100 d-flex justify-content-between align-items-center"><div><div class="text-secondary small mb-2"><?= mh($stat[0]) ?></div><strong><?= (int)$stat[1] ?></strong></div><i class="bi <?= mh($stat[2]) ?>" aria-hidden="true"></i></div></div>
<?php endforeach; ?></div>
<section class="panel" aria-labelledby="maintenanceHeading"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 id="maintenanceHeading" class="h5 fw-bold mb-0">Maintenance Records</h2><span class="small text-secondary" id="resultCount" role="status" aria-live="polite"></span></div>
<div class="row g-3 mb-4"><div class="col-md-6"><label for="searchInput" class="form-label">Search records</label><input id="searchInput" type="search" class="form-control" placeholder="Resource, note, reporter, or record ID"></div><div class="col-md-3"><label for="statusFilter" class="form-label">Status</label><select id="statusFilter" class="form-select"><option value="">All Statuses</option><option value="Open">Open</option><option value="Completed">Completed</option></select></div><div class="col-md-3 d-flex align-items-end"><button id="resetFilters" type="button" class="btn btn-outline-secondary w-100">Reset Filters</button></div></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Record ID</th><th>Resource</th><th>Maintenance Notes</th><th>Reported By</th><th>Started</th><th>Completed</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($records as $record): $status = $record['ended_at'] === null ? 'Open' : 'Completed'; ?>
<tr class="record-row" data-search="<?= mh(strtolower('MNT-' . $record['maintenance_note_id'] . ' ' . $record['resource_name'] . ' ' . $record['note'] . ' ' . $record['reported_by'])) ?>" data-status="<?= mh($status) ?>"><td class="fw-semibold">MNT-<?= (int)$record['maintenance_note_id'] ?></td><td><?= mh($record['resource_name']) ?></td><td style="min-width:220px;white-space:pre-wrap"><?= mh($record['note']) ?></td><td><?= mh($record['reported_by']) ?></td><td class="text-nowrap"><?= mh($record['started_at']) ?></td><td class="text-nowrap"><?= $record['ended_at'] === null ? '—' : mh($record['ended_at']) ?></td><td><span class="badge <?= $status === 'Open' ? 'text-bg-warning' : 'text-bg-success' ?>"><?= mh($status) ?></span></td><td>
<?php if ($status === 'Open'): ?><form method="post" onsubmit="return confirm('Mark this maintenance record completed?');"><input type="hidden" name="csrf_token" value="<?= mh(rh_csrf()) ?>"><input type="hidden" name="action" value="complete"><input type="hidden" name="maintenance_note_id" value="<?= (int)$record['maintenance_note_id'] ?>"><button class="btn btn-sm btn-outline-success" type="submit">Complete</button></form><?php else: ?><span class="text-secondary small">Closed</span><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div><div id="noResults" class="alert alert-light border d-none">No maintenance records match your filters.</div>
</section><footer class="small text-secondary mt-4">&copy; <?= date('Y') ?> ResourceHub — Equipment &amp; Resources Reservation System</footer>
</main></div>
<div class="modal fade" id="addMaintenance" tabindex="-1" aria-labelledby="addMaintenanceTitle" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h2 class="modal-title fs-5" id="addMaintenanceTitle">Add Maintenance Record</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= mh(rh_csrf()) ?>"><input type="hidden" name="action" value="create"><div class="mb-3"><label class="form-label" for="resource_id">Resource</label><select class="form-select" id="resource_id" name="resource_id" required><option value="">Select resource</option><?php foreach ($resources as $resource): ?><option value="<?= (int)$resource['resource_id'] ?>"><?= mh($resource['name']) ?> (<?= mh($resource['status']) ?>)</option><?php endforeach; ?></select></div><div><label class="form-label" for="note">Issue / Maintenance Notes</label><textarea class="form-control" id="note" name="note" rows="4" maxlength="2000" required></textarea></div><p class="small text-secondary mt-3 mb-0">Starting maintenance changes the resource status to Maintenance. Resources with active or upcoming bookings cannot be newly placed into maintenance.</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Record</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const searchInput=document.getElementById('searchInput'),statusFilter=document.getElementById('statusFilter'),rows=document.querySelectorAll('.record-row');
function filterRecords(){let visible=0;const q=searchInput.value.trim().toLowerCase(),status=statusFilter.value;rows.forEach(row=>{const show=row.dataset.search.includes(q)&&(!status||row.dataset.status===status);row.classList.toggle('d-none',!show);if(show)visible++;});document.getElementById('resultCount').textContent=visible+' of '+rows.length+' records';document.getElementById('noResults').classList.toggle('d-none',visible!==0);}
searchInput.addEventListener('input',filterRecords);statusFilter.addEventListener('change',filterRecords);document.getElementById('resetFilters').addEventListener('click',()=>{searchInput.value='';statusFilter.value='';filterRecords();});filterRecords();
</script></body></html>
