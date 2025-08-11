<?php
require_once '../config.php';
requireLogin();

$pdo = getDBConnection();

// Get statistics
$stats = [];

// Total destinations
$stmt = $pdo->query("SELECT COUNT(*) FROM destinations");
$stats['destinations'] = $stmt->fetchColumn();

// Total categories
$stmt = $pdo->query("SELECT COUNT(*) FROM categories");
$stats['categories'] = $stmt->fetchColumn();

// Total comments
$stmt = $pdo->query("SELECT COUNT(*) FROM comments");
$stats['comments'] = $stmt->fetchColumn();

// Pending comments (if moderation is enabled)
$stmt = $pdo->query("SELECT COUNT(*) FROM comments WHERE is_approved = 0");
$stats['pending_comments'] = $stmt->fetchColumn();

// Recent destinations
$stmt = $pdo->query("
    SELECT d.*, c.name as category_name 
    FROM destinations d 
    LEFT JOIN categories c ON d.category_id = c.category_id 
    ORDER BY d.created_at DESC 
    LIMIT 5
");
$recent_destinations = $stmt->fetchAll();

// Recent comments
$stmt = $pdo->query("
    SELECT cm.*, d.name as destination_name 
    FROM comments cm 
    JOIN destinations d ON cm.destination_id = d.destination_id 
    ORDER BY cm.post_date DESC 
    LIMIT 5
");
$recent_comments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #28a745;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 10px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .stat-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 15px;
            border: none;
        }

        .stat-card .card-body {
            padding: 1.5rem;
        }

        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">
                            <i class="fas fa-map-marked-alt me-2"></i>
                            <?php echo SITE_NAME; ?>
                        </h5>
                        <small class="text-white-50">Admin Panel</small>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="destinations.php">
                                <i class="fas fa-map-marker-alt me-2"></i>Destinations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="fas fa-tags me-2"></i>Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="comments.php">
                                <i class="fas fa-comments me-2"></i>Comments
                                <?php if ($stats['pending_comments'] > 0): ?>
                                    <span class="badge bg-warning text-dark ms-1">
                                        <?php echo $stats['pending_comments']; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="users.php">
                                    <i class="fas fa-users me-2"></i>Users
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item mt-3">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-external-link-alt me-2"></i>View Website
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="text-muted">
                        Welcome back, <strong><?php echo sanitizeInput($_SESSION['username']); ?></strong>
                        <span class="badge bg-<?php echo $_SESSION['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                            <?php echo ucfirst($_SESSION['role']); ?>
                        </span>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="h4 mb-0"><?php echo $stats['destinations']; ?></div>
                                        <div class="small">Total Destinations</div>
                                    </div>
                                    <div class="h1 text-white-50">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="h4 mb-0"><?php echo $stats['categories']; ?></div>
                                        <div class="small">Categories</div>
                                    </div>
                                    <div class="h1 text-white-50">
                                        <i class="fas fa-tags"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="h4 mb-0"><?php echo $stats['comments']; ?></div>
                                        <div class="small">Total Comments</div>
                                    </div>
                                    <div class="h1 text-white-50">
                                        <i class="fas fa-comments"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="h4 mb-0"><?php echo $stats['pending_comments']; ?></div>
                                        <div class="small">Pending Comments</div>
                                    </div>
                                    <div class="h1 text-white-50">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="destinations.php?action=create" class="btn btn-success">
                                        <i class="fas fa-plus me-1"></i>Add Destination
                                    </a>
                                    <a href="categories.php?action=create" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Add Category
                                    </a>
                                    <a href="destinations.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-list me-1"></i>Manage Destinations
                                    </a>
                                    <a href="comments.php" class="btn btn-outline-info">
                                        <i class="fas fa-comments me-1"></i>Moderate Comments
                                    </a>
                                    <?php if (isAdmin()): ?>
                                        <a href="users.php" class="btn btn-outline-warning">
                                            <i class="fas fa-users me-1"></i>Manage Users
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <!-- Recent Destinations -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Destinations</h5>
                                <a href="destinations.php" class="btn btn-sm btn-outline-success">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_destinations)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-map-marker-alt fa-2x mb-2"></i>
                                        <p>No destinations yet. <a href="destinations.php?action=create">Add your first destination</a></p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_destinations as $destination): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">
                                                        <a href="../destination.php?id=<?php echo $destination['destination_id']; ?>&slug=<?php echo $destination['slug']; ?>"
                                                            class="text-decoration-none" target="_blank">
                                                            <?php echo sanitizeInput($destination['name']); ?>
                                                        </a>
                                                    </h6>
                                                    <small><?php echo date('M j', strtotime($destination['created_at'])); ?></small>
                                                </div>
                                                <p class="mb-1 text-muted small">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo sanitizeInput($destination['region']); ?>
                                                    <?php if ($destination['category_name']): ?>
                                                        <span class="badge bg-light text-dark ms-2">
                                                            <?php echo sanitizeInput($destination['category_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                                <div class="small">
                                                    <a href="destinations.php?action=edit&id=<?php echo $destination['destination_id']; ?>"
                                                        class="text-success me-2">Edit</a>
                                                    <a href="../destination.php?id=<?php echo $destination['destination_id']; ?>&slug=<?php echo $destination['slug']; ?>"
                                                        target="_blank" class="text-primary">View</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Comments -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Comments</h5>
                                <a href="comments.php" class="btn btn-sm btn-outline-info">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_comments)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-comments fa-2x mb-2"></i>
                                        <p>No comments yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_comments as $comment): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo sanitizeInput($comment['user_name']); ?></h6>
                                                    <small><?php echo date('M j', strtotime($comment['post_date'])); ?></small>
                                                </div>
                                                <p class="mb-1 text-muted small">
                                                    On: <a href="../destination.php?id=<?php echo $comment['destination_id']; ?>"
                                                        class="text-decoration-none" target="_blank">
                                                        <?php echo sanitizeInput($comment['destination_name']); ?>
                                                    </a>
                                                </p>
                                                <p class="mb-1 small">
                                                    <?php echo sanitizeInput(substr($comment['comment_text'], 0, 100)) . '...'; ?>
                                                </p>
                                                <div class="small">
                                                    <a href="comments.php?action=view&id=<?php echo $comment['comment_id']; ?>"
                                                        class="text-info me-2">View</a>
                                                    <?php if ($comment['is_approved'] == 0): ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Approved</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Info -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">System Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>PHP Version:</strong></td>
                                                <td><?php echo PHP_VERSION; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Database:</strong></td>
                                                <td>MySQL <?php echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Upload Directory:</strong></td>
                                                <td>
                                                    <?php echo UPLOAD_DIR; ?>
                                                    <span class="badge bg-<?php echo is_writable(UPLOAD_DIR) ? 'success' : 'danger'; ?>">
                                                        <?php echo is_writable(UPLOAD_DIR) ? 'Writable' : 'Not Writable'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Session Timeout:</strong></td>
                                                <td><?php echo SESSION_TIMEOUT / 60; ?> minutes</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Max File Size:</strong></td>
                                                <td><?php echo number_format(MAX_FILE_SIZE / 1024 / 1024, 1); ?> MB</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Login Time:</strong></td>
                                                <td><?php echo date('M j, Y g:i A', $_SESSION['login_time']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>