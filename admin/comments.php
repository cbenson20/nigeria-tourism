<?php
require_once '../config.php';
requireLogin();

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve':
                $comment_id = intval($_POST['comment_id']);
                $stmt = $pdo->prepare("UPDATE comments SET is_approved = 1 WHERE comment_id = ?");
                if ($stmt->execute([$comment_id])) {
                    $message = "Comment approved successfully!";
                } else {
                    $error = "Failed to approve comment.";
                }
                break;

            case 'reject':
                $comment_id = intval($_POST['comment_id']);
                $stmt = $pdo->prepare("UPDATE comments SET is_approved = 0 WHERE comment_id = ?");
                if ($stmt->execute([$comment_id])) {
                    $message = "Comment rejected successfully!";
                } else {
                    $error = "Failed to reject comment.";
                }
                break;

            case 'delete':
                $comment_id = intval($_POST['comment_id']);
                $stmt = $pdo->prepare("DELETE FROM comments WHERE comment_id = ?");
                if ($stmt->execute([$comment_id])) {
                    $message = "Comment deleted successfully!";
                } else {
                    $error = "Failed to delete comment.";
                }
                break;

            case 'bulk_action':
                $selected_comments = $_POST['selected_comments'] ?? [];
                $bulk_action = $_POST['bulk_action_type'];

                if (!empty($selected_comments) && $bulk_action) {
                    $placeholders = str_repeat('?,', count($selected_comments) - 1) . '?';

                    switch ($bulk_action) {
                        case 'approve':
                            $stmt = $pdo->prepare("UPDATE comments SET is_approved = 1 WHERE comment_id IN ($placeholders)");
                            break;
                        case 'reject':
                            $stmt = $pdo->prepare("UPDATE comments SET is_approved = 0 WHERE comment_id IN ($placeholders)");
                            break;
                        case 'delete':
                            $stmt = $pdo->prepare("DELETE FROM comments WHERE comment_id IN ($placeholders)");
                            break;
                    }

                    if ($stmt->execute($selected_comments)) {
                        $message = "Bulk action completed successfully!";
                    } else {
                        $error = "Failed to perform bulk action.";
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$destination_filter = $_GET['destination'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query based on filters
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    if ($status_filter === 'pending') {
        $where_conditions[] = "c.is_approved = 0";
    } elseif ($status_filter === 'approved') {
        $where_conditions[] = "c.is_approved = 1";
    }
}

if ($destination_filter) {
    $where_conditions[] = "d.destination_id = ?";
    $params[] = $destination_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get comments with pagination
$query = "
    SELECT c.*, d.name as destination_name, d.slug as destination_slug
    FROM comments c
    JOIN destinations d ON c.destination_id = d.destination_id
    $where_clause
    ORDER BY c.post_date DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$comments = $stmt->fetchAll();

// Get total count for pagination
$count_query = "
    SELECT COUNT(*)
    FROM comments c
    JOIN destinations d ON c.destination_id = d.destination_id
    $where_clause
";

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_comments = $count_stmt->fetchColumn();
$total_pages = ceil($total_comments / $per_page);

// Get destinations for filter dropdown
$destinations_stmt = $pdo->query("SELECT destination_id, name FROM destinations ORDER BY name");
$destinations = $destinations_stmt->fetchAll();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending
    FROM comments
";
$stats = $pdo->query($stats_query)->fetch();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comments Management - <?php echo SITE_NAME; ?></title>
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

        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .comment-content {
            max-height: 100px;
            overflow-y: auto;
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
                            <a class="nav-link" href="dashboard.php">
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
                            <a class="nav-link active" href="comments.php">
                                <i class="fas fa-comments me-2"></i>Comments
                                <?php if ($stats['pending'] > 0): ?>
                                    <span class="badge bg-warning text-dark ms-1"><?php echo $stats['pending']; ?></span>
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
                    <h1 class="h2">Comments Management</h1>
                    <div class="text-muted">
                        <span class="badge bg-primary"><?php echo $stats['total']; ?> Total</span>
                        <span class="badge bg-success"><?php echo $stats['approved']; ?> Approved</span>
                        <span class="badge bg-warning text-dark"><?php echo $stats['pending']; ?> Pending</span>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Comments</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Destination</label>
                                <select name="destination" class="form-select">
                                    <option value="">All Destinations</option>
                                    <?php foreach ($destinations as $dest): ?>
                                        <option value="<?php echo $dest['destination_id']; ?>"
                                            <?php echo $destination_filter == $dest['destination_id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitizeInput($dest['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                    <a href="comments.php" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Comments Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Comments (<?php echo $total_comments; ?>)</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-success" onclick="selectAll()">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">Clear</button>
                        </div>
                    </div>

                    <?php if (empty($comments)): ?>
                        <div class="card-body text-center">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No comments found matching your criteria.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" id="commentsForm">
                            <div class="card-body p-0">
                                <!-- Bulk Actions -->
                                <div class="p-3 border-bottom bg-light">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <select name="bulk_action_type" class="form-select form-select-sm d-inline-block w-auto me-2">
                                                <option value="">Bulk Actions</option>
                                                <option value="approve">Approve</option>
                                                <option value="reject">Reject</option>
                                                <option value="delete">Delete</option>
                                            </select>
                                            <button type="submit" name="action" value="bulk_action" class="btn btn-sm btn-primary"
                                                onclick="return confirmBulkAction()">Apply</button>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <small class="text-muted">Select comments to perform bulk actions</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="40">
                                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll()">
                                                </th>
                                                <th>Author</th>
                                                <th>Comment</th>
                                                <th>Destination</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($comments as $comment): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="selected_comments[]"
                                                            value="<?php echo $comment['comment_id']; ?>" class="comment-checkbox">
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo sanitizeInput($comment['user_name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo sanitizeInput($comment['user_email']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="comment-content">
                                                            <?php echo nl2br(sanitizeInput($comment['comment_text'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <a href="../destination.php?id=<?php echo $comment['destination_id']; ?>&slug=<?php echo $comment['destination_slug']; ?>"
                                                            target="_blank" class="text-decoration-none">
                                                            <?php echo sanitizeInput($comment['destination_name']); ?>
                                                            <i class="fas fa-external-link-alt fa-xs"></i>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($comment['post_date'])); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo date('g:i A', strtotime($comment['post_date'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($comment['is_approved']): ?>
                                                            <span class="badge bg-success">Approved</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <?php if (!$comment['is_approved']): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                                                    <button type="submit" name="action" value="approve"
                                                                        class="btn btn-success btn-sm" title="Approve">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                                                    <button type="submit" name="action" value="reject"
                                                                        class="btn btn-warning btn-sm" title="Reject">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>

                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                                                <button type="submit" name="action" value="delete"
                                                                    class="btn btn-danger btn-sm" title="Delete"
                                                                    onclick="return confirm('Are you sure you want to delete this comment?')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </form>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer">
                                <nav>
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll() {
            const selectAll = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.comment-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('.comment-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            selectAllCheckbox.checked = true;
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.comment-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAllCheckbox.checked = false;
        }

        function confirmBulkAction() {
            const selected = document.querySelectorAll('.comment-checkbox:checked');
            const action = document.querySelector('select[name="bulk_action_type"]').value;

            if (selected.length === 0) {
                alert('Please select at least one comment.');
                return false;
            }

            if (!action) {
                alert('Please select a bulk action.');
                return false;
            }

            return confirm(`Are you sure you want to ${action} ${selected.length} comment(s)?`);
        }
    </script>
</body>

</html>