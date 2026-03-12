<?php
$pageTitle = "Voter Feedback";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/analytics.php';
requireLogin();

$userId = $_SESSION['user_id'];
$workerStmt = $conn->prepare("SELECT id, name, constituency, ward, booth FROM workers WHERE user_id = ?");
$workerStmt->bind_param("i", $userId);
$workerStmt->execute();
$workerRow = $workerStmt->get_result()->fetch_assoc();
$workerStmt->close();

if (!$workerRow) {
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/sidebar.php';
    echo '<div class="card-box"><div class="empty-state"><i class="fas fa-info-circle"></i>No worker profile linked to your account.</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$workerId = $workerRow['id'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $voterName = trim($_POST['voter_name'] ?? '');
    $ward = trim($_POST['ward'] ?? '');
    $constituency = trim($_POST['constituency'] ?? '');
    $feedbackText = trim($_POST['feedback_text'] ?? '');

    if (empty($feedbackText) || empty($ward)) {
        setFlash('danger', 'Ward and feedback text are required.');
    } else {
        // AI Sentiment Analysis
        $result = analyzeSentiment($feedbackText);

        $stmt = $conn->prepare("INSERT INTO voter_feedback (worker_id, voter_name, ward, constituency, feedback_text, sentiment, sentiment_score, confidence) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssdd", $workerId, $voterName, $ward, $constituency, $feedbackText, $result['label'], $result['score'], $result['confidence']);
        $stmt->execute();
        $stmt->close();

        setFlash('success', 'Feedback recorded! Sentiment: ' . htmlspecialchars($result['label']) . ' (score: ' . $result['score'] . ')');
    }
    header("Location: feedback.php");
    exit;
}

// Fetch recent feedback by this worker
$history = $conn->prepare("SELECT * FROM voter_feedback WHERE worker_id = ? ORDER BY created_at DESC LIMIT 20");
$history->bind_param("i", $workerId);
$history->execute();
$feedbacks = $history->get_result();

// Get wards for dropdown
$wards = $conn->query("SELECT DISTINCT ward FROM areas WHERE ward IS NOT NULL AND ward != '' ORDER BY ward");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-comments"></i> Voter Feedback Collection</h4>
</div>

<!-- Feedback Form -->
<div class="card-box">
    <h5><i class="fas fa-pencil-alt"></i> Record New Feedback</h5>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px;">
        <i class="fas fa-robot"></i> AI will automatically analyze the sentiment of the feedback.
    </p>

    <form method="POST">
        <?php csrfField(); ?>
        <div class="form-row">
            <div class="form-group">
                <label>Voter Name (optional)</label>
                <input type="text" name="voter_name" class="form-control" placeholder="Anonymous if left blank">
            </div>
            <div class="form-group">
                <label>Constituency</label>
                <input type="text" name="constituency" class="form-control"
                    value="<?php echo htmlspecialchars($workerRow['constituency'] ?? ''); ?>"
                    placeholder="e.g., Constituency A">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Ward *</label>
                <select name="ward" class="form-control" required>
                    <option value="">-- Select Ward --</option>
                    <?php while ($wr = $wards->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($wr['ward']); ?>" <?php echo ($workerRow['ward'] ?? '') === $wr['ward'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($wr['ward']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Feedback Text *</label>
            <textarea name="feedback_text" class="form-control" rows="4" required
                placeholder="Enter what the voter said about the party, candidate, local issues, etc."></textarea>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Submit & Analyze
        </button>
    </form>
</div>

<!-- Feedback History -->
<div class="card-box">
    <h5><i class="fas fa-history"></i> My Recent Feedback Entries</h5>
    <?php if ($feedbacks->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ward</th>
                        <th>Voter</th>
                        <th>Feedback</th>
                        <th>Sentiment</th>
                        <th>Score</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($f = $feedbacks->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f['ward']); ?></td>
                            <td><?php echo htmlspecialchars($f['voter_name'] ?: 'Anonymous'); ?></td>
                            <td style="max-width:300px;white-space:normal;">
                                <?php echo htmlspecialchars(mb_strimwidth($f['feedback_text'], 0, 120, '...')); ?></td>
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
                            <td><?php echo date('d M Y, h:i A', strtotime($f['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-comments"></i>No feedback recorded yet. Start collecting!</div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>