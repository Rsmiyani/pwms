<?php
/**
 * Notification API - Handles AJAX requests for notifications
 * GET: Fetch unread notifications
 * POST action=mark_read: Mark notification(s) as read
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Rate limiting: max 60 requests per minute per session
$rateLimitKey = 'notif_rate_' . floor(time() / 60);
if (!isset($_SESSION[$rateLimitKey])) {
    // Clear old rate limit keys
    foreach ($_SESSION as $k => $v) {
        if (str_starts_with($k, 'notif_rate_') && $k !== $rateLimitKey) {
            unset($_SESSION[$k]);
        }
    }
    $_SESSION[$rateLimitKey] = 0;
}
$_SESSION[$rateLimitKey]++;
if ($_SESSION[$rateLimitKey] > 60) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch unread count + recent notifications
    $stmt = $conn->prepare("SELECT id, title, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    $unreadCount = 0;
    while ($row = $result->fetch_assoc()) {
        if (!$row['is_read'])
            $unreadCount++;
        $row['time_ago'] = timeAgo($row['created_at']);
        $notifications[] = $row;
    }
    $stmt->close();

    echo json_encode(['unread_count' => $unreadCount, 'notifications' => $notifications]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF for state-changing POST requests
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $notifId = (int) ($_POST['id'] ?? 0);
        if ($notifId > 0) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notifId, $userId);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }
}

function timeAgo($datetime)
{
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0)
        return $diff->y . 'y ago';
    if ($diff->m > 0)
        return $diff->m . 'mo ago';
    if ($diff->d > 0)
        return $diff->d . 'd ago';
    if ($diff->h > 0)
        return $diff->h . 'h ago';
    if ($diff->i > 0)
        return $diff->i . 'm ago';
    return 'Just now';
}
