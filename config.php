<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // Default XAMPP username
define('DB_PASS', '');      // Default XAMPP password (empty)
define('DB_NAME', 'nigeria_tourism');

// Site settings
define('SITE_NAME', 'Explore Nigeria');
define('SITE_URL', 'http://localhost/nigeria-tourism-cms');
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CAPTCHA_LENGTH', 5);

// Create database connection
function getDBConnection()
{
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please check your configuration.");
    }
}

function processUploadedImage($file, $max_width = 1200)
{
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $filename = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $target_path = UPLOAD_DIR . $filename;

    // Resize if needed
    list($width, $height) = getimagesize($file['tmp_name']);
    if ($width > $max_width) {
        $ratio = $height / $width;
        $new_height = $max_width * $ratio;

        $image_p = imagecreatetruecolor($max_width, $new_height);
        $image = imagecreatefromjpeg($file['tmp_name']); // Adjust for PNG/GIF as needed
        imagecopyresampled($image_p, $image, 0, 0, 0, 0, $max_width, $new_height, $width, $height);
        imagejpeg($image_p, $target_path, 85); // Save with 85% quality
        imagedestroy($image_p);
    } else {
        move_uploaded_file($file['tmp_name'], $target_path);
    }

    return $filename;
}
// Security functions
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateId($id)
{
    return filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function isAdmin()
{
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdmin()
{
    if (!isAdmin()) {
        header("Location: index.php");
        exit();
    }
}

// Generate CSRF token
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Create slug from title
function createSlug($string)
{
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
//set default timezone
date_default_timezone_set('Africa/Lagos');
