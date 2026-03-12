<?php
$pageTitle = "User Management";
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$currentAdminId = $_SESSION['user_id'];

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    $targetId = (int) ($_POST['user_id'] ?? 0);

    if ($targetId === $currentAdminId) {
        setFlash('danger', 'You cannot change your own role.');
    } elseif ($targetId > 0) {
        if ($_POST['action'] === 'make_admin') {
            $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $stmt->bind_param("i", $targetId);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'User promoted to Admin successfully.');
        } elseif ($_POST['action'] === 'make_worker') {
            $stmt = $conn->prepare("UPDATE users SET role = 'worker' WHERE id = ?");
            $stmt->bind_param("i", $targetId);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'User demoted to Worker role.');
        } elseif ($_POST['action'] === 'reset_password') {
            $newPass = trim($_POST['new_password'] ?? '');
            if (empty($newPass) || strlen($newPass) < MIN_PASSWORD_LENGTH) {
                setFlash('danger', 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.');
            } else {
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed, $targetId);
                $stmt->execute();
                $stmt->close();
                setFlash('success', 'Password reset successfully.');
            }
        } elseif ($_POST['action'] === 'toggle_status') {
            $newStatus = $_POST['new_status'] === 'Active' ? 'Active' : 'Inactive';
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $targetId);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'User status updated.');
        }
    }
    header("Location: users.php");
    exit;
}

// Fetch all users
$roleFilter = $_GET['role'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($roleFilter) {
    $where .= " AND u.role = ?";
    $params[] = $roleFilter;
    $types .= "s";
}
if ($searchFilter) {
    $search = '%' . $searchFilter . '%';
    $where .= " AND (u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

$sql = "SELECT u.*, 
        (SELECT w.id FROM workers w WHERE w.user_id = u.id LIMIT 1) as worker_id
        FROM users u $where ORDER BY u.role DESC, u.created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Count stats
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN role='worker' THEN 1 ELSE 0 END) as workers,
    SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active
    FROM users")->fetch_assoc();

$extraCSS = '
<style>
/* ── User Management Page ── */
.um-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; margin-bottom:22px; }
.um-stat {
    display:flex; align-items:center; gap:14px;
    background:var(--white); border-radius:var(--radius-lg); border:1px solid var(--border);
    padding:20px; transition:all var(--transition);
}
.um-stat:hover { transform:translateY(-3px); box-shadow:var(--shadow); }
.um-stat-icon {
    width:52px; height:52px; border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:22px; flex-shrink:0;
}
.um-stat-num { font-size:26px; font-weight:800; color:var(--text); line-height:1; }
.um-stat-label { font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }

