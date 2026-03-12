<?php
$pageTitle = "My Tasks";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/gamification.php';
requireLogin();

// Find the worker record linked to this user
$userId = $_SESSION['user_id'];
$workerStmt = $conn->prepare("SELECT id FROM workers WHERE user_id = ?");
$workerStmt->bind_param("i", $userId);
$workerStmt->execute();
$workerResult = $workerStmt->get_result();
$workerRow = $workerResult->fetch_assoc();
$workerStmt->close();

if (!$workerRow) {
    // If admin is visiting, show message
    if (isAdmin()) {
        include __DIR__ . '/../includes/header.php';
        include __DIR__ . '/../includes/sidebar.php';
        echo '<div class="card-box"><div class="empty-state"><i class="fas fa-info-circle"></i>You are logged in as admin. No worker profile is linked.<br><a href="' . BASE_URL . '/admin/dashboard.php" class="btn btn-primary" style="margin-top:15px;">Go to Admin Dashboard</a></div></div>';
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
    echo '<div class="alert alert-danger">No worker profile found for your account.</div>';
    exit;
}

$workerId = $workerRow['id'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    verifyCsrf();
    $aId = (int)$_POST['assignment_id'];
    $newStatus = $_POST['new_status'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (in_array($newStatus, ['Pending', 'In Progress', 'Completed'])) {
        // Verify this assignment belongs to this worker
        $verify = $conn->prepare("SELECT id FROM task_assignments WHERE id = ? AND worker_id = ?");
        $verify->bind_param("ii", $aId, $workerId);
        $verify->execute();
        if ($verify->get_result()->num_rows > 0) {
            $completedAt = $newStatus === 'Completed' ? date('Y-m-d H:i:s') : null;
            $upd = $conn->prepare("UPDATE task_assignments SET status=?, remarks=?, completed_at=? WHERE id=?");
            $upd->bind_param("sssi", $newStatus, $remarks, $completedAt, $aId);
            $upd->execute();
            $upd->close();

            // Award points on completion (only if not already awarded)
            if ($newStatus === 'Completed') {
                $dupCheck = $conn->prepare("SELECT id FROM worker_points WHERE reference_type = 'task' AND reference_id = ?");
                $dupCheck->bind_param("i", $aId);
                $dupCheck->execute();
                if ($dupCheck->get_result()->num_rows === 0) {
                    $pStmt = $conn->prepare("SELECT t.priority FROM task_assignments ta JOIN tasks t ON ta.task_id = t.id WHERE ta.id = ?");
                    $pStmt->bind_param("i", $aId);
                    $pStmt->execute();
                    $pRow = $pStmt->get_result()->fetch_assoc();
                    $pStmt->close();
                    $pts = PTS_TASK_MEDIUM;
                    if ($pRow) {
                        if ($pRow['priority'] === 'High') $pts = PTS_TASK_HIGH;
                        elseif ($pRow['priority'] === 'Low') $pts = PTS_TASK_LOW;
                    }
                    awardPoints($workerId, $pts, 'Task completed: ' . $pRow['priority'] . ' priority', 'task', $aId);
                }
                $dupCheck->close();
            }

            setFlash('success', 'Task status updated.');
        }
        $verify->close();
    }
    header("Location: my_tasks.php");
    exit;
}

// Filters
$where = "WHERE ta.worker_id = ?";
$params = [$workerId];
$types = "i";

if (!empty($_GET['status'])) {
    $where .= " AND ta.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}
if (!empty($_GET['priority'])) {
    $where .= " AND t.priority = ?";
    $params[] = $_GET['priority'];
    $types .= "s";
}

$sql = "SELECT ta.*, t.title, t.description, t.constituency, t.ward, t.booth, t.priority, t.due_date, t.campaign_type
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        $where
        ORDER BY FIELD(ta.status, 'In Progress', 'Pending', 'Completed'), t.due_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-clipboard-list"></i> My Tasks</h4>
</div>

<!-- Filters -->
<div class="card-box">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="">All</option>
                <option value="Pending" <?php echo ($_GET['status'] ?? '') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="In Progress" <?php echo ($_GET['status'] ?? '') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="Completed" <?php echo ($_GET['status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </div>
        <div class="form-group">
            <label>Priority</label>
            <select name="priority" class="form-control">
                <option value="">All</option>
                <option value="Low" <?php echo ($_GET['priority'] ?? '') === 'Low' ? 'selected' : ''; ?>>Low</option>
                <option value="Medium" <?php echo ($_GET['priority'] ?? '') === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="High" <?php echo ($_GET['priority'] ?? '') === 'High' ? 'selected' : ''; ?>>High</option>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="my_tasks.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>
</div>

<!-- Tasks -->
<?php if ($tasks->num_rows > 0): ?>
    <?php while ($t = $tasks->fetch_assoc()): ?>
    <div class="card-box">
        <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:10px;">
            <div style="flex:1;">
                <h5 style="margin-bottom:10px;">
                    <?php echo htmlspecialchars($t['title']); ?>
                    <span class="badge badge-<?php echo strtolower($t['priority']); ?>" style="margin-left:8px;"><?php echo $t['priority']; ?></span>
                    <span class="badge badge-<?php echo strtolower(str_replace(' ', '', $t['status'])); ?>" style="margin-left:4px;"><?php echo $t['status']; ?></span>
                </h5>
                <?php if ($t['description']): ?>
                    <p style="color:#555;margin-bottom:10px;"><?php echo nl2br(htmlspecialchars($t['description'])); ?></p>
                <?php endif; ?>
                <div style="font-size:13px;color:#888;">
                    <?php
                    $area = array_filter([$t['constituency'], $t['ward'], $t['booth']]);
                    if ($area) echo '<i class="fas fa-map-marker-alt"></i> ' . htmlspecialchars(implode(' / ', $area)) . ' &nbsp;|&nbsp; ';
                    if ($t['campaign_type']) echo '<i class="fas fa-bullhorn"></i> ' . htmlspecialchars($t['campaign_type']) . ' &nbsp;|&nbsp; ';
                    if ($t['due_date']) echo '<i class="fas fa-calendar"></i> Due: ' . date('d M Y', strtotime($t['due_date']));
                    ?>
                </div>
                <?php if ($t['remarks']): ?>
                    <div style="margin-top:8px;font-size:13px;"><strong>Remarks:</strong> <?php echo htmlspecialchars($t['remarks']); ?></div>
                <?php endif; ?>
                <?php if ($t['completed_at']): ?>
                    <div style="margin-top:5px;font-size:12px;color:#28a745;"><i class="fas fa-check"></i> Completed: <?php echo date('d M Y, h:i A', strtotime($t['completed_at'])); ?></div>
                <?php endif; ?>

                <?php
                // Show uploaded proofs
                $proofStmt = $conn->prepare("SELECT * FROM task_proofs WHERE assignment_id = ? ORDER BY uploaded_at DESC");
                $proofStmt->bind_param("i", $t['id']);
                $proofStmt->execute();
                $proofs = $proofStmt->get_result();
                if ($proofs->num_rows > 0):
                ?>
                <div style="margin-top:12px;">
                    <strong style="font-size:13px;"><i class="fas fa-images"></i> Proof Images:</strong>
                    <div class="proof-gallery">
                        <?php while ($proof = $proofs->fetch_assoc()): ?>
                        <a href="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($proof['file_path']); ?>" target="_blank" class="proof-thumb">
                            <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($proof['file_path']); ?>" alt="<?php echo htmlspecialchars($proof['file_name']); ?>">
                        </a>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; $proofStmt->close(); ?>
            </div>
            <div>
                <form method="POST" style="min-width:0;width:100%;max-width:250px;">
                    <?php csrfField(); ?>
                    <input type="hidden" name="assignment_id" value="<?php echo $t['id']; ?>">
                    <div class="form-group">
                        <label style="font-size:12px;">Update Status</label>
                        <select name="new_status" class="form-control" style="font-size:13px;">
                            <?php foreach (['Pending', 'In Progress', 'Completed'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $t['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" style="font-size:13px;"><?php echo htmlspecialchars($t['remarks'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success btn-sm" style="width:100%;"><i class="fas fa-save"></i> Update</button>
                </form>

                <!-- Proof Upload Form -->
                <form method="POST" action="<?php echo BASE_URL; ?>/api/upload_proof.php" enctype="multipart/form-data" style="margin-top:10px;min-width:0;width:100%;max-width:250px;">
                    <?php csrfField(); ?>
                    <input type="hidden" name="assignment_id" value="<?php echo $t['id']; ?>">
                    <input type="hidden" name="redirect_url" value="<?php echo BASE_URL; ?>/worker/my_tasks.php">
                    <div class="form-group">
                        <label style="font-size:12px;"><i class="fas fa-camera"></i> Upload Proof</label>
                        <input type="file" name="proof_image" accept="image/*" class="form-control" style="font-size:12px;padding:4px;" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%;"><i class="fas fa-upload"></i> Upload Photo</button>
                </form>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="card-box">
        <div class="empty-state"><i class="fas fa-clipboard"></i>No tasks assigned to you.</div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
