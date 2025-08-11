<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: admin/dashboard.php");
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();

            // Redirect to dashboard or intended page
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'admin/dashboard.php';
            header("Location: $redirect");
            exit();
        } else {
            $error_message = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #28a745, #20c997);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .login-card {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 15px;
        }

        .card-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-header text-center py-4">
                        <h3 class="mb-0">
                            <i class="fas fa-map-marked-alt me-2"></i>
                            <?php echo SITE_NAME; ?>
                        </h3>
                        <p class="mb-0 opacity-75">Admin Login</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username" name="username"
                                        value="<?php echo sanitizeInput($username ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <p class="mb-2">
                                <a href="index.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Website
                                </a>
                            </p>
                            <p class="text-muted small">
                                Don't have an account?
                                <a href="register.php" class="text-success">Register here</a>
                            </p>
                        </div>

                        <!-- Demo Credentials -->
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6 class="text-muted mb-2">Demo Credentials:</h6>
                            <div class="small">
                                <strong>Admin:</strong> admin / admin123<br>
                                <strong>Editor:</strong> editor / admin123
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>