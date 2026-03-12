<?php
$pageTitle = "Sentiment Analysis";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/analytics.php';
requireAdmin();

// Get overview
$overview = getSentimentOverview($conn);

// Get all feedback with optional ward filter
$wardFilter = trim($_GET['ward'] ?? '');
$fbWhere = "";
$fbParams = [];
$fbTypes = "";
if ($wardFilter) {
    $fbWhere = "WHERE f.ward = ?";
    $fbParams[] = $wardFilter;
    $fbTypes = "s";
}

$fbSql = "SELECT f.*, w.name as worker_name
          FROM voter_feedback f
          LEFT JOIN workers w ON w.id = f.worker_id
          $fbWhere
          ORDER BY f.created_at DESC LIMIT 100";
$fbStmt = $conn->prepare($fbSql);
if ($fbTypes) $fbStmt->bind_param($fbTypes, ...$fbParams);
$fbStmt->execute();
$allFeedback = $fbStmt->get_result();

// Totals
$totals = $conn->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN sentiment='Positive' THEN 1 ELSE 0 END) as pos,
    SUM(CASE WHEN sentiment='Neutral' THEN 1 ELSE 0 END) as neu,
    SUM(CASE WHEN sentiment='Negative' THEN 1 ELSE 0 END) as neg,
    AVG(sentiment_score) as avg_score
    FROM voter_feedback")->fetch_assoc();

// Get wards for filter
$wards = $conn->query("SELECT DISTINCT ward FROM voter_feedback ORDER BY ward");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-brain"></i> AI Sentiment Analysis</h4>
</div>

<!-- Summary Cards -->
<div class="analytics-cards">
    <div class="analytics-card">
        <div class="analytics-card-icon" style="background:#e8f4fd;color:#0d6efd;">
            <i class="fas fa-comments"></i>
        </div>
        <div class="analytics-card-data">
            <span class="analytics-card-number"><?php echo (int)$totals['total']; ?></span>
            <span class="analytics-card-label">Total Feedback</span>
        </div>
    </div>
    <div class="analytics-card">
        <div class="analytics-card-icon" style="background:#d1e7dd;color:#198754;">
            <i class="fas fa-smile"></i>
        </div>
        <div class="analytics-card-data">
            <span class="analytics-card-number"><?php echo (int)$totals['pos']; ?></span>
            <span class="analytics-card-label">Positive</span>
        </div>
    </div>
    <div class="analytics-card">
        <div class="analytics-card-icon" style="background:#fff3cd;color:#856404;">
            <i class="fas fa-meh"></i>
        </div>
        <div class="analytics-card-data">
            <span class="analytics-card-number"><?php echo (int)$totals['neu']; ?></span>
            <span class="analytics-card-label">Neutral</span>
        </div>
    </div>
    <div class="analytics-card">
        <div class="analytics-card-icon" style="background:#f8d7da;color:#842029;">
            <i class="fas fa-frown"></i>
        </div>
        <div class="analytics-card-data">
            <span class="analytics-card-number"><?php echo (int)$totals['neg']; ?></span>
            <span class="analytics-card-label">Negative</span>
        </div>
    </div>
</div>

