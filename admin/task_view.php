<?php
$pageTitle = "Task Details";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/gamification.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: tasks.php");
    exit;
}

// Handle status update by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    verifyCsrf();
    $aId = (int)$_POST['assignment_id'];
    $newStatus = $_POST['new_status'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (in_array($newStatus, ['Pending', 'In Progress', 'Completed'])) {
        $completedAt = $newStatus === 'Completed' ? date('Y-m-d H:i:s') : null;
        $stmt = $conn->prepare("UPDATE task_assignments SET status=?, remarks=?, completed_at=? WHERE id=?");
        $stmt->bind_param("sssi", $newStatus, $remarks, $completedAt, $aId);
        $stmt->execute();
        $stmt->close();

        // Notify the worker about status change
        $wStmt = $conn->prepare("SELECT w.id as wid, w.user_id, t.title, t.priority FROM task_assignments ta JOIN workers w ON ta.worker_id = w.id JOIN tasks t ON ta.task_id = t.id WHERE ta.id = ? AND w.user_id IS NOT NULL");
        $wStmt->bind_param("i", $aId);
        $wStmt->execute();
        $wResult = $wStmt->get_result()->fetch_assoc();
        if ($wResult && $wResult['user_id']) {
            createNotification($wResult['user_id'], 'Task Status Updated', 'Your task "' . $wResult['title'] . '" status changed to ' . $newStatus, 'task_updated', BASE_URL . '/worker/my_tasks.php');
        }
        // Award points when admin marks task completed (only if not already awarded)
        if ($newStatus === 'Completed' && $wResult && $wResult['wid']) {
            $dupCheck = $conn->prepare("SELECT id FROM worker_points WHERE reference_type = 'task' AND reference_id = ?");
            $dupCheck->bind_param("i", $aId);
            $dupCheck->execute();
            if ($dupCheck->get_result()->num_rows === 0) {
                $pts = PTS_TASK_MEDIUM;
                if ($wResult['priority'] === 'High') $pts = PTS_TASK_HIGH;
                elseif ($wResult['priority'] === 'Low') $pts = PTS_TASK_LOW;
                awardPoints($wResult['wid'], $pts, 'Task completed (admin): ' . $wResult['priority'] . ' priority', 'task', $aId);
            }
            $dupCheck->close();
        }
        $wStmt->close();

        setFlash('success', 'Assignment status updated.');
    }
    header("Location: task_view.php?id=$id");
    exit;
}

// Fetch task
$stmt = $conn->prepare("SELECT t.*, u.name as creator_name FROM tasks t LEFT JOIN users u ON t.created_by = u.id WHERE t.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    setFlash('danger', 'Task not found.');
    header("Location: tasks.php");
    exit;
}

