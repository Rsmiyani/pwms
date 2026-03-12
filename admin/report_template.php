<div class="report-header" style="text-align:center;padding:20px;border-bottom:2px solid #0d4f4f;">
    <h2 style="color:#0d4f4f;margin:0;">PWMS Performance Report</h2>
    <p style="color:#666;margin:4px 0 0;"><?php echo $startDate; ?> to <?php echo $endDate; ?></p>
    <p style="color:#999;font-size:12px;margin:2px 0 0;">Generated on <?php echo date('d M Y, h:i A'); ?></p>
</div>

<div style="display:flex;gap:16px;flex-wrap:wrap;padding:20px 0;">
    <div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#f0f2f5;border-radius:8px;">
        <div style="font-size:24px;font-weight:800;color:#0d4f4f;"><?php echo (int)$overallStats['total_tasks']; ?></div>
        <div style="font-size:11px;color:#666;text-transform:uppercase;">Tasks Assigned</div>
    </div>
    <div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#d1e7dd;border-radius:8px;">
        <div style="font-size:24px;font-weight:800;color:#198754;"><?php echo (int)$overallStats['completed_tasks']; ?></div>
        <div style="font-size:11px;color:#666;text-transform:uppercase;">Completed</div>
    </div>
    <div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#fff3cd;border-radius:8px;">
        <div style="font-size:24px;font-weight:800;color:#856404;"><?php echo (int)$overallStats['total_checkins']; ?></div>
        <div style="font-size:11px;color:#666;text-transform:uppercase;">Check-ins</div>
    </div>
    <div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#e8f4fd;border-radius:8px;">
        <div style="font-size:24px;font-weight:800;color:#0d6efd;"><?php echo (int)$overallStats['active_workers']; ?></div>
        <div style="font-size:11px;color:#666;text-transform:uppercase;">Active Workers</div>
    </div>
</div>

<h3 style="color:#0d4f4f;border-bottom:1px solid #ddd;padding-bottom:8px;">Booth-Level Performance</h3>
<?php if (!empty($boothRows)): ?>
<table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:20px;">
    <thead>
        <tr style="background:#0d4f4f;color:#fff;">
            <th style="padding:8px;text-align:left;">Booth</th>
            <th style="padding:8px;">Workers</th>
            <th style="padding:8px;">Tasks</th>
            <th style="padding:8px;">Completed</th>
            <th style="padding:8px;">Rate</th>
            <th style="padding:8px;">Check-ins</th>
            <th style="padding:8px;">Points</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($boothRows as $b):
            $rate = $b['total_tasks'] > 0 ? round($b['completed_tasks'] / $b['total_tasks'] * 100) : 0;
        ?>
        <tr style="border-bottom:1px solid #eee;">
            <td style="padding:8px;font-weight:600;"><?php echo htmlspecialchars($b['booth']); ?></td>
            <td style="padding:8px;text-align:center;"><?php echo $b['worker_count']; ?></td>
            <td style="padding:8px;text-align:center;"><?php echo $b['total_tasks']; ?></td>
            <td style="padding:8px;text-align:center;"><?php echo $b['completed_tasks']; ?></td>
            <td style="padding:8px;text-align:center;font-weight:700;"><?php echo $rate; ?>%</td>
            <td style="padding:8px;text-align:center;"><?php echo $b['checkin_count']; ?></td>
            <td style="padding:8px;text-align:center;"><?php echo number_format($b['total_points']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:#999;">No booth activity recorded in this period.</p>
<?php endif; ?>

<h3 style="color:#0d4f4f;border-bottom:1px solid #ddd;padding-bottom:8px;">Top Performers</h3>
<?php if (!empty($topRows)): ?>
<table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
        <tr style="background:#0d4f4f;color:#fff;">
            <th style="padding:8px;">#</th>
            <th style="padding:8px;text-align:left;">Worker</th>
            <th style="padding:8px;">Booth</th>
            <th style="padding:8px;">Tasks Done</th>
            <th style="padding:8px;">Check-ins</th>
            <th style="padding:8px;">Points</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; foreach ($topRows as $tp): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td style="padding:8px;text-align:center;font-weight:700;"><?php echo $i++; ?></td>
            <td style="padding:8px;font-weight:600;"><?php echo htmlspecialchars($tp['name']); ?></td>
            <td style="padding:8px;text-align:center;"><?php echo htmlspecialchars($tp['booth'] ?? '-'); ?></td>
            <td style="padding:8px;text-align:center;"><?php echo $tp['tasks_done']; ?></td>
            <td style="padding:8px;text-align:center;"><?php echo $tp['checkins']; ?></td>
            <td style="padding:8px;text-align:center;font-weight:700;"><?php echo number_format($tp['points']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:#999;">No worker activity recorded in this period.</p>
<?php endif; ?>

<div style="margin-top:30px;padding-top:16px;border-top:1px solid #ddd;text-align:center;color:#999;font-size:11px;">
    Party Worker Management System &mdash; Auto-generated Report
</div>