<!-- Ward Breakdown -->
<div class="card-box">
    <h5><i class="fas fa-chart-bar"></i> Sentiment by Ward</h5>
    <?php
    $overviewData = [];
    if ($overview->num_rows > 0):
        while ($r = $overview->fetch_assoc()) $overviewData[] = $r;
    ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ward</th>
                    <th>Total</th>
                    <th>Positive</th>
                    <th>Neutral</th>
                    <th>Negative</th>
                    <th>Avg Score</th>
                    <th>Sentiment Bar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overviewData as $o): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($o['ward']); ?></strong></td>
                    <td><?php echo $o['total_feedback']; ?></td>
                    <td><span style="color:#198754;font-weight:600;"><?php echo $o['positive']; ?></span></td>
                    <td><span style="color:#856404;font-weight:600;"><?php echo $o['neutral']; ?></span></td>
                    <td><span style="color:#dc3545;font-weight:600;"><?php echo $o['negative']; ?></span></td>
                    <td>
                        <?php
                        $sc = round($o['avg_score'], 2);
                        $scColor = $sc > 0.15 ? '#198754' : ($sc < -0.15 ? '#dc3545' : '#856404');
                        ?>
                        <span style="color:<?php echo $scColor; ?>;font-weight:700;"><?php echo $sc; ?></span>
                    </td>
                    <td style="min-width:180px;">
                        <?php
                        $total = max(1, $o['total_feedback']);
                        $pPct = round($o['positive'] / $total * 100);
                        $nePct = round($o['neutral'] / $total * 100);
                        $ngPct = 100 - $pPct - $nePct;
                        ?>
                        <div class="sentiment-bar">
                            <div class="sentiment-bar-pos" style="width:<?php echo $pPct; ?>%;" title="<?php echo $pPct; ?>% Positive"></div>
                            <div class="sentiment-bar-neu" style="width:<?php echo $nePct; ?>%;" title="<?php echo $nePct; ?>% Neutral"></div>
                            <div class="sentiment-bar-neg" style="width:<?php echo $ngPct; ?>%;" title="<?php echo $ngPct; ?>% Negative"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Chart -->
    <div style="max-width:600px;margin:24px auto;">
        <canvas id="sentimentChart" height="280"></canvas>
    </div>

    <?php else: ?>
        <div class="empty-state"><i class="fas fa-chart-bar"></i>No feedback data yet. Workers need to collect voter feedback first.</div>
    <?php endif; ?>
</div>

<!-- All Feedback -->
<div class="card-box">
    <h5><i class="fas fa-list-ul"></i> Recent Feedback</h5>
    <form method="GET" class="filter-bar" style="margin-bottom:16px;">
        <div class="form-group">
            <label>Filter by Ward</label>
            <select name="ward" class="form-control" onchange="this.form.submit()">
                <option value="">All Wards</option>
                <?php while ($wr = $wards->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($wr['ward']); ?>" <?php echo $wardFilter === $wr['ward'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($wr['ward']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
    </form>

    <?php if ($allFeedback->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ward</th>
                    <th>Voter</th>
                    <th>Feedback</th>
                    <th>Sentiment</th>
                    <th>Score</th>
                    <th>Collected By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($f = $allFeedback->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($f['ward']); ?></td>
                    <td><?php echo htmlspecialchars($f['voter_name'] ?: 'Anonymous'); ?></td>
                    <td style="max-width:250px;white-space:normal;"><?php echo htmlspecialchars(mb_strimwidth($f['feedback_text'], 0, 100, '...')); ?></td>
                    <td>
                        <?php
                        $sentColors = ['Positive' => 'badge-active', 'Negative' => 'badge-inactive', 'Neutral' => 'badge-pending'];
                        $sentIcons = ['Positive' => 'fa-smile', 'Negative' => 'fa-frown', 'Neutral' => 'fa-meh'];
                        ?>
                        <span class="badge <?php echo $sentColors[$f['sentiment']] ?? 'badge-pending'; ?>">
                            <i class="fas <?php echo $sentIcons[$f['sentiment']] ?? 'fa-meh'; ?>"></i>
                            <?php echo $f['sentiment']; ?>
                        </span>
                    </td>
                    <td><?php echo $f['sentiment_score']; ?></td>
                    <td><?php echo htmlspecialchars($f['worker_name'] ?? '-'); ?></td>
                    <td><?php echo date('d M, h:i A', strtotime($f['created_at'])); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-comments"></i>No feedback entries found.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if (!empty($overviewData)): ?>
var ctx = document.getElementById('sentimentChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($overviewData, 'ward')); ?>,
        datasets: [
            {
                label: 'Positive',
                data: <?php echo json_encode(array_map('intval', array_column($overviewData, 'positive'))); ?>,
                backgroundColor: '#198754'
            },
            {
                label: 'Neutral',
                data: <?php echo json_encode(array_map('intval', array_column($overviewData, 'neutral'))); ?>,
                backgroundColor: '#ffc107'
            },
            {
                label: 'Negative',
                data: <?php echo json_encode(array_map('intval', array_column($overviewData, 'negative'))); ?>,
                backgroundColor: '#dc3545'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' }, title: { display: true, text: 'Sentiment Distribution by Ward' } },
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
