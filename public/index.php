<?php
require_once __DIR__ . '/includes/auth.php';
$activePublicPage = 'home';
$pageTitle = 'ResourceHub | Home';

$categories = $resources = [];
$catalogueError = false;
try {
    $pdo = rh_pdo();
    $categories = $pdo->query('SELECT name FROM categories ORDER BY name')->fetchAll();
    $resources = $pdo->query("SELECT r.resource_id AS id, r.name, r.description, c.name AS category
        FROM resources r JOIN categories c ON c.category_id = r.category_id
        WHERE r.status = 'available' ORDER BY r.resource_id LIMIT 4")->fetchAll();
} catch (Throwable $e) {
    error_log('ResourceHub homepage catalogue unavailable: ' . $e->getMessage());
    $catalogueError = true;
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description"
          content="ResourceHub Equipment and Resources Reservation System">

    <title><?= escapeHtml($pageTitle) ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="/assets/css/public.css">
</head>

<body class="public-page">

<!-- Navigation -->
<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<main class="container my-4">

    <!-- Welcome Banner -->
    <section class="hero mb-4">
        <div>
            <h1>Welcome to ResourceHub</h1>

            <p class="mt-3 mb-4">
                Find, reserve, and manage shared equipment and resources
                for academic and project needs.
            </p>

            <a href="/resources.php" class="btn btn-primary btn-lg">
                Browse Resources
                <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </section>

    <!-- Search -->
    <section class="search-panel mb-4" aria-label="Resource search">
        <form action="/resources.php" method="get" class="row g-3 align-items-center">

            <div class="col-lg-7">
                <label class="visually-hidden" for="searchInput">
                    Search resources
                </label>

                <input
                    type="search"
                    id="searchInput" name="q"
                    class="form-control form-control-lg"
                    placeholder="Search for resources (e.g., laptop, camera)..."
                >
            </div>

            <div class="col-lg-3">
                <label class="visually-hidden" for="categorySelect">
                    Select category
                </label>

                <select id="categorySelect" name="category" class="form-select form-select-lg">
                    <option value="">All Categories</option>

                    <?php foreach ($categories as $category): ?>
                        <option value="<?= escapeHtml($category['name']) ?>">
                            <?= escapeHtml($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 d-grid">
                <button class="btn btn-primary btn-lg" type="submit">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>

        </form>
    </section>

    <!-- Categories -->
    <section class="mb-4" aria-labelledby="categoryHeading">
        <h2 id="categoryHeading" class="h4 fw-bold mb-3">
            Browse by Category
        </h2>

        <div class="row g-3">
            <?php foreach ($categories as $category): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a
                        class="category-link"
                        href="/resources.php?category=<?= rawurlencode($category['name']) ?>"
                    >
                        <div class="category-card">
                            <i class="bi bi-grid"
                               aria-hidden="true"></i>

                            <h3><?= escapeHtml($category['name']) ?></h3>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Featured Resources -->
    <section id="resources" class="mb-5" aria-labelledby="resourcesHeading">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 id="resourcesHeading" class="h4 fw-bold mb-0">
                Featured Resources
            </h2>

            <a href="/resources.php">View full catalogue</a>
        </div>

        <div class="row g-4" id="resourceGrid">

            <?php foreach ($resources as $resource): ?>
                <div
                    class="col-sm-6 col-lg-3 resource-item"
                    data-name="<?= escapeHtml(strtolower($resource['name'])) ?>"
                    data-category="<?= escapeHtml($resource['category']) ?>"
                >
                    <article class="resource-card">
                        <div class="card-content">
                            <h3><?= escapeHtml($resource['name']) ?></h3>

                            <p><?= escapeHtml($resource['description'] ?? '') ?></p>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="demo-label">
                                    <?= escapeHtml($resource['category']) ?>
                                </span>

                                <a
                                    href="/resource-details.php?id=<?= (int) $resource['id'] ?>"
                                    class="btn btn-primary btn-sm"
                                >
                                    View Details
                                </a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>

        </div>

        <?php if ($catalogueError): ?><p role="alert">The catalogue is temporarily unavailable.</p>
        <?php elseif (!$resources): ?><p>No resources are currently available to feature.</p><?php endif; ?>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="feature-strip mb-5">
        <div class="row g-4 text-center">

            <div class="col-md-4">
                <i class="bi bi-search" aria-hidden="true"></i>
                <h2 class="h6 fw-bold mt-2">Find Resources</h2>
                <p class="small mb-0">
                    Browse equipment and locate what you need.
                </p>
            </div>

            <div class="col-md-4">
                <i class="bi bi-calendar-check" aria-hidden="true"></i>
                <h2 class="h6 fw-bold mt-2">Make Reservations</h2>
                <p class="small mb-0">
                    Select a resource and choose a suitable time period.
                </p>
            </div>

            <div class="col-md-4">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                <h2 class="h6 fw-bold mt-2">Manage Bookings</h2>
                <p class="small mb-0">
                    Review and manage your reservations securely.
                </p>
            </div>

        </div>
    </section>

    <!-- Information -->
    <section id="about" class="mb-4">
        <h2 class="h4 fw-bold">About ResourceHub</h2>

        <p class="text-secondary">
            ResourceHub is an Equipment &amp; Resources Reservation System
            designed to simplify access to shared resources.
        </p>
    </section>

    <section id="login-info" class="mb-5">
        <div class="alert alert-light border">
            <strong>Account access:</strong>
            ResourceHub accounts are created by an administrator.
            <a href="/my-reservations.php" class="ms-1">View My Reservations</a>
        </div>
    </section>

</main>

<!-- Footer -->
<?php require __DIR__ . '/includes/public-footer.php'; ?>

<!-- Bootstrap JavaScript -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>
