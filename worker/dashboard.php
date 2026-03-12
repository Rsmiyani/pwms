<?php
$pageTitle = "Dashboard";
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

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM task_assignments WHERE worker_id = ? AND status = 'Pending'");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$pendingTasks = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM task_assignments WHERE worker_id = ? AND status = 'In Progress'");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$inProgressTasks = (int)$stmt->get_result()->fetch_assoc()['c'];
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

$completionRate = $totalAssigned > 0 ? round(($completedTasks / $totalAssigned) * 100) : 0;

// Progress to next level
$progressPercent = 0;
if ($level['next'] !== null) {
    $range = $level['next'] - $level['min'];
    $current = $totalPoints - $level['min'];
    $progressPercent = $range > 0 ? min(100, round(($current / $range) * 100)) : 0;
}

// Leaderboard rank
$rankResult = $conn->query("SELECT w.id, COALESCE(SUM(wp.points), 0) as total FROM workers w LEFT JOIN worker_points wp ON w.id = wp.worker_id WHERE w.status = 'Active' GROUP BY w.id ORDER BY total DESC");
$rank = 0; $totalActive = 0;
while ($r = $rankResult->fetch_assoc()) {
    $totalActive++;
    if ((int)$r['id'] === $workerId) $rank = $totalActive;
}

