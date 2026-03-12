<?php
$pageTitle = "Tasks";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/pagination.php';
requireAdmin();

// Handle delete (POST only with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verifyCsrf();
    $id = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash('success', 'Task deleted successfully.');
    } else {
        setFlash('danger', 'Failed to delete task.');
    }
    $stmt->close();
    header("Location: tasks.php");
    exit;
}

// Filters
$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where .= " AND (t.title LIKE ?)";
    $params[] = $search;
    $types .= "s";
}
if (!empty($_GET['priority'])) {
    $where .= " AND t.priority = ?";
    $params[] = $_GET['priority'];
    $types .= "s";
}
if (!empty($_GET['constituency'])) {
    $where .= " AND t.constituency = ?";
    $params[] = $_GET['constituency'];
    $types .= "s";
}

// Count total
$countSql = "SELECT COUNT(*) as total FROM tasks t $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$perPage = 15;
$totalPages = max(1, ceil($totalRecords / $perPage));
$currentPage = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($currentPage - 1) * $perPage;

$sql = "SELECT t.*, 
    (SELECT COUNT(*) FROM task_assignments ta WHERE ta.task_id = t.id) as assigned_count,
    (SELECT COUNT(*) FROM task_assignments ta WHERE ta.task_id = t.id AND ta.status = 'Completed') as completed_count
    FROM tasks t $where ORDER BY t.created_at DESC LIMIT ? OFFSET ?";

$pParams = $params;
$pParams[] = $perPage;
$pParams[] = $offset;
$pTypes = $types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($pTypes, ...$pParams);
$stmt->execute();
$tasks = $stmt->get_result();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-tasks"></i> All Tasks</h4>
    <a href="task_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Task</a>
</div>

<!-- Filters -->
<div class="card-box">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Search</label>
            <input type="text" name="search" class="form-control" placeholder="Task title..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
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
            <label>Constituency</label>
            <input type="text" name="constituency" class="form-control" placeholder="Constituency" value="<?php echo htmlspecialchars($_GET['constituency'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            <a href="tasks.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>
</div>

<!-- Tasks Table -->
<div class="card-box">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th data-sort="title">Title</th>
                    <th data-sort="area">Area</th>
                    <th data-sort="priority">Priority</th>
                    <th data-sort="due_date">Due Date</th>
                    <th data-sort="assigned">Assigned</th>
                    <th>Progress</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tasks->num_rows > 0): ?>
                    <?php $i = $offset + 1; while ($row = $tasks->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                        <td>
                            <?php 
                            $area = array_filter([$row['constituency'], $row['ward'], $row['booth']]);
                            echo htmlspecialchars(implode(' / ', $area) ?: '-');
                            ?>
                        </td>
                        <td><span class="badge badge-<?php echo strtolower($row['priority']); ?>"><?php echo $row['priority']; ?></span></td>
                        <td><?php echo $row['due_date'] ? date('d M Y', strtotime($row['due_date'])) : '-'; ?></td>
                        <td><?php echo $row['assigned_count']; ?> worker(s)</td>
                        <td>
                            <?php 
                            if ($row['assigned_count'] > 0) {
                                $pct = round(($row['completed_count'] / $row['assigned_count']) * 100);
                                echo '<div style="background:var(--bg-alt);border-radius:10px;height:8px;width:80px;display:inline-block;vertical-align:middle;">
                                    <div style="background:' . ($pct == 100 ? 'var(--success)' : 'var(--info)') . ';border-radius:10px;height:8px;width:' . $pct . '%;"></div>
                                </div> ' . $pct . '%';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="task_view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-secondary" title="View"><i class="fas fa-eye"></i></a>
                                <a href="task_add.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Delete this task and all assignments?')">
                                    <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                    <?php csrfField(); ?>
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-state">No tasks found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php renderPagination($currentPage, $totalPages, $totalRecords, $perPage, getPaginationBaseUrl()); ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
