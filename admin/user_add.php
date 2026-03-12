<?php
$pageTitle = "Add User";
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = in_array($_POST['role'] ?? '', ['admin', 'worker']) ? $_POST['role'] : 'worker';
    $status   = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    if (empty($name) || empty($phone) || empty($password)) {
        setFlash('danger', 'Name, phone and password are required.');
    } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
        setFlash('danger', 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.');
    } else {
        // Check duplicate phone
        $check = $conn->prepare("SELECT id FROM users WHERE phone = ?");
        $check->bind_param("s", $phone);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            setFlash('danger', 'A user with this phone number already exists.');
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, phone, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $phone, $email, $hashed, $role, $status);
            if ($stmt->execute()) {
                setFlash('success', 'User created successfully.');
                header("Location: users.php");
                exit;
            } else {
                setFlash('danger', 'Failed to create user.');
            }
            $stmt->close();
        }
        $check->close();
    }
}

$extraCSS = '
<style>
.ua-wrap {
    max-width:640px; margin:0 auto;
}
.ua-card {
    background:var(--white); border-radius:var(--radius-lg);
    border:1px solid var(--border); overflow:hidden;
    box-shadow:var(--shadow-sm);
}
.ua-card-header {
    background:linear-gradient(135deg, var(--primary), var(--primary-dark));
    color:#fff; padding:24px 28px;
}
.ua-card-header h5 {
    margin:0; font-size:18px; font-weight:700;
    display:flex; align-items:center; gap:10px;
}
.ua-card-header p {
    margin:6px 0 0; font-size:12px; opacity:.8;
}
.ua-card-body {
    padding:28px;
}
.ua-row {
    display:grid; grid-template-columns:1fr 1fr; gap:18px;
    margin-bottom:18px;
}
.ua-field { display:flex; flex-direction:column; }
.ua-field.full { grid-column:1/-1; }
.ua-field label {
    font-size:12px; font-weight:600; color:var(--text-secondary);
    text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px;
}
.ua-field label .req { color:var(--danger); }
.ua-field input,
.ua-field select {
    padding:10px 14px; border:1.5px solid var(--border);
    border-radius:var(--radius); font-size:14px;
    font-family:inherit; background:var(--white);
    transition:border var(--transition-fast);
}
.ua-field input:focus,
.ua-field select:focus {
    border-color:var(--primary); outline:none;
    box-shadow:0 0 0 3px rgba(var(--primary-rgb),.1);
}
.ua-role-cards {
    display:grid; grid-template-columns:1fr 1fr; gap:12px;
    grid-column:1/-1; margin-bottom:18px;
}
.ua-role-card {
    position:relative; cursor:pointer;
    border:2px solid var(--border); border-radius:var(--radius-lg);
    padding:18px 16px; text-align:center;
    transition:all var(--transition);
}
.ua-role-card:hover { border-color:var(--primary); }
.ua-role-card input { display:none; }
.ua-role-card input:checked + .ua-role-inner {
    color:var(--primary);
}
.ua-role-card:has(input:checked) {
    border-color:var(--primary);
    background:var(--primary-light);
    box-shadow:0 0 0 3px rgba(var(--primary-rgb),.1);
}
.ua-role-card:has(input[value="admin"]:checked) {
    border-color:var(--accent);
    background:var(--accent-light);
    box-shadow:0 0 0 3px rgba(var(--accent-rgb),.1);
}
.ua-role-card:has(input[value="admin"]:checked) .ua-role-inner {
    color:var(--accent);
}
.ua-role-icon {
    font-size:28px; margin-bottom:8px;
}
.ua-role-name {
    font-size:14px; font-weight:700;
}
.ua-role-desc {
    font-size:11px; color:var(--text-muted); margin-top:2px;
}
.ua-card-foot {
    padding:0 28px 28px;
    display:flex; gap:10px; justify-content:flex-end;
}
.ua-card-foot .btn { padding:10px 24px; font-size:14px; border-radius:var(--radius); }
.ua-divider {
    border:none; border-top:1px dashed var(--border); margin:6px 0 18px;
    grid-column:1/-1;
}
@media (max-width:600px) {
    .ua-row { grid-template-columns:1fr; }
    .ua-role-cards { grid-template-columns:1fr 1fr; }
}
</style>';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-user-plus"></i> Add New User</h4>
    <a href="users.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="ua-wrap">
    <div class="ua-card">
        <div class="ua-card-header">
            <h5><i class="fas fa-id-card"></i> New User Account</h5>
            <p>Create a standalone user account for system access</p>
        </div>
        <form method="POST">
            <?php csrfField(); ?>
            <div class="ua-card-body">
                <div class="ua-row">
                    <div class="ua-field">
                        <label>Name <span class="req">*</span></label>
                        <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="Full name">
                    </div>
                    <div class="ua-field">
                        <label>Phone <span class="req">*</span></label>
                        <input type="text" name="phone" required maxlength="15" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="10-digit phone">
                    </div>
                </div>
                <div class="ua-row">
                    <div class="ua-field">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="Optional email">
                    </div>
                    <div class="ua-field">
                        <label>Password <span class="req">*</span></label>
                        <input type="password" name="password" required minlength="8" placeholder="Min 8 characters">
                    </div>
                </div>

                <hr class="ua-divider">

                <!-- Role selection cards -->
                <div class="ua-role-cards">
                    <label class="ua-role-card">
                        <input type="radio" name="role" value="worker" <?php echo ($_POST['role'] ?? 'worker') === 'worker' ? 'checked' : ''; ?>>
                        <div class="ua-role-inner">
                            <div class="ua-role-icon"><i class="fas fa-user"></i></div>
                            <div class="ua-role-name">Worker</div>
                            <div class="ua-role-desc">Field tasks & check-ins</div>
                        </div>
                    </label>
                    <label class="ua-role-card">
                        <input type="radio" name="role" value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'checked' : ''; ?>>
                        <div class="ua-role-inner">
                            <div class="ua-role-icon"><i class="fas fa-user-shield"></i></div>
                            <div class="ua-role-name">Admin</div>
                            <div class="ua-role-desc">Full system access</div>
                        </div>
                    </label>
                </div>

                <div class="ua-row">
                    <div class="ua-field">
                        <label>Status</label>
                        <select name="status">
                            <option value="Active" <?php echo ($_POST['status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo ($_POST['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="ua-card-foot">
                <a href="users.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
