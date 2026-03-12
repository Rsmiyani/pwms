<?php
/**
 * AI & Data Analytics Engine
 * - Sentiment Analysis (keyword-based NLP)
 * - Predictive Task Assignment (historical scoring)
 * - Performance Report generation
 */

// ── Sentiment Word Lists ──
function getSentimentWords() {
    return [
        'positive' => [
            'good','great','excellent','amazing','wonderful','fantastic','happy','satisfied',
            'helpful','supportive','nice','love','best','improved','progress','trust',
            'confident','hopeful','pleased','impressed','thankful','grateful','positive',
            'reliable','efficient','brilliant','outstanding','perfect','strong','dedicated',
            'committed','respected','honest','fair','clean','developed','growing','prosper',
            'peaceful','safe','better','benefit','success','victory','win','proud','joy',
            'appreciate','agree','support','yes','definitely','absolutely','fine','okay'
        ],
        'negative' => [
            'bad','terrible','horrible','awful','worst','hate','angry','disappointed',
            'corrupt','useless','failure','poor','dirty','broken','neglected','ignored',
            'liar','cheat','fraud','scam','waste','nothing','never','problem','issue',
            'complaint','suffer','struggling','expensive','unsafe','dangerous','fear',
            'worried','frustrated','annoyed','fed up','disgusted','pathetic','shameful',
            'careless','irresponsible','unfair','biased','false','fake','wrong','no',
            'delay','slow','incompetent','unhappy','dissatisfied','worse','decline','fail'
        ]
    ];
}

/**
 * Analyze sentiment of text → returns ['score' => float, 'label' => string, 'confidence' => float]
 * Score: -1.0 (very negative) to +1.0 (very positive)
 */
function analyzeSentiment($text) {
    $words = getSentimentWords();
    $text = strtolower(preg_replace('/[^a-zA-Z\s]/', ' ', $text));
    $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $total = count($tokens);
    if ($total === 0) return ['score' => 0, 'label' => 'Neutral', 'confidence' => 0];

    $posCount = 0;
    $negCount = 0;
    foreach ($tokens as $token) {
        if (in_array($token, $words['positive'])) $posCount++;
        if (in_array($token, $words['negative'])) $negCount++;
    }

    $matchedCount = $posCount + $negCount;
    $score = $matchedCount > 0 ? ($posCount - $negCount) / $matchedCount : 0;
    $confidence = $total > 0 ? min(1, $matchedCount / max(5, $total) * 2) : 0;

    if ($score > 0.15) $label = 'Positive';
    elseif ($score < -0.15) $label = 'Negative';
    else $label = 'Neutral';

    return ['score' => round($score, 3), 'label' => $label, 'confidence' => round($confidence, 2)];
}

/**
 * Get sentiment summary for a ward
 */