/* ── User Cards Grid ── */
.um-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));
    gap:16px;
    margin-top:16px;
}
.um-card {
    background:var(--white); border-radius:var(--radius-lg);
    border:1px solid var(--border); overflow:hidden;
    transition:all var(--transition); position:relative;
}
.um-card:hover { box-shadow:var(--shadow); transform:translateY(-2px); }
.um-card-head {
    display:flex; align-items:center; gap:14px;
    padding:18px 20px 14px; border-bottom:1px solid var(--border);
    background:linear-gradient(135deg, var(--primary-light) 0%, #f8fafc 100%);
}
.um-card.is-admin .um-card-head {
    background:linear-gradient(135deg, #fef2f2 0%, #fff7ed 100%);
}
.um-card-avatar {
    width:48px; height:48px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; font-weight:700; color:#fff; flex-shrink:0;
    background:linear-gradient(135deg, var(--primary), var(--primary-dark));
    box-shadow:0 4px 12px rgba(var(--primary-rgb),.25);
}
.um-card.is-admin .um-card-avatar {
    background:linear-gradient(135deg, var(--accent), var(--accent-dark));
    box-shadow:0 4px 12px rgba(var(--accent-rgb),.3);
}
.um-card-identity { flex:1; min-width:0; }
.um-card-name {
    font-size:15px; font-weight:700; color:var(--text);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    display:flex; align-items:center; gap:8px;
}
.um-card-role-tag {
    display:inline-flex; align-items:center; gap:4px;
    padding:2px 10px; border-radius:20px;
    font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; flex-shrink:0;
}
.um-card-role-tag.admin { background:#c62828; color:#fff; }
.um-card-role-tag.worker { background:var(--success-light); color:var(--success); }
.um-card-joined { font-size:11px; color:var(--text-muted); margin-top:2px; }
.um-card-joined i { margin-right:3px; }

.um-card-body { padding:14px 20px 10px; }
.um-card-info {
    display:grid; grid-template-columns:1fr 1fr; gap:8px 16px;
}
.um-card-field { }
.um-card-field-label {
    font-size:10px; color:var(--text-muted); text-transform:uppercase;
    letter-spacing:.5px; margin-bottom:1px;
}
.um-card-field-value {
    font-size:13px; color:var(--text); font-weight:500;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.um-card-field-value i { margin-right:4px; color:var(--text-muted); font-size:12px; }

.um-card-foot {
    padding:12px 20px 16px;
    display:flex; align-items:center; gap:6px; flex-wrap:wrap;
    border-top:1px solid var(--border);
}
.um-card-foot .btn { font-size:11px; padding:6px 12px; border-radius:8px; }
.um-card-foot .btn i { font-size:11px; }

.um-you-badge {
    display:inline-flex; align-items:center; gap:5px;
    background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    color:#fff; padding:6px 14px; border-radius:20px;
    font-size:11px; font-weight:600; letter-spacing:.3px;
}

/* Promote/Demote special buttons */
.btn-promote {
    background:linear-gradient(135deg, #e8702a, #f5a623); color:#fff;
    box-shadow:0 2px 8px rgba(232,112,42,.25);
}
.btn-promote:hover { background:linear-gradient(135deg, #cf5f1f, #e8702a); box-shadow:0 4px 14px rgba(232,112,42,.35); }
.btn-demote {
    background:var(--white); color:var(--text-secondary);
    border:1.5px solid var(--border);
}
.btn-demote:hover { background:#f8f9fa; border-color:#adb5bd; color:var(--text); }

/* Status dot */
.um-status-dot {
    width:8px; height:8px; border-radius:50%;
    display:inline-block; margin-right:4px; flex-shrink:0;
}
.um-status-dot.active { background:var(--success); box-shadow:0 0 6px rgba(5,150,105,.4); }
.um-status-dot.inactive { background:var(--danger); box-shadow:0 0 6px rgba(220,38,38,.3); }

/* ── Reset Password Modal ── */
.um-modal-overlay {
    display:none; position:fixed; top:0; left:0; width:100%; height:100%;
    background:rgba(0,0,0,.45); backdrop-filter:blur(4px);
    z-index:9999; align-items:center; justify-content:center;
}
.um-modal {
    background:var(--white); border-radius:var(--radius-lg);
    padding:0; max-width:420px; width:92%;
    box-shadow:var(--shadow-lg); overflow:hidden;
    animation:umModalIn .3s ease;
}
@keyframes umModalIn {
    from { opacity:0; transform:translateY(20px) scale(.96); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}
.um-modal-header {
    background:linear-gradient(135deg, var(--primary), var(--primary-dark));
    color:#fff; padding:18px 24px;
}
.um-modal-header h5 { margin:0; font-size:16px; font-weight:700; }
.um-modal-header p { margin:4px 0 0; font-size:12px; opacity:.8; }
.um-modal-body { padding:24px; }
.um-modal-body .form-group { margin-bottom:16px; }
.um-modal-body .form-group label { font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px; display:block; }
.um-modal-body .form-control {
    width:100%; padding:10px 14px; border:1.5px solid var(--border);
    border-radius:var(--radius); font-size:14px; transition:border var(--transition-fast);
}
.um-modal-body .form-control:focus { border-color:var(--primary); outline:none; box-shadow:0 0 0 3px rgba(var(--primary-rgb),.1); }
.um-modal-actions { display:flex; gap:10px; padding:0 24px 20px; }

/* ── Empty State ── */
.um-empty {
    text-align:center; padding:48px 20px; color:var(--text-muted);
}
.um-empty i { font-size:42px; margin-bottom:14px; display:block; opacity:.4; }
.um-empty p { font-size:14px; margin:0; }

/* Responsive */
@media (max-width:768px) {
    .um-grid { grid-template-columns:1fr; }
    .um-stats { grid-template-columns:repeat(2,1fr); }
    .um-card-info { grid-template-columns:1fr; }
}
@media (max-width:480px) {
    .um-stats { grid-template-columns:1fr; }
}
</style>';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
    <h4><i class="fas fa-users-cog"></i> User Management</h4>
    <a href="user_add.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add User</a>
</div>

<!-- Stats -->
<div class="um-stats">
    <div class="um-stat">
        <div class="um-stat-icon" style="background:var(--primary-light);color:var(--primary);">
            <i class="fas fa-users"></i>
        </div>
        <div>
            <div class="um-stat-num"><?php echo (int) $stats['total']; ?></div>
            <div class="um-stat-label">Total Users</div>
        </div>
    </div>
    <div class="um-stat">
        <div class="um-stat-icon" style="background:var(--accent-light);color:var(--accent);">
            <i class="fas fa-user-shield"></i>
        </div>
        <div>
            <div class="um-stat-num"><?php echo (int) $stats['admins']; ?></div>
            <div class="um-stat-label">Admins</div>
        </div>
    </div>
    <div class="um-stat">
        <div class="um-stat-icon" style="background:var(--success-light);color:var(--success);">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <div class="um-stat-num"><?php echo (int) $stats['workers']; ?></div>
            <div class="um-stat-label">Workers</div>
        </div>
    </div>
    <div class="um-stat">
        <div class="um-stat-icon" style="background:var(--warning-light);color:var(--warning);">
            <i class="fas fa-user-check"></i>
        </div>
        <div>
            <div class="um-stat-num"><?php echo (int) $stats['active']; ?></div>
            <div class="um-stat-label">Active</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card-box">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Search</label>
            <input type="text" name="search" class="form-control" placeholder="Name, phone, or email..."
                value="<?php echo htmlspecialchars($searchFilter); ?>">
        </div>
        <div class="form-group">
            <label>Role</label>
            <select name="role" class="form-control">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="worker" <?php echo $roleFilter === 'worker' ? 'selected' : ''; ?>>Worker</option>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            <a href="users.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>
</div>

<!-- User Cards -->
<?php if ($users->num_rows > 0): ?>
    <div class="um-grid">
        <?php while ($u = $users->fetch_assoc()):
            $isMe = (int) $u['id'] === $currentAdminId;
            $isAdm = $u['role'] === 'admin';
            $initial = strtoupper(substr($u['name'], 0, 1));
            ?>
            <div class="um-card <?php echo $isAdm ? 'is-admin' : ''; ?>">
                <!-- Card Head -->
                <div class="um-card-head">
                    <div class="um-card-avatar"><?php echo $initial; ?></div>
                    <div class="um-card-identity">
                        <div class="um-card-name">
                            <?php echo htmlspecialchars($u['name']); ?>
                            <span class="um-card-role-tag <?php echo $u['role']; ?>">
                                <i class="fas <?php echo $isAdm ? 'fa-user-shield' : 'fa-user'; ?>"></i>
                                <?php echo ucfirst($u['role']); ?>
                            </span>
                        </div>
                        <div class="um-card-joined">
                            <i class="fas fa-calendar-alt"></i>
                            Joined <?php echo date('d M Y', strtotime($u['created_at'])); ?>
                        </div>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="um-card-body">
                    <div class="um-card-info">
                        <div class="um-card-field">
                            <div class="um-card-field-label">Phone</div>
                            <div class="um-card-field-value"><i
                                    class="fas fa-phone"></i><?php echo htmlspecialchars($u['phone']); ?></div>
                        </div>
                        <div class="um-card-field">
                            <div class="um-card-field-label">Email</div>
                            <div class="um-card-field-value"><i
                                    class="fas fa-envelope"></i><?php echo htmlspecialchars($u['email'] ?: '—'); ?></div>
                        </div>
                        <div class="um-card-field">
                            <div class="um-card-field-label">Status</div>
                            <div class="um-card-field-value">
                                <span class="um-status-dot <?php echo strtolower($u['status']); ?>"></span>
                                <?php echo $u['status']; ?>
                            </div>
                        </div>
                        <div class="um-card-field">
                            <div class="um-card-field-label">Worker Profile</div>
                            <div class="um-card-field-value">
                                <?php if ($u['worker_id']): ?>
                                    <a href="worker_edit.php?id=<?php echo $u['worker_id']; ?>"
                                        style="color:var(--primary);text-decoration:none;font-weight:600;">
                                        <i class="fas fa-id-card"></i>#<?php echo $u['worker_id']; ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Footer -->
                <div class="um-card-foot">
                    <?php if ($isMe): ?>
                        <span class="um-you-badge"><i class="fas fa-shield-alt"></i> Current Admin (You)</span>
                    <?php else: ?>
                        <?php if ($u['role'] === 'worker'): ?>
                            <form method="POST" style="display:inline;"
                                onsubmit="return confirm('Promote <?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?> to Admin?\nThey will get full admin access.');">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="make_admin">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn btn-promote"><i class="fas fa-crown"></i> Promote to Admin</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display:inline;"
                                onsubmit="return confirm('Demote <?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?> to Worker role?');">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="make_worker">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn btn-demote"><i class="fas fa-arrow-down"></i> Demote</button>
                            </form>
                        <?php endif; ?>

                        <!-- Status Toggle -->
                        <form method="POST" style="display:inline;">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <?php if ($u['status'] === 'Active'): ?>
                                <input type="hidden" name="new_status" value="Inactive">
                                <button type="submit" class="btn btn-danger" title="Deactivate User"><i class="fas fa-ban"></i></button>
                            <?php else: ?>
                                <input type="hidden" name="new_status" value="Active">
                                <button type="submit" class="btn btn-success" title="Activate User"><i
                                        class="fas fa-check"></i></button>
                            <?php endif; ?>
                        </form>

                        <!-- Reset Password -->
                        <button type="button" class="btn btn-outline" title="Reset Password"
                            onclick="openResetModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')">
                            <i class="fas fa-key"></i> Password
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="card-box">
        <div class="um-empty">
            <i class="fas fa-users-slash"></i>
            <p>No users found matching your filters.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Password Reset Modal -->
<div class="um-modal-overlay" id="resetModal">
    <div class="um-modal">
        <div class="um-modal-header">
            <h5><i class="fas fa-key"></i> Reset Password</h5>
            <p>User: <strong id="resetUserName"></strong></p>
        </div>
        <form method="POST">
            <?php csrfField(); ?>
            <div class="um-modal-body">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="8"
                        placeholder="Minimum 8 characters">
                </div>
            </div>
            <div class="um-modal-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Reset Password</button>
                <button type="button" class="btn btn-outline" onclick="closeResetModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openResetModal(userId, userName) {
        document.getElementById('resetUserId').value = userId;
        document.getElementById('resetUserName').textContent = userName;
        document.getElementById('resetModal').style.display = 'flex';
    }
    function closeResetModal() {
        document.getElementById('resetModal').style.display = 'none';
    }
    document.getElementById('resetModal').addEventListener('click', function (e) {
        if (e.target === this) closeResetModal();
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>