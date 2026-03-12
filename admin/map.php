<?php
$pageTitle = "Live Worker Map";
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

// Get all recent check-ins (last 24 hours by default, or filtered)
$filterHours = isset($_GET['hours']) ? max(1, min(168, (int)$_GET['hours'])) : 24;
$filterWorker = $_GET['worker'] ?? '';

$where = "WHERE wc.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
$params = [$filterHours];
$types = "i";

if (!empty($filterWorker)) {
    $where .= " AND w.name LIKE ?";
    $params[] = "%$filterWorker%";
    $types .= "s";
}

// Get latest check-in per worker for markers
$sql = "SELECT wc.id, wc.latitude, wc.longitude, wc.location_name, wc.type, wc.created_at,
               w.name as worker_name, w.phone, w.constituency, w.ward, w.booth
        FROM worker_checkins wc
        JOIN workers w ON wc.worker_id = w.id
        $where
        ORDER BY wc.created_at DESC
        LIMIT 500";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$checkins = $stmt->get_result();

$markers = [];
$seenWorkers = [];
while ($row = $checkins->fetch_assoc()) {
    $markers[] = [
        'lat' => (float)$row['latitude'],
        'lng' => (float)$row['longitude'],
        'name' => $row['worker_name'],
        'phone' => $row['phone'],
        'type' => $row['type'],
        'location' => $row['location_name'] ?: 'N/A',
        'area' => implode(' / ', array_filter([$row['constituency'], $row['ward'], $row['booth']])),
        'time' => date('d M Y, h:i A', strtotime($row['created_at'])),
        'isLatest' => !isset($seenWorkers[$row['worker_name']])
    ];
    $seenWorkers[$row['worker_name']] = true;
}

// Get booth locations
$boothsSql = "SELECT booth_name, booth_code, constituency, ward, latitude, longitude FROM areas WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
$boothsResult = $conn->query($boothsSql);
$booths = [];
while ($b = $boothsResult->fetch_assoc()) {
    $booths[] = [
        'lat' => (float)$b['latitude'],
        'lng' => (float)$b['longitude'],
        'name' => $b['booth_name'] ?: $b['booth_code'],
        'area' => implode(' / ', array_filter([$b['constituency'], $b['ward']])
    )];
}

// Stats
$uniqueWorkers = count($seenWorkers);
$totalPins = count($markers);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-globe-asia"></i> Live Worker Map</h4>
</div>

<!-- Filter Bar -->
<div class="card-box">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Time Range</label>
            <select name="hours" class="form-control">
                <option value="1" <?php echo $filterHours == 1 ? 'selected' : ''; ?>>Last 1 hour</option>
                <option value="6" <?php echo $filterHours == 6 ? 'selected' : ''; ?>>Last 6 hours</option>
                <option value="24" <?php echo $filterHours == 24 ? 'selected' : ''; ?>>Last 24 hours</option>
                <option value="48" <?php echo $filterHours == 48 ? 'selected' : ''; ?>>Last 48 hours</option>
                <option value="168" <?php echo $filterHours == 168 ? 'selected' : ''; ?>>Last 7 days</option>
            </select>
        </div>
        <div class="form-group">
            <label>Worker Name</label>
            <input type="text" name="worker" class="form-control" placeholder="Search worker..." value="<?php echo htmlspecialchars($filterWorker); ?>">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="map.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>
</div>

<!-- Map Stats -->
<div class="stat-cards" style="margin-bottom:20px;">
    <div class="stat-card blue">
        <div class="stat-number"><?php echo $uniqueWorkers; ?></div>
        <div class="stat-label">Active Workers</div>
        <div class="stat-icon"><i class="fas fa-users"></i></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-number"><?php echo $totalPins; ?></div>
        <div class="stat-label">Check-in Points</div>
        <div class="stat-icon"><i class="fas fa-map-pin"></i></div>
    </div>
    <div class="stat-card green">
        <div class="stat-number"><?php echo count($booths); ?></div>
        <div class="stat-label">Booth Locations</div>
        <div class="stat-icon"><i class="fas fa-building"></i></div>
    </div>
</div>

