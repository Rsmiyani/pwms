<?php
$pageTitle = "Add Worker";
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'Volunteer');
    $party_position = trim($_POST['party_position'] ?? '');
    $constituency = trim($_POST['constituency'] ?? '');
    $ward = trim($_POST['ward'] ?? '');
    $booth = trim($_POST['booth'] ?? '');
    $allowedResponsibilities = ['Door-to-door', 'Social Media', 'Event Management', 'Data Collection', 'Call Outreach', 'Other'];
    $responsibility_type = in_array($_POST['responsibility_type'] ?? '', $allowedResponsibilities) ? $_POST['responsibility_type'] : 'Door-to-door';
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';
    $create_login = isset($_POST['create_login']);
    $password = trim($_POST['password'] ?? '');

    if (empty($name) || empty($phone)) {
        setFlash('danger', 'Name and phone are required.');
    } elseif ($create_login && !empty($password) && strlen($password) < MIN_PASSWORD_LENGTH) {
        setFlash('danger', 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.');
    } else {
        // Check duplicate phone
        $check = $conn->prepare("SELECT id FROM workers WHERE phone = ?");
        $check->bind_param("s", $phone);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            setFlash('danger', 'A worker with this phone number already exists.');
        } else {
            $user_id = null;

            // Create user login if requested
            if ($create_login && !empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $userStmt = $conn->prepare("INSERT INTO users (name, phone, email, password, role) VALUES (?, ?, ?, ?, 'worker')");
                $userStmt->bind_param("ssss", $name, $phone, $email, $hashed);
                $userStmt->execute();
                $user_id = $conn->insert_id;
                $userStmt->close();
            }

            $stmt = $conn->prepare("INSERT INTO workers (user_id, name, phone, email, role, party_position, constituency, ward, booth, responsibility_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssssss", $user_id, $name, $phone, $email, $role, $party_position, $constituency, $ward, $booth, $responsibility_type, $status);
            if ($stmt->execute()) {
                setFlash('success', 'Worker added successfully.');
                header("Location: workers.php");
                exit;
            } else {
                setFlash('danger', 'Failed to add worker.');
            }
            $stmt->close();
        }
        $check->close();
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-user-plus"></i> Add New Worker</h4>
    <a href="workers.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="card-box">
    <form method="POST">
        <?php csrfField(); ?>
        <div class="form-row">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" class="form-control" required
                    value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Phone *</label>
                <input type="text" name="phone" class="form-control" required maxlength="15"
                    value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="Volunteer">Volunteer</option>
                    <option value="Booth President">Booth President</option>
                    <option value="Mandal Head">Mandal Head</option>
                    <option value="District Head">District Head</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Party Position</label>
                <input type="text" name="party_position" class="form-control" placeholder="e.g., President, Secretary"
                    value="<?php echo htmlspecialchars($_POST['party_position'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Responsibility Type</label>
                <select name="responsibility_type" class="form-control">
                    <option value="Door-to-door">Door-to-door</option>
                    <option value="Social Media">Social Media</option>
                    <option value="Event Management">Event Management</option>
                    <option value="Data Collection">Data Collection</option>
                    <option value="Call Outreach">Call Outreach</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Constituency</label>
                <input type="text" name="constituency" class="form-control"
                    value="<?php echo htmlspecialchars($_POST['constituency'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Ward</label>
                <input type="text" name="ward" class="form-control"
                    value="<?php echo htmlspecialchars($_POST['ward'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Booth</label>
                <input type="text" name="booth" class="form-control"
                    value="<?php echo htmlspecialchars($_POST['booth'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
        </div>

        <hr style="margin:20px 0;border:none;border-top:1px solid #eee;">
        <h5 style="margin-bottom:15px;color:#555;"><i class="fas fa-key"></i> Login Access (Optional)</h5>

        <div class="form-row">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="create_login" id="createLogin"
                        onchange="document.getElementById('passwordField').style.display = this.checked ? 'block' : 'none'">
                    Create login account for this worker
                </label>
            </div>
            <div class="form-group" id="passwordField" style="display:none;">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Set password for worker login">
            </div>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Worker</button>
            <a href="workers.php" class="btn btn-outline" style="margin-left:10px;">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>