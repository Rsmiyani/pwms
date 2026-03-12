<?php
$pageTitle = "Worker Check-ins";
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

// Filters
$filterWorker = $_GET['worker'] ?? '';
$filterType = $_GET['type'] ?? '';
$filterDate = $_GET['date'] ?? '';

$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($filterWorker)) {
    $where .= " AND w.name LIKE ?";
    $params[] = "%$filterWorker%";
    $types .= "s";
}
if (!empty($filterType)) {
    $where .= " AND wc.type = ?";
    $params[] = $filterType;
    $types .= "s";
}
if (!empty($filterDate)) {
    $where .= " AND DATE(wc.created_at) = ?";
    $params[] = $filterDate;
    $types .= "s";
}

$sql = "SELECT wc.*, w.name as worker_name, w.phone, w.constituency, w.ward, w.booth
        FROM worker_checkins wc
        JOIN workers w ON wc.worker_id = w.id
        $where
        ORDER BY wc.created_at DESC
        LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$checkins = $stmt->get_result();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-map-marker-alt"></i> Worker Check-ins</h4>
</div>

<!-- Filters -->
<div class="card-box">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Worker Name</label>
            <input type="text" name="worker" class="form-control" placeholder="Search worker..." value="<?php echo htmlspecialchars($filterWorker); ?>">
        </div>
        <div class="form-group">
            <label>Type</label>
            <select name="type" class="form-control">
                <option value="">All</option>
                <option value="check-in" <?php echo $filterType === 'check-in' ? 'selected' : ''; ?>>Check-In</option>
                <option value="check-out" <?php echo $filterType === 'check-out' ? 'selected' : ''; ?>>Check-Out</option>
            </select>
        </div>
        <div class="form-group">
            <label>Date</label>
            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filterDate); ?>">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="checkins.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>
</div>

<!-- Check-ins Table -->
<div class="card-box">
    <h5><i class="fas fa-list"></i> Check-in Records (<?php echo $checkins->num_rows; ?>)</h5>
    <?php if ($checkins->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Worker</th>
                    <th>Phone</th>
                    <th>Area</th>
                    <th>Type</th>
                    <th>Location Name</th>
                    <th>Coordinates</th>
                    <th>Map</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($c = $checkins->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c['worker_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($c['phone']); ?></td>
                    <td><?php echo htmlspecialchars(implode(' / ', array_filter([$c['constituency'], $c['ward'], $c['booth']]))); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $c['type'] === 'check-in' ? 'active' : 'inactive'; ?>">
                            <i class="fas fa-<?php echo $c['type'] === 'check-in' ? 'sign-in-alt' : 'sign-out-alt'; ?>"></i>
                            <?php echo ucfirst($c['type']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($c['location_name'] ?: '-'); ?></td>
                    <td style="font-size:12px;"><?php echo $c['latitude'] . ', ' . $c['longitude']; ?></td>
                    <td>
                        <a href="https://www.google.com/maps?q=<?php echo $c['latitude']; ?>,<?php echo $c['longitude']; ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary" title="View on Google Maps">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </td>
                    <td style="white-space:nowrap;"><?php echo date('d M Y, h:i A', strtotime($c['created_at'])); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-map"></i>No check-in records found.</div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