// Fetch assignments (parameterized)
$asgStmt = $conn->prepare("
    SELECT ta.*, w.name as worker_name, w.phone as worker_phone, w.booth as worker_booth
    FROM task_assignments ta
    JOIN workers w ON ta.worker_id = w.id
    WHERE ta.task_id = ?
    ORDER BY ta.status ASC, w.name ASC
");
$asgStmt->bind_param("i", $id);
$asgStmt->execute();
$assignments = $asgStmt->get_result();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-clipboard-check"></i> Task Details</h4>
    <div>
        <a href="task_add.php?edit=<?php echo $task['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
        <a href="tasks.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="task-detail-grid">
    <!-- Task Info -->
    <div class="card-box">
        <h5><i class="fas fa-info-circle"></i> Task Information</h5>
        <div class="detail-item">
            <label>Title</label>
            <div class="value"><?php echo htmlspecialchars($task['title']); ?></div>
        </div>
        <div class="detail-item">
            <label>Description</label>
            <div class="value"><?php echo nl2br(htmlspecialchars($task['description'] ?: 'No description')); ?></div>
        </div>
        <div class="form-row">
            <div class="detail-item">
                <label>Constituency</label>
                <div class="value"><?php echo htmlspecialchars($task['constituency'] ?: '-'); ?></div>
            </div>
            <div class="detail-item">
                <label>Ward</label>
                <div class="value"><?php echo htmlspecialchars($task['ward'] ?: '-'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="detail-item">
                <label>Booth</label>
                <div class="value"><?php echo htmlspecialchars($task['booth'] ?: '-'); ?></div>
            </div>
            <div class="detail-item">
                <label>Campaign Type</label>
                <div class="value"><?php echo htmlspecialchars($task['campaign_type'] ?: '-'); ?></div>
            </div>
        </div>
    </div>

    <!-- Meta Info -->
    <div>
        <div class="card-box">
            <div class="detail-item">
                <label>Priority</label>
                <div class="value"><span class="badge badge-<?php echo strtolower($task['priority']); ?>"><?php echo $task['priority']; ?></span></div>
            </div>
            <div class="detail-item">
                <label>Due Date</label>
                <div class="value"><?php echo $task['due_date'] ? date('d M Y', strtotime($task['due_date'])) : 'No due date'; ?></div>
            </div>
            <div class="detail-item">
                <label>Created By</label>
                <div class="value"><?php echo htmlspecialchars($task['creator_name'] ?? 'Unknown'); ?></div>
            </div>
            <div class="detail-item">
                <label>Created At</label>
                <div class="value"><?php echo date('d M Y, h:i A', strtotime($task['created_at'])); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Assigned Workers -->
<div class="card-box" style="margin-top:25px;">
    <h5><i class="fas fa-user-check"></i> Assigned Workers (<?php echo $assignments->num_rows; ?>)</h5>
    <?php if ($assignments->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Worker</th>
                    <th>Phone</th>
                    <th>Booth</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Completed At</th>
                    <th>Proofs</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = $assignments->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($a['worker_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($a['worker_phone']); ?></td>
                    <td><?php echo htmlspecialchars($a['worker_booth'] ?? '-'); ?></td>
                    <td><span class="badge badge-<?php echo strtolower(str_replace(' ', '', $a['status'])); ?>"><?php echo $a['status']; ?></span></td>
                    <td><?php echo htmlspecialchars($a['remarks'] ?? '-'); ?></td>
                    <td><?php echo $a['completed_at'] ? date('d M Y, h:i A', strtotime($a['completed_at'])) : '-'; ?></td>
                    <td>
                        <?php
                        $proofStmt = $conn->prepare("SELECT * FROM task_proofs WHERE assignment_id = ? ORDER BY uploaded_at DESC");
                        $proofStmt->bind_param("i", $a['id']);
                        $proofStmt->execute();
                        $proofs = $proofStmt->get_result();
                        if ($proofs->num_rows > 0):
                        ?>
                        <div class="proof-gallery" style="margin:0;">
                            <?php while ($proof = $proofs->fetch_assoc()): ?>
                            <a href="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($proof['file_path']); ?>" target="_blank" class="proof-thumb">
                                <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($proof['file_path']); ?>" alt="<?php echo htmlspecialchars($proof['file_name']); ?>">
                            </a>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                            <span style="color:#999;font-size:12px;">No proofs</span>
                        <?php endif; $proofStmt->close(); ?>
                    </td>
                    <td>
                        <form method="POST" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                            <?php csrfField(); ?>
                            <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                            <select name="new_status" class="form-control" style="width:auto;padding:4px 8px;font-size:12px;">
                                <?php foreach (['Pending', 'In Progress', 'Completed'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $a['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="remarks" class="form-control" style="width:auto;min-width:80px;max-width:120px;flex:1;padding:4px 8px;font-size:12px;" placeholder="Remarks" value="<?php echo htmlspecialchars($a['remarks'] ?? ''); ?>">
                            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-user-slash"></i>No workers assigned to this task.</div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
