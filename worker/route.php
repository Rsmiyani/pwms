<?php
$pageTitle = "Route Planner";
require_once __DIR__ . '/../config/auth.php';
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
    echo '<div class="card-box"><div class="empty-state"><i class="fas fa-info-circle"></i>No worker profile linked.</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$workerId = $worker['id'];

// Get pending/in-progress task locations
$stmt = $conn->prepare("
    SELECT ta.id as assignment_id, t.title, t.booth, t.ward, t.constituency, t.priority,
           a.latitude, a.longitude, a.booth_name, a.booth_code
    FROM task_assignments ta
    JOIN tasks t ON ta.task_id = t.id
    LEFT JOIN areas a ON t.booth = a.booth_name
    WHERE ta.worker_id = ? AND ta.status IN ('Pending', 'In Progress')
    AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL
    ORDER BY FIELD(t.priority, 'High', 'Medium', 'Low')
");
$stmt->bind_param("i", $workerId);
$stmt->execute();
$tasks = $stmt->get_result();

$taskLocations = [];
while ($t = $tasks->fetch_assoc()) {
    $taskLocations[] = [
        'lat' => (float)$t['latitude'],
        'lng' => (float)$t['longitude'],
        'title' => $t['title'],
        'booth' => $t['booth_name'] ?: $t['booth'],
        'priority' => $t['priority'],
        'area' => implode(' / ', array_filter([$t['constituency'], $t['ward']]))
    ];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-route"></i> Route Planner</h4>
</div>

<?php if (empty($taskLocations)): ?>
<div class="card-box">
    <div class="empty-state">
        <i class="fas fa-route"></i>
        No pending tasks with booth locations found.<br>
        <small>Tasks must be assigned to booths that have GPS coordinates set.</small>
    </div>
</div>
<?php else: ?>

<div class="card-box">
    <h5><i class="fas fa-info-circle"></i> Route Instructions</h5>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:10px;">
        The map below shows the optimized route between your assigned task locations.
        Click <strong>"Get My Location"</strong> to start the route from your current position,
        or the route will start from the first task location.
    </p>
    <button type="button" id="locate-me-btn" class="btn btn-primary btn-sm" onclick="startFromMyLocation()">
        <i class="fas fa-crosshairs"></i> Get My Location &amp; Optimize Route
    </button>
    <span id="route-status" style="margin-left:12px;font-size:13px;color:var(--text-muted);"></span>
</div>

<!-- Task Stop List -->
<div class="card-box">
    <h5><i class="fas fa-list-ol"></i> Task Stops (<?php echo count($taskLocations); ?>)</h5>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Task</th>
                    <th>Booth</th>
                    <th>Priority</th>
                    <th>Area</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($taskLocations as $idx => $loc): ?>
                <tr>
                    <td><strong><?php echo $idx + 1; ?></strong></td>
                    <td><?php echo htmlspecialchars($loc['title']); ?></td>
                    <td><?php echo htmlspecialchars($loc['booth']); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($loc['priority']); ?>"><?php echo $loc['priority']; ?></span></td>
                    <td style="font-size:12px;color:var(--text-secondary);"><?php echo htmlspecialchars($loc['area']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Route Map -->
<div class="card-box" style="padding:0;overflow:hidden;">
    <div class="map-toolbar">
        <span><i class="fas fa-route"></i> <strong>Optimized Route</strong></span>
        <div class="map-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#059669;"></span> Start</span>
            <span class="legend-item"><span class="legend-dot" style="background:#e8702a;"></span> Task Stop</span>
            <span class="legend-item"><span class="legend-dot" style="background:#0284c7;"></span> You</span>
        </div>
    </div>
    <div id="route-map" style="height:500px;width:100%;"></div>
</div>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Leaflet Routing Machine (uses OSRM — free, no API key) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>

<script>
(function() {
    var stops = <?php echo json_encode($taskLocations); ?>;
    if (!stops.length) return;

    // Nearest-neighbor sort for route optimization
    function optimizeRoute(points, startIdx) {
        var visited = [startIdx];
        var current = startIdx;
        while (visited.length < points.length) {
            var nearest = -1, minDist = Infinity;
            for (var i = 0; i < points.length; i++) {
                if (visited.indexOf(i) !== -1) continue;
                var d = haversine(points[current].lat, points[current].lng, points[i].lat, points[i].lng);
                if (d < minDist) { minDist = d; nearest = i; }
            }
            if (nearest === -1) break;
            visited.push(nearest);
            current = nearest;
        }
        return visited;
    }

    function haversine(lat1, lng1, lat2, lng2) {
        var R = 6371000;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLng/2) * Math.sin(dLng/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    var center = [stops[0].lat, stops[0].lng];
    var map = L.map('route-map').setView(center, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    var routeControl = null;
    var userMarker = null;

    function buildRoute(startLat, startLng, isUserLoc) {
        // Combine start + task stops
        var allPoints = [];
        if (isUserLoc) {
            allPoints.push({lat: startLat, lng: startLng, title: 'Your Location', booth: '', priority: '', isUser: true});
        }
        stops.forEach(function(s) { allPoints.push(s); });

        // Optimize from first point
        var order = optimizeRoute(allPoints, 0);
        var waypoints = order.map(function(idx) {
            return L.latLng(allPoints[idx].lat, allPoints[idx].lng);
        });

        // Remove old route
        if (routeControl) map.removeControl(routeControl);

        routeControl = L.Routing.control({
            waypoints: waypoints,
            routeWhileDragging: false,
            addWaypoints: false,
            show: false,
            createMarker: function(i, wp) {
                var pt = allPoints[order[i]];
                var color = pt.isUser ? '#0284c7' : (i === 0 && !isUserLoc ? '#059669' : '#e8702a');
                var label = pt.isUser ? '<i class="fas fa-user"></i>' : (i + (isUserLoc ? 0 : 1));

                var icon = L.divIcon({
                    className: 'route-stop-icon',
                    html: '<div style="background:' + color + ';color:#fff;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3);">' + label + '</div>',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });

                return L.marker(wp.latLng, {icon: icon}).bindPopup(
                    '<strong>' + pt.title + '</strong>' +
                    (pt.booth ? '<br><span style="font-size:12px;">' + pt.booth + '</span>' : '') +
                    (pt.priority ? '<br><span class="badge badge-' + pt.priority.toLowerCase() + '">' + pt.priority + '</span>' : '')
                );
            },
            lineOptions: {
                styles: [{color: '#0f4c75', opacity: 0.8, weight: 5}],
                missingRouteStyles: [{color: '#e8702a', opacity: 0.5, weight: 3, dashArray: '10, 10'}]
            }
        }).addTo(map);

        routeControl.on('routesfound', function(e) {
            var route = e.routes[0];
            var distKm = (route.summary.totalDistance / 1000).toFixed(1);
            var timeMin = Math.round(route.summary.totalTime / 60);
            document.getElementById('route-status').innerHTML =
                '<i class="fas fa-road" style="color:var(--accent);"></i> <strong>' + distKm + ' km</strong> &middot; ~<strong>' + timeMin + ' min</strong> drive';
        });
    }

    // Build initial route from first task
    buildRoute(stops[0].lat, stops[0].lng, false);

    // Expose for "start from my location" button
    window.startFromMyLocation = function() {
        var btn = document.getElementById('locate-me-btn');
        var status = document.getElementById('route-status');
        btn.disabled = true;
        status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting location...';

        if (!navigator.geolocation) {
            status.innerHTML = '<span style="color:var(--danger);">Geolocation not supported.</span>';
            btn.disabled = false;
            return;
        }

        navigator.geolocation.getCurrentPosition(function(pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;

            if (userMarker) map.removeLayer(userMarker);
            userMarker = L.circleMarker([lat, lng], {
                radius: 10, fillColor: '#0284c7', color: '#fff', weight: 3, fillOpacity: 1
            }).addTo(map).bindPopup('<strong>Your Location</strong>').openPopup();

            buildRoute(lat, lng, true);
            btn.disabled = false;
        }, function() {
            status.innerHTML = '<span style="color:var(--danger);">Could not get location.</span>';
            btn.disabled = false;
        }, {enableHighAccuracy: true, timeout: 15000});
    };
})();
</script>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
