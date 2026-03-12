<?php
$pageTitle = "Edit Worker";
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: workers.php");
    exit;
}

// Fetch worker
$stmt = $conn->prepare("SELECT * FROM workers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$worker = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$worker) {
    setFlash('danger', 'Worker not found.');
    header("Location: workers.php");
    exit;
}

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

    if (empty($name) || empty($phone)) {
        setFlash('danger', 'Name and phone are required.');
    } else {
        // Check duplicate phone (exclude current)
        $check = $conn->prepare("SELECT id FROM workers WHERE phone = ? AND id != ?");
        $check->bind_param("si", $phone, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            setFlash('danger', 'Another worker with this phone number exists.');
        } else {
            $stmt = $conn->prepare("UPDATE workers SET name=?, phone=?, email=?, role=?, party_position=?, constituency=?, ward=?, booth=?, responsibility_type=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssssssi", $name, $phone, $email, $role, $party_position, $constituency, $ward, $booth, $responsibility_type, $status, $id);
            if ($stmt->execute()) {
                setFlash('success', 'Worker updated successfully.');
                header("Location: workers.php");
                exit;
            } else {
                setFlash('danger', 'Failed to update worker.');
            }
            $stmt->close();
        }
        $check->close();
    }
    // Keep POSTed values for form
    $worker = array_merge($worker, $_POST);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-user-edit"></i> Edit Worker</h4>
    <a href="workers.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="card-box">
    <form method="POST">
        <?php csrfField(); ?>
        <div class="form-row">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" class="form-control" required
                    value="<?php echo htmlspecialchars($worker['name']); ?>">
            </div>
            <div class="form-group">
                <label>Phone *</label>
                <input type="text" name="phone" class="form-control" required maxlength="15"
                    value="<?php echo htmlspecialchars($worker['phone']); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control"
                    value="<?php echo htmlspecialchars($worker['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <?php foreach (['Volunteer', 'Booth President', 'Mandal Head', 'District Head', 'Other'] as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo $worker['role'] === $r ? 'selected' : ''; ?>>
                            <?php echo $r; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Party Position</label>
                <input type="text" name="party_position" class="form-control"
                    value="<?php echo htmlspecialchars($worker['party_position'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Responsibility Type</label>
                <select name="responsibility_type" class="form-control">
                    <?php foreach (['Door-to-door', 'Social Media', 'Event Management', 'Data Collection', 'Call Outreach', 'Other'] as $rt): ?>
                        <option value="<?php echo $rt; ?>" <?php echo ($worker['responsibility_type'] ?? '') === $rt ? 'selected' : ''; ?>><?php echo $rt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Constituency</label>
                <input type="text" name="constituency" class="form-control"
                    value="<?php echo htmlspecialchars($worker['constituency'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Ward</label>
                <input type="text" name="ward" class="form-control"
                    value="<?php echo htmlspecialchars($worker['ward'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Booth</label>
                <input type="text" name="booth" class="form-control"
                    value="<?php echo htmlspecialchars($worker['booth'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="Active" <?php echo $worker['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $worker['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive
                    </option>
                </select>
            </div>
        </div>
        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Worker</button>
            <a href="workers.php" class="btn btn-outline" style="margin-left:10px;">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>