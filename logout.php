<?php
session_start();
$_SESSION = [];

// Clear the session cookie from the browser
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $p["path"],
        $p["domain"],
        $p["secure"],
        $p["httponly"]
    );
}

session_destroy();
require_once __DIR__ . '/config/db.php';
header("Location: " . BASE_URL . "/login.php");
exit;
