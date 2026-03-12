<?php
$pageTitle = "Reports";
require_once __DIR__ . '/../config/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/pagination.php';
$filterConstituency = $_GET['constituency'] ?? '';
$filterWard = $_GET['ward'] ?? '';

$constFilter = "";
$constParams = [];
$constTypes = "";

if (!empty($filterConstituency)) {
    $constFilter .= " AND t.constituency = ?";
    $constParams[] = $filterConstituency;
    $constTypes .= "s";
}
if (!empty($filterWard)) {
    $constFilter .= " AND t.ward = ?";
    $constParams[] = $filterWard;
    $constTypes .= "s";
}

// 1. Tasks per area (ward/booth) — with pagination
$areaCountSQL = "SELECT COUNT(*) as total FROM (SELECT t.ward, t.booth FROM tasks t WHERE 1=1 $constFilter GROUP BY t.ward, t.booth) sub";
$areaCountStmt = $conn->prepare($areaCountSQL);
if (!empty($constParams)) {
    $areaCountStmt->bind_param($constTypes, ...$constParams);
}
$areaCountStmt->execute();
$areaTotalRecords = (int)$areaCountStmt->get_result()->fetch_assoc()['total'];
$areaCountStmt->close();

$areaPerPage = 10;
$areaTotalPages = max(1, ceil($areaTotalRecords / $areaPerPage));
$areaCurrentPage = max(1, min($areaTotalPages, (int)($_GET['apage'] ?? 1)));
$areaOffset = ($areaCurrentPage - 1) * $areaPerPage;

$areaSQL = "SELECT t.ward, t.booth, COUNT(*) as cnt FROM tasks t WHERE 1=1 $constFilter GROUP BY t.ward, t.booth ORDER BY cnt DESC LIMIT ? OFFSET ?";
$areaTypes = $constTypes . "ii";
$areaParams = $constParams;
$areaParams[] = $areaPerPage;
$areaParams[] = $areaOffset;
$areaStmt = $conn->prepare($areaSQL);
if (!empty($areaParams)) {
    $areaStmt->bind_param($areaTypes, ...$areaParams);
}
$areaStmt->execute();
$tasksPerArea = $areaStmt->get_result();

// 2. Completed vs Pending vs In Progress
$statusSQL = "SELECT ta.status, COUNT(*) as cnt FROM task_assignments ta JOIN tasks t ON ta.task_id = t.id WHERE 1=1 $constFilter GROUP BY ta.status";
$statusStmt = $conn->prepare($statusSQL);
if (!empty($constParams)) {
    $statusStmt->bind_param($constTypes, ...$constParams);
}
$statusStmt->execute();
$statusResult = $statusStmt->get_result();
$statusCounts = ['Pending' => 0, 'In Progress' => 0, 'Completed' => 0];
while ($row = $statusResult->fetch_assoc()) {
    $statusCounts[$row['status']] = (int)$row['cnt'];
}
$totalAssignments = array_sum($statusCounts);

// 3. Top 5 most active workers
$topSQL = "SELECT w.name, w.constituency, w.ward, w.booth, COUNT(ta.id) as completed_count
    FROM task_assignments ta
    JOIN workers w ON ta.worker_id = w.id
    JOIN tasks t ON ta.task_id = t.id
    WHERE ta.status = 'Completed' $constFilter
    GROUP BY ta.worker_id
    ORDER BY completed_count DESC
    LIMIT 5";
$topStmt = $conn->prepare($topSQL);
if (!empty($constParams)) {
    $topStmt->bind_param($constTypes, ...$constParams);
}
$topStmt->execute();
$topWorkers = $topStmt->get_result();

// 4. Tasks by campaign type
$campaignSQL = "SELECT t.campaign_type, COUNT(*) as cnt FROM tasks t WHERE t.campaign_type IS NOT NULL AND t.campaign_type != '' $constFilter GROUP BY t.campaign_type ORDER BY cnt DESC";
$campaignStmt = $conn->prepare($campaignSQL);
if (!empty($constParams)) {
    $campaignStmt->bind_param($constTypes, ...$constParams);
}
$campaignStmt->execute();
$campaignResult = $campaignStmt->get_result();

// Get filter options
$constituencies = $conn->query("SELECT DISTINCT constituency FROM tasks WHERE constituency IS NOT NULL AND constituency != '' ORDER BY constituency");
$wards = $conn->query("SELECT DISTINCT ward FROM tasks WHERE ward IS NOT NULL AND ward != '' ORDER BY ward");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-chart-bar"></i> Reports & Analytics</h4>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="export.php?format=csv&type=tasks&constituency=<?php echo urlencode($filterConstituency); ?>&ward=<?php echo urlencode($filterWard); ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> Export CSV</a>
        <a href="export.php?format=pdf&type=tasks&constituency=<?php echo urlencode($filterConstituency); ?>&ward=<?php echo urlencode($filterWard); ?>" class="btn btn-danger btn-sm" target="_blank"><i class="fas fa-file-pdf"></i> Print / PDF</a>
        <a href="export.php?format=csv&type=workers&constituency=<?php echo urlencode($filterConstituency); ?>&ward=<?php echo urlencode($filterWard); ?>" class="btn btn-primary btn-sm"><i class="fas fa-users"></i> Workers CSV</a>
    </div>
