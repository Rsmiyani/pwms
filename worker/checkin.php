<?php
$pageTitle = "Check-In / Check-Out";
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/gamification.php';
requireLogin();

$userId = $_SESSION['user_id'];
$workerStmt = $conn->prepare("SELECT id, name, booth FROM workers WHERE user_id = ?");
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

// Get assigned booth coordinates for geo-fencing
$boothLat = null;
$boothLng = null;
$boothName = $workerRow['booth'] ?? '';
$geofenceEnabled = false;
$geofenceRadius = 500; // meters

if (!empty($boothName)) {
    $bStmt = $conn->prepare("SELECT latitude, longitude, booth_name FROM areas WHERE booth_name = ? AND latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 1");
    $bStmt->bind_param("s", $boothName);
    $bStmt->execute();
    $bRow = $bStmt->get_result()->fetch_assoc();
    $bStmt->close();
    if ($bRow) {
        $boothLat = (float)$bRow['latitude'];
        $boothLng = (float)$bRow['longitude'];
        $geofenceEnabled = true;
    }
}

// Handle check-in/check-out POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $lat = floatval($_POST['latitude'] ?? 0);
    $lng = floatval($_POST['longitude'] ?? 0);
    $locName = trim($_POST['location_name'] ?? '');
    $type = $_POST['type'] ?? 'check-in';

    if ($lat == 0 && $lng == 0) {
        setFlash('danger', 'Could not get your location. Please allow GPS access.');
    } else {
        // Geo-fence validation
        if ($geofenceEnabled && $boothLat !== null) {
            $distance = haversineDistance($lat, $lng, $boothLat, $boothLng);
            if ($distance > $geofenceRadius) {
                $distKm = round($distance / 1000, 2);
                setFlash('danger', "You are {$distKm} km away from your assigned booth ({$boothName}). Check-in is only allowed within {$geofenceRadius}m.");
                header("Location: checkin.php");
                exit;
            }
        }

        if (!in_array($type, ['check-in', 'check-out'])) $type = 'check-in';
        $stmt = $conn->prepare("INSERT INTO worker_checkins (worker_id, latitude, longitude, location_name, type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iddss", $workerId, $lat, $lng, $locName, $type);
        $stmt->execute();
        $checkinId = $conn->insert_id;
        $stmt->close();

        // Award points for check-in
        awardPoints($workerId, PTS_CHECKIN, ucfirst($type) . ' recorded', 'checkin', $checkinId);

        setFlash('success', ucfirst($type) . ' recorded successfully!');
    }
    header("Location: checkin.php");
    exit;
}

/**
 * Haversine distance in meters between two lat/lng points
 */
function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000; // Earth radius in meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

// Get recent check-ins
$history = $conn->prepare("SELECT * FROM worker_checkins WHERE worker_id = ? ORDER BY created_at DESC LIMIT 20");
$history->bind_param("i", $workerId);
$history->execute();
$checkins = $history->get_result();

// Get last action to suggest next
$lastAction = $conn->prepare("SELECT type FROM worker_checkins WHERE worker_id = ? ORDER BY created_at DESC LIMIT 1");
$lastAction->bind_param("i", $workerId);
$lastAction->execute();
$lastRow = $lastAction->get_result()->fetch_assoc();
$suggestedType = ($lastRow && $lastRow['type'] === 'check-in') ? 'check-out' : 'check-in';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-map-marker-alt"></i> Check-In / Check-Out</h4>
</div>

<!-- Check-in Card -->
<div class="card-box">
    <h5><i class="fas fa-satellite-dish"></i> Record Your Location</h5>

    <?php if ($geofenceEnabled): ?>
    <div class="alert alert-info" data-persist style="display:flex;align-items:center;gap:10px;">
        <i class="fas fa-shield-alt"></i>
        <span>Geo-fence active: You must be within <strong><?php echo $geofenceRadius; ?>m</strong> of <strong><?php echo htmlspecialchars($boothName); ?></strong> to check in.</span>
    </div>
    <?php endif; ?>

    <div id="geo-status" class="alert alert-warning" style="display:none;" data-persist>
        <i class="fas fa-spinner fa-spin"></i> <span id="geo-msg">Getting your location...</span>
    </div>
    <div id="fence-status" class="alert" style="display:none;" data-persist>
        <span id="fence-msg"></span>
    </div>

    <form method="POST" id="checkin-form">
        <?php csrfField(); ?>
        <input type="hidden" name="latitude" id="latitude" value="0">
        <input type="hidden" name="longitude" id="longitude" value="0">

        <div class="form-row">
            <div class="form-group">
                <label>Type</label>
                <select name="type" class="form-control">
                    <option value="check-in" <?php echo $suggestedType === 'check-in' ? 'selected' : ''; ?>>Check-In</option>
                    <option value="check-out" <?php echo $suggestedType === 'check-out' ? 'selected' : ''; ?>>Check-Out</option>
                </select>
            </div>
            <div class="form-group">
                <label>Location Name (optional)</label>
                <input type="text" name="location_name" class="form-control" placeholder="e.g. Booth #3, Ward Office">
            </div>
        </div>

        <div id="location-preview" style="display:none;margin-bottom:15px;padding:12px;background:#f0f2f5;border-radius:8px;">
            <strong><i class="fas fa-map-pin"></i> Detected Coordinates:</strong>
            <span id="coords-display">-</span>
        </div>

        <button type="button" id="get-location-btn" class="btn btn-primary" onclick="getLocation()">
            <i class="fas fa-crosshairs"></i> Get My Location
        </button>
        <button type="submit" id="submit-btn" class="btn btn-success" disabled>
            <i class="fas fa-check"></i> Submit
        </button>
    </form>

    <?php if ($geofenceEnabled): ?>
    <div id="checkin-map" style="height:250px;border-radius:10px;margin-top:18px;border:1.5px solid var(--border);display:none;"></div>
    <?php endif; ?>
