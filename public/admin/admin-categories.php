<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

$pageTitle = 'ResourceHub | Manage Categories';
$error = '';
$notice = '';
$old = ['category_id' => 0, 'name' => '', 'description' => ''];
$mode = 'add';

try {
    $pdo = rh_pdo();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!rh_csrf_valid($_POST['csrf_token'] ?? null)) {
            throw new DomainException('Your session token expired. Refresh the page and try again.');
        }
        $mode = (string) ($_POST['action'] ?? '');
        if (!in_array($mode, ['add', 'edit', 'remove'], true)) {
            throw new DomainException('Invalid category action.');
        }
        $id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($mode === 'remove') {
            if (!$id || $id < 1) { throw new DomainException('Invalid category selected.'); }
            $delete = $pdo->prepare('DELETE FROM categories WHERE category_id = ? AND NOT EXISTS (SELECT 1 FROM resources WHERE category_id = ?)');
            $delete->execute([$id, $id]);
            if ($delete->rowCount() !== 1) { throw new DomainException('Only existing, unused categories can be removed.'); }
            header('Location: /admin/admin-categories.php?saved=remove', true, 303);
            exit;
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $old = ['category_id' => $id ?: 0, 'name' => $name, 'description' => $description];
        if ($name === '' || mb_strlen($name) > 100) {
            throw new DomainException('Category name must be between 1 and 100 characters.');
        }
        if (mb_strlen($description) > 500) {
            throw new DomainException('Description must not exceed 500 characters.');
        }
        if ($mode === 'edit' && (!$id || $id < 1)) {
            throw new DomainException('Invalid category selected.');
        }
        if ($mode === 'add') {
            $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (:name, :description)');
            $stmt->execute(['name' => $name, 'description' => $description]);
        } else {
            $stmt = $pdo->prepare('UPDATE categories SET name = :name, description = :description WHERE category_id = :id');
            $stmt->execute(['name' => $name, 'description' => $description, 'id' => $id]);
        }
        header('Location: /admin/admin-categories.php?saved=' . $mode, true, 303);
        exit;
    }
    $stmt = $pdo->query('SELECT c.category_id, c.name, c.description, COUNT(r.resource_id) AS resource_count FROM categories c LEFT JOIN resources r ON r.category_id = c.category_id GROUP BY c.category_id, c.name, c.description ORDER BY c.name, c.category_id');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (DomainException $e) {
    $error = $e->getMessage();
} catch (PDOException $e) {
    error_log('ResourceHub categories error: ' . $e->getMessage());
    $error = ($e->getCode() === '23000') ? 'A category with this name may already exist.' : 'Unable to complete the database operation. Please try again.';
} catch (Throwable $e) {
    error_log('ResourceHub categories error: ' . $e->getMessage());
    $error = 'Unable to load categories. Please try again.';
}
$categories = $categories ?? [];
if (($_GET['saved'] ?? '') === 'add') $notice = 'Category added successfully.';
if (($_GET['saved'] ?? '') === 'edit') $notice = 'Category updated successfully.';
if (($_GET['saved'] ?? '') === 'remove') $notice = 'Category removed successfully.';
$totalResources = array_sum(array_map(static fn($c) => (int) $c['resource_count'], $categories));
function categoryEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= categoryEscape($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-page"><div class="admin-layout">
<?php $activeAdminPage = 'categories'; require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
<main class="admin-main">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
<div><h1 class="h2 fw-bold mb-1">Manage Categories</h1><p class="text-secondary mb-0">Organize and manage ResourceHub resource categories.</p></div>
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" id="addCategory"><i class="bi bi-plus-lg me-1"></i>Add Category</button>
</div>
<?php if ($notice): ?><div class="alert alert-success" role="status"><?= categoryEscape($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= categoryEscape($error) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
<div class="col-md-4"><div class="stat"><div class="text-secondary small">Total Categories</div><div class="value"><?= count($categories) ?></div></div></div>
<div class="col-md-4"><div class="stat"><div class="text-secondary small">Categories with Resources</div><div class="value"><?= count(array_filter($categories, static fn($c) => (int) $c['resource_count'] > 0)) ?></div></div></div>
<div class="col-md-4"><div class="stat"><div class="text-secondary small">Resources Assigned</div><div class="value"><?= $totalResources ?></div></div></div>
</div>
<section class="panel" aria-labelledby="categoryTableHeading">
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3"><h2 id="categoryTableHeading" class="h5 fw-bold mb-0">Category Directory</h2><span class="small text-secondary" id="resultCount" role="status" aria-live="polite"></span></div>
<div class="row g-3 mb-4">
<div class="col-md-9"><label for="categorySearch" class="form-label fw-semibold">Search Categories</label><input id="categorySearch" type="search" class="form-control" placeholder="Search by name or description..."></div>
<div class="col-md-3 d-flex align-items-end"><button id="resetFilters" class="btn btn-outline-secondary w-100" type="button">Reset Filters</button></div>
</div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th scope="col">ID</th><th scope="col">Category</th><th scope="col">Description</th><th scope="col">Resources</th><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach ($categories as $category): ?>
<tr class="category-row" data-search="<?= categoryEscape(strtolower($category['name'] . ' ' . ($category['description'] ?? ''))) ?>">
<td>#<?= (int) $category['category_id'] ?></td><td class="fw-semibold"><?= categoryEscape($category['name']) ?></td><td class="text-secondary"><?= categoryEscape($category['description'] ?? '') ?></td><td><?= (int) $category['resource_count'] ?></td>
<td><button class="btn btn-sm btn-outline-primary edit-category" type="button" data-bs-toggle="modal" data-bs-target="#categoryModal" data-id="<?= (int) $category['category_id'] ?>" data-name="<?= categoryEscape($category['name']) ?>" data-description="<?= categoryEscape($category['description'] ?? '') ?>">Edit</button>
<?php if ((int)$category['resource_count'] === 0): ?>
<form class="d-inline" method="post" onsubmit="return confirm('Remove this unused category?');">
<input type="hidden" name="csrf_token" value="<?= categoryEscape(rh_csrf()) ?>">
<input type="hidden" name="action" value="remove"><input type="hidden" name="category_id" value="<?= (int)$category['category_id'] ?>">
<button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<div id="noResults" class="alert alert-light border d-none" role="status">No categories match your search.</div>
<p class="small text-secondary mb-0">Only categories without resources can be removed.</p>
</section>
<footer class="text-center text-secondary small py-4">&copy; <?= date('Y') ?> ResourceHub &middot; Equipment &amp; Resources Reservation System</footer>
</main></div>
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalTitle" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
<form method="post" action="/admin/admin-categories.php">
<div class="modal-header"><h2 class="modal-title fs-5" id="categoryModalTitle">Add Category</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= categoryEscape(rh_csrf()) ?>"><input type="hidden" name="action" id="categoryAction" value="add"><input type="hidden" name="category_id" id="categoryId" value="0">
<div class="mb-3"><label class="form-label" for="categoryName">Category Name</label><input class="form-control" type="text" name="name" id="categoryName" maxlength="100" required></div>
<div class="mb-3"><label class="form-label" for="categoryDescription">Description</label><textarea class="form-control" name="description" id="categoryDescription" rows="4" maxlength="500"></textarea></div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Category</button></div>
</form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const searchInput=document.getElementById('categorySearch'), rows=document.querySelectorAll('.category-row'), count=document.getElementById('resultCount'), empty=document.getElementById('noResults');
function applyFilters(){const term=searchInput.value.trim().toLowerCase();let visible=0;rows.forEach(row=>{const show=row.dataset.search.includes(term);row.classList.toggle('d-none',!show);if(show)visible++;});count.textContent=`${visible} of ${rows.length} categories`;empty.classList.toggle('d-none',visible!==0);}
searchInput.addEventListener('input',applyFilters);document.getElementById('resetFilters').addEventListener('click',()=>{searchInput.value='';applyFilters();});applyFilters();
function setCategoryForm(action,id,name,description){document.getElementById('categoryAction').value=action;document.getElementById('categoryId').value=id;document.getElementById('categoryName').value=name;document.getElementById('categoryDescription').value=description;document.getElementById('categoryModalTitle').textContent=action==='edit'?'Edit Category':'Add Category';}
document.getElementById('addCategory').addEventListener('click',()=>setCategoryForm('add',0,'',''));
document.querySelectorAll('.edit-category').forEach(button=>button.addEventListener('click',()=>setCategoryForm('edit',button.dataset.id,button.dataset.name,button.dataset.description)));
<?php if ($error && in_array($mode, ['add', 'edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
setCategoryForm(<?= json_encode($mode) ?>, <?= json_encode($old['category_id']) ?>, <?= json_encode($old['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($old['description'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
bootstrap.Modal.getOrCreateInstance(document.getElementById('categoryModal')).show();
<?php endif; ?>
</script></body></html>
