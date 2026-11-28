<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$activePublicPage = 'reservations';
$pageTitle = 'ResourceHub | My Reservations';
$user = rh_current_user();
$error = '';
$notice = '';
$rows = [];
if ($user !== null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!rh_csrf_valid($_POST['csrf_token'] ?? null)) {
            $error = 'Your session token has expired. Refresh the page and try again.';
        } elseif (($_POST['action'] ?? '') !== 'cancel' || !ctype_digit((string)($_POST['reservation_id'] ?? ''))) {
            $error = 'Invalid cancellation request.';
        } else {
            try {
                $reservationService = new ReservationService(rh_pdo());
                if ($reservationService->cancelForUser((int)$_POST['reservation_id'], (int)$user['user_id'])) {
                    header('Location: /my-reservations.php?cancelled=1', true, 303);
                    exit;
                }
                $error = 'This reservation cannot be cancelled. It may already have started or been cancelled.';
            } catch (Throwable $e) {
                error_log('ResourceHub reservation cancellation error: ' . $e->getMessage());
                $error = 'Unable to cancel the reservation right now.';
            }
        }
    }
    if (($_GET['cancelled'] ?? '') === '1') $notice = 'Reservation cancelled successfully.';
    try {
        $pdo = rh_pdo();
        $stmt = $pdo->prepare('SELECT r.reservation_id, r.resource_id, r.start_datetime, r.end_datetime, r.status, (r.start_datetime > NOW()) AS is_future, (r.end_datetime <= NOW()) AS has_ended, res.name AS resource_name, c.name AS category_name FROM reservations r JOIN resources res ON res.resource_id = r.resource_id LEFT JOIN categories c ON c.category_id = res.category_id WHERE r.user_id = :user_id ORDER BY r.start_datetime DESC, r.reservation_id DESC');
        $stmt->execute(['user_id' => (int)$user['user_id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('ResourceHub my reservations query error: ' . $e->getMessage());
        $error = 'Unable to load your reservations right now.';
    }
}
function mr_h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mr_status(array $r): string {
    if ($r['status'] === 'cancelled') return 'Cancelled';
    if ($r['status'] === 'completed' || (int)$r['has_ended'] === 1) return 'Completed';
    if ((int)$r['is_future'] === 1) return 'Upcoming';
    return 'In Progress';
}
$counts = ['Upcoming' => 0, 'Completed' => 0, 'Cancelled' => 0, 'In Progress' => 0];
foreach ($rows as &$r) { $r['display_status'] = mr_status($r); ++$counts[$r['display_status']]; }
unset($r);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="description" content="View your ResourceHub reservations."><title><?= mr_h($pageTitle) ?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link rel="stylesheet" href="/assets/css/public.css"></head>
<body class="public-page">
<?php require __DIR__ . '/includes/public-navbar.php'; ?>
<main class="container py-4 py-lg-5">
<nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/">Home</a></li><li class="breadcrumb-item active" aria-current="page">My Reservations</li></ol></nav>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h1 class="fw-bold mb-1">My Reservations</h1><p class="text-secondary mb-0">Review upcoming bookings and reservation history.</p></div><a href="/resources.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Browse Resources</a></div>
<?php if ($user === null): ?>
<div class="alert alert-info">Please <a href="/login.php">log in</a> to view your reservations.</div>
<?php else: ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= mr_h($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="alert alert-success" role="status"><?= mr_h($notice) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
<?php foreach ([['Upcoming','bi-calendar-event'],['In Progress','bi-clock-history'],['Completed','bi-check-circle'],['Cancelled','bi-x-circle']] as $stat): ?>
<div class="col-md-3 col-sm-6"><div class="panel p-3 d-flex align-items-center gap-3"><div class="summary-icon"><i class="bi <?= mr_h($stat[1]) ?>"></i></div><div><div class="text-secondary small"><?= mr_h($stat[0]) ?></div><div class="fs-3 fw-bold"><?= $counts[$stat[0]] ?></div></div></div></div>
<?php endforeach; ?>
</div>
<section class="panel p-3 p-md-4" aria-labelledby="reservationsHeading"><div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3"><h2 id="reservationsHeading" class="h4 fw-bold mb-0">Reservation History</h2><div><label for="statusFilter" class="visually-hidden">Filter by reservation status</label><select id="statusFilter" class="form-select"><option value="all">All Reservations</option><option value="Upcoming">Upcoming</option><option value="In Progress">In Progress</option><option value="Completed">Completed</option><option value="Cancelled">Cancelled</option></select></div></div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th scope="col">Reservation</th><th scope="col">Resource</th><th scope="col">Start</th><th scope="col">End</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr class="reservation-row" data-status="<?= mr_h($r['display_status']) ?>"><td class="fw-semibold">#<?= (int)$r['reservation_id'] ?></td><td><a class="fw-semibold text-decoration-none" href="/resource-details.php?id=<?= (int)$r['resource_id'] ?>"><?= mr_h($r['resource_name']) ?></a><br><small class="text-secondary"><?= mr_h($r['category_name'] ?? 'Uncategorized') ?></small></td><td><?= mr_h(date('M j, Y', strtotime($r['start_datetime']))) ?><br><small><?= mr_h(date('g:i A', strtotime($r['start_datetime']))) ?></small></td><td><?= mr_h(date('M j, Y', strtotime($r['end_datetime']))) ?><br><small><?= mr_h(date('g:i A', strtotime($r['end_datetime']))) ?></small></td><td><span class="status-pill status-<?= mr_h(strtolower(str_replace(' ', '-', $r['display_status']))) ?>"><?= mr_h($r['display_status']) ?></span></td><td><?php if ($r['status'] === 'active' && (int)$r['is_future'] === 1): ?><form method="post" onsubmit="return confirm('Cancel this reservation?');"><input type="hidden" name="csrf_token" value="<?= mr_h(rh_csrf()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="reservation_id" value="<?= (int)$r['reservation_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button></form><?php else: ?><span class="text-secondary small">—</span><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div><div id="noResults" class="alert alert-light border mt-3 <?= $rows ? 'd-none' : '' ?>" role="status"><?= $rows ? 'No reservations match this status.' : 'You have no reservations yet.' ?></div></section>
<?php endif; ?>
</main>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>const filter=document.getElementById('statusFilter');if(filter){const rows=[...document.querySelectorAll('.reservation-row')];const empty=document.getElementById('noResults');function applyFilter(){let count=0;rows.forEach(row=>{const show=filter.value==='all'||row.dataset.status===filter.value;row.classList.toggle('d-none',!show);if(show)count++;});empty.classList.toggle('d-none',count!==0);}filter.addEventListener('change',applyFilter);applyFilter();}</script>
</body></html>
