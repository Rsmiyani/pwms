<?php
$pageTitle = "Performance Report";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/gamification.php';
requireAdmin();

// Date range defaults to last 7 days — validate format to prevent injection
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
// Ensure dates are valid Y-m-d format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-d', strtotime('-7 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = date('Y-m-d');
$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';

$generating = isset($_GET['generate']);

// Get booth performance data
$boothData = getBoothPerformanceData($conn, $startDateTime, $endDateTime);
$boothRows = [];
while ($r = $boothData->fetch_assoc()) $boothRows[] = $r;

// Get top performers
$topPerformers = getTopPerformers($conn, $startDateTime, $endDateTime, 10);
$topRows = [];
while ($r = $topPerformers->fetch_assoc()) $topRows[] = $r;

// Overall stats for the period (parameterized)
$osStmt = $conn->prepare("SELECT
    (SELECT COUNT(*) FROM task_assignments WHERE updated_at BETWEEN ? AND ?) as total_tasks,
    (SELECT COUNT(*) FROM task_assignments WHERE status='Completed' AND completed_at BETWEEN ? AND ?) as completed_tasks,
    (SELECT COUNT(*) FROM worker_checkins WHERE created_at BETWEEN ? AND ?) as total_checkins,
    (SELECT COUNT(DISTINCT worker_id) FROM worker_checkins WHERE created_at BETWEEN ? AND ?) as active_workers,
    (SELECT COALESCE(SUM(points),0) FROM worker_points WHERE created_at BETWEEN ? AND ?) as total_points
");
$osStmt->bind_param("ssssssssss", $startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime);
$osStmt->execute();
$overallStats = $osStmt->get_result()->fetch_assoc();
$osStmt->close();

// Save report if generating
if ($generating) {
    ob_start();
    include __DIR__ . '/report_template.php';
    $reportHtml = ob_get_clean();

    $title = "Performance Report: $startDate to $endDate";
    $genBy = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO performance_reports (report_title, start_date, end_date, report_html, generated_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $title, $startDate, $endDate, $reportHtml, $genBy);
    $stmt->execute();
    $reportId = $conn->insert_id;
    $stmt->close();

    setFlash('success', 'Report generated and saved! <a href="report_view.php?id=' . $reportId . '" style="color:inherit;text-decoration:underline;font-weight:700;">View Report</a>');
    header("Location: performance_report.php?start_date=$startDate&end_date=$endDate");
    exit;
}

// Fetch saved reports
$savedReports = $conn->query("SELECT pr.*, u.name as generated_by_name FROM performance_reports pr LEFT JOIN users u ON u.id = pr.generated_by ORDER BY pr.created_at DESC LIMIT 20");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-file-alt"></i> Automated Performance Reports</h4>
</div>

<!-- Date Range Selector -->
<div class="card-box">
    <h5><i class="fas fa-calendar-alt"></i> Select Report Period</h5>
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
        </div>
        <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
        </div>
        <div class="form-group" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-eye"></i> Preview</button>
            <button type="submit" name="generate" value="1" class="btn btn-success"><i class="fas fa-file-pdf"></i> Generate & Save</button>
        </div>
    </form>
    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="?start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline">Last 7 Days</a>
        <a href="?start_date=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline">Last 30 Days</a>
        <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline">This Month</a>
    </div>
</div>

<!-- Report Preview -->
<div class="card-box">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <h5><i class="fas fa-chart-line"></i> Report Preview: <?php echo $startDate; ?> to <?php echo $endDate; ?></h5>
        <button onclick="window.print()" class="btn btn-sm btn-outline" title="Print / Save as PDF">
            <i class="fas fa-print"></i> Print PDF
        </button>
    </div>

    <!-- Overall Stats -->
    <div class="report-stats">
        <div class="report-stat">
            <i class="fas fa-clipboard-check" style="color:var(--primary);"></i>
            <div class="report-stat-num"><?php echo (int)$overallStats['total_tasks']; ?></div>
            <div class="report-stat-label">Tasks Assigned</div>
        </div>
        <div class="report-stat">
            <i class="fas fa-check-double" style="color:#198754;"></i>
            <div class="report-stat-num"><?php echo (int)$overallStats['completed_tasks']; ?></div>
            <div class="report-stat-label">Tasks Completed</div>
        </div>
        <div class="report-stat">
            <i class="fas fa-map-pin" style="color:var(--accent);"></i>
            <div class="report-stat-num"><?php echo (int)$overallStats['total_checkins']; ?></div>
            <div class="report-stat-label">Check-ins</div>
        </div>
        <div class="report-stat">
            <i class="fas fa-users" style="color:#7c3aed;"></i>
            <div class="report-stat-num"><?php echo (int)$overallStats['active_workers']; ?></div>
            <div class="report-stat-label">Active Workers</div>
        </div>
        <div class="report-stat">
            <i class="fas fa-star" style="color:#d97706;"></i>
            <div class="report-stat-num"><?php echo number_format((int)$overallStats['total_points']); ?></div>
            <div class="report-stat-label">Points Earned</div>
        </div>
    </div>

    <!-- Booth Performance -->
    <h6 style="margin:24px 0 12px;font-weight:700;"><i class="fas fa-building"></i> Booth-Level Performance</h6>
    <?php if (!empty($boothRows)): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Booth</th>
                    <th>Constituency</th>
                    <th>Ward</th>
                    <th>Workers</th>
                    <th>Tasks</th>
                    <th>Completed</th>
                    <th>Rate</th>
                    <th>Check-ins</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($boothRows as $b):
                    $rate = $b['total_tasks'] > 0 ? round($b['completed_tasks'] / $b['total_tasks'] * 100) : 0;
                    $rateColor = $rate >= 80 ? '#198754' : ($rate >= 50 ? '#e67e22' : '#dc3545');
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($b['booth']); ?></strong></td>
                    <td><?php echo htmlspecialchars($b['constituency']); ?></td>
                    <td><?php echo htmlspecialchars($b['ward']); ?></td>
                    <td><?php echo $b['worker_count']; ?></td>
                    <td><?php echo $b['total_tasks']; ?></td>
                    <td><?php echo $b['completed_tasks']; ?></td>
                    <td><span style="color:<?php echo $rateColor; ?>;font-weight:700;"><?php echo $rate; ?>%</span></td>
                    <td><?php echo $b['checkin_count']; ?></td>
                    <td><?php echo number_format($b['total_points']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="max-width:700px;margin:20px auto;">
        <canvas id="boothChart" height="300"></canvas>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-building"></i>No booth activity in this period.</div>
    <?php endif; ?>

    <!-- Top Performers -->
    <h6 style="margin:24px 0 12px;font-weight:700;"><i class="fas fa-trophy"></i> Top Performers</h6>
    <?php if (!empty($topRows)): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Worker</th>
                    <th>Booth</th>
                    <th>Tasks Done</th>
                    <th>Check-ins</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($topRows as $tp): ?>
                <tr>
                    <td>
                        <?php if ($i <= 3): ?>
                            <span class="rank-badge rank-<?php echo $i; ?>"><?php echo $i; ?></span>
                        <?php else: echo $i; endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($tp['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($tp['booth'] ?? '-'); ?></td>
                    <td><?php echo $tp['tasks_done']; ?></td>
                    <td><?php echo $tp['checkins']; ?></td>
                    <td><strong><?php echo number_format($tp['points']); ?></strong></td>
                </tr>
                <?php $i++; endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-trophy"></i>No worker activity in this period.</div>
    <?php endif; ?>
</div>

<!-- Saved Reports -->
<div class="card-box">
    <h5><i class="fas fa-archive"></i> Saved Reports</h5>
    <?php if ($savedReports->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Period</th>
                    <th>Generated By</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($rp = $savedReports->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($rp['report_title']); ?></td>
                    <td><?php echo $rp['start_date']; ?> to <?php echo $rp['end_date']; ?></td>
                    <td><?php echo htmlspecialchars($rp['generated_by_name'] ?? '-'); ?></td>
                    <td><?php echo date('d M Y, h:i A', strtotime($rp['created_at'])); ?></td>
                    <td>
                        <a href="report_view.php?id=<?php echo $rp['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-archive"></i>No reports generated yet. Select a date range and click "Generate & Save".</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if (!empty($boothRows)): ?>
var ctx = document.getElementById('boothChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($boothRows, 'booth')); ?>,
        datasets: [
            {
                label: 'Completed Tasks',
                data: <?php echo json_encode(array_map('intval', array_column($boothRows, 'completed_tasks'))); ?>,
                backgroundColor: '#0d4f4f'
            },
            {
                label: 'Check-ins',
                data: <?php echo json_encode(array_map('intval', array_column($boothRows, 'checkin_count'))); ?>,
                backgroundColor: '#e67e22'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' }, title: { display: true, text: 'Booth Activity Comparison' } },
        scales: { y: { beginAtZero: true } }
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