</div>

<!-- History -->
<div class="card-box">
    <h5><i class="fas fa-history"></i> Recent Check-ins</h5>
    <?php if ($checkins->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Location Name</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($c = $checkins->fetch_assoc()): ?>
                <tr>
                    <td>
                        <span class="badge badge-<?php echo $c['type'] === 'check-in' ? 'active' : 'inactive'; ?>">
                            <i class="fas fa-<?php echo $c['type'] === 'check-in' ? 'sign-in-alt' : 'sign-out-alt'; ?>"></i>
                            <?php echo ucfirst($c['type']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($c['location_name'] ?: '-'); ?></td>
                    <td><?php echo $c['latitude']; ?></td>
                    <td><?php echo $c['longitude']; ?></td>
                    <td><?php echo date('d M Y, h:i A', strtotime($c['created_at'])); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-map"></i>No check-ins recorded yet.</div>
    <?php endif; ?>
</div>

<?php if ($geofenceEnabled): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>

<script>
var boothLat = <?php echo $geofenceEnabled ? $boothLat : 'null'; ?>;
var boothLng = <?php echo $geofenceEnabled ? $boothLng : 'null'; ?>;
var fenceRadius = <?php echo $geofenceRadius; ?>;
var geofenceOn = <?php echo $geofenceEnabled ? 'true' : 'false'; ?>;
var miniMap = null;

function haversineDist(lat1, lng1, lat2, lng2) {
    var R = 6371000;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLng = (lng2 - lng1) * Math.PI / 180;
    var a = Math.sin(dLat/2)*Math.sin(dLat/2) +
            Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*
            Math.sin(dLng/2)*Math.sin(dLng/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function showMiniMap(lat, lng) {
    var mapDiv = document.getElementById('checkin-map');
    if (!mapDiv || !window.L) return;
    mapDiv.style.display = 'block';
    if (miniMap) { miniMap.remove(); }
    miniMap = L.map('checkin-map').setView([boothLat, boothLng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(miniMap);
    L.circle([boothLat, boothLng], { radius: fenceRadius, color: '#e67e22', fillOpacity: 0.12, weight: 2 }).addTo(miniMap);
    L.marker([boothLat, boothLng]).addTo(miniMap).bindPopup('Booth Location');
    var youIcon = L.divIcon({ className: 'you-marker', html: '<i class="fas fa-user" style="color:#0d6efd;font-size:18px;"></i>', iconSize: [24, 24], iconAnchor: [12, 12] });
    L.marker([lat, lng], { icon: youIcon }).addTo(miniMap).bindPopup('You are here');
    miniMap.fitBounds([[boothLat, boothLng], [lat, lng]], { padding: [40, 40] });
}

function getLocation() {
    var statusDiv = document.getElementById('geo-status');
    var msgSpan = document.getElementById('geo-msg');
    var fenceDiv = document.getElementById('fence-status');
    var fenceMsg = document.getElementById('fence-msg');
    var btn = document.getElementById('get-location-btn');

    statusDiv.style.display = 'flex';
    statusDiv.className = 'alert alert-warning';
    msgSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting your location...';
    btn.disabled = true;
    if (fenceDiv) fenceDiv.style.display = 'none';

    if (!navigator.geolocation) {
        statusDiv.className = 'alert alert-danger';
        msgSpan.textContent = 'Geolocation is not supported by your browser.';
        btn.disabled = false;
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(position) {
            var lat = position.coords.latitude;
            var lng = position.coords.longitude;

            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('coords-display').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
            document.getElementById('location-preview').style.display = 'block';

            statusDiv.className = 'alert alert-success';
            msgSpan.innerHTML = '<i class="fas fa-check-circle"></i> Location detected!';

            if (geofenceOn && boothLat !== null) {
                var dist = haversineDist(lat, lng, boothLat, boothLng);
                fenceDiv.style.display = 'flex';
                if (dist <= fenceRadius) {
                    fenceDiv.className = 'alert alert-success';
                    fenceMsg.innerHTML = '<i class="fas fa-check-circle"></i> You are <strong>' + Math.round(dist) + 'm</strong> from your booth — within geo-fence. Check-in allowed!';
                    document.getElementById('submit-btn').disabled = false;
                } else {
                    fenceDiv.className = 'alert alert-danger';
                    fenceMsg.innerHTML = '<i class="fas fa-times-circle"></i> You are <strong>' + (dist >= 1000 ? (dist/1000).toFixed(2) + ' km' : Math.round(dist) + 'm') + '</strong> from your booth. Must be within ' + fenceRadius + 'm.';
                    document.getElementById('submit-btn').disabled = true;
                }
                showMiniMap(lat, lng);
            } else {
                document.getElementById('submit-btn').disabled = false;
            }
            btn.disabled = false;
        },
        function(error) {
            statusDiv.className = 'alert alert-danger';
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    msgSpan.textContent = 'Location access denied. Please allow GPS in browser settings.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    msgSpan.textContent = 'Location unavailable. Please try again.';
                    break;
                case error.TIMEOUT:
                    msgSpan.textContent = 'Location request timed out. Please try again.';
                    break;
                default:
                    msgSpan.textContent = 'Unable to get location.';
            }
            btn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