</div>

<!-- Filters -->
<div class="card-box">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Constituency</label>
            <select name="constituency" class="form-control">
                <option value="">All</option>
                <?php while ($c = $constituencies->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($c['constituency']); ?>" <?php echo $filterConstituency === $c['constituency'] ? 'selected' : ''; ?>>
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
                    <option value="<?php echo htmlspecialchars($w['ward']); ?>" <?php echo $filterWard === $w['ward'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['ward']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply Filter</button>
            <a href="reports.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="stat-cards">
    <div class="stat-card blue">
        <div class="stat-number"><?php echo $totalAssignments; ?></div>
        <div class="stat-label">Total Assignments</div>
        <div class="stat-icon"><i class="fas fa-list"></i></div>
    </div>
    <div class="stat-card green">
        <div class="stat-number"><?php echo $statusCounts['Completed']; ?></div>
        <div class="stat-label">Completed</div>
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-number"><?php echo $statusCounts['In Progress']; ?></div>
        <div class="stat-label">In Progress</div>
        <div class="stat-icon"><i class="fas fa-spinner"></i></div>
    </div>
    <div class="stat-card red">
        <div class="stat-number"><?php echo $statusCounts['Pending']; ?></div>
        <div class="stat-label">Pending</div>
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
    </div>
</div>

<!-- Charts Row -->
<div class="charts-grid">
    <div class="card-box">
        <h5><i class="fas fa-chart-pie"></i> Task Status Distribution</h5>
        <div class="chart-container">
            <canvas id="statusPie"></canvas>
        </div>
    </div>
    <div class="card-box">
        <h5><i class="fas fa-chart-bar"></i> Tasks by Campaign Type</h5>
        <div class="chart-container">
            <canvas id="campaignBar"></canvas>
        </div>
    </div>
</div>

<div class="charts-grid">
    <!-- Tasks Per Area -->
    <div class="card-box">
        <h5><i class="fas fa-map"></i> Tasks Per Area</h5>
        <?php if ($tasksPerArea->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Ward</th><th>Booth</th><th>Tasks</th></tr>
                </thead>
                <tbody>
                    <?php while ($row = $tasksPerArea->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['ward'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['booth'] ?: '-'); ?></td>
                        <td><strong><?php echo $row['cnt']; ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php
            $areaBaseUrl = strtok($_SERVER['REQUEST_URI'], '?') . '?';
            $qp = $_GET;
            unset($qp['apage']);
            if (!empty($qp)) $areaBaseUrl .= http_build_query($qp) . '&';
            $areaBaseUrl .= 'apage=';
            renderPagination($areaCurrentPage, $areaTotalPages, $areaTotalRecords, $areaPerPage, $areaBaseUrl);
        ?>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-inbox"></i>No data available</div>
        <?php endif; ?>
    </div>

    <!-- Top 5 Workers -->
    <div class="card-box">
        <h5><i class="fas fa-trophy"></i> Top 5 Most Active Workers</h5>
        <?php if ($topWorkers->num_rows > 0): ?>
            <?php $rank = 1; while ($w = $topWorkers->fetch_assoc()): ?>
            <div class="top-worker-item">
                <div class="rank"><?php echo $rank++; ?></div>
                <div class="worker-info">
                    <div class="name"><?php echo htmlspecialchars($w['name']); ?></div>
                    <div class="area"><?php echo htmlspecialchars(implode(' / ', array_filter([$w['constituency'], $w['ward'], $w['booth']]))); ?></div>
                </div>
                <div class="count"><?php echo $w['completed_count']; ?></div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-inbox"></i>No completed tasks yet</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Status Pie Chart
new Chart(document.getElementById('statusPie').getContext('2d'), {
    type: 'pie',
    data: {
        labels: ['Pending', 'In Progress', 'Completed'],
        datasets: [{
            data: [<?php echo $statusCounts['Pending']; ?>, <?php echo $statusCounts['In Progress']; ?>, <?php echo $statusCounts['Completed']; ?>],
            backgroundColor: ['#ffc107', '#17a2b8', '#28a745'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Campaign Type Bar Chart
<?php
$campaignLabels = [];
$campaignValues = [];
while ($row = $campaignResult->fetch_assoc()) {
    $campaignLabels[] = $row['campaign_type'];
    $campaignValues[] = (int)$row['cnt'];
}
?>
new Chart(document.getElementById('campaignBar').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($campaignLabels); ?>,
        datasets: [{
            label: 'Tasks',
            data: <?php echo json_encode($campaignValues); ?>,
            backgroundColor: ['#FF6B00', '#17a2b8', '#28a745', '#ffc107', '#dc3545', '#6f42c1'],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
