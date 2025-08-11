<?php
require_once '../config.php';
requireLogin();

// Check if user is admin
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $username = sanitizeInput($_POST['username']);
                $email = sanitizeInput($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];

                // Validate inputs
                if (empty($username) || empty($email) || empty($password)) {
                    $error = "All fields are required.";
                    break;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Invalid email format.";
                    break;
                }

                if (strlen($password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                    break;
                }

                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $error = "Username or email already exists.";
                    break;
                }

                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())");

                if ($stmt->execute([$username, $email, $hashed_password, $role])) {
                    $message = "User created successfully!";
                } else {
                    $error = "Failed to create user.";
                }
                break;

            case 'edit':
                $user_id = intval($_POST['user_id']);
                $username = sanitizeInput($_POST['username']);
                $email = sanitizeInput($_POST['email']);
                $role = $_POST['role'];
                $new_password = $_POST['new_password'];

                // Validate inputs
                if (empty($username) || empty($email)) {
                    $error = "Username and email are required.";
                    break;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Invalid email format.";
                    break;
                }

                // Check if username or email already exists (exclude current user)
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
                $stmt->execute([$username, $email, $user_id]);
                if ($stmt->fetch()) {
                    $error = "Username or email already exists.";
                    break;
                }

                // Update user
                if (!empty($new_password)) {
                    if (strlen($new_password) < 6) {
                        $error = "Password must be at least 6 characters long.";
                        break;
                    }
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, role = ? WHERE user_id = ?");
                    $result = $stmt->execute([$username, $email, $hashed_password, $role, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE user_id = ?");
                    $result = $stmt->execute([$username, $email, $role, $user_id]);
                }

                if ($result) {
                    $message = "User updated successfully!";
                } else {
                    $error = "Failed to update user.";
                }
                break;

            case 'delete':
                $user_id = intval($_POST['user_id']);

                // Prevent deleting own account
                if ($user_id == $_SESSION['user_id']) {
                    $error = "You cannot delete your own account.";
                    break;
                }

                // Check if user has any comments (optional: you might want to handle this differently)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_email IN (SELECT email FROM users WHERE user_id = ?)");
                $stmt->execute([$user_id]);
                $comment_count = $stmt->fetchColumn();

                if ($comment_count > 0) {
                    $error = "Cannot delete user with existing comments. Please remove comments first.";
                    break;
                }

                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                if ($stmt->execute([$user_id])) {
                    $message = "User deleted successfully!";
                } else {
                    $error = "Failed to delete user.";
                }
                break;
        }
    }
}

// Get filter parameters
$role_filter = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query based on filters
$where_conditions = [];
$params = [];

if ($role_filter !== 'all') {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($search) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get users with pagination
$query = "
    SELECT * FROM users
    $where_clause
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM users $where_clause";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users
    FROM users
")->fetch();

// Get user being edited (if any)
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo SITE_NAME; ?></title>
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
                            <a class="nav-link" href="comments.php">
                                <i class="fas fa-comments me-2"></i>Comments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="users.php">
                                <i class="fas fa-users me-2"></i>Users
                            </a>
                        </li>
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
                    <h1 class="h2">User Management</h1>
                    <div class="text-muted">
                        <span class="badge bg-primary"><?php echo $stats['total']; ?> Total</span>
                        <span class="badge bg-danger"><?php echo $stats['admins']; ?> Admins</span>
                        <span class="badge bg-success"><?php echo $stats['users']; ?> Users</span>
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

                <!-- Add/Edit User Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php echo $edit_user ? 'Edit User' : 'Add New User'; ?>
                            <?php if ($edit_user): ?>
                                <a href="users.php" class="btn btn-sm btn-outline-secondary float-end">Cancel</a>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="username" required
                                            value="<?php echo $edit_user ? sanitizeInput($edit_user['username']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" required
                                            value="<?php echo $edit_user ? sanitizeInput($edit_user['email']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <?php echo $edit_user ? 'New Password (leave blank to keep current)' : 'Password'; ?>
                                        </label>
                                        <input type="password" class="form-control"
                                            name="<?php echo $edit_user ? 'new_password' : 'password'; ?>"
                                            <?php echo !$edit_user ? 'required' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <select class="form-select" name="role" required>
                                            <option value="user" <?php echo ($edit_user && $edit_user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                                            <option value="admin" <?php echo ($edit_user && $edit_user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <?php if ($edit_user): ?>
                                <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
                                <button type="submit" name="action" value="edit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Update User
                                </button>
                            <?php else: ?>
                                <button type="submit" name="action" value="create" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i>Create User
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select">
                                    <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                                    <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Users</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Search by username or email"
                                    value="<?php echo sanitizeInput($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                    <a href="users.php" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Users (<?php echo $total_users; ?>)</h5>
                    </div>

                    <?php if (empty($users)): ?>
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No users found matching your criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Created</th>
                                            <th>Last Login</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr <?php echo $user['user_id'] == $_SESSION['user_id'] ? 'class="table-info"' : ''; ?>>
                                                <td><?php echo $user['user_id']; ?></td>
                                                <td>
                                                    <strong><?php echo sanitizeInput($user['username']); ?></strong>
                                                    <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                                        <span class="badge bg-primary ms-1">You</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo sanitizeInput($user['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'success'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('g:i A', strtotime($user['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($user['last_login']): ?>
                                                        <?php echo date('M j, Y', strtotime($user['last_login'])); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo date('g:i A', strtotime($user['last_login'])); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Never</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="users.php?edit=<?php echo $user['user_id']; ?>"
                                                            class="btn btn-outline-primary btn-sm" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>

                                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                <button type="submit" name="action" value="delete"
                                                                    class="btn btn-outline-danger btn-sm" title="Delete"
                                                                    onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

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
</body>

</html>