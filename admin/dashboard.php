<?php
$pageTitle = "Dashboard";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/gamification.php';
requireAdmin();

// Total workers
$totalWorkers = $conn->query("SELECT COUNT(*) as c FROM workers WHERE status='Active'")->fetch_assoc()['c'];

// Total tasks
$totalTasks = $conn->query("SELECT COUNT(*) as c FROM tasks")->fetch_assoc()['c'];

// Completed tasks
$completedTasks = $conn->query("SELECT COUNT(*) as c FROM task_assignments WHERE status='Completed'")->fetch_assoc()['c'];

// Pending tasks
$pendingTasks = $conn->query("SELECT COUNT(*) as c FROM task_assignments WHERE status='Pending'")->fetch_assoc()['c'];

// In Progress tasks
$inProgressTasks = $conn->query("SELECT COUNT(*) as c FROM task_assignments WHERE status='In Progress'")->fetch_assoc()['c'];

// Tasks per area (booth)
$tasksPerArea = $conn->query("SELECT booth, COUNT(*) as cnt FROM tasks WHERE booth IS NOT NULL AND booth != '' GROUP BY booth ORDER BY cnt DESC LIMIT 10");

// Top 5 workers by completed tasks
$topWorkers = $conn->query("
    SELECT w.name, w.constituency, w.ward, w.booth, COUNT(ta.id) as completed_count
    FROM task_assignments ta
    JOIN workers w ON ta.worker_id = w.id
    WHERE ta.status = 'Completed'
    GROUP BY ta.worker_id
    ORDER BY completed_count DESC
    LIMIT 5
");

// Data for charts - tasks by status
$statusData = [
    'Pending' => (int) $pendingTasks,
    'In Progress' => (int) $inProgressTasks,
    'Completed' => (int) $completedTasks
];

// Tasks by constituency
$tasksByConstituency = $conn->query("SELECT constituency, COUNT(*) as cnt FROM tasks WHERE constituency IS NOT NULL AND constituency != '' GROUP BY constituency ORDER BY cnt DESC");



include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Stat Cards -->
<div class="stat-cards">
    <div class="stat-card orange">
        <div class="stat-number"><?php echo $totalWorkers; ?></div>
        <div class="stat-label">Active Workers</div>
        <div class="stat-icon"><i class="fas fa-users"></i></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-number"><?php echo $totalTasks; ?></div>
        <div class="stat-label">Total Tasks</div>
        <div class="stat-icon"><i class="fas fa-tasks"></i></div>
    </div>
    <div class="stat-card green">
        <div class="stat-number"><?php echo $completedTasks; ?></div>
        <div class="stat-label">Completed</div>
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
    </div>
    <div class="stat-card red">
        <div class="stat-number"><?php echo $pendingTasks + $inProgressTasks; ?></div>
        <div class="stat-label">Pending / In Progress</div>
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
    </div>
</div>

<!-- Charts -->
<div class="charts-grid">
    <div class="card-box">
        <h5><i class="fas fa-chart-pie"></i> Task Status Overview</h5>
        <div class="chart-container">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
    <div class="card-box">
        <h5><i class="fas fa-chart-bar"></i> Tasks by Constituency</h5>
        <div class="chart-container">
            <canvas id="constituencyChart"></canvas>
        </div>
    </div>
</div>

<div class="charts-grid">
    <!-- Tasks Per Area Table -->
    <div class="card-box">
        <h5><i class="fas fa-map-marker-alt"></i> Tasks Per Booth</h5>
        <?php if ($tasksPerArea->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Booth</th>
                            <th>Tasks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $tasksPerArea->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['booth']); ?></td>
                                <td><strong><?php echo $row['cnt']; ?></strong></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-inbox"></i>No tasks found</div>
        <?php endif; ?>
    </div>

    <!-- Top 5 Workers (Gamified) -->
    <div class="card-box">
        <h5><i class="fas fa-trophy"></i> Top Performers <a href="<?php echo BASE_URL; ?>/admin/leaderboard.php" style="font-size:12px;margin-left:auto;color:var(--accent);">View Full Leaderboard &rarr;</a></h5>
        <?php
        $topGam = getLeaderboard('', '', 5);
        if (!empty($topGam)):
            foreach ($topGam as $idx => $tw):
                $twLevel = $tw['level'];
        ?>
            <div class="top-worker-item">
                <div class="rank"><?php echo $idx + 1; ?></div>
                <div class="worker-info">
                    <div class="name">
                        <?php echo htmlspecialchars($tw['name']); ?>
                        <span class="level-badge" style="--level-color:<?php echo $twLevel['color']; ?>; margin-left:6px;">
                            <i class="fas <?php echo $twLevel['icon']; ?>"></i> <?php echo $twLevel['name']; ?>
                        </span>
                    </div>
                    <div class="area">
                        <?php echo htmlspecialchars(implode(' / ', array_filter([$tw['constituency'], $tw['ward'], $tw['booth']]))); ?>
                    </div>
                </div>
                <div class="count"><?php echo number_format($tw['total_points']); ?> <small style="font-size:11px;color:var(--text-muted);">pts</small></div>
            </div>
        <?php endforeach; else: ?>
            <div class="empty-state"><i class="fas fa-inbox"></i>No completed tasks yet</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Status Pie Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_keys($statusData)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($statusData)); ?>,
                backgroundColor: ['#e8702a', '#0284c7', '#059669'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Constituency Bar Chart
    <?php
    $constLabels = [];
    $constValues = [];
    if ($tasksByConstituency) {
        while ($row = $tasksByConstituency->fetch_assoc()) {
            $constLabels[] = $row['constituency'];
            $constValues[] = (int) $row['cnt'];
        }
    }
    ?>
    const constCtx = document.getElementById('constituencyChart').getContext('2d');
    new Chart(constCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($constLabels); ?>,
            datasets: [{
                label: 'Tasks',
                data: <?php echo json_encode($constValues); ?>,
                backgroundColor: 'rgba(232, 112, 42, 0.85)',
                hoverBackgroundColor: '#e8702a',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>