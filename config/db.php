<?php
// Database configuration
// Update these values to match your environment
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pwms');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection — never expose internal errors
if ($conn->connect_error) {
    error_log('PWMS DB connection failed: ' . $conn->connect_error);
    die('Service temporarily unavailable. Please try again later.');
}

$conn->set_charset("utf8mb4");

// Application base URL (no trailing slash)
// For root deployment: keep as '' (empty)
// For subdirectory deployment (e.g., XAMPP): set to '/Party-worker'
define('BASE_URL', '/Party-worker');

// Minimum password length
define('MIN_PASSWORD_LENGTH', 8);
