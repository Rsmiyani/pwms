<?php
/**
 * Gamification Engine — Points, Levels & Badges
 */

// ── Point values ──
define('PTS_TASK_LOW', 10);
define('PTS_TASK_MEDIUM', 25);
define('PTS_TASK_HIGH', 50);
define('PTS_CHECKIN', 5);
define('PTS_PROOF_UPLOAD', 10);

// ── Level thresholds ──
function getWorkerLevel($totalPoints) {
    if ($totalPoints >= 600) return ['name' => 'Platinum', 'icon' => 'fa-gem',       'color' => '#7c3aed', 'min' => 600, 'next' => null];
    if ($totalPoints >= 300) return ['name' => 'Gold',     'icon' => 'fa-crown',     'color' => '#d97706', 'min' => 300, 'next' => 600];
    if ($totalPoints >= 100) return ['name' => 'Silver',   'icon' => 'fa-medal',     'color' => '#64748b', 'min' => 100, 'next' => 300];
    return                          ['name' => 'Bronze',   'icon' => 'fa-shield-alt','color' => '#b45309', 'min' => 0,   'next' => 100];
}

// ── Badge definitions ──
function getBadgeDefinitions() {
    return [
        'first_task'      => ['name' => 'First Task',         'icon' => 'fa-star',           'desc' => 'Complete your first task',           'color' => '#e8702a'],
        'task_10'          => ['name' => 'Task Master',        'icon' => 'fa-award',          'desc' => 'Complete 10 tasks',                  'color' => '#0284c7'],
        'task_50'          => ['name' => 'Task Legend',        'icon' => 'fa-trophy',         'desc' => 'Complete 50 tasks',                  'color' => '#7c3aed'],
        'task_100'         => ['name' => 'Centurion',          'icon' => 'fa-chess-queen',    'desc' => 'Complete 100 tasks',                 'color' => '#d97706'],
        'high_achiever'    => ['name' => 'High Achiever',      'icon' => 'fa-fire',           'desc' => 'Complete 10 high-priority tasks',    'color' => '#dc2626'],
        'checkin_10'       => ['name' => 'Regular',            'icon' => 'fa-map-pin',        'desc' => 'Record 10 check-ins',                'color' => '#059669'],
        'checkin_50'       => ['name' => 'Field Warrior',      'icon' => 'fa-hiking',         'desc' => 'Record 50 check-ins',                'color' => '#0f4c75'],
        'checkin_100'      => ['name' => '100 Check-ins',      'icon' => 'fa-mountain',       'desc' => 'Record 100 check-ins',               'color' => '#7c3aed'],
        'early_bird'       => ['name' => 'Early Bird',         'icon' => 'fa-sun',            'desc' => 'Check in before 7 AM',               'color' => '#f59e0b'],
        'proof_pro'        => ['name' => 'Proof Pro',          'icon' => 'fa-camera',         'desc' => 'Upload 20 proof images',             'color' => '#06b6d4'],
        'level_silver'     => ['name' => 'Silver Rank',        'icon' => 'fa-medal',          'desc' => 'Reach Silver level (100 pts)',       'color' => '#64748b'],
        'level_gold'       => ['name' => 'Gold Rank',          'icon' => 'fa-crown',          'desc' => 'Reach Gold level (300 pts)',         'color' => '#d97706'],
        'level_platinum'   => ['name' => 'Platinum Rank',      'icon' => 'fa-gem',            'desc' => 'Reach Platinum level (600 pts)',     'color' => '#7c3aed'],
    ];
}

/**
 * Award points to a worker (and check badges after)
 */
function awardPoints($workerId, $points, $reason, $refType = 'manual', $refId = null) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO worker_points (worker_id, points, reason, reference_type, reference_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissi", $workerId, $points, $reason, $refType, $refId);
    $stmt->execute();
    $stmt->close();

    // Check and award any new badges
    checkAndAwardBadges($workerId);
}

/**
 * Get total points for a worker
 */
function getWorkerTotalPoints($workerId) {
    global $conn;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) as total FROM worker_points WHERE worker_id = ?");
    $stmt->bind_param("i", $workerId);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return (int)$total;
}

/**
 * Get earned badges for a worker
 */
