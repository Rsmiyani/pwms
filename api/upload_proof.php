<?php
/**
 * Upload Proof API - handles file upload for task proof images
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/gamification.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/");
    exit;
}

verifyCsrf();

$assignmentId = (int)($_POST['assignment_id'] ?? 0);
// Sanitize redirect URL — only allow local paths starting with BASE_URL
$defaultRedirect = BASE_URL . '/worker/my_tasks.php';
$redirectUrl = $_POST['redirect_url'] ?? $defaultRedirect;
if (!str_starts_with($redirectUrl, BASE_URL . '/')) {
    $redirectUrl = $defaultRedirect;
}

if ($assignmentId <= 0) {
    setFlash('danger', 'Invalid assignment.');
    header("Location: $redirectUrl");
    exit;
}

// Verify the user owns this assignment (worker) or is admin
$userId = $_SESSION['user_id'];
if (!isAdmin()) {
    $verify = $conn->prepare("SELECT ta.id FROM task_assignments ta JOIN workers w ON ta.worker_id = w.id WHERE ta.id = ? AND w.user_id = ?");
    $verify->bind_param("ii", $assignmentId, $userId);
    $verify->execute();
    if ($verify->get_result()->num_rows === 0) {
        setFlash('danger', 'Unauthorized.');
        header("Location: $redirectUrl");
        exit;
    }
    $verify->close();
}

// Validate file upload
if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
    setFlash('danger', 'No file uploaded or upload error.');
    header("Location: $redirectUrl");
    exit;
}

$file = $_FILES['proof_image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Validate MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedTypes)) {
    setFlash('danger', 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.');
    header("Location: $redirectUrl");
    exit;
}

if ($file['size'] > $maxSize) {
    setFlash('danger', 'File too large. Maximum size is 5MB.');
    header("Location: $redirectUrl");
    exit;
}

// Generate safe filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$ext = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    $ext = 'jpg';
}
$safeName = 'proof_' . $assignmentId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$uploadDir = __DIR__ . '/../uploads/proofs/';
$uploadPath = $uploadDir . $safeName;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    $dbPath = 'uploads/proofs/' . $safeName;
    $originalName = basename($file['name']);
    $stmt = $conn->prepare("INSERT INTO task_proofs (assignment_id, file_name, file_path) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $assignmentId, $originalName, $dbPath);
    $stmt->execute();
    $proofId = $conn->insert_id;
    $stmt->close();

    // Award points for proof upload
    $wStmt = $conn->prepare("SELECT worker_id FROM task_assignments WHERE id = ?");
    $wStmt->bind_param("i", $assignmentId);
    $wStmt->execute();
    $wRow = $wStmt->get_result()->fetch_assoc();
    $wStmt->close();
    if ($wRow) {
        awardPoints($wRow['worker_id'], PTS_PROOF_UPLOAD, 'Proof image uploaded', 'proof', $proofId);
    }

    setFlash('success', 'Proof image uploaded successfully.');
} else {
    setFlash('danger', 'Failed to save file.');
}

header("Location: $redirectUrl");
exit;
