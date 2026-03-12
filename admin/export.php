<?php
/**
 * Export Reports - CSV download and printable HTML for PDF
 */
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$format = $_GET['format'] ?? 'csv';
$type = $_GET['type'] ?? 'tasks';
$filterConstituency = $_GET['constituency'] ?? '';
$filterWard = $_GET['ward'] ?? '';

$constFilter = "";
$params = [];
$types = "";

if (!empty($filterConstituency)) {
    $constFilter .= " AND t.constituency = ?";
    $params[] = $filterConstituency;
    $types .= "s";
}
if (!empty($filterWard)) {
    $constFilter .= " AND t.ward = ?";
    $params[] = $filterWard;
    $types .= "s";
}

if ($type === 'tasks') {
    $sql = "SELECT t.title, t.constituency, t.ward, t.booth, t.priority, t.campaign_type, t.due_date,
                   w.name AS worker_name, w.phone AS worker_phone,
                   ta.status, ta.remarks, ta.completed_at
            FROM task_assignments ta
            JOIN tasks t ON ta.task_id = t.id
            JOIN workers w ON ta.worker_id = w.id
            WHERE 1=1 $constFilter
            ORDER BY t.created_at DESC, w.name ASC";
    $headers = ['Task', 'Constituency', 'Ward', 'Booth', 'Priority', 'Campaign Type', 'Due Date', 'Worker', 'Phone', 'Status', 'Remarks', 'Completed At'];
    $keys = ['title', 'constituency', 'ward', 'booth', 'priority', 'campaign_type', 'due_date', 'worker_name', 'worker_phone', 'status', 'remarks', 'completed_at'];
    $filename = 'task_report';
} elseif ($type === 'workers') {
    $sql = "SELECT w.name, w.phone, w.email, w.role, w.party_position, w.constituency, w.ward, w.booth, w.responsibility_type, w.status,
                   COUNT(ta.id) AS total_tasks,
                   SUM(CASE WHEN ta.status = 'Completed' THEN 1 ELSE 0 END) AS completed_tasks
            FROM workers w
            LEFT JOIN task_assignments ta ON ta.worker_id = w.id
            WHERE 1=1" . str_replace('t.constituency', 'w.constituency', str_replace('t.ward', 'w.ward', $constFilter)) . "
            GROUP BY w.id
            ORDER BY w.name ASC";
    $headers = ['Name', 'Phone', 'Email', 'Role', 'Position', 'Constituency', 'Ward', 'Booth', 'Responsibility', 'Status', 'Total Tasks', 'Completed'];
    $keys = ['name', 'phone', 'email', 'role', 'party_position', 'constituency', 'ward', 'booth', 'responsibility_type', 'status', 'total_tasks', 'completed_tasks'];
    $filename = 'worker_report';
} elseif ($type === 'checkins') {
    $sql = "SELECT w.name AS worker_name, w.phone, w.constituency, w.ward, w.booth,
                   wc.type, wc.location_name, wc.latitude, wc.longitude, wc.created_at
            FROM worker_checkins wc
            JOIN workers w ON wc.worker_id = w.id
            WHERE 1=1" . str_replace('t.constituency', 'w.constituency', str_replace('t.ward', 'w.ward', $constFilter)) . "
            ORDER BY wc.created_at DESC";
    $headers = ['Worker', 'Phone', 'Constituency', 'Ward', 'Booth', 'Type', 'Location Name', 'Latitude', 'Longitude', 'Time'];
    $keys = ['worker_name', 'phone', 'constituency', 'ward', 'booth', 'type', 'location_name', 'latitude', 'longitude', 'created_at'];
    $filename = 'checkin_report';
} else {
    setFlash('danger', 'Invalid report type.');
    header("Location: reports.php");
    exit;
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// === CSV Export ===
if ($format === 'csv') {
    $filename .= '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $headers);

    foreach ($rows as $row) {
        $line = [];
        foreach ($keys as $key) {
            $line[] = $row[$key] ?? '';
        }
        fputcsv($output, $line);
    }
    fclose($output);
    exit;
}

// === Printable HTML (for PDF via browser print) ===
if ($format === 'pdf') {
    $filename .= '_' . date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($filename); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; color: #333; }
        h1 { font-size: 20px; text-align: center; margin-bottom: 5px; }
        .subtitle { text-align: center; font-size: 12px; color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { background: #1a1a2e; color: #fff; padding: 8px 6px; text-align: left; }
        td { padding: 6px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        .print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #FF6B00; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .print-btn:hover { background: #e55d00; }
        @media print { .print-btn { display: none; } }
        .footer-info { margin-top: 20px; font-size: 11px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print / Save as PDF</button>
    <h1>Party Worker Management System - <?php echo ucfirst($type); ?> Report</h1>
    <div class="subtitle">Generated on <?php echo date('d M Y, h:i A'); ?>
        <?php if ($filterConstituency): ?> | Constituency: <?php echo htmlspecialchars($filterConstituency); ?><?php endif; ?>
        <?php if ($filterWard): ?> | Ward: <?php echo htmlspecialchars($filterWard); ?><?php endif; ?>
    </div>

    <?php if (count($rows) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <?php foreach ($headers as $h): ?>
                    <th><?php echo htmlspecialchars($h); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($rows as $row): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <?php foreach ($keys as $key): ?>
                    <td><?php echo htmlspecialchars($row[$key] ?? '-'); ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="text-align:center;padding:40px;color:#999;">No data available for this report.</p>
    <?php endif; ?>

    <div class="footer-info">Total Records: <?php echo count($rows); ?> | PWMS Report</div>
</body>
</html>
<?php
    exit;
}