function getWorkerBadges($workerId) {
    global $conn;
    $stmt = $conn->prepare("SELECT badge_key, earned_at FROM worker_badges WHERE worker_id = ? ORDER BY earned_at DESC");
    $stmt->bind_param("i", $workerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $badges = [];
    while ($row = $result->fetch_assoc()) {
        $badges[$row['badge_key']] = $row['earned_at'];
    }
    $stmt->close();
    return $badges;
}

/**
 * Check all badge conditions and award any newly earned ones
 */
function checkAndAwardBadges($workerId) {
    global $conn;

    $earned = getWorkerBadges($workerId);
    $totalPoints = getWorkerTotalPoints($workerId);

    // Completed tasks count
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM task_assignments WHERE worker_id = ? AND status = 'Completed'");
    $stmt->bind_param("i", $workerId);
    $stmt->execute();
    $completedTasks = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    // High-priority completed tasks
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM task_assignments ta JOIN tasks t ON ta.task_id = t.id WHERE ta.worker_id = ? AND ta.status = 'Completed' AND t.priority = 'High'");
    $stmt->bind_param("i", $workerId);
    $stmt->execute();
    $highTasks = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    // Check-in count
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM worker_checkins WHERE worker_id = ?");
    $stmt->bind_param("i", $workerId);
    $stmt->execute();
    $checkins = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    // Early bird check (any check-in before 7 AM)
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM worker_checkins WHERE worker_id = ? AND TIME(created_at) < '07:00:00'");
    $stmt->bind_param("i", $workerId);
    $stmt->execute();
    $earlyCheckins = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    // Proof uploads count
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM task_proofs tp JOIN task_assignments ta ON tp.assignment_id = ta.id WHERE ta.worker_id = ?");
    $stmt->bind_param("i", $workerId);
    $stmt->execute();
    $proofUploads = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    // Define conditions: badge_key => condition
    $checks = [
        'first_task'    => $completedTasks >= 1,
        'task_10'       => $completedTasks >= 10,
        'task_50'       => $completedTasks >= 50,
        'task_100'      => $completedTasks >= 100,
        'high_achiever' => $highTasks >= 10,
        'checkin_10'    => $checkins >= 10,
        'checkin_50'    => $checkins >= 50,
        'checkin_100'   => $checkins >= 100,
        'early_bird'    => $earlyCheckins >= 1,
        'proof_pro'     => $proofUploads >= 20,
        'level_silver'  => $totalPoints >= 100,
        'level_gold'    => $totalPoints >= 300,
        'level_platinum'=> $totalPoints >= 600,
    ];

    foreach ($checks as $badgeKey => $met) {
        if ($met && !isset($earned[$badgeKey])) {
            $stmt = $conn->prepare("INSERT IGNORE INTO worker_badges (worker_id, badge_key) VALUES (?, ?)");
            $stmt->bind_param("is", $workerId, $badgeKey);
            $stmt->execute();
            $stmt->close();

            // Notify the worker
            $defs = getBadgeDefinitions();
            if (isset($defs[$badgeKey])) {
                $wStmt = $conn->prepare("SELECT user_id FROM workers WHERE id = ?");
                $wStmt->bind_param("i", $workerId);
                $wStmt->execute();
                $wRow = $wStmt->get_result()->fetch_assoc();
                $wStmt->close();
                if ($wRow && $wRow['user_id']) {
                    createNotification(
                        $wRow['user_id'],
                        'Badge Earned: ' . $defs[$badgeKey]['name'],
                        'Congratulations! You earned the "' . $defs[$badgeKey]['name'] . '" badge — ' . $defs[$badgeKey]['desc'],
                        'general',
                        BASE_URL . '/worker/profile.php'
                    );
                }
            }
        }
    }
}

/**
 * Get leaderboard data
 */
function getLeaderboard($constituency = '', $ward = '', $limit = 10) {
    global $conn;

    $where = "WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($constituency)) {
        $where .= " AND w.constituency = ?";
        $params[] = $constituency;
        $types .= "s";
    }
    if (!empty($ward)) {
        $where .= " AND w.ward = ?";
        $params[] = $ward;
        $types .= "s";
    }

    $sql = "SELECT w.id, w.name, w.constituency, w.ward, w.booth,
                   COALESCE(SUM(wp.points), 0) as total_points,
                   (SELECT COUNT(*) FROM task_assignments ta2 WHERE ta2.worker_id = w.id AND ta2.status = 'Completed') as tasks_done,
                   (SELECT COUNT(*) FROM worker_badges wb2 WHERE wb2.worker_id = w.id) as badge_count
            FROM workers w
            LEFT JOIN worker_points wp ON w.id = wp.worker_id
            $where AND w.status = 'Active'
            GROUP BY w.id
            ORDER BY total_points DESC
            LIMIT ?";
    $params[] = $limit;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $leaderboard = [];
    while ($row = $result->fetch_assoc()) {
        $row['level'] = getWorkerLevel((int)$row['total_points']);
        $leaderboard[] = $row;
    }
    $stmt->close();
    return $leaderboard;
}
