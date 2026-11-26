<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
$pageTitle = 'ResourceHub | Manage Resources';
function resourceEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$errors = [];
$notice = isset($_GET['saved']) ? 'Resource saved successfully.' : '';
$editing = null;
$form = ['resource_id'=>'', 'name'=>'', 'description'=>'', 'category_id'=>'', 'location'=>'', 'status'=>'available'];
try {
    $db = rh_pdo();
    $categories = $db->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $validCategories = array_map('intval', array_column($categories, 'category_id'));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form = [
            'resource_id'=>(string)($_POST['resource_id'] ?? ''),
            'name'=>trim((string)($_POST['name'] ?? '')),
            'description'=>trim((string)($_POST['description'] ?? '')),
            'category_id'=>(string)($_POST['category_id'] ?? ''),
            'location'=>trim((string)($_POST['location'] ?? '')),
            'status'=>(string)($_POST['status'] ?? '')
        ];
        $editing = ctype_digit($form['resource_id']) && (int)$form['resource_id'] > 0 ? (int)$form['resource_id'] : null;
        if (!rh_csrf_valid($_POST['csrf_token'] ?? null)) { $errors[] = 'Your session token expired. Refresh the page and try again.'; }
        if ($form['name'] === '' || strlen($form['name']) > 150) { $errors[] = 'Name is required and must be at most 150 bytes.'; }
        if (strlen($form['description']) > 5000) { $errors[] = 'Description is too long.'; }
        if ($form['location'] === '' || mb_strlen($form['location']) > 150) { $errors[] = 'Location is required and must be at most 150 characters.'; }
        if (!ctype_digit($form['category_id']) || !in_array((int)$form['category_id'], $validCategories, true)) { $errors[] = 'Select a valid category.'; }
        if (!in_array($form['status'], ['available','maintenance','inactive'], true)) { $errors[] = 'Select a valid resource status.'; }
        if ($form['resource_id'] !== '' && $editing === null) { $errors[] = 'Invalid resource identifier.'; }
        if (!$errors) {
            if ($editing !== null) {
                $db->beginTransaction();
                $exists = $db->prepare('SELECT status FROM resources WHERE resource_id = ? FOR UPDATE');
                $exists->execute([$editing]);
                $previous = $exists->fetchColumn();
                if ($previous === false) { $errors[] = 'The resource no longer exists.'; }
                elseif ($previous !== $form['status'] && ($previous === 'maintenance' || $form['status'] === 'maintenance')) {
                    $errors[] = 'Use Maintenance to start or finish a repair so its history is preserved.';
                } elseif ($previous !== $form['status'] && $form['status'] === 'inactive') {
                    $pending = $db->prepare("SELECT COUNT(*) FROM reservations WHERE resource_id = ? AND status = 'active' AND end_datetime > NOW()");
                    $pending->execute([$editing]);
                    if ($pending->fetchColumn()) { $errors[] = 'Resolve active or upcoming bookings before deactivating this resource.'; }
                }
                if (!$errors) {
                    $stmt = $db->prepare('UPDATE resources SET name = ?, description = ?, category_id = ?, location = ?, status = ? WHERE resource_id = ?');
                    $stmt->execute([$form['name'], $form['description'], (int)$form['category_id'], $form['location'], $form['status'], $editing]);
                }
                if ($errors) { $db->rollBack(); } else { $db->commit(); }
            } elseif ($form['status'] === 'maintenance') {
                $errors[] = 'Create the resource first, then use Maintenance to record the repair.';
            } else {
                $stmt = $db->prepare('INSERT INTO resources (name, description, category_id, location, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$form['name'], $form['description'], (int)$form['category_id'], $form['location'], $form['status']]);
            }
            if (!$errors) { header('Location: /admin/admin-resources.php?saved=1', true, 303); exit; }
        }
    }
    $resources = $db->query('SELECT r.resource_id, r.name, r.description, r.category_id, c.name AS category, r.location, r.status FROM resources r JOIN categories c ON c.category_id = r.category_id ORDER BY r.resource_id DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    error_log('ResourceHub resource management error: ' . $ex->getMessage());
    $errors[] = 'Resource data could not be loaded or saved. Please check the server error log.';
    $resources = $resources ?? [];
    $categories = $categories ?? [];
}
$counts = array_count_values(array_column($resources, 'status'));
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= resourceEscape($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="/assets/css/admin.css" rel="stylesheet"></head>
<body class="admin-page"><div class="admin-layout">
<?php $activeAdminPage = 'resources'; require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
<main class="admin-main">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><div><h1 class="h2 fw-bold mb-1">Manage Resources</h1><p class="text-secondary mb-0">Manage equipment and shared resources.</p></div><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#resourceModal" id="addResource" <?= !$categories ? 'disabled' : '' ?>><i class="bi bi-plus-circle me-2"></i>Add Resource</button></div>
<?php if ($notice): ?><div class="alert alert-success" role="status"><?= resourceEscape($notice) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>Unable to complete request:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= resourceEscape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3 mb-4"><?php foreach (['Total Resources'=>count($resources),'Available'=>($counts['available']??0),'Maintenance'=>($counts['maintenance']??0),'Inactive'=>($counts['inactive']??0)] as $label=>$count): ?><div class="col-6 col-xl-3"><div class="stat h-100"><div class="text-secondary small"><?= resourceEscape($label) ?></div><strong><?= (int)$count ?></strong></div></div><?php endforeach; ?></div>
<section class="panel" aria-labelledby="listHeading"><div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><h2 id="listHeading" class="h5 fw-bold mb-0">Resource Directory</h2><span id="resultCount" class="small text-secondary" role="status" aria-live="polite"></span></div>
<div class="row g-3 mb-3"><div class="col-md-5"><label class="form-label" for="searchInput">Search resources</label><input id="searchInput" class="form-control" type="search" placeholder="Name, ID or location"></div><div class="col-md-3"><label class="form-label" for="categorySelect">Category</label><select id="categorySelect" class="form-select"><option value="">All Categories</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['category_id'] ?>"><?= resourceEscape($category['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label" for="statusSelect">Status</label><select id="statusSelect" class="form-select"><option value="">All Statuses</option><?php foreach (['available','maintenance','inactive'] as $status): ?><option value="<?= $status ?>"><?= ucfirst($status) ?></option><?php endforeach; ?></select></div><div class="col-md-2 d-flex align-items-end"><button id="resetFilters" type="button" class="btn btn-outline-secondary w-100">Reset Filters</button></div></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Resource ID</th><th>Resource Name</th><th>Category</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($resources as $resource): ?>
<tr class="resource-row" data-search="<?= resourceEscape(strtolower($resource['resource_id'].' '.$resource['name'].' '.$resource['location'])) ?>" data-category="<?= (int)$resource['category_id'] ?>" data-status="<?= resourceEscape($resource['status']) ?>"><td class="fw-semibold">#<?= (int)$resource['resource_id'] ?></td><td><?= resourceEscape($resource['name']) ?></td><td><?= resourceEscape($resource['category']) ?></td><td><?= resourceEscape($resource['location']) ?></td><td><span class="badge text-bg-<?= ['available'=>'success','maintenance'=>'warning','inactive'=>'secondary'][$resource['status']] ?? 'secondary' ?>"><?= resourceEscape(ucfirst($resource['status'])) ?></span></td><td><button type="button" class="btn btn-sm btn-outline-primary edit-resource" data-resource="<?= resourceEscape(json_encode($resource, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">Edit</button></td></tr>
<?php endforeach; ?></tbody></table></div><div id="noResults" class="alert alert-light border d-none" role="status">No resources match the selected filters.</div><p class="small text-secondary mb-0">Deleting resources is disabled to preserve existing reservation and maintenance history. Availability for a specific time is determined by reservation records.</p></section>
</main></div>
<div class="modal fade" id="resourceModal" tabindex="-1" aria-labelledby="resourceModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" action="/admin/admin-resources.php"><div class="modal-header"><h2 class="modal-title fs-5" id="resourceModalTitle">Add Resource</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= resourceEscape(rh_csrf()) ?>"><input type="hidden" name="resource_id" id="formResourceId" value="<?= resourceEscape($form['resource_id']) ?>"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="formName">Resource name</label><input class="form-control" id="formName" name="name" maxlength="150" required value="<?= resourceEscape($form['name']) ?>"></div><div class="col-md-6"><label class="form-label" for="formCategory">Category</label><select class="form-select" id="formCategory" name="category_id" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['category_id'] ?>" <?= (string)$category['category_id'] === $form['category_id'] ? 'selected' : '' ?>><?= resourceEscape($category['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label" for="formLocation">Location</label><input class="form-control" id="formLocation" name="location" maxlength="150" required value="<?= resourceEscape($form['location']) ?>"></div><div class="col-md-6"><label class="form-label" for="formStatus">Status</label><select class="form-select" id="formStatus" name="status"><?php foreach (['available','maintenance','inactive'] as $status): ?><option value="<?= $status ?>" <?= $form['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label" for="formDescription">Description</label><textarea class="form-control" id="formDescription" name="description" rows="3" maxlength="5000"><?= resourceEscape($form['description']) ?></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Resource</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const searchInput=document.getElementById('searchInput'),categorySelect=document.getElementById('categorySelect'),statusSelect=document.getElementById('statusSelect'),rows=document.querySelectorAll('.resource-row');
function applyFilters(){const q=searchInput.value.trim().toLowerCase(),category=categorySelect.value,status=statusSelect.value;let count=0;rows.forEach(row=>{const show=row.dataset.search.includes(q)&&(!category||row.dataset.category===category)&&(!status||row.dataset.status===status);row.classList.toggle('d-none',!show);if(show)count++;});document.getElementById('resultCount').textContent=count+' of '+rows.length+' resources';document.getElementById('noResults').classList.toggle('d-none',count!==0);}
[searchInput,categorySelect,statusSelect].forEach(el=>el.addEventListener(el===searchInput?'input':'change',applyFilters));document.getElementById('resetFilters').addEventListener('click',()=>{searchInput.value='';categorySelect.value='';statusSelect.value='';applyFilters();});applyFilters();
const modal=new bootstrap.Modal(document.getElementById('resourceModal'));
document.getElementById('addResource').addEventListener('click',()=>{document.getElementById('resourceModalTitle').textContent='Add Resource';document.getElementById('formResourceId').value='';document.getElementById('formName').value='';document.getElementById('formCategory').value='';document.getElementById('formLocation').value='';document.getElementById('formStatus').value='available';document.getElementById('formDescription').value='';});
document.querySelectorAll('.edit-resource').forEach(button=>button.addEventListener('click',()=>{const r=JSON.parse(button.dataset.resource);document.getElementById('resourceModalTitle').textContent='Edit Resource #'+r.resource_id;document.getElementById('formResourceId').value=r.resource_id;document.getElementById('formName').value=r.name;document.getElementById('formCategory').value=r.category_id;document.getElementById('formLocation').value=r.location;document.getElementById('formStatus').value=r.status;document.getElementById('formDescription').value=r.description??'';modal.show();}));
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors): ?>modal.show();<?php endif; ?>
</script></body></html>
