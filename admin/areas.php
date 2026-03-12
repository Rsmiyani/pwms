<?php
$pageTitle = "Areas & Booths";
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    if ($_POST['action'] === 'add') {
        $constituency = trim($_POST['constituency'] ?? '');
        $ward = trim($_POST['ward'] ?? '');
        $booth_code = trim($_POST['booth_code'] ?? '');
        $booth_name = trim($_POST['booth_name'] ?? '');
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $latitude = $latitude !== '' ? floatval($latitude) : null;
        $longitude = $longitude !== '' ? floatval($longitude) : null;

        if (empty($constituency)) {
            setFlash('danger', 'Constituency is required.');
        } else {
            $stmt = $conn->prepare("INSERT INTO areas (constituency, ward, booth_code, booth_name, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdd", $constituency, $ward, $booth_code, $booth_name, $latitude, $longitude);
            if ($stmt->execute()) {
                setFlash('success', 'Area added successfully.');
            } else {
                setFlash('danger', 'Failed to add area.');
            }
            $stmt->close();
        }
        header("Location: areas.php");
        exit;
    }
}

// Handle delete (POST only with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verifyCsrf();
    $id = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM areas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    setFlash('success', 'Area deleted.');
    header("Location: areas.php");
    exit;
}

// Fetch areas
$areas = $conn->query("SELECT * FROM areas ORDER BY constituency, ward, booth_code");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-map-marker-alt"></i> Areas & Booths</h4>
</div>

<!-- Add Area Form -->
<div class="card-box">
    <h5><i class="fas fa-plus-circle"></i> Add New Area</h5>
    <form method="POST" class="filter-bar">
        <input type="hidden" name="action" value="add">
        <?php csrfField(); ?>
        <div class="form-group">
            <label>Constituency *</label>
            <input type="text" name="constituency" class="form-control" required placeholder="e.g., Constituency A">
        </div>
        <div class="form-group">
            <label>Ward</label>
            <input type="text" name="ward" class="form-control" placeholder="e.g., Ward 1">
        </div>
        <div class="form-group">
            <label>Booth Code</label>
            <input type="text" name="booth_code" class="form-control" placeholder="e.g., B001">
        </div>
        <div class="form-group">
            <label>Booth Name</label>
            <input type="text" name="booth_name" class="form-control" placeholder="e.g., Booth 1">
        </div>
        <div class="form-group">
            <label>Latitude</label>
            <input type="number" step="any" name="latitude" id="area-lat" class="form-control" placeholder="e.g., 23.0225">
        </div>
        <div class="form-group">
            <label>Longitude</label>
            <input type="number" step="any" name="longitude" id="area-lng" class="form-control" placeholder="e.g., 72.5714">
        </div>
        <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;">
            <button type="button" class="btn btn-secondary" id="fetch-loc-btn" onclick="fetchLocation()">
                <i class="fas fa-crosshairs"></i> Auto-detect Location
            </button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
        </div>
    </form>
    <div id="loc-status" style="display:none;margin-top:10px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>
    <div id="area-preview-map" style="display:none;height:220px;border-radius:10px;margin-top:12px;border:1.5px solid var(--border);"></div>
</div>

<!-- Areas Table -->
<div class="card-box">
    <h5><i class="fas fa-list"></i> All Areas</h5>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Constituency</th>
                    <th>Ward</th>
                    <th>Booth Code</th>
                    <th>Booth Name</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($areas->num_rows > 0): ?>
                    <?php $i = 1; while ($row = $areas->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['constituency']); ?></td>
                        <td><?php echo htmlspecialchars($row['ward'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['booth_code'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['booth_name'] ?? '-'); ?></td>
                        <td><?php echo $row['latitude'] ? number_format($row['latitude'], 6) : '-'; ?></td>
                        <td><?php echo $row['longitude'] ? number_format($row['longitude'], 6) : '-'; ?></td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Delete this area?')">
                                <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                <?php csrfField(); ?>
                                <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-state">No areas added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var previewMap = null;
function fetchLocation() {
    var btn = document.getElementById('fetch-loc-btn');
    var status = document.getElementById('loc-status');
    status.style.display = 'block';
    status.style.background = '#fff3cd';
    status.style.color = '#856404';
    status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detecting your location...';
    btn.disabled = true;

    if (!navigator.geolocation) {
        status.style.background = '#f8d7da';
        status.style.color = '#842029';
        status.innerHTML = '<i class="fas fa-times-circle"></i> Geolocation not supported by your browser.';
        btn.disabled = false;
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            document.getElementById('area-lat').value = lat.toFixed(8);
            document.getElementById('area-lng').value = lng.toFixed(8);
            status.style.background = '#d1e7dd';
            status.style.color = '#0f5132';
            status.innerHTML = '<i class="fas fa-check-circle"></i> Location detected: <strong>' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</strong>';
            btn.disabled = false;

            // Show preview map
            var mapDiv = document.getElementById('area-preview-map');
            mapDiv.style.display = 'block';
            if (previewMap) previewMap.remove();
            previewMap = L.map('area-preview-map').setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(previewMap);
            L.marker([lat, lng]).addTo(previewMap).bindPopup('Booth Location').openPopup();
            L.circle([lat, lng], { radius: 500, color: '#e67e22', fillOpacity: 0.1, weight: 2 }).addTo(previewMap);
        },
        function(err) {
            status.style.background = '#f8d7da';
            status.style.color = '#842029';
            var msg = 'Unable to get location.';
            if (err.code === 1) msg = 'Location access denied. Please allow GPS in browser settings.';
            else if (err.code === 2) msg = 'Location unavailable. Try again.';
            else if (err.code === 3) msg = 'Location request timed out. Try again.';
            status.innerHTML = '<i class="fas fa-times-circle"></i> ' + msg;
            btn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
