<?php
// Initialize the session before any HTML is sent to the browser.
require_once __DIR__ . '/includes/auth.php';
$bookingErrors = [];
$bookingSuccess = isset($_GET['booked']) && $_GET['booked'] === '1';
$availabilityResult = null;
$action = (string) ($_POST['booking_action'] ?? 'reserve');
$bookingValues = ['start_date' => '', 'start_time' => '', 'end_date' => '', 'end_time' => ''];

// The server timezone is used consistently for booking validation and MySQL DATETIME values.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($bookingValues as $key => $_) {
        $bookingValues[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $postedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!rh_csrf_valid($_POST['csrf_token'] ?? null)) {
        $bookingErrors[] = 'Your session has expired. Refresh the page and try again.';
    }
    $bookingUser = rh_current_user();
    if (!$bookingUser && $action === 'reserve') {
        $bookingErrors[] = 'Please log in before reserving equipment.';
    }
    if (!in_array($action, ['check', 'reserve'], true)) {
        $bookingErrors[] = 'Invalid reservation action.';
    }
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $bookingValues['start_date'] . ' ' . $bookingValues['start_time']);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $bookingValues['end_date'] . ' ' . $bookingValues['end_time']);
    if (!$start || !$end || $start->format('Y-m-d H:i') !== $bookingValues['start_date'] . ' ' . $bookingValues['start_time'] || $end->format('Y-m-d H:i') !== $bookingValues['end_date'] . ' ' . $bookingValues['end_time']) {
        $bookingErrors[] = 'Enter valid start and end dates and times.';
    } elseif ($start <= new DateTimeImmutable('now') || $end <= $start) {
        $bookingErrors[] = 'The start must be in the future and the end must be after the start.';
    }
    if (!$postedId) {
        $bookingErrors[] = 'Invalid resource ID.';
    }
    if (!$bookingErrors) {
        $bookingPdo = null;
        try {
            $bookingPdo = rh_pdo();
            if ($action === 'reserve') {
                $bookingPdo->beginTransaction();
            }
            // Lock the resource row to serialize reservations made through this workflow.
            $lock = $bookingPdo->prepare('SELECT status FROM resources WHERE resource_id = :id' . ($action === 'reserve' ? ' FOR UPDATE' : ''));
            $lock->execute(['id' => $postedId]);
            $lockedStatus = $lock->fetchColumn();
            if ($lockedStatus !== 'available') {
                $bookingErrors[] = 'This resource is not currently available for reservation.';
            } else {
                $conflicts = $bookingPdo->prepare("SELECT COUNT(*) FROM reservations WHERE resource_id = :id AND status = 'active' AND start_datetime < :end_time AND end_datetime > :start_time");
                $conflicts->execute(['id' => $postedId, 'end_time' => $end->format('Y-m-d H:i:s'), 'start_time' => $start->format('Y-m-d H:i:s')]);
                if ((int) $conflicts->fetchColumn() > 0) {
                    $bookingErrors[] = 'This resource is already reserved during the selected period. Choose different dates or times.';
                } else {
                    if ($action === 'check') {
                        $availabilityResult = 'Available! No conflicting active reservations were found for this period. ' . ($bookingUser ? 'Select Reserve Now to book it.' : 'Log in to reserve it.');
                    } else {
                    $create = $bookingPdo->prepare("INSERT INTO reservations (user_id, resource_id, start_datetime, end_datetime, status) VALUES (:user_id, :resource_id, :start_time, :end_time, 'active')");
                    $create->execute(['user_id' => (int) $bookingUser['user_id'], 'resource_id' => $postedId, 'start_time' => $start->format('Y-m-d H:i:s'), 'end_time' => $end->format('Y-m-d H:i:s')]);
                    }
                }
            }
            if ($action === 'reserve') {
                if ($bookingErrors) {
                    $bookingPdo->rollBack();
                } else {
                    $bookingPdo->commit();
                    header('Location: /resource-details.php?id=' . $postedId . '&booked=1', true, 303);
                    exit;
                }
            }
        } catch (Throwable $exception) {
            if ($bookingPdo instanceof PDO && $bookingPdo->inTransaction()) {
                $bookingPdo->rollBack();
            }
            error_log('ResourceHub booking failed: ' . $exception->getMessage());
            $bookingErrors[] = 'The reservation could not be saved. Please try again.';
        }
    }
}

$activePublicPage = 'resources';
$pageTitle = 'ResourceHub | Resource Details';

function escapeHtml(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$resource = null;
$databaseError = false;
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($id === null || $id === false) {
    http_response_code(404);
    $pageTitle = 'ResourceHub | Resource Not Found';
} else {
    try {
        $pdo = require __DIR__ . '/../config/database.php';
        $statement = $pdo->prepare(
            'SELECT r.resource_id, r.name, r.description, r.location, r.status, c.name AS category
             FROM resources AS r
             LEFT JOIN categories AS c ON c.category_id = r.category_id
             WHERE r.resource_id = :resource_id
             LIMIT 1'
        );
        $statement->execute(['resource_id' => $id]);
        $resource = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($resource === null) {
            http_response_code(404);
            $pageTitle = 'ResourceHub | Resource Not Found';
        } else {
            $pageTitle = 'ResourceHub | ' . $resource['name'];
        }
    } catch (Throwable $exception) {
        error_log('ResourceHub resource details database error: ' . $exception->getMessage());
        $databaseError = true;
        http_response_code(503);
        $pageTitle = 'ResourceHub | Temporarily Unavailable';
    }
}

$status = $resource['status'] ?? '';
$statusLabel = match ($status) {
    'available' => 'Available',
    'maintenance' => 'Maintenance',
    'inactive' => 'Inactive',
    default => 'Unknown',
};
$statusClass = match ($status) {
    'available' => 'text-bg-success',
    'maintenance' => 'text-bg-warning',
    default => 'text-bg-secondary',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Resource details on ResourceHub">
<title><?= escapeHtml($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/public.css">
</head>
<body class="public-page">
<?php require __DIR__ . '/includes/public-navbar.php'; ?>
<main class="container py-4">
<nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/">Home</a></li><li class="breadcrumb-item"><a href="/resources.php">Browse Resources</a></li><li class="breadcrumb-item active" aria-current="page">Resource Details</li></ol></nav>
<?php if ($databaseError): ?>
<section class="text-center py-5"><i class="bi bi-exclamation-circle display-3 text-secondary" aria-hidden="true"></i><h1 class="mt-3">Resource Details Temporarily Unavailable</h1><p class="text-secondary">We couldn't load this resource right now. Please try again later.</p><a href="/resources.php" class="btn btn-primary">Back to Resources</a></section>
<?php elseif ($resource === null): ?>
<section class="text-center py-5"><i class="bi bi-exclamation-circle display-3 text-secondary" aria-hidden="true"></i><h1 class="mt-3">Resource Not Found</h1><p class="text-secondary">The requested resource could not be found.</p><a href="/resources.php" class="btn btn-primary">Back to Resources</a></section>
<?php else: ?>
<div class="row g-4 mb-5">
<div class="col-lg-7"><article class="detail-card">
<div class="detail-image d-flex justify-content-center align-items-center bg-light border-bottom" role="img" aria-label="Equipment image placeholder"><i class="bi bi-box-seam display-1 text-secondary" aria-hidden="true"></i></div>
<div class="detail-content"><span class="category-label"><?= escapeHtml($resource['category'] ?? 'Uncategorized') ?></span><h1 class="fw-bold mt-2 mb-3"><?= escapeHtml($resource['name']) ?></h1>
<p class="text-secondary"><?= nl2br(escapeHtml($resource['description'])) ?></p><hr>
<h2 class="h5 fw-bold">Resource Information</h2>
<p class="mb-2"><i class="bi bi-geo-alt me-2" aria-hidden="true"></i><strong>Location:</strong> <?= escapeHtml($resource['location'] ?: 'Not specified') ?></p>
<p class="mb-3"><i class="bi bi-info-circle me-2" aria-hidden="true"></i><strong>Resource status:</strong> <span class="badge <?= $statusClass ?>"><?= escapeHtml($statusLabel) ?></span></p>
<p class="small text-secondary">Resource status is not a guarantee that a particular reservation period is free.</p>
<a href="/resources.php" class="btn btn-outline-primary mt-2"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to Catalogue</a>
</div></article></div>
<div class="col-lg-5"><section class="booking-card" aria-labelledby="bookingHeading">
<h2 id="bookingHeading" class="h4 fw-bold mb-2">Reserve This Resource</h2><p class="text-secondary small">Select your preferred reservation period.</p>
<?php if ($bookingSuccess): ?>
<div class="alert alert-success" role="status"><strong>Reservation confirmed.</strong> Your booking has been saved. <a href="/my-reservations.php" class="alert-link">View My Reservations</a>.</div>
<?php endif; ?>
<?php if ($availabilityResult !== null): ?><div class="alert alert-success" role="status"><?= escapeHtml($availabilityResult) ?></div><?php endif; ?>
<?php foreach ($bookingErrors as $bookingError): ?>
<div class="alert alert-danger" role="alert"><?= escapeHtml($bookingError) ?></div>
<?php endforeach; ?>
<?php if ($status !== 'available'): ?>
<div class="alert alert-warning small">This resource is currently <?= escapeHtml(strtolower($statusLabel)) ?> and cannot be reserved.</div>
<?php endif; ?>
<?php $viewer = rh_current_user(); ?>
<?php if (!$viewer): ?>
<div class="alert alert-info">Please <a href="/login.php">log in</a> to reserve this resource.</div>
<?php endif; ?>
<form id="reservationForm" method="post" action="/resource-details.php?id=<?= (int) $resource['resource_id'] ?>">
<input type="hidden" name="csrf_token" value="<?= escapeHtml(rh_csrf()) ?>">
<div class="mb-3"><label for="startDate" class="form-label fw-semibold">Start Date</label><input type="date" id="startDate" name="start_date" value="<?= escapeHtml($bookingValues['start_date']) ?>" class="form-control" required></div>
<div class="mb-3"><label for="startTime" class="form-label fw-semibold">Start Time</label><input type="time" id="startTime" name="start_time" value="<?= escapeHtml($bookingValues['start_time']) ?>" class="form-control" required></div>
<div class="mb-3"><label for="endDate" class="form-label fw-semibold">End Date</label><input type="date" id="endDate" name="end_date" value="<?= escapeHtml($bookingValues['end_date']) ?>" class="form-control" required></div>
<div class="mb-4"><label for="endTime" class="form-label fw-semibold">End Time</label><input type="time" id="endTime" name="end_time" value="<?= escapeHtml($bookingValues['end_time']) ?>" class="form-control" required></div>
<div class="d-grid gap-2">
<button type="submit" name="booking_action" value="check" class="btn btn-outline-primary w-100 py-2" <?= $status !== 'available' ? 'disabled' : '' ?>><i class="bi bi-search me-2" aria-hidden="true"></i>Check Availability</button>
<button type="submit" name="booking_action" value="reserve" class="btn btn-primary w-100 py-2" <?= (!$viewer || $status !== 'available') ? 'disabled' : '' ?>><i class="bi bi-calendar-check me-2" aria-hidden="true"></i>Reserve Now</button>
</div>
</form>
<hr><div class="d-grid gap-2"><a href="/my-reservations.php" class="btn btn-outline-primary"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>My Reservations</a><?php if (!$viewer): ?><a href="/login.php" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Login</a><?php endif; ?></div>
<p class="small text-secondary mt-3 mb-0">Bookings are checked against existing active reservations before they are saved.</p>
</section></div></div>
<?php endif; ?>
</main>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const form = document.getElementById('reservationForm');
if (form) form.addEventListener('submit', function(event) {
    const start = new Date(document.getElementById('startDate').value + 'T' + document.getElementById('startTime').value);
    const end = new Date(document.getElementById('endDate').value + 'T' + document.getElementById('endTime').value);
    if (!Number.isFinite(start.getTime()) || !Number.isFinite(end.getTime()) || start <= new Date() || end <= start) {
        event.preventDefault();
        alert('Choose a future start time and an end time after the start.');
    }
});
</script>
</body></html>
