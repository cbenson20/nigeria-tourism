<?php
require_once(__DIR__ . '/../config.php');
requireLogin();

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $destination_id = $_POST['destination_id'] ?? null;

        try {
            switch ($action) {
                case 'create':
                case 'update':
                    // Validate inputs
                    $name = sanitizeInput($_POST['name']);
                    $description = sanitizeInput($_POST['description']);
                    $region = sanitizeInput($_POST['region']);
                    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;

                    if (empty($name) || empty($description) || empty($region)) {
                        throw new Exception("All required fields must be filled.");
                    }

                    // Handle image upload
                    $image_path = null;
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        // Validate image
                        $valid_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
                        $file_info = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($file_info, $_FILES['image']['tmp_name']);

                        if (!in_array($mime_type, $valid_mime_types)) {
                            throw new Exception("Only JPG, PNG, and GIF images are allowed.");
                        }

                        if ($_FILES['image']['size'] > MAX_FILE_SIZE) {
                            throw new Exception("Image size must be less than " . (MAX_FILE_SIZE / 1024 / 1024) . "MB");
                        }

                        // Create uploads directory if it doesn't exist
                        if (!file_exists(UPLOAD_DIR)) {
                            mkdir(UPLOAD_DIR, 0755, true);
                        }

                        // Generate unique filename
                        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $filename = 'destination_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $target_path = UPLOAD_DIR . $filename;

                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            $image_path = $filename;
                        }
                    }

                    // Handle image removal
                    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                        if (!empty($_POST['current_image']) && file_exists(UPLOAD_DIR . $_POST['current_image'])) {
                            unlink(UPLOAD_DIR . $_POST['current_image']);
                        }
                        $image_path = null;
                    } elseif ($action === 'update' && !empty($_POST['current_image']) && !isset($_FILES['image'])) {
                        // Keep existing image if not changing
                        $image_path = $_POST['current_image'];
                    }

                    // Create slug
                    $slug = createSlug($name);

                    if ($action === 'create') {
                        // Insert new destination
                        $stmt = $pdo->prepare("
                            INSERT INTO destinations (name, description, region, category_id, image_path, slug)
                            VALUES (:name, :description, :region, :category_id, :image_path, :slug)
                        ");
                        $stmt->execute([
                            ':name' => $name,
                            ':description' => $description,
                            ':region' => $region,
                            ':category_id' => $category_id,
                            ':image_path' => $image_path,
                            ':slug' => $slug
                        ]);
                        $message = "Destination created successfully!";
                    } else {
                        // Update existing destination
                        $stmt = $pdo->prepare("
                            UPDATE destinations SET
                                name = :name,
                                description = :description,
                                region = :region,
                                category_id = :category_id,
                                image_path = :image_path,
                                slug = :slug,
                                updated_at = NOW()
                            WHERE destination_id = :destination_id
                        ");
                        $stmt->execute([
                            ':name' => $name,
                            ':description' => $description,
                            ':region' => $region,
                            ':category_id' => $category_id,
                            ':image_path' => $image_path,
                            ':slug' => $slug,
                            ':destination_id' => $destination_id
                        ]);
                        $message = "Destination updated successfully!";
                    }
                    break;

                case 'delete':
                    // Get image path before deleting
                    $stmt = $pdo->prepare("SELECT image_path FROM destinations WHERE destination_id = ?");
                    $stmt->execute([$destination_id]);
                    $destination = $stmt->fetch();

                    // Delete destination
                    $stmt = $pdo->prepare("DELETE FROM destinations WHERE destination_id = ?");
                    $stmt->execute([$destination_id]);

                    // Delete associated image
                    if (!empty($destination['image_path']) && file_exists(UPLOAD_DIR . $destination['image_path'])) {
                        unlink(UPLOAD_DIR . $destination['image_path']);
                    }

                    $message = "Destination deleted successfully!";
                    break;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Check if we're in edit mode
$is_edit_mode = isset($_GET['edit']);
$current_destination = null;
$categories = [];

if ($is_edit_mode) {
    $destination_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM destinations WHERE destination_id = ?");
    $stmt->execute([$destination_id]);
    $current_destination = $stmt->fetch();

    if (!$current_destination) {
        $error = "Destination not found.";
        $is_edit_mode = false;
    }
}

// Get all categories for dropdown
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Get all destinations for listing
$stmt = $pdo->query("
    SELECT d.*, c.name as category_name 
    FROM destinations d 
    LEFT JOIN categories c ON d.category_id = c.category_id 
    ORDER BY d.created_at DESC
");
$destinations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit_mode ? 'Edit' : 'Add'; ?> Destination - <?php echo SITE_NAME; ?></title>
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

        .destination-image {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->


            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $is_edit_mode ? 'Edit Destination' : 'Add New Destination'; ?></h1>
                    <a href="destinations.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to List
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Destination Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="<?php echo $is_edit_mode ? 'update' : 'create'; ?>">
                            <?php if ($is_edit_mode): ?>
                                <input type="hidden" name="destination_id" value="<?php echo $current_destination['destination_id']; ?>">
                                <input type="hidden" name="current_image" value="<?php echo $current_destination['image_path'] ?? ''; ?>">
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Destination Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required
                                            value="<?php echo $current_destination['name'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="region" class="form-label">Region *</label>
                                        <input type="text" class="form-control" id="region" name="region" required
                                            value="<?php echo $current_destination['region'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>"
                                            <?php echo (isset($current_destination['category_id']) && $current_destination['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo sanitizeInput($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?php
                                                                                                                        echo $current_destination['description'] ?? '';
                                                                                                                        ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">Destination Image</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <div class="form-text">Max size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB (JPEG, PNG, GIF)</div>

                                <?php if ($is_edit_mode && !empty($current_destination['image_path'])): ?>
                                    <div class="mt-3">
                                        <img src="../<?php echo UPLOAD_DIR . $current_destination['image_path']; ?>"
                                            class="destination-image img-thumbnail mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image" value="1">
                                            <label class="form-check-label" for="remove_image">Remove current image</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i>
                                <?php echo $is_edit_mode ? 'Update' : 'Create'; ?> Destination
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Destinations List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Destinations (<?php echo count($destinations); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($destinations)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                                <h4>No destinations yet</h4>
                                <p class="text-muted">Add your first destination to get started.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Region</th>
                                            <th>Category</th>
                                            <th>Image</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($destinations as $destination): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo sanitizeInput($destination['name']); ?></strong>
                                                </td>
                                                <td><?php echo sanitizeInput($destination['region']); ?></td>
                                                <td>
                                                    <?php if ($destination['category_name']): ?>
                                                        <span class="badge bg-secondary"><?php echo sanitizeInput($destination['category_name']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($destination['image_path']): ?>
                                                        <img src="../<?php echo UPLOAD_DIR . $destination['image_path']; ?>"
                                                            class="destination-image" style="max-height: 50px;">
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M j, Y', strtotime($destination['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="destinations.php?edit=<?php echo $destination['destination_id']; ?>"
                                                            class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline"
                                                            onsubmit="return confirm('Are you sure you want to delete this destination?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="destination_id" value="<?php echo $destination['destination_id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
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
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>