function getWardSentiment($conn, $ward = null) {
    $where = $ward ? "WHERE f.ward = ?" : "";
    $sql = "SELECT f.ward, f.sentiment,
            COUNT(*) as cnt
            FROM voter_feedback f
            $where
            GROUP BY f.ward, f.sentiment
            ORDER BY f.ward, f.sentiment";
    $stmt = $conn->prepare($sql);
    if ($ward) $stmt->bind_param("s", $ward);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get all wards with feedback counts + average scores
 */
function getSentimentOverview($conn) {
    $sql = "SELECT f.ward,
            COUNT(*) as total_feedback,
            SUM(CASE WHEN f.sentiment = 'Positive' THEN 1 ELSE 0 END) as positive,
            SUM(CASE WHEN f.sentiment = 'Neutral' THEN 1 ELSE 0 END) as neutral,
            SUM(CASE WHEN f.sentiment = 'Negative' THEN 1 ELSE 0 END) as negative,
            AVG(f.sentiment_score) as avg_score
            FROM voter_feedback f
            GROUP BY f.ward
            ORDER BY avg_score DESC";
    return $conn->query($sql);
}

// ═══════════════════════════════════════════
//  PREDICTIVE TASK ASSIGNMENT
// ═══════════════════════════════════════════

/**
 * Calculate worker suitability scores for a given task type / priority
 * Returns array sorted by score DESC
 */
function getWorkerRecommendations($conn, $priority = null, $campaignType = null, $constituency = null) {
    // Get all active workers
    $workers = $conn->query("SELECT id, name, phone, constituency, ward, booth, responsibility_type FROM workers WHERE status = 'Active' ORDER BY name");
    $results = [];

    while ($w = $workers->fetch_assoc()) {
        $wid = $w['id'];

        // 1. Completion rate for matching priority/campaign type (parameterized)
        $conditions = "WHERE ta.worker_id = ?";
        $bindTypes = "i";
        $bindVals = [$wid];

        if ($priority) {
            $conditions .= " AND t.priority = ?";
            $bindTypes .= "s";
            $bindVals[] = $priority;
        }
        if ($campaignType) {
            $conditions .= " AND t.campaign_type = ?";
            $bindTypes .= "s";
            $bindVals[] = $campaignType;
        }

        $sql = "SELECT
            COUNT(*) as total_assigned,
            SUM(CASE WHEN ta.status = 'Completed' THEN 1 ELSE 0 END) as completed,
            AVG(CASE WHEN ta.completed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, ta.updated_at, ta.completed_at) END) as avg_hours
            FROM task_assignments ta
            JOIN tasks t ON t.id = ta.task_id
            $conditions";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($bindTypes, ...$bindVals);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $totalAssigned = (int)($stats['total_assigned'] ?? 0);
        $completed = (int)($stats['completed'] ?? 0);
        $completionRate = $totalAssigned > 0 ? ($completed / $totalAssigned) : 0;

        // 2. Overall completion rate (all tasks)
        $sqlAll = "SELECT COUNT(*) as total, SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as done FROM task_assignments WHERE worker_id = ?";
        $stmtAll = $conn->prepare($sqlAll);
        $stmtAll->bind_param("i", $wid);
        $stmtAll->execute();
        $allStats = $stmtAll->get_result()->fetch_assoc();
        $stmtAll->close();
        $overallRate = $allStats['total'] > 0 ? ($allStats['done'] / $allStats['total']) : 0;

        // 3. Current workload (pending tasks)
        $sqlPending = "SELECT COUNT(*) as pending FROM task_assignments WHERE worker_id = ? AND status != 'Completed'";
        $stmtP = $conn->prepare($sqlPending);
        $stmtP->bind_param("i", $wid);
        $stmtP->execute();
        $pending = (int)$stmtP->get_result()->fetch_assoc()['pending'];
        $stmtP->close();

        // 4. Location match bonus
        $locationBonus = 0;
        if ($constituency && $w['constituency'] === $constituency) $locationBonus += 0.15;

        // 5. Responsibility type match
        $typeBonus = 0;
        if ($campaignType && $w['responsibility_type'] === $campaignType) $typeBonus += 0.1;

        // Calculate composite score (0-100)
        $score = 0;
        $score += $completionRate * 35;      // 35% weight: matching task completion rate
        $score += $overallRate * 25;          // 25% weight: overall reliability
        $score += max(0, (1 - $pending / 10)) * 20; // 20% weight: availability (fewer pending = better)
        $score += $locationBonus * 100;       // Location match bonus (~15 pts)
        $score += $typeBonus * 100;           // Type match bonus (~10 pts)

        // Experience bonus (more tasks = more reliable data)
        if ($totalAssigned >= 10) $score += 5;
        elseif ($totalAssigned >= 5) $score += 3;

        $results[] = [
            'worker' => $w,
            'score' => round(min(100, $score), 1),
            'completion_rate' => round($completionRate * 100, 1),
            'overall_rate' => round($overallRate * 100, 1),
            'total_assigned' => $totalAssigned,
            'completed' => $completed,
            'pending' => $pending,
            'match_reasons' => array_filter([
                $locationBonus > 0 ? 'Same constituency' : null,
                $typeBonus > 0 ? 'Matching skill type' : null,
                $completionRate >= 0.8 ? 'High completion rate' : null,
                $pending === 0 ? 'No pending tasks' : null,
            ])
        ];
    }

    usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    return $results;
}

// ═══════════════════════════════════════════
//  PERFORMANCE REPORT
// ═══════════════════════════════════════════

/**
 * Generate booth-level performance stats for a date range
 */
function getBoothPerformanceData($conn, $startDate, $endDate) {
    $sql = "SELECT
        w.booth,
        w.constituency,
        w.ward,
        COUNT(DISTINCT w.id) as worker_count,
        (SELECT COUNT(*) FROM task_assignments ta
         JOIN workers tw ON tw.id = ta.worker_id
         WHERE tw.booth = w.booth AND ta.updated_at BETWEEN ? AND ?) as total_tasks,
        (SELECT COUNT(*) FROM task_assignments ta
         JOIN workers tw ON tw.id = ta.worker_id
         WHERE tw.booth = w.booth AND ta.status = 'Completed' AND ta.updated_at BETWEEN ? AND ?) as completed_tasks,
        (SELECT COUNT(*) FROM worker_checkins wc
         JOIN workers cw ON cw.id = wc.worker_id
         WHERE cw.booth = w.booth AND wc.created_at BETWEEN ? AND ?) as checkin_count,
        (SELECT COALESCE(SUM(wp.points), 0) FROM worker_points wp
         JOIN workers pw ON pw.id = wp.worker_id
         WHERE pw.booth = w.booth AND wp.created_at BETWEEN ? AND ?) as total_points
        FROM workers w
        WHERE w.booth IS NOT NULL AND w.booth != ''
        GROUP BY w.booth, w.constituency, w.ward
        ORDER BY completed_tasks DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get top performing workers for a date range
 */
function getTopPerformers($conn, $startDate, $endDate, $limit = 10) {
    $sql = "SELECT w.id, w.name, w.booth, w.constituency,
            (SELECT COUNT(*) FROM task_assignments ta WHERE ta.worker_id = w.id AND ta.status = 'Completed' AND ta.completed_at BETWEEN ? AND ?) as tasks_done,
            (SELECT COUNT(*) FROM worker_checkins wc WHERE wc.worker_id = w.id AND wc.created_at BETWEEN ? AND ?) as checkins,
            (SELECT COALESCE(SUM(wp.points), 0) FROM worker_points wp WHERE wp.worker_id = w.id AND wp.created_at BETWEEN ? AND ?) as points
            FROM workers w
            WHERE w.status = 'Active'
            HAVING tasks_done > 0 OR checkins > 0
            ORDER BY points DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $limit);
    $stmt->execute();
    return $stmt->get_result();
}
