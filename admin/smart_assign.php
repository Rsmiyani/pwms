<?php
$pageTitle = "Smart Task Assignment";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/analytics.php';
requireAdmin();

// Filters
$priority = $_GET['priority'] ?? '';
$campaignType = $_GET['campaign_type'] ?? '';
$constituency = $_GET['constituency'] ?? '';

$recommendations = getWorkerRecommendations(
    $conn,
    $priority ?: null,
    $campaignType ?: null,
    $constituency ?: null
);

// Get constituencies for filter
$constituencies = $conn->query("SELECT DISTINCT constituency FROM workers WHERE constituency IS NOT NULL AND constituency != '' ORDER BY constituency");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-magic"></i> AI Smart Task Assignment</h4>
    <a href="task_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Task</a>
</div>

<div class="card-box">
    <h5><i class="fas fa-filter"></i> Define Task Requirements</h5>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:14px;">
        <i class="fas fa-robot"></i> AI analyzes each worker's historical performance, completion rates, current workload, and skill match to recommend the best candidates.
    </p>
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Task Priority</label>
            <select name="priority" class="form-control">
                <option value="">Any Priority</option>
                <option value="Low" <?php echo $priority === 'Low' ? 'selected' : ''; ?>>Low</option>
                <option value="Medium" <?php echo $priority === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="High" <?php echo $priority === 'High' ? 'selected' : ''; ?>>High</option>
            </select>
        </div>
        <div class="form-group">
            <label>Campaign Type</label>
            <select name="campaign_type" class="form-control">
                <option value="">Any Type</option>
                <?php foreach (['Door-to-door', 'Event', 'Call Outreach', 'Social Media', 'Rally', 'Other'] as $ct): ?>
                <option value="<?php echo $ct; ?>" <?php echo $campaignType === $ct ? 'selected' : ''; ?>><?php echo $ct; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Constituency</label>
            <select name="constituency" class="form-control">
                <option value="">Any</option>
                <?php while ($c = $constituencies->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($c['constituency']); ?>" <?php echo $constituency === $c['constituency'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['constituency']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Analyze</button>
        </div>
    </form>
</div>

<!-- Results -->
<div class="card-box">
    <h5><i class="fas fa-ranking-star"></i> Worker Recommendations
        <?php if ($priority || $campaignType || $constituency): ?>
        <span style="font-weight:400;font-size:13px;color:var(--text-secondary);">
            — Filtered:
            <?php echo $priority ? "Priority: $priority" : ''; ?>
            <?php echo $campaignType ? " | Type: $campaignType" : ''; ?>
            <?php echo $constituency ? " | Area: $constituency" : ''; ?>
        </span>
        <?php endif; ?>
    </h5>

    <?php if (!empty($recommendations)): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Worker</th>
                    <th>AI Score</th>
                    <th>Completion Rate</th>
                    <th>Tasks Done</th>
                    <th>Pending</th>
                    <th>Area</th>
                    <th>Match Reasons</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 1; foreach ($recommendations as $rec):
                    $w = $rec['worker'];
                    $scoreColor = $rec['score'] >= 60 ? '#198754' : ($rec['score'] >= 30 ? '#e67e22' : '#dc3545');
                ?>
                <tr>
                    <td>
                        <?php if ($rank <= 3): ?>
                            <span class="rank-badge rank-<?php echo $rank; ?>"><?php echo $rank; ?></span>
                        <?php else: ?>
                            <?php echo $rank; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($w['name']); ?></strong>
                        <br><small style="color:var(--text-muted);"><?php echo htmlspecialchars($w['phone']); ?></small>
                    </td>
                    <td>
                        <div class="score-bar-wrap">
                            <div class="score-bar" style="width:<?php echo $rec['score']; ?>%;background:<?php echo $scoreColor; ?>;"></div>
                        </div>
                        <span style="font-weight:700;color:<?php echo $scoreColor; ?>;"><?php echo $rec['score']; ?></span>
                    </td>
                    <td>
                        <?php echo $rec['completion_rate']; ?>%
                        <small style="color:var(--text-muted);">(overall: <?php echo $rec['overall_rate']; ?>%)</small>
                    </td>
                    <td><?php echo $rec['completed']; ?>/<?php echo $rec['total_assigned']; ?></td>
                    <td>
                        <span class="badge <?php echo $rec['pending'] === 0 ? 'badge-active' : ($rec['pending'] <= 3 ? 'badge-pending' : 'badge-inactive'); ?>">
                            <?php echo $rec['pending']; ?> pending
                        </span>
                    </td>
                    <td>
                        <small><?php echo htmlspecialchars($w['constituency'] . ' / ' . $w['ward']); ?></small>
                    </td>
                    <td>
                        <?php if (!empty($rec['match_reasons'])): ?>
                            <?php foreach ($rec['match_reasons'] as $reason): ?>
                            <span class="ai-tag"><i class="fas fa-check"></i> <?php echo $reason; ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="task_add.php" class="btn btn-sm btn-primary" title="Create task for this worker">
                            <i class="fas fa-plus"></i> Assign
                        </a>
                    </td>
                </tr>
                <?php $rank++; endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-users"></i>No active workers found.</div>
    <?php endif; ?>
</div>

<!-- How It Works -->
<div class="card-box">
    <h5><i class="fas fa-info-circle"></i> How AI Scoring Works</h5>
    <div class="ai-explainer">
        <div class="ai-factor">
            <div class="ai-factor-weight">35%</div>
            <div>
                <strong>Task Completion Rate</strong>
                <p>Completion rate for matching task priority/type</p>
            </div>
        </div>
        <div class="ai-factor">
            <div class="ai-factor-weight">25%</div>
            <div>
                <strong>Overall Reliability</strong>
                <p>Historical completion rate across all tasks</p>
            </div>
        </div>
        <div class="ai-factor">
            <div class="ai-factor-weight">20%</div>
            <div>
                <strong>Current Availability</strong>
                <p>Fewer pending tasks = higher score</p>
            </div>
        </div>
        <div class="ai-factor">
            <div class="ai-factor-weight">15%</div>
            <div>
                <strong>Location Match</strong>
                <p>Bonus for same constituency/ward</p>
            </div>
        </div>
        <div class="ai-factor">
            <div class="ai-factor-weight">5%</div>
            <div>
                <strong>Experience</strong>
                <p>More completed tasks = more reliable data</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
