<?php
require_once 'config.php';

$pdo = getDBConnection();

// Initialize variables
$search_query = '';
$category_filter = '';
$page = 1;
$items_per_page = 6;

// Get search parameters from URL
if (isset($_GET['search'])) {
    $search_query = sanitizeInput($_GET['search']);
}

if (isset($_GET['category']) && validateId($_GET['category'])) {
    $category_filter = (int)$_GET['category'];
}

if (isset($_GET['page']) && validateId($_GET['page'])) {
    $page = (int)$_GET['page'];
}

// Build SQL query with proper parameter binding
$where_conditions = [];
$params = [];

if (!empty($search_query)) {
    $where_conditions[] = "(d.name LIKE :search_name OR d.description LIKE :search_desc OR d.region LIKE :search_region)";
    $params[':search_name'] = "%$search_query%";
    $params[':search_desc'] = "%$search_query%";
    $params[':search_region'] = "%$search_query%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "d.category_id = :category";
    $params[':category'] = $category_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ---------------------
// Get total count for pagination
// ---------------------
$count_sql = "SELECT COUNT(*) FROM destinations d $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_destinations = $count_stmt->fetchColumn();
$total_pages = ceil($total_destinations / $items_per_page);

// ---------------------
// Get destinations with pagination
// ---------------------
$offset = ($page - 1) * $items_per_page;
$sql = "SELECT d.*, c.name as category_name 
        FROM destinations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        $where_clause 
        ORDER BY d.created_at DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

// Bind all named parameters for WHERE clause
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind pagination parameters (must be integers)
$stmt->bindValue(':limit', (int)$items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$destinations = $stmt->fetchAll();

// Fetch categories for the category filter dropdown
$category_stmt = $pdo->query("SELECT category_id, name FROM categories ORDER BY name");
$categories = $category_stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo SITE_NAME; ?> - Discover Nigeria's Beauty</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1578662996442-48f60103fc96?ixlib=rb-4.0.3') center/cover;
            height: 400px;
            color: white;
            display: flex;
            align-items: center;
        }

        .destination-card {
            transition: transform 0.3s ease;
        }

        .destination-card:hover {
            transform: translateY(-5px);
        }

        .navbar-brand {
            font-weight: bold;
            color: #28a745 !important;
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
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="destinations.php">All Destinations</a></li>
                    <li class="nav-item"><a class="nav-link" href="categories.php">Categories</a></li>
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
                                    <hr class="dropdown-divider" />
                                </li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">Discover Nigeria's Hidden Treasures</h1>
            <p class="lead mb-4">Explore breathtaking destinations, rich culture, and unforgettable experiences across Nigeria</p>
            <a href="destinations.php" class="btn btn-success btn-lg"><i class="fas fa-compass me-2"></i>Start Exploring</a>
        </div>
    </section>

    <!-- Search Section -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <form method="GET" class="d-flex gap-2">
                        <input
                            type="text"
                            class="form-control"
                            name="search"
                            placeholder="Search destinations..."
                            value="<?php echo sanitizeInput($search_query); ?>" />
                        <select name="category" class="form-select" style="max-width: 200px;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>" <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizeInput($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-success"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Destinations Section -->
    <section class="py-5">
        <div class="container">
            <?php if (!empty($search_query) || !empty($category_filter)): ?>
                <div class="mb-4">
                    <h3>Search Results</h3>
                    <p class="text-muted">
                        Found <?php echo $total_destinations; ?> destination(s)
                        <?php if (!empty($search_query)): ?>
                            for "<?php echo sanitizeInput($search_query); ?>"
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="text-center mb-5">
                    <h2>Featured Destinations</h2>
                    <p class="text-muted">Discover amazing places across Nigeria</p>
                </div>
            <?php endif; ?>

            <?php if (empty($destinations)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>No destinations found</h4>
                    <p class="text-muted">Try adjusting your search criteria</p>
                    <a href="index.php" class="btn btn-success">View All Destinations</a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($destinations as $destination): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card h-100 destination-card shadow-sm">
                                <?php if (!empty($destination['image_path']) && file_exists($destination['image_path'])): ?>
                                    <img
                                        src="<?php echo sanitizeInput($destination['image_path']); ?>"
                                        class="card-img-top"
                                        style="height: 200px; object-fit: cover;"
                                        alt="<?php echo sanitizeInput($destination['name']); ?>" />
                                <?php else: ?>
                                    <div
                                        class="card-img-top bg-success d-flex align-items-center justify-content-center"
                                        style="height: 200px;">
                                        <i class="fas fa-image fa-3x text-white opacity-50"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo sanitizeInput($destination['name']); ?></h5>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo sanitizeInput($destination['region']); ?>
                                        <?php if ($destination['category_name']): ?>
                                            <span class="badge bg-success ms-2"><?php echo sanitizeInput($destination['category_name']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="card-text flex-grow-1"><?php echo sanitizeInput(substr($destination['description'], 0, 150)) . '...'; ?></p>
                                    <a href="destination.php?id=<?php echo $destination['destination_id']; ?>&slug=<?php echo $destination['slug']; ?>" class="btn btn-success mt-auto">
                                        <i class="fas fa-arrow-right me-1"></i>Learn More
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Destinations pagination" class="mt-4">
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