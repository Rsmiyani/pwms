<?php
$pageTitle = "Workers";
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

// Handle delete (POST only with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verifyCsrf();
    $id = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM workers WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash('success', 'Worker deleted successfully.');
    } else {
        setFlash('danger', 'Failed to delete worker.');
    }
    $stmt->close();
    header("Location: workers.php");
    exit;
}

// Filters
$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where .= " AND (w.name LIKE ? OR w.phone LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}
if (!empty($_GET['constituency'])) {
    $where .= " AND w.constituency = ?";
    $params[] = $_GET['constituency'];
    $types .= "s";
}
if (!empty($_GET['ward'])) {
    $where .= " AND w.ward = ?";
    $params[] = $_GET['ward'];
    $types .= "s";
}
if (!empty($_GET['role'])) {
    $where .= " AND w.role = ?";
    $params[] = $_GET['role'];
    $types .= "s";
}
if (!empty($_GET['responsibility'])) {
    $where .= " AND w.responsibility_type = ?";
    $params[] = $_GET['responsibility'];
    $types .= "s";
}
if (!empty($_GET['status'])) {
    $where .= " AND w.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

require_once __DIR__ . '/../config/pagination.php';

// Count total for pagination
$countSql = "SELECT COUNT(*) as total FROM workers w $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$perPage = 15;
$totalPages = max(1, ceil($totalRecords / $perPage));
$currentPageNum = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($currentPageNum - 1) * $perPage;

$sql = "SELECT w.* FROM workers w $where ORDER BY w.created_at DESC LIMIT ? OFFSET ?";
$pTypes = $types . "ii";
$pParams = $params;
$pParams[] = $perPage;
$pParams[] = $offset;
$stmt = $conn->prepare($sql);
if (!empty($pParams)) {
    $stmt->bind_param($pTypes, ...$pParams);
}
$stmt->execute();
$workers = $stmt->get_result();

// Get distinct values for filters
$constituencies = $conn->query("SELECT DISTINCT constituency FROM workers WHERE constituency IS NOT NULL AND constituency != '' ORDER BY constituency");
$wards = $conn->query("SELECT DISTINCT ward FROM workers WHERE ward IS NOT NULL AND ward != '' ORDER BY ward");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-users"></i> All Workers</h4>
    <a href="worker_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Worker</a>
</div>

<!-- Filters -->
<div class="card-box">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Search</label>
            <input type="text" name="search" class="form-control" placeholder="Name or phone..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Constituency</label>
            <select name="constituency" class="form-control">
                <option value="">All</option>
                <?php while ($c = $constituencies->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($c['constituency']); ?>" <?php echo ($_GET['constituency'] ?? '') === $c['constituency'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['constituency']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Ward</label>
            <select name="ward" class="form-control">
                <option value="">All</option>
                <?php while ($w = $wards->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($w['ward']); ?>" <?php echo ($_GET['ward'] ?? '') === $w['ward'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['ward']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Role</label>
            <select name="role" class="form-control">
                <option value="">All</option>
                <option value="Volunteer" <?php echo ($_GET['role'] ?? '') === 'Volunteer' ? 'selected' : ''; ?>>Volunteer</option>
                <option value="Booth President" <?php echo ($_GET['role'] ?? '') === 'Booth President' ? 'selected' : ''; ?>>Booth President</option>
                <option value="Mandal Head" <?php echo ($_GET['role'] ?? '') === 'Mandal Head' ? 'selected' : ''; ?>>Mandal Head</option>
            </select>
        </div>
        <div class="form-group">
            <label>Responsibility</label>
            <select name="responsibility" class="form-control">
                <option value="">All</option>
                <option value="Door-to-door" <?php echo ($_GET['responsibility'] ?? '') === 'Door-to-door' ? 'selected' : ''; ?>>Door-to-door</option>
                <option value="Social Media" <?php echo ($_GET['responsibility'] ?? '') === 'Social Media' ? 'selected' : ''; ?>>Social Media</option>
                <option value="Event Management" <?php echo ($_GET['responsibility'] ?? '') === 'Event Management' ? 'selected' : ''; ?>>Event Management</option>
                <option value="Data Collection" <?php echo ($_GET['responsibility'] ?? '') === 'Data Collection' ? 'selected' : ''; ?>>Data Collection</option>
                <option value="Call Outreach" <?php echo ($_GET['responsibility'] ?? '') === 'Call Outreach' ? 'selected' : ''; ?>>Call Outreach</option>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="">All</option>
                <option value="Active" <?php echo ($_GET['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo ($_GET['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            <a href="workers.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>
</div>

<!-- Workers Table -->
<div class="card-box">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th data-sort="name">Name</th>
                    <th data-sort="phone">Phone</th>
                    <th data-sort="role">Role</th>
                    <th data-sort="constituency">Constituency</th>
                    <th data-sort="ward">Ward</th>
                    <th data-sort="booth">Booth</th>
                    <th data-sort="responsibility">Responsibility</th>
                    <th data-sort="status">Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($workers->num_rows > 0): ?>
                    <?php $i = $offset + 1; while ($row = $workers->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['role']); ?></td>
                        <td><?php echo htmlspecialchars($row['constituency'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['ward'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['booth'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['responsibility_type'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                        <td>
                            <div class="action-btns">
                                <a href="worker_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Delete this worker?')">
                                    <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                    <?php csrfField(); ?>
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="empty-state">No workers found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php renderPagination($currentPageNum, $totalPages, $totalRecords, $perPage, getPaginationBaseUrl()); ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
