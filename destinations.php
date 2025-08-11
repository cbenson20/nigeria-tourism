<?php

require_once 'config.php';

$pdo = getDBConnection();

// Handle search and filtering
$search_query = '';
$category_filter = '';
$page = 1;
$items_per_page = 12;

if (isset($_GET['search'])) {
    $search_query = sanitizeInput($_GET['search']);
}

if (isset($_GET['category']) && validateId($_GET['category'])) {
    $category_filter = (int)$_GET['category'];
}

if (isset($_GET['page']) && validateId($_GET['page'])) {
    $page = (int)$_GET['page'];
}

// Build SQL query
$where_conditions = [];
$params = [];

if (!empty($search_query)) {
    $where_conditions[] = "(d.name LIKE :search OR d.description LIKE :search OR d.region LIKE :search)";
    $params['search'] = "%$search_query%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "d.category_id = :category";
    $params['category'] = $category_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM destinations d $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_destinations = $count_stmt->fetchColumn();
$total_pages = ceil($total_destinations / $items_per_page);

// Get destinations
$offset = ($page - 1) * $items_per_page;
$sql = "SELECT d.*, c.name as category_name,
               (SELECT COUNT(*) FROM comments WHERE destination_id = d.destination_id AND is_approved = 1) as comment_count
        FROM destinations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        $where_clause 
        ORDER BY d.name ASC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$destinations = $stmt->fetchAll();

// Get all categories for filter dropdown
$categories_stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $categories_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Destinations - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .destination-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .destination-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .navbar-brand {
            font-weight: bold;
            color: #28a745 !important;
        }

        .page-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 60px 0;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-map-marked-alt me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="destinations.php">All Destinations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo sanitizeInput($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="admin/dashboard.php">Dashboard</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container text-center">
            <h1 class="display-4 mb-3">Explore All Destinations</h1>
            <p class="lead">Discover amazing places across Nigeria - from national parks to historical sites</p>
        </div>
    </section>

    <!-- Search and Filter -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="search" class="form-label">Search Destinations</label>
                            <input type="text" class="form-control" id="search" name="search"
                                placeholder="Enter destination name, region, or keywords..."
                                value="<?php echo sanitizeInput($search_query); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="category" class="form-label">Filter by Category</label>
                            <select name="category" id="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>"
                                        <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitizeInput($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="destinations.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Results Section -->
    <section class="py-5">
        <div class="container">
            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>
                        <?php if (!empty($search_query) || !empty($category_filter)): ?>
                            Search Results
                        <?php else: ?>
                            All Destinations
                        <?php endif; ?>
                    </h3>
                    <p class="text-muted mb-0">
                        Showing <?php echo count($destinations); ?> of <?php echo $total_destinations; ?> destination(s)
                        <?php if (!empty($search_query)): ?>
                            for "<?php echo sanitizeInput($search_query); ?>"
                        <?php endif; ?>
                        <?php if (!empty($category_filter)): ?>
                            <?php
                            $selected_category = array_filter($categories, fn($cat) => $cat['category_id'] == $category_filter);
                            if ($selected_category):
                                $selected_category = reset($selected_category);
                            ?>
                                in <?php echo sanitizeInput($selected_category['name']); ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($total_pages > 1): ?>
                    <div class="text-muted small">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($destinations)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>No destinations found</h4>
                    <p class="text-muted mb-4">Try adjusting your search criteria or browse all destinations.</p>
                    <?php if (!empty($search_query) || !empty($category_filter)): ?>
                        <a href="destinations.php" class="btn btn-success">
                            <i class="fas fa-list me-1"></i>View All Destinations
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Destinations Grid -->
                <div class="row">
                    <?php foreach ($destinations as $destination): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                            <div class="card destination-card shadow-sm h-100">
                                <?php if (!empty($destination['image_path']) && file_exists($destination['image_path'])): ?>
                                    <img src="<?php echo sanitizeInput($destination['image_path']); ?>"
                                        class="card-img-top" style="height: 200px; object-fit: cover;"
                                        alt="<?php echo sanitizeInput($destination['name']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-success d-flex align-items-center justify-content-center"
                                        style="height: 200px;">
                                        <i class="fas fa-mountain fa-3x text-white opacity-50"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo sanitizeInput($destination['name']); ?></h5>

                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo sanitizeInput($destination['region']); ?>
                                        </small>
                                        <?php if ($destination['category_name']): ?>
                                            <span class="badge bg-success ms-2 small">
                                                <?php echo sanitizeInput($destination['category_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <p class="card-text flex-grow-1 small">
                                        <?php echo sanitizeInput(substr(strip_tags($destination['description']), 0, 120)) . '...'; ?>
                                    </p>

                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <small class="text-muted">
                                            <i class="fas fa-comments me-1"></i>
                                            <?php echo $destination['comment_count']; ?> comment(s)
                                        </small>
                                        <a href="destination.php?id=<?php echo $destination['destination_id']; ?>&slug=<?php echo $destination['slug']; ?>"
                                            class="btn btn-success btn-sm">
                                            <i class="fas fa-arrow-right me-1"></i>Explore
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Destinations pagination" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo SITE_NAME; ?></h5>
                    <p>Promoting Nigerian tourism and cultural awareness through digital innovation.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; <?php echo date('Y'); ?> Explore Nigeria Initiative. All rights reserved.</p>
                    <p class="small text-muted">Winnipeg-based Cultural NGO</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>