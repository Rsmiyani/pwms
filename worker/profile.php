<?php
$pageTitle = "My Profile";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/gamification.php';
requireLogin();

$userId = $_SESSION['user_id'];
$workerStmt = $conn->prepare("SELECT * FROM workers WHERE user_id = ?");
$workerStmt->bind_param("i", $userId);
$workerStmt->execute();
$worker = $workerStmt->get_result()->fetch_assoc();
$workerStmt->close();

if (!$worker) {
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/sidebar.php';
    echo '<div class="card-box"><div class="empty-state"><i class="fas fa-info-circle"></i>No worker profile linked to your account.</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$workerId = $worker['id'];
$totalPoints = getWorkerTotalPoints($workerId);
$level = getWorkerLevel($totalPoints);
$earnedBadges = getWorkerBadges($workerId);
$badgeDefs = getBadgeDefinitions();

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM task_assignments WHERE worker_id = ? AND status = 'Completed'");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$completedTasks = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM task_assignments WHERE worker_id = ?");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$totalAssigned = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM worker_checkins WHERE worker_id = ?");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$totalCheckins = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM task_proofs tp JOIN task_assignments ta ON tp.assignment_id = ta.id WHERE ta.worker_id = ?");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$totalProofs = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Progress to next level
$progressPercent = 0;
if ($level['next'] !== null) {
    $range = $level['next'] - $level['min'];
    $current = $totalPoints - $level['min'];
    $progressPercent = $range > 0 ? min(100, round(($current / $range) * 100)) : 0;
}

// Recent points activity
$stmt = $conn->prepare("SELECT points, reason, created_at FROM worker_points WHERE worker_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$recentPoints = $stmt->get_result();
$stmt->close();

// Leaderboard rank
$rankResult = $conn->query("SELECT w.id, COALESCE(SUM(wp.points), 0) as total FROM workers w LEFT JOIN worker_points wp ON w.id = wp.worker_id WHERE w.status = 'Active' GROUP BY w.id ORDER BY total DESC");
$rank = 0;
$totalActive = 0;
while ($r = $rankResult->fetch_assoc()) {
    $totalActive++;
    if ((int)$r['id'] === $workerId) $rank = $totalActive;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-user-circle"></i> My Profile</h4>
</div>

<!-- Profile Hero -->
<div class="profile-hero">
    <div class="profile-avatar-lg">
        <?php echo strtoupper(substr($worker['name'], 0, 1)); ?>
    </div>
    <div class="profile-hero-info">
        <h2><?php echo htmlspecialchars($worker['name']); ?></h2>
        <p class="profile-meta">
            <?php echo htmlspecialchars($worker['role'] ?? 'Volunteer'); ?>
            <?php if ($worker['party_position']): ?>
                &middot; <?php echo htmlspecialchars($worker['party_position']); ?>
            <?php endif; ?>
        </p>
        <p class="profile-area">
            <i class="fas fa-map-marker-alt"></i>
            <?php echo htmlspecialchars(implode(' / ', array_filter([$worker['constituency'], $worker['ward'], $worker['booth']]))); ?>
        </p>
    </div>
    <div class="profile-hero-level">
        <div class="level-display" style="--level-color: <?php echo $level['color']; ?>">
            <i class="fas <?php echo $level['icon']; ?>"></i>
            <span class="level-name"><?php echo $level['name']; ?></span>
        </div>
        <div class="profile-rank">Rank #<?php echo $rank; ?> of <?php echo $totalActive; ?></div>
    </div>
</div>

<!-- Points & Progress -->
<div class="gamification-stats">
    <div class="gam-stat-card points-card">
        <div class="gam-stat-icon"><i class="fas fa-bolt"></i></div>
        <div class="gam-stat-value"><?php echo number_format($totalPoints); ?></div>
        <div class="gam-stat-label">Total Points</div>
    </div>
    <div class="gam-stat-card">
        <div class="gam-stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="gam-stat-value"><?php echo $completedTasks; ?></div>
        <div class="gam-stat-label">Tasks Done</div>
    </div>
    <div class="gam-stat-card">
        <div class="gam-stat-icon"><i class="fas fa-map-pin"></i></div>
        <div class="gam-stat-value"><?php echo $totalCheckins; ?></div>
        <div class="gam-stat-label">Check-ins</div>
    </div>
    <div class="gam-stat-card">
        <div class="gam-stat-icon"><i class="fas fa-award"></i></div>
        <div class="gam-stat-value"><?php echo count($earnedBadges); ?></div>
        <div class="gam-stat-label">Badges</div>
    </div>
</div>

<!-- Level Progress -->
<div class="card-box">
    <h5><i class="fas fa-chart-line"></i> Level Progress</h5>
    <div class="level-progress-bar">
        <div class="level-progress-labels">
            <span style="color: <?php echo $level['color']; ?>; font-weight: 600;">
                <i class="fas <?php echo $level['icon']; ?>"></i> <?php echo $level['name']; ?>
            </span>
            <?php if ($level['next'] !== null): ?>
                <span class="text-muted"><?php echo number_format($totalPoints); ?> / <?php echo number_format($level['next']); ?> pts</span>
            <?php else: ?>
                <span style="color: var(--accent); font-weight: 600;">MAX LEVEL</span>
            <?php endif; ?>
        </div>
        <div class="progress-track">
            <div class="progress-fill" style="width: <?php echo $level['next'] !== null ? $progressPercent : 100; ?>%; background: <?php echo $level['color']; ?>">
            </div>
        </div>
        <?php if ($level['next'] !== null): ?>
            <div class="level-progress-hint">
                <?php echo number_format($level['next'] - $totalPoints); ?> more points to reach next level
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Badges -->
<div class="card-box">
    <h5><i class="fas fa-award"></i> Badges (<?php echo count($earnedBadges); ?> / <?php echo count($badgeDefs); ?>)</h5>
    <div class="badges-grid">
        <?php foreach ($badgeDefs as $key => $badge):
            $earned = isset($earnedBadges[$key]);
        ?>
        <div class="badge-card <?php echo $earned ? 'earned' : 'locked'; ?>">
            <div class="badge-card-icon" style="<?php echo $earned ? 'background:' . $badge['color'] . '15; color:' . $badge['color'] : ''; ?>">
                <i class="fas <?php echo $badge['icon']; ?>"></i>
            </div>
            <div class="badge-card-name"><?php echo htmlspecialchars($badge['name']); ?></div>
            <div class="badge-card-desc"><?php echo htmlspecialchars($badge['desc']); ?></div>
            <?php if ($earned): ?>
                <div class="badge-card-date">
                    <i class="fas fa-check-circle"></i> <?php echo date('d M Y', strtotime($earnedBadges[$key])); ?>
                </div>
            <?php else: ?>
                <div class="badge-card-locked"><i class="fas fa-lock"></i> Locked</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Recent Activity -->
<div class="card-box">
    <h5><i class="fas fa-history"></i> Recent Points Activity</h5>
    <?php if ($recentPoints->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Points</th>
                    <th>Reason</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($p = $recentPoints->fetch_assoc()): ?>
                <tr>
                    <td><span class="points-tag">+<?php echo $p['points']; ?></span></td>
                    <td><?php echo htmlspecialchars($p['reason']); ?></td>
                    <td style="color:var(--text-secondary);font-size:12px;"><?php echo date('d M Y, h:i A', strtotime($p['created_at'])); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-bolt"></i>No points earned yet. Complete tasks to earn!</div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
