<?php
// ── Session & Security Hardening ──
@ini_set('session.cookie_httponly', 1);
@ini_set('session.cookie_samesite', 'Strict');
@ini_set('session.use_strict_mode', 1);
@ini_set('session.use_only_cookies', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    @ini_set('session.cookie_secure', 1);
}
session_start();

require_once __DIR__ . '/../config/db.php';

// ── Security Headers ──
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com; img-src 'self' data: https://*.tile.openstreetmap.org; font-src 'self' https://cdnjs.cloudflare.com data:; connect-src 'self' https://router.project-osrm.org;");

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * Check if logged-in user is admin
 */
function isAdmin()
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Redirect if not logged in
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

/**
 * Redirect if not admin
 */
function requireAdmin()
{
    requireLogin();
    if (!isAdmin()) {
        header("Location: " . BASE_URL . "/worker/my_tasks.php");
        exit;
    }
}

/**
 * Set flash message
 */
function setFlash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash()
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Create a notification for a user
 */
function createNotification($userId, $title, $message, $type = 'general', $link = null)
{
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $title, $message, $type, $link);
    $stmt->execute();
    $stmt->close();
}

/**
 * Generate or retrieve CSRF token
 */
function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field
 */
function csrfField()
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Validate CSRF token from POST request. Dies on failure.
 */
function verifyCsrf()
{
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Invalid or missing CSRF token.');
    }
}
