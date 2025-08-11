<?php
require_once 'config.php';

$pdo = getDBConnection();

// Get destination ID from URL
$destination_id = isset($_GET['id']) ? validateId($_GET['id']) : null;
$slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : '';

if (!$destination_id) {
    header("Location: index.php");
    exit();
}

// Get destination details
$stmt = $pdo->prepare("
    SELECT d.*, c.name as category_name 
    FROM destinations d 
    LEFT JOIN categories c ON d.category_id = c.category_id 
    WHERE d.destination_id = :id
");
$stmt->execute(['id' => $destination_id]);
$destination = $stmt->fetch();

if (!$destination) {
    header("Location: index.php");
    exit();
}

// Verify slug matches (for SEO-friendly URLs)
if ($slug !== $destination['slug']) {
    header("Location: destination.php?id={$destination_id}&slug={$destination['slug']}", true, 301);
    exit();
}

// Handle comment submission
$comment_success = false;
$comment_error = '';
$captcha_error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $user_name = sanitizeInput($_POST['user_name'] ?? '');
    $comment_text = sanitizeInput($_POST['comment_text'] ?? '');
    $captcha_input = sanitizeInput($_POST['captcha'] ?? '');
    $captcha_session = $_POST['captcha_session'] ?? '';

    // Validate CAPTCHA
    $captcha_stmt = $pdo->prepare("SELECT captcha_text FROM captcha_sessions WHERE session_id = :session_id AND expires_at > NOW()");
    $captcha_stmt->execute(['session_id' => $captcha_session]);
    $captcha_record = $captcha_stmt->fetch();

    if (!$captcha_record || strtolower($captcha_input) !== strtolower($captcha_record['captcha_text'])) {
        $captcha_error = true;
        $comment_error = 'Invalid CAPTCHA. Please try again.';
    } elseif (empty($user_name) || empty($comment_text)) {
        $comment_error = 'Please fill in all fields.';
    } elseif (strlen($comment_text) < 10) {
        $comment_error = 'Comment must be at least 10 characters long.';
    } else {
        // Insert comment
        $insert_stmt = $pdo->prepare("
            INSERT INTO comments (destination_id, user_name, comment_text) 
            VALUES (:destination_id, :user_name, :comment_text)
        ");

        if ($insert_stmt->execute([
            'destination_id' => $destination_id,
            'user_name' => $user_name,
            'comment_text' => $comment_text
        ])) {
            // Clean up used CAPTCHA session
            $pdo->prepare("DELETE FROM captcha_sessions WHERE session_id = :session_id")
                ->execute(['session_id' => $captcha_session]);

            $comment_success = true;
            // Clear form data
            $user_name = '';
            $comment_text = '';
        } else {
            $comment_error = 'Error submitting comment. Please try again.';
        }
    }
}

// Get comments for this destination
$comments_stmt = $pdo->prepare("
    SELECT * FROM comments 
    WHERE destination_id = :destination_id AND is_approved = 1 
    ORDER BY post_date DESC
");
$comments_stmt->execute(['destination_id' => $destination_id]);
$comments = $comments_stmt->fetchAll();

// Generate new CAPTCHA session
$captcha_session_id = bin2hex(random_bytes(16));
$captcha_text = '';
for ($i = 0; $i < CAPTCHA_LENGTH; $i++) {
    $captcha_text .= chr(rand(65, 90)); // Random uppercase letters
}

// Store CAPTCHA in database
$pdo->prepare("INSERT INTO captcha_sessions (session_id, captcha_text) VALUES (:session_id, :captcha_text)")
    ->execute([
        'session_id' => $captcha_session_id,
        'captcha_text' => $captcha_text
    ]);

// Clean up expired CAPTCHA sessions
$pdo->query("DELETE FROM captcha_sessions WHERE expires_at < NOW()");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeInput($destination['name']); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-image {
            height: 400px;
            object-fit: cover;
            width: 100%;
        }

        .hero-placeholder {
            height: 400px;
            background: linear-gradient(135deg, #28a745, #20c997);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .comment-card {
            border-left: 4px solid #28a745;
        }

        .captcha-image {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 3px;
            text-align: center;
            color: #495057;
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
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="destinations.php">All Destinations</a>
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

    <!-- Destination Header -->
    <div class="container-fluid p-0">
        <?php if (!empty($destination['image_path']) && file_exists($destination['image_path'])): ?>
            <img src="<?php echo sanitizeInput($destination['image_path']); ?>"
                class="hero-image" alt="<?php echo sanitizeInput($destination['name']); ?>">
        <?php else: ?>
            <div class="hero-placeholder">
                <div class="text-center text-white">
                    <i class="fas fa-mountain fa-4x mb-3 opacity-75"></i>
                    <h1><?php echo sanitizeInput($destination['name']); ?></h1>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Destination Content -->
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="destinations.php">Destinations</a></li>
                        <?php if ($destination['category_name']): ?>
                            <li class="breadcrumb-item">
                                <a href="categories.php?category=<?php echo $destination['category_id']; ?>">
                                    <?php echo sanitizeInput($destination['category_name']); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active"><?php echo sanitizeInput($destination['name']); ?></li>
                    </ol>
                </nav>

                <!-- Destination Info -->
                <div class="mb-4">
                    <h1 class="display-5 mb-3"><?php echo sanitizeInput($destination['name']); ?></h1>
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <span class="badge bg-success fs-6">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <?php echo sanitizeInput($destination['region']); ?>
                        </span>
                        <?php if ($destination['category_name']): ?>
                            <span class="badge bg-secondary fs-6">
                                <i class="fas fa-tag me-1"></i>
                                <?php echo sanitizeInput($destination['category_name']); ?>
                            </span>
                        <?php endif; ?>
                        <span class="badge bg-info fs-6">
                            <i class="fas fa-comments me-1"></i>
                            <?php echo count($comments); ?> Comment(s)
                        </span>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-5">
                    <h3>About This Destination</h3>
                    <div class="text-muted">
                        <?php echo nl2br(sanitizeInput($destination['description'])); ?>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="mb-5">
                    <h3>Visitor Comments (<?php echo count($comments); ?>)</h3>

                    <?php if ($comment_success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            Thank you for your comment! It has been posted successfully.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($comment_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $comment_error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Comment Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Leave a Comment</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="user_name" class="form-label">Your Name *</label>
                                        <input type="text" class="form-control" id="user_name" name="user_name"
                                            value="<?php echo isset($user_name) ? sanitizeInput($user_name) : ''; ?>"
                                            required maxlength="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="captcha" class="form-label">Security Code *</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="captcha" name="captcha"
                                                placeholder="Enter the code" required maxlength="10"
                                                <?php echo $captcha_error ? 'style="border-color: #dc3545;"' : ''; ?>>
                                            <div class="captcha-image">
                                                <?php echo $captcha_text; ?>
                                            </div>
                                        </div>
                                        <div class="form-text">Enter the letters shown above</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="comment_text" class="form-label">Your Comment *</label>
                                    <textarea class="form-control" id="comment_text" name="comment_text" rows="4"
                                        required minlength="10" maxlength="1000"
                                        placeholder="Share your experience about this destination..."><?php echo isset($comment_text) ? sanitizeInput($comment_text) : ''; ?></textarea>
                                    <div class="form-text">Minimum 10 characters</div>
                                </div>
                                <input type="hidden" name="captcha_session" value="<?php echo $captcha_session_id; ?>">
                                <button type="submit" name="submit_comment" class="btn btn-success">
                                    <i class="fas fa-paper-plane me-1"></i>Post Comment
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Display Comments -->
                    <?php if (empty($comments)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <p>No comments yet. Be the first to share your experience!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="card comment-card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0">
                                            <i class="fas fa-user-circle me-1"></i>
                                            <?php echo sanitizeInput($comment['user_name']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M j, Y \a\t g:i A', strtotime($comment['post_date'])); ?>
                                        </small>
                                    </div>
                                    <p class="card-text">
                                        <?php echo nl2br(sanitizeInput($comment['comment_text'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Facts</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-map-marker-alt text-success me-2"></i>
                                <strong>Region:</strong> <?php echo sanitizeInput($destination['region']); ?>
                            </li>
                            <?php if ($destination['category_name']): ?>
                                <li class="mb-2">
                                    <i class="fas fa-tag text-success me-2"></i>
                                    <strong>Category:</strong> <?php echo sanitizeInput($destination['category_name']); ?>
                                </li>
                            <?php endif; ?>
                            <li class="mb-2">
                                <i class="fas fa-calendar text-success me-2"></i>
                                <strong>Added:</strong> <?php echo date('M j, Y', strtotime($destination['created_at'])); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-comments text-success me-2"></i>
                                <strong>Comments:</strong> <?php echo count($comments); ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Share Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Share This Destination</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="shareOnFacebook()">
                                <i class="fab fa-facebook me-2"></i>Facebook
                            </button>
                            <button class="btn btn-info" onclick="shareOnTwitter()">
                                <i class="fab fa-twitter me-2"></i>Twitter
                            </button>
                            <button class="btn btn-success" onclick="copyLink()">
                                <i class="fas fa-link me-2"></i>Copy Link
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
    <script>
        function shareOnFacebook() {
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent('<?php echo sanitizeInput($destination['name']); ?> - <?php echo SITE_NAME; ?>');
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank', 'width=600,height=400');
        }

        function shareOnTwitter() {
            const url = encodeURIComponent(window.location.href);
            const text = encodeURIComponent('Check out <?php echo sanitizeInput($destination['name']); ?> in <?php echo sanitizeInput($destination['region']); ?>!');
            window.open(`https://twitter.com/intent/tweet?text=${text}&url=${url}`, '_blank', 'width=600,height=400');
        }

        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('Link copied to clipboard!');
            });
        }
    </script>
</body>

</html>