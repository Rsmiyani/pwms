<?php
$pageTitle = "Leaderboard";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/gamification.php';
requireAdmin();

$filterConstituency = $_GET['constituency'] ?? '';
$filterWard = $_GET['ward'] ?? '';

$leaderboard = getLeaderboard($filterConstituency, $filterWard, 20);

// Get filter options
$constituencies = $conn->query("SELECT DISTINCT constituency FROM workers WHERE constituency IS NOT NULL AND constituency != '' AND status='Active' ORDER BY constituency");
$wards = $conn->query("SELECT DISTINCT ward FROM workers WHERE ward IS NOT NULL AND ward != '' AND status='Active' ORDER BY ward");

// Badge definitions
$badgeDefs = getBadgeDefinitions();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-trophy"></i> Leaderboard</h4>
</div>

<!-- Filters -->
<div class="card-box">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Constituency</label>
            <select name="constituency" class="form-control">
                <option value="">All Constituencies</option>
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
                <option value="">All Wards</option>
                <?php while ($w = $wards->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($w['ward']); ?>" <?php echo $filterWard === $w['ward'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['ward']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="leaderboard.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>
</div>

<?php if (empty($leaderboard)): ?>
    <div class="card-box">
        <div class="empty-state"><i class="fas fa-trophy"></i>No workers found. Points will appear when workers complete tasks.</div>
    </div>
<?php else: ?>

<!-- Top 3 Podium -->
<?php if (count($leaderboard) >= 1): ?>
<div class="podium-section">
    <?php
    $podiumOrder = [];
    if (isset($leaderboard[1])) $podiumOrder[] = ['data' => $leaderboard[1], 'rank' => 2, 'class' => 'silver'];
    $podiumOrder[] = ['data' => $leaderboard[0], 'rank' => 1, 'class' => 'gold'];
    if (isset($leaderboard[2])) $podiumOrder[] = ['data' => $leaderboard[2], 'rank' => 3, 'class' => 'bronze'];
    ?>
    <?php foreach ($podiumOrder as $p):
        $w = $p['data'];
        $level = $w['level'];
    ?>
    <div class="podium-card podium-<?php echo $p['class']; ?>">
        <div class="podium-rank"><?php echo $p['rank']; ?></div>
        <div class="podium-avatar"><?php echo strtoupper(substr($w['name'], 0, 1)); ?></div>
        <div class="podium-name"><?php echo htmlspecialchars($w['name']); ?></div>
        <div class="podium-level">
            <i class="fas <?php echo $level['icon']; ?>" style="color:<?php echo $level['color']; ?>"></i>
            <?php echo $level['name']; ?>
        </div>
        <div class="podium-points"><?php echo number_format($w['total_points']); ?> pts</div>
        <div class="podium-meta">
            <span><i class="fas fa-check"></i> <?php echo $w['tasks_done']; ?> tasks</span>
            <span><i class="fas fa-award"></i> <?php echo $w['badge_count']; ?> badges</span>
        </div>
        <div class="podium-area"><?php echo htmlspecialchars(implode(' / ', array_filter([$w['constituency'], $w['ward']]))); ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Full Leaderboard Table -->
<div class="card-box">
    <h5><i class="fas fa-list-ol"></i> Full Rankings</h5>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Worker</th>
                    <th>Level</th>
                    <th>Points</th>
                    <th>Tasks Done</th>
                    <th>Badges</th>
                    <th>Area</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaderboard as $idx => $w):
                    $level = $w['level'];
                    $workerBadges = getWorkerBadges($w['id']);
                ?>
                <tr>
                    <td>
                        <?php if ($idx < 3): ?>
                            <span class="rank-medal rank-<?php echo ['gold','silver','bronze'][$idx]; ?>"><?php echo $idx + 1; ?></span>
                        <?php else: ?>
                            <strong><?php echo $idx + 1; ?></strong>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($w['name']); ?></strong></td>
                    <td>
                        <span class="level-badge" style="--level-color:<?php echo $level['color']; ?>">
                            <i class="fas <?php echo $level['icon']; ?>"></i> <?php echo $level['name']; ?>
                        </span>
                    </td>
                    <td><strong><?php echo number_format($w['total_points']); ?></strong></td>
                    <td><?php echo $w['tasks_done']; ?></td>
                    <td>
                        <div class="badge-icons">
                            <?php foreach ($workerBadges as $bKey => $bDate):
                                if (isset($badgeDefs[$bKey])):
                                    $b = $badgeDefs[$bKey];
                            ?>
                                <span class="badge-icon-item" title="<?php echo htmlspecialchars($b['name'] . ' — ' . $b['desc']); ?>" style="color:<?php echo $b['color']; ?>">
                                    <i class="fas <?php echo $b['icon']; ?>"></i>
                                </span>
                            <?php endif; endforeach; ?>
                            <?php if (empty($workerBadges)): ?>
                                <span style="color:var(--text-muted);font-size:12px;">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="font-size:12px;color:var(--text-secondary);"><?php echo htmlspecialchars(implode(' / ', array_filter([$w['constituency'], $w['ward'], $w['booth']]))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