<!-- Map -->
<div class="card-box" style="padding:0;overflow:hidden;">
    <div class="map-toolbar">
        <span><i class="fas fa-globe-asia"></i> <strong>Worker Heatmap</strong> — last <?php echo $filterHours; ?>h</span>
        <div class="map-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#059669;"></span> Check-in</span>
            <span class="legend-item"><span class="legend-dot" style="background:#dc2626;"></span> Check-out</span>
            <span class="legend-item"><span class="legend-dot" style="background:#0f4c75;"></span> Booth</span>
        </div>
    </div>
    <div id="worker-map" style="height:520px;width:100%;"></div>
</div>

<!-- Leaflet CSS & JS (free, no API key) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Leaflet heatmap plugin -->
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<script>
(function() {
    var markers = <?php echo json_encode($markers); ?>;
    var booths = <?php echo json_encode($booths); ?>;

    // Calculate center from data
    var allLats = markers.map(function(m){ return m.lat; }).concat(booths.map(function(b){ return b.lat; }));
    var allLngs = markers.map(function(m){ return m.lng; }).concat(booths.map(function(b){ return b.lng; }));

    var centerLat = allLats.length ? allLats.reduce(function(a,b){ return a+b; }, 0) / allLats.length : 23.03;
    var centerLng = allLngs.length ? allLngs.reduce(function(a,b){ return a+b; }, 0) / allLngs.length : 72.58;

    var map = L.map('worker-map').setView([centerLat, centerLng], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // Heatmap layer from check-in data
    if (markers.length > 0) {
        var heatData = markers.map(function(m) {
            return [m.lat, m.lng, 0.6];
        });
        L.heatLayer(heatData, {
            radius: 25,
            blur: 15,
            maxZoom: 15,
            gradient: {0.2: '#0f4c75', 0.4: '#0284c7', 0.6: '#e8702a', 0.8: '#f59e0b', 1.0: '#dc2626'}
        }).addTo(map);
    }

    // Worker check-in markers
    markers.forEach(function(m) {
        var color = m.type === 'check-in' ? '#059669' : '#dc2626';
        var opacity = m.isLatest ? 1 : 0.5;
        var radius = m.isLatest ? 8 : 5;

        var circle = L.circleMarker([m.lat, m.lng], {
            radius: radius,
            fillColor: color,
            color: '#fff',
            weight: 2,
            opacity: opacity,
            fillOpacity: opacity * 0.85
        }).addTo(map);

        circle.bindPopup(
            '<div style="font-family:Inter,sans-serif;min-width:180px;">' +
            '<strong style="font-size:14px;">' + m.name + '</strong><br>' +
            '<span style="color:#64748b;font-size:12px;">' + m.phone + '</span><br>' +
            '<hr style="margin:6px 0;border:0;border-top:1px solid #e2e6ec;">' +
            '<div style="font-size:12px;"><strong>Type:</strong> ' + m.type.charAt(0).toUpperCase() + m.type.slice(1) + '</div>' +
            '<div style="font-size:12px;"><strong>Location:</strong> ' + m.location + '</div>' +
            '<div style="font-size:12px;"><strong>Area:</strong> ' + m.area + '</div>' +
            '<div style="font-size:12px;color:#94a3b8;margin-top:4px;"><i class="fas fa-clock"></i> ' + m.time + '</div>' +
            '</div>'
        );
    });

    // Booth markers
    booths.forEach(function(b) {
        var boothIcon = L.divIcon({
            className: 'booth-map-icon',
            html: '<i class="fas fa-building"></i>',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });

        L.marker([b.lat, b.lng], {icon: boothIcon}).addTo(map)
            .bindPopup(
                '<div style="font-family:Inter,sans-serif;">' +
                '<strong style="color:#0f4c75;">' + b.name + '</strong><br>' +
                '<span style="font-size:12px;color:#64748b;">' + b.area + '</span>' +
                '</div>'
            );

        // 500m radius circle for geofence visualization
        L.circle([b.lat, b.lng], {
            radius: 500,
            color: '#0f4c75',
            fillColor: '#0f4c75',
            fillOpacity: 0.06,
            weight: 1,
            dashArray: '5, 5'
        }).addTo(map);
    });

    // Fit bounds if we have data
    if (allLats.length > 1) {
        var bounds = L.latLngBounds(
            allLats.map(function(lat, i){ return [lat, allLngs[i]]; })
        );
        map.fitBounds(bounds.pad(0.15));
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
