<?php
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$editMode = false;
$task = null;
$assignedWorkerIds = [];

// Edit mode
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($task) {
        $editMode = true;
        $pageTitle = "Edit Task";
        // Get assigned workers
        $aStmt = $conn->prepare("SELECT worker_id FROM task_assignments WHERE task_id = ?");
        $aStmt->bind_param("i", $editId);
        $aStmt->execute();
        $aResult = $aStmt->get_result();
        while ($a = $aResult->fetch_assoc()) {
            $assignedWorkerIds[] = $a['worker_id'];
        }
        $aStmt->close();
    }
}

if (!$editMode) {
    $pageTitle = "Create Task";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $constituency = trim($_POST['constituency'] ?? '');
    $ward = trim($_POST['ward'] ?? '');
    $booth = trim($_POST['booth'] ?? '');
    $priority = $_POST['priority'] ?? 'Medium';
    $campaign_type = trim($_POST['campaign_type'] ?? '');
    $due_date = $_POST['due_date'] ?? null;
    $worker_ids = $_POST['workers'] ?? [];

    if (empty($title)) {
        setFlash('danger', 'Task title is required.');
    } else {
        if ($editMode) {
            // Update task
            $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, constituency=?, ward=?, booth=?, priority=?, campaign_type=?, due_date=? WHERE id=?");
            $due = !empty($due_date) ? $due_date : null;
            $stmt->bind_param("ssssssssi", $title, $description, $constituency, $ward, $booth, $priority, $campaign_type, $due, $editId);
            $stmt->execute();
            $stmt->close();

            // Sync assignments: only remove deselected workers, add new ones (preserves existing data)
            $newWorkerIds = array_map('intval', $worker_ids);
            $existingIds = [];
            $exStmt = $conn->prepare("SELECT worker_id FROM task_assignments WHERE task_id = ?");
            $exStmt->bind_param("i", $editId);
            $exStmt->execute();
            $exResult = $exStmt->get_result();
            while ($exRow = $exResult->fetch_assoc()) {
                $existingIds[] = (int)$exRow['worker_id'];
            }
            $exStmt->close();

            // Remove workers that were deselected
            $toRemove = array_diff($existingIds, $newWorkerIds);
            if (!empty($toRemove)) {
                $delStmt = $conn->prepare("DELETE FROM task_assignments WHERE task_id = ? AND worker_id = ?");
                foreach ($toRemove as $rid) {
                    $delStmt->bind_param("ii", $editId, $rid);
                    $delStmt->execute();
                }
                $delStmt->close();
            }

            // Add newly selected workers
            $toAdd = array_diff($newWorkerIds, $existingIds);
            if (!empty($toAdd)) {
                $addStmt = $conn->prepare("INSERT INTO task_assignments (task_id, worker_id, status) VALUES (?, ?, 'Pending')");
                foreach ($toAdd as $aid) {
                    $addStmt->bind_param("ii", $editId, $aid);
                    $addStmt->execute();
                }
                $addStmt->close();
            }

            $taskId = $editId;
            // Notify only newly-added workers
            $notifyWorkerIds = $toAdd;
        } else {
            // Insert task
            $stmt = $conn->prepare("INSERT INTO tasks (title, description, constituency, ward, booth, priority, campaign_type, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $due = !empty($due_date) ? $due_date : null;
            $created_by = $_SESSION['user_id'];
            $stmt->bind_param("ssssssssi", $title, $description, $constituency, $ward, $booth, $priority, $campaign_type, $due, $created_by);
            $stmt->execute();
            $taskId = $conn->insert_id;
            $stmt->close();

            // Assign workers for new task
            $notifyWorkerIds = [];
            if (!empty($worker_ids)) {
                $assignStmt = $conn->prepare("INSERT INTO task_assignments (task_id, worker_id, status) VALUES (?, ?, 'Pending')");
                foreach ($worker_ids as $wid) {
                    $wid = (int)$wid;
                    $assignStmt->bind_param("ii", $taskId, $wid);
                    $assignStmt->execute();
                    $notifyWorkerIds[] = $wid;
                }
                $assignStmt->close();
            }
        }

        // Send notifications to assigned workers
        if (!empty($notifyWorkerIds)) {
            $workerUserStmt = $conn->prepare("SELECT user_id, name FROM workers WHERE id = ? AND user_id IS NOT NULL");
            foreach ($notifyWorkerIds as $wid) {
                $wid = (int)$wid;
                $workerUserStmt->bind_param("i", $wid);
                $workerUserStmt->execute();
                $wUser = $workerUserStmt->get_result()->fetch_assoc();
                if ($wUser && $wUser['user_id']) {
                    $notifTitle = $editMode ? 'Task Updated' : 'New Task Assigned';
                    $notifMsg = ($editMode ? 'Task "' : 'You have been assigned to "') . $title . '"';
                    $notifType = $editMode ? 'task_updated' : 'task_assigned';
                    $notifLink = BASE_URL . '/worker/my_tasks.php';
                    createNotification($wUser['user_id'], $notifTitle, $notifMsg, $notifType, $notifLink);
                }
            }
            $workerUserStmt->close();
        }

        setFlash('success', $editMode ? 'Task updated successfully.' : 'Task created successfully.');
        header("Location: tasks.php");
        exit;
    }
}

// Fetch active workers for assignment
$allWorkers = $conn->query("SELECT id, name, phone, constituency, ward, booth FROM workers WHERE status='Active' ORDER BY name");

$extraCSS = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css">';
$extraJS = '<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    new TomSelect("#worker-select", {
        plugins: ["remove_button", "clear_button"],
        placeholder: "Search workers by name, phone, or area\u2026",
        maxItems: null,
        searchField: ["text"],
        render: {
            option: function(data, escape) {
                var parts = data.text.split(" \u2014 ");
                return "<div><span>" + escape(parts[0]) + "</span>" +
                    (parts[1] ? "<small style=\"display:block;color:var(--text-muted);font-size:12px\">" + escape(parts[1]) + "</small>" : "") + "</div>";
            }
        }
    });
});
</script>';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-<?php echo $editMode ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $editMode ? 'Edit Task' : 'Create New Task'; ?></h4>
    <a href="tasks.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="card-box">
    <form method="POST">
        <?php csrfField(); ?>
        <div class="form-group">
            <label>Title *</label>
            <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($task['title'] ?? $_POST['title'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($task['description'] ?? $_POST['description'] ?? ''); ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Constituency</label>
                <input type="text" name="constituency" class="form-control" value="<?php echo htmlspecialchars($task['constituency'] ?? $_POST['constituency'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Ward</label>
                <input type="text" name="ward" class="form-control" value="<?php echo htmlspecialchars($task['ward'] ?? $_POST['ward'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Booth</label>
                <input type="text" name="booth" class="form-control" value="<?php echo htmlspecialchars($task['booth'] ?? $_POST['booth'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Priority</label>
                <select name="priority" class="form-control">
                    <?php foreach (['Low', 'Medium', 'High'] as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo ($task['priority'] ?? $_POST['priority'] ?? 'Medium') === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Campaign Type</label>
                <select name="campaign_type" class="form-control">
                    <option value="">-- Select --</option>
                    <?php foreach (['Door-to-door', 'Event', 'Call Outreach', 'Social Media', 'Rally', 'Other'] as $ct): ?>
                        <option value="<?php echo $ct; ?>" <?php echo ($task['campaign_type'] ?? $_POST['campaign_type'] ?? '') === $ct ? 'selected' : ''; ?>><?php echo $ct; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($task['due_date'] ?? $_POST['due_date'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Assign Workers</label>
            <select multiple name="workers[]" id="worker-select" class="form-control" style="display:none;">
                <?php
                $postedWorkers = $_POST['workers'] ?? $assignedWorkerIds;
                while ($w = $allWorkers->fetch_assoc()):
                    $selected = in_array($w['id'], $postedWorkers) ? 'selected' : '';
                    $location = $w['constituency'] . '/' . $w['ward'] . '/' . $w['booth'];
                    $label = htmlspecialchars($w['name'] . ' (' . $w['phone'] . ') — ' . $location);
                ?>
                <option value="<?php echo $w['id']; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $editMode ? 'Update Task' : 'Create Task'; ?></button>
            <a href="tasks.php" class="btn btn-outline" style="margin-left:10px;">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