// Recent tasks (last 5)
$stmt = $conn->prepare("SELECT ta.status, ta.completed_at, t.title, t.priority, t.due_date
    FROM task_assignments ta JOIN tasks t ON ta.task_id = t.id
    WHERE ta.worker_id = ? ORDER BY ta.updated_at DESC LIMIT 5");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$recentTasks = $stmt->get_result();
$stmt->close();

// Recent points (last 5)
$stmt = $conn->prepare("SELECT points, reason, created_at FROM worker_points WHERE worker_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$recentPoints = $stmt->get_result();
$stmt->close();

// Recent check-ins (last 3)
$stmt = $conn->prepare("SELECT type, location_name, created_at FROM worker_checkins WHERE worker_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$recentCheckins = $stmt->get_result();
$stmt->close();

$extraCSS = '
<style>
/* ── Worker Dashboard ── */
.wd-hero {
    display:flex; align-items:center; gap:20px; flex-wrap:wrap;
    background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 50%, var(--primary-deeper) 100%);
    border-radius:var(--radius-lg); padding:28px 32px; color:#fff; margin-bottom:20px;
    position:relative; overflow:hidden;
}
.wd-hero::before {
    content:""; position:absolute; top:-40px; right:-40px;
    width:200px; height:200px; border-radius:50%;
    background:rgba(255,255,255,.05);
}
.wd-hero::after {
    content:""; position:absolute; bottom:-60px; right:60px;
    width:160px; height:160px; border-radius:50%;
    background:rgba(255,255,255,.04);
}
.wd-avatar {
    width:68px; height:68px; border-radius:50%;
    background:rgba(255,255,255,.15); border:3px solid rgba(255,255,255,.3);
    display:flex; align-items:center; justify-content:center;
    font-size:28px; font-weight:800; flex-shrink:0;
}
.wd-hero-info { flex:1; min-width:0; }
.wd-hero-name { font-size:22px; font-weight:800; margin-bottom:4px; }
.wd-hero-meta { font-size:13px; opacity:.8; }
.wd-hero-meta i { margin-right:4px; }
.wd-hero-right { text-align:center; position:relative; z-index:1; }
.wd-level-circle {
    width:80px; height:80px; border-radius:50%;
    background:rgba(255,255,255,.12); border:3px solid rgba(255,255,255,.25);
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    margin:0 auto 6px;
}
.wd-level-circle i { font-size:24px; margin-bottom:2px; }
.wd-level-circle span { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
.wd-rank-tag {
    display:inline-flex; align-items:center; gap:4px;
    background:rgba(255,255,255,.15); padding:4px 12px; border-radius:20px;
    font-size:11px; font-weight:600;
}

.wd-stats {
    display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr));
    gap:14px; margin-bottom:20px;
}
.wd-stat {
    background:var(--white); border-radius:var(--radius-lg);
    border:1px solid var(--border); padding:20px;
    text-align:center; transition:all var(--transition);
}
.wd-stat:hover { transform:translateY(-3px); box-shadow:var(--shadow); }
.wd-stat-icon {
    width:44px; height:44px; border-radius:12px;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:18px; margin-bottom:10px;
}
.wd-stat-num { font-size:28px; font-weight:800; color:var(--text); line-height:1; }
.wd-stat-label { font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.4px; margin-top:4px; }

/* ── Completion Ring ── */
.wd-ring-wrap { text-align:center; padding:20px; }
.wd-ring { position:relative; width:120px; height:120px; margin:0 auto 12px; }
.wd-ring svg { transform:rotate(-90deg); }
.wd-ring-label {
    position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
    font-size:26px; font-weight:800; color:var(--text);
}
.wd-ring-sub { font-size:12px; color:var(--text-secondary); }

/* ── Level Progress Mini ── */
.wd-level-bar { margin-top:14px; }
.wd-level-bar-labels { display:flex; justify-content:space-between; font-size:11px; margin-bottom:6px; }
.wd-level-bar-track {
    height:10px; background:var(--bg-alt); border-radius:5px; overflow:hidden;
}
.wd-level-bar-fill {
    height:100%; border-radius:5px; transition:width .6s ease;
}

/* ── Recent Activity ── */
.wd-activity-item {
    display:flex; align-items:center; gap:12px;
    padding:12px 0; border-bottom:1px solid var(--border);
}
.wd-activity-item:last-child { border-bottom:none; }
.wd-activity-dot {
    width:36px; height:36px; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:14px; flex-shrink:0;
}
.wd-activity-info { flex:1; min-width:0; }
.wd-activity-title { font-size:13px; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wd-activity-sub { font-size:11px; color:var(--text-muted); }
.wd-activity-badge { font-size:12px; font-weight:700; flex-shrink:0; }

/* ── Badge Showcase ── */
.wd-badges-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
.wd-badge-chip {
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 14px; border-radius:20px;
    font-size:11px; font-weight:600;
    background:var(--bg); border:1px solid var(--border);
    transition:all var(--transition-fast);
}
.wd-badge-chip.earned { border-color:transparent; }
.wd-badge-chip.earned:hover { transform:translateY(-2px); box-shadow:var(--shadow-sm); }
.wd-badge-chip i { font-size:13px; }

.wd-two-col {
    display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;
}
@media (max-width:768px) {
    .wd-two-col { grid-template-columns:1fr; }
    .wd-stats { grid-template-columns:repeat(2,1fr); }
    .wd-hero { flex-direction:column; text-align:center; }
    .wd-hero-right { margin-top:10px; }
}
@media (max-width:480px) {
    .wd-stats { grid-template-columns:1fr; }
    .wd-hero { padding:20px 16px; }
    .wd-hero-name { font-size:18px; }
    .wd-avatar { width:52px; height:52px; font-size:22px; }
    .wd-stat { padding:14px; }
    .wd-stat-num { font-size:22px; }
}
</style>';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Hero Banner -->
<div class="wd-hero">
    <div class="wd-avatar"><?php echo strtoupper(substr($worker['name'], 0, 1)); ?></div>
    <div class="wd-hero-info">
        <div class="wd-hero-name"><?php echo htmlspecialchars($worker['name']); ?></div>
        <div class="wd-hero-meta">
            <i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($worker['role'] ?? 'Volunteer'); ?>
            <?php if ($worker['party_position']): ?>
                &nbsp;&middot;&nbsp; <?php echo htmlspecialchars($worker['party_position']); ?>
            <?php endif; ?>
        </div>
        <div class="wd-hero-meta" style="margin-top:4px;">
            <i class="fas fa-map-marker-alt"></i>
            <?php echo htmlspecialchars(implode(' / ', array_filter([$worker['constituency'], $worker['ward'], $worker['booth']]))); ?>
        </div>
    </div>
    <div class="wd-hero-right">
        <div class="wd-level-circle">
            <i class="fas <?php echo $level['icon']; ?>"></i>
            <span><?php echo $level['name']; ?></span>
        </div>
        <div class="wd-rank-tag"><i class="fas fa-trophy"></i> Rank #<?php echo $rank; ?> of <?php echo $totalActive; ?></div>
    </div>
</div>

<!-- Stats Grid -->
<div class="wd-stats">
    <div class="wd-stat">
        <div class="wd-stat-icon" style="background:var(--accent-light);color:var(--accent);"><i class="fas fa-bolt"></i></div>
        <div class="wd-stat-num"><?php echo number_format($totalPoints); ?></div>
        <div class="wd-stat-label">Total Points</div>
    </div>
    <div class="wd-stat">
        <div class="wd-stat-icon" style="background:var(--success-light);color:var(--success);"><i class="fas fa-check-circle"></i></div>
        <div class="wd-stat-num"><?php echo $completedTasks; ?></div>
        <div class="wd-stat-label">Tasks Done</div>
    </div>
    <div class="wd-stat">
        <div class="wd-stat-icon" style="background:var(--warning-light);color:var(--warning);"><i class="fas fa-clock"></i></div>
        <div class="wd-stat-num"><?php echo $pendingTasks; ?></div>
        <div class="wd-stat-label">Pending</div>
    </div>
    <div class="wd-stat">
        <div class="wd-stat-icon" style="background:var(--info-light);color:var(--info);"><i class="fas fa-spinner"></i></div>
        <div class="wd-stat-num"><?php echo $inProgressTasks; ?></div>
        <div class="wd-stat-label">In Progress</div>
    </div>
    <div class="wd-stat">
        <div class="wd-stat-icon" style="background:var(--success-light);color:var(--success);"><i class="fas fa-map-pin"></i></div>
        <div class="wd-stat-num"><?php echo $totalCheckins; ?></div>
        <div class="wd-stat-label">Check-ins</div>
    </div>
    <div class="wd-stat">
        <div class="wd-stat-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="fas fa-award"></i></div>
        <div class="wd-stat-num"><?php echo count($earnedBadges); ?></div>
        <div class="wd-stat-label">Badges</div>
    </div>
</div>

<!-- Two Column: Completion Ring + Level Progress -->
<div class="wd-two-col">
    <div class="card-box">
        <h5><i class="fas fa-bullseye"></i> Completion Rate</h5>
        <div class="wd-ring-wrap">
            <div class="wd-ring">
                <svg width="120" height="120" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="var(--bg-alt)" stroke-width="12"/>
                    <circle cx="60" cy="60" r="52" fill="none"
                        stroke="<?php echo $completionRate >= 75 ? 'var(--success)' : ($completionRate >= 40 ? 'var(--warning)' : 'var(--accent)'); ?>"
                        stroke-width="12" stroke-linecap="round"
                        stroke-dasharray="<?php echo round(326.7 * $completionRate / 100); ?> 326.7"/>
                </svg>
                <div class="wd-ring-label"><?php echo $completionRate; ?>%</div>
            </div>
            <div class="wd-ring-sub"><?php echo $completedTasks; ?> of <?php echo $totalAssigned; ?> tasks completed</div>
        </div>
    </div>
    <div class="card-box">
        <h5><i class="fas fa-chart-line"></i> Level Progress</h5>
        <div style="text-align:center;padding:20px 0 10px;">
            <div style="font-size:42px;color:<?php echo $level['color']; ?>;margin-bottom:6px;">
                <i class="fas <?php echo $level['icon']; ?>"></i>
            </div>
            <div style="font-size:18px;font-weight:800;color:var(--text);"><?php echo $level['name']; ?></div>
            <div style="font-size:13px;color:var(--text-secondary);margin-top:2px;"><?php echo number_format($totalPoints); ?> points</div>
        </div>
        <div class="wd-level-bar">
            <div class="wd-level-bar-labels">
                <span style="color:<?php echo $level['color']; ?>;font-weight:600;"><?php echo $level['name']; ?></span>
                <?php if ($level['next'] !== null): ?>
                    <span style="color:var(--text-muted);"><?php echo number_format($totalPoints); ?> / <?php echo number_format($level['next']); ?></span>
                <?php else: ?>
                    <span style="color:var(--accent);font-weight:600;">MAX LEVEL</span>
                <?php endif; ?>
            </div>
            <div class="wd-level-bar-track">
                <div class="wd-level-bar-fill" style="width:<?php echo $level['next'] !== null ? $progressPercent : 100; ?>%;background:<?php echo $level['color']; ?>;"></div>
            </div>
            <?php if ($level['next'] !== null): ?>
                <div style="font-size:11px;color:var(--text-muted);margin-top:6px;text-align:center;">
                    <?php echo number_format($level['next'] - $totalPoints); ?> more points to next level
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Badges Showcase -->
<div class="card-box">
    <h5><i class="fas fa-award"></i> Badges (<?php echo count($earnedBadges); ?> / <?php echo count($badgeDefs); ?>)</h5>
    <div class="wd-badges-row">
        <?php foreach ($badgeDefs as $key => $badge):
            $earned = isset($earnedBadges[$key]);
        ?>
        <span class="wd-badge-chip <?php echo $earned ? 'earned' : ''; ?>"
              style="<?php echo $earned ? 'background:' . $badge['color'] . '12;color:' . $badge['color'] . ';border-color:' . $badge['color'] . '30;' : 'opacity:.45;'; ?>"
              title="<?php echo htmlspecialchars($badge['desc']); ?>">
            <i class="fas <?php echo $badge['icon']; ?>"></i>
            <?php echo htmlspecialchars($badge['name']); ?>
            <?php if (!$earned): ?><i class="fas fa-lock" style="font-size:9px;opacity:.5;"></i><?php endif; ?>
        </span>
        <?php endforeach; ?>
    </div>
</div>

<!-- Two Column: Recent Tasks + Recent Points -->
<div class="wd-two-col">
    <div class="card-box">
        <h5><i class="fas fa-clipboard-list"></i> Recent Tasks</h5>
        <?php if ($recentTasks->num_rows > 0): ?>
            <?php while ($t = $recentTasks->fetch_assoc()): ?>
            <div class="wd-activity-item">
                <div class="wd-activity-dot" style="background:<?php
                    echo $t['status'] === 'Completed' ? 'var(--success-light);color:var(--success)' :
                        ($t['status'] === 'In Progress' ? 'var(--info-light);color:var(--info)' : 'var(--warning-light);color:var(--warning)');
                ?>;">
                    <i class="fas <?php echo $t['status'] === 'Completed' ? 'fa-check' : ($t['status'] === 'In Progress' ? 'fa-spinner' : 'fa-clock'); ?>"></i>
                </div>
                <div class="wd-activity-info">
                    <div class="wd-activity-title"><?php echo htmlspecialchars($t['title']); ?></div>
                    <div class="wd-activity-sub">
                        <span class="badge badge-<?php echo strtolower($t['priority']); ?>" style="font-size:9px;padding:2px 8px;"><?php echo $t['priority']; ?></span>
                        <?php if ($t['due_date']): ?>
                            &nbsp; Due <?php echo date('d M', strtotime($t['due_date'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="wd-activity-badge badge badge-<?php echo strtolower(str_replace(' ', '', $t['status'])); ?>" style="font-size:10px;padding:3px 10px;">
                    <?php echo $t['status']; ?>
                </span>
            </div>
            <?php endwhile; ?>
            <div style="text-align:center;margin-top:14px;">
                <a href="my_tasks.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-right"></i> View All Tasks</a>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding:24px;"><i class="fas fa-clipboard"></i>No tasks assigned yet.</div>
        <?php endif; ?>
    </div>
    <div class="card-box">
        <h5><i class="fas fa-bolt"></i> Recent Points</h5>
        <?php if ($recentPoints->num_rows > 0): ?>
            <?php while ($p = $recentPoints->fetch_assoc()): ?>
            <div class="wd-activity-item">
                <div class="wd-activity-dot" style="background:var(--accent-light);color:var(--accent);">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="wd-activity-info">
                    <div class="wd-activity-title"><?php echo htmlspecialchars($p['reason']); ?></div>
                    <div class="wd-activity-sub"><?php echo date('d M Y, h:i A', strtotime($p['created_at'])); ?></div>
                </div>
                <span class="wd-activity-badge" style="color:var(--success);font-weight:800;">+<?php echo $p['points']; ?></span>
            </div>
            <?php endwhile; ?>
            <div style="text-align:center;margin-top:14px;">
                <a href="profile.php" class="btn btn-outline btn-sm"><i class="fas fa-user-circle"></i> Full Profile</a>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding:24px;"><i class="fas fa-bolt"></i>Complete tasks to earn points!</div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Check-ins -->
<?php if ($recentCheckins->num_rows > 0): ?>
<div class="card-box">
    <h5><i class="fas fa-map-pin"></i> Recent Check-ins</h5>
    <?php while ($ci = $recentCheckins->fetch_assoc()): ?>
    <div class="wd-activity-item">
        <div class="wd-activity-dot" style="background:<?php echo $ci['type'] === 'check-in' ? 'var(--success-light);color:var(--success)' : 'var(--danger-light);color:var(--danger)'; ?>;">
            <i class="fas <?php echo $ci['type'] === 'check-in' ? 'fa-sign-in-alt' : 'fa-sign-out-alt'; ?>"></i>
        </div>
        <div class="wd-activity-info">
            <div class="wd-activity-title"><?php echo ucfirst(str_replace('-', ' ', $ci['type'])); ?></div>
            <div class="wd-activity-sub"><?php echo htmlspecialchars($ci['location_name'] ?: 'Unknown location'); ?></div>
        </div>
        <span class="wd-activity-badge" style="color:var(--text-muted);font-size:11px;">
            <?php echo date('d M, h:i A', strtotime($ci['created_at'])); ?>
        </span>
    </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="card-box">
    <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
        <a href="my_tasks.php" class="btn btn-primary"><i class="fas fa-clipboard-list"></i> My Tasks</a>
        <a href="checkin.php" class="btn btn-success"><i class="fas fa-map-marker-alt"></i> Check-In</a>
        <a href="route.php" class="btn btn-secondary"><i class="fas fa-route"></i> Route Planner</a>
        <a href="feedback.php" class="btn btn-outline"><i class="fas fa-comments"></i> Voter Feedback</a>
        <a href="profile.php" class="btn btn-outline"><i class="fas fa-trophy"></i> Profile & Badges</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
