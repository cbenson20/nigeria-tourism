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
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error_message = 'Username must be at least 3 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        $pdo = getDBConnection();

        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email");
        $stmt->execute(['username' => $username, 'email' => $email]);

        if ($stmt->fetch()) {
            $error_message = 'Username or email already exists.';
        } else {
            // Create new user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, 'editor')");

            if ($stmt->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => $password_hash
            ])) {
                $success_message = 'Registration successful! You can now log in.';
                // Clear form data
                $username = '';
                $email = '';
            } else {
                $error_message = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #28a745, #20c997);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .register-card {
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
            <div class="col-md-6 col-lg-5">
                <div class="card register-card">
                    <div class="card-header text-center py-4">
                        <h3 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>
                            Create Account
                        </h3>
                        <p class="mb-0 opacity-75">Join <?php echo SITE_NAME; ?></p>
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
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-success btn-sm">Login Now</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username" name="username"
                                        value="<?php echo sanitizeInput($username ?? ''); ?>"
                                        required minlength="3" maxlength="50">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo sanitizeInput($email ?? ''); ?>"
                                        required maxlength="100">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password"
                                        required minlength="6">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                        required minlength="6">
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
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
                                Already have an account?
                                <a href="login.php" class="text-success">Login here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;

            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>

</html>