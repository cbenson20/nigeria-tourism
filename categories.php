<?php

require_once(__DIR__ . '/config.php');
requireLogin();

$pdo = getDBConnection();
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        if (isset($_POST['create_category'])) {
            $name = sanitizeInput($_POST['name']);

            if (empty($name)) {
                $error_message = 'Category name is required.';
            } else {
                // Check if category already exists
                $stmt = $pdo->prepare("SELECT category_id FROM categories WHERE name = :name");
                $stmt->execute(['name' => $name]);

                if ($stmt->fetch()) {
                    $error_message = 'Category already exists.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
                    if ($stmt->execute(['name' => $name])) {
                        $success_message = 'Category created successfully!';
                    } else {
                        $error_message = 'Error creating category.';
                    }
                }
            }
        } elseif (isset($_POST['update_category'])) {
            $category_id = validateId($_POST['category_id']);
            $name = sanitizeInput($_POST['name']);

            if (!$category_id || empty($name)) {
                $error_message = 'Invalid data provided.';
            } else {
                // Check if another category has the same name
                $stmt = $pdo->prepare("SELECT category_id FROM categories WHERE name = :name AND category_id != :id");
                $stmt->execute(['name' => $name, 'id' => $category_id]);

                if ($stmt->fetch()) {
                    $error_message = 'Another category with this name already exists.';
                } else {
                    $stmt = $pdo->prepare("UPDATE categories SET name = :name WHERE category_id = :id");
                    if ($stmt->execute(['name' => $name, 'id' => $category_id])) {
                        $success_message = 'Category updated successfully!';
                    } else {
                        $error_message = 'Error updating category.';
                    }
                }
            }
        } elseif (isset($_POST['delete_category'])) {
            $category_id = validateId($_POST['category_id']);

            if (!$category_id) {
                $error_message = 'Invalid category ID.';
            } else {
                // Check if category has destinations
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM destinations WHERE category_id = :id");
                $stmt->execute(['id' => $category_id]);
                $destination_count = $stmt->fetchColumn();

                if ($destination_count > 0) {
                    $error_message = "Cannot delete category. It has $destination_count destination(s) assigned to it.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = :id");
                    if ($stmt->execute(['id' => $category_id])) {
                        $success_message = 'Category deleted successfully!';
                    } else {
                        $error_message = 'Error deleting category.';
                    }
                }
            }
        }
    }
}

// Get all categories with destination count
$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM destinations WHERE category_id = c.category_id) as destination_count
    FROM categories c 
    ORDER BY c.name
");
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - <?php echo SITE_NAME; ?></title>
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
                            <a class="nav-link active" href="categories.php">
                                <i class="fas fa-tags me-2"></i>Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="comments.php">
                                <i class="fas fa-comments me-2"></i>Comments
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
                    <h1 class="h2">Manage Categories</h1>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Add New Category -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Add New Category</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Category Name *</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                            required maxlength="100" placeholder="e.g., National Parks">
                                    </div>
                                    <button type="submit" name="create_category" class="btn btn-success">
                                        <i class="fas fa-plus me-1"></i>Add Category
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Categories List -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">All Categories (<?php echo count($categories); ?>)</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($categories)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                        <h4>No categories yet</h4>
                                        <p class="text-muted">Add your first category to organize destinations.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Destinations</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($categories as $category): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo sanitizeInput($category['name']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?php echo $category['destination_count']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small><?php echo date('M j, Y', strtotime($category['created_at'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-primary"
                                                                    onclick="editCategory(<?php echo $category['category_id']; ?>, '<?php echo addslashes($category['name']); ?>')">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <?php if ($category['destination_count'] == 0): ?>
                                                                    <button type="button" class="btn btn-outline-danger"
                                                                        onclick="deleteCategory(<?php echo $category['category_id']; ?>, '<?php echo addslashes($category['name']); ?>')">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="button" class="btn btn-outline-secondary" disabled
                                                                        title="Cannot delete - has destinations">
                                                                        <i class="fas fa-lock"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" id="editCategoryId" name="category_id">
                        <div class="mb-3">
                            <label for="editName" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="editName" name="name" required maxlength="100">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_category" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the category <strong id="deleteName"></strong>?</p>
                    <p class="text-warning small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" id="deleteCategoryId" name="category_id">
                        <button type="submit" name="delete_category" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>Delete Category
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCategory(id, name) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editName').value = name;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteCategory(id, name) {
            document.getElementById('deleteCategoryId').value = id;
            document.getElementById('deleteName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>

</html>