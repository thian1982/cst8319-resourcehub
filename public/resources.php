<?php
declare(strict_types=1);

// Initialize authentication before any HTML output.
require_once __DIR__ . '/includes/auth.php';

$activePublicPage = 'resources';
$pageTitle = 'ResourceHub | Browse Resources';
$categories = [];
$resources = [];
$databaseError = false;

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    // Database credentials are loaded from config/ outside the public directory.
    $pdo = require dirname(__DIR__) . '/config/database.php';
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database configuration did not return a PDO connection.');
    }

    $categories = $pdo->query('SELECT name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    $resources = $pdo->query(
        "SELECT r.resource_id AS id, r.name, r.description, r.location, r.status, c.name AS category
         FROM resources r
         INNER JOIN categories c ON c.category_id = r.category_id
         WHERE r.status <> 'inactive'
         ORDER BY c.name, r.name"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('ResourceHub catalogue error: ' . $exception->getMessage());
    $databaseError = true;
    http_response_code(503);
}

$selectedCategory = $_GET['category'] ?? '';
if (!is_string($selectedCategory) || !in_array($selectedCategory, $categories, true)) {
    $selectedCategory = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse ResourceHub equipment and resources.">
    <title><?= escapeHtml($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/public.css">
</head>
<body class="public-page">
<?php require __DIR__ . '/includes/public-navbar.php'; ?>
<main class="container">
    <section class="page-heading">
        <h1 class="fw-bold">Browse Resources</h1>
        <p class="text-secondary">Explore equipment and resources available through ResourceHub.</p>
    </section>
    <div class="row g-4 mb-5">
        <aside class="col-lg-3">
            <div class="filter-panel">
                <h2 class="mb-4">Filter Resources</h2>
                <div class="mb-4">
                    <label for="searchInput" class="form-label fw-semibold">Search</label>
                    <input type="search" id="searchInput" value="<?= escapeHtml(is_string($_GET['q'] ?? null) ? $_GET['q'] : '') ?>" class="form-control" placeholder="Search resources...">
                </div>
                <div class="mb-4">
                    <label for="categorySelect" class="form-label fw-semibold">Category</label>
                    <select id="categorySelect" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= escapeHtml((string)$category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>><?= escapeHtml((string)$category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4"><label for="statusSelect" class="form-label fw-semibold">Resource status</label>
                    <select id="statusSelect" class="form-select"><option value="">All statuses</option><option value="available">Available</option><option value="maintenance">Maintenance</option></select></div>
                <button type="button" id="resetFilters" class="btn btn-outline-secondary w-100">Reset Filters</button>
                <div class="alert alert-info small mt-4 mb-0">Catalogue information comes from the ResourceHub database. Reservation availability is checked separately.</div>
            </div>
        </aside>
        <section class="col-lg-9" aria-labelledby="catalogueHeading">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 id="catalogueHeading" class="h4 fw-bold mb-0">Resource Catalogue</h2>
                <span id="resultCount" class="text-secondary small" role="status" aria-live="polite"></span>
            </div>
            <?php if ($databaseError): ?>
                <div class="alert alert-danger" role="alert">The resource catalogue is temporarily unavailable. Please try again later.</div>
            <?php else: ?>
                <div class="row g-4" id="resourceGrid">
                    <?php foreach ($resources as $resource): ?>
                        <div class="col-md-6 col-xl-4 resource-item"
                             data-name="<?= escapeHtml(strtolower((string)$resource['name'])) ?>"
                             data-description="<?= escapeHtml(strtolower((string)($resource['description'] ?? ''))) ?>"
                             data-status="<?= escapeHtml((string)$resource['status']) ?>"
                             data-category="<?= escapeHtml((string)$resource['category']) ?>">
                            <article class="resource-card">
                                <div class="d-flex align-items-center justify-content-center bg-light border-bottom" style="height:180px" aria-hidden="true">
                                    <i class="bi bi-box-seam text-secondary" style="font-size:4rem"></i>
                                </div>
                                <div class="card-content">
                                    <span class="category-label"><?= escapeHtml((string)$resource['category']) ?></span>
                                    <h3 class="mt-2"><?= escapeHtml((string)$resource['name']) ?></h3>
                                    <p><?= escapeHtml((string)($resource['description'] ?? '')) ?></p>
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <span class="badge <?= $resource['status'] === 'available' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= escapeHtml(ucfirst((string)$resource['status'])) ?></span>
                                        <a class="btn btn-primary btn-sm" href="/resource-details.php?id=<?= (int)$resource['id'] ?>">View Details</a>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="noResults" class="alert alert-info mt-4 d-none" role="status">No resources match the selected filters.</div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!$databaseError): ?>
<script>
const searchInput = document.getElementById('searchInput');
const categorySelect = document.getElementById('categorySelect');
const statusSelect = document.getElementById('statusSelect');
const resetButton = document.getElementById('resetFilters');
const resourceItems = document.querySelectorAll('.resource-item');
const resultCount = document.getElementById('resultCount');
const noResults = document.getElementById('noResults');
function filterResources() {
    const search = searchInput.value.trim().toLowerCase();
    const category = categorySelect.value;
    let visibleCount = 0;
    resourceItems.forEach(function (item) {
        const matchesSearch = item.dataset.name.includes(search) || item.dataset.description.includes(search);
        const matchesCategory = !category || item.dataset.category === category;
        const show = matchesSearch && matchesCategory && (!statusSelect.value || item.dataset.status === statusSelect.value);
        item.classList.toggle('d-none', !show);
        if (show) visibleCount++;
    });
    resultCount.textContent = visibleCount + ' of ' + resourceItems.length + ' resources';
    noResults.classList.toggle('d-none', visibleCount > 0);
}
searchInput.addEventListener('input', filterResources);
statusSelect.addEventListener('change', filterResources);
categorySelect.addEventListener('change', filterResources);
resetButton.addEventListener('click', function () {
    searchInput.value = '';
    categorySelect.value = '';
    statusSelect.value = '';
    history.replaceState(null, '', window.location.pathname);
    filterResources();
});
filterResources();
</script>
<?php endif; ?>
</body>
</html>
