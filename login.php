<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: " . BASE_URL . "/admin/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "/worker/dashboard.php");
    }
    exit;
}

$error = '';

// ── Database-based brute-force protection ──
// Tracks attempts per phone number AND per IP address so attackers
// cannot bypass lockout by clearing cookies or switching accounts.
$maxAttemptsPerPhone = 5;   // max failed logins per phone number
$maxAttemptsPerIP = 15;  // max failed logins per IP address
$lockoutWindow = 900; // 15-minute window (seconds)
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

/**
 * Count recent failed login attempts from the database
 */
function getFailedAttempts($conn, $field, $value, $windowSeconds)
{
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE $field = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->bind_param("si", $value, $windowSeconds);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check IP-based lockout first (before even looking at phone)
    $ipAttempts = getFailedAttempts($conn, 'ip_address', $clientIP, $lockoutWindow);
    if ($ipAttempts >= $maxAttemptsPerIP) {
        $error = "Too many failed attempts from your network. Please try again later.";
    }

    // Check phone-based lockout (only if phone was provided)
    if (empty($error) && !empty($phone)) {
        $phoneAttempts = getFailedAttempts($conn, 'phone', $phone, $lockoutWindow);
        if ($phoneAttempts >= $maxAttemptsPerPhone) {
            $error = "This account is temporarily locked due to too many failed attempts. Please try again in 15 minutes.";
        }
    }

    if (empty($error)) {
        if (empty($phone) || empty($password)) {
            $error = 'Please enter both phone number and password.';
        } elseif (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            $error = 'Invalid session. Please try again.';
        } else {
            $stmt = $conn->prepare("SELECT id, name, phone, password, role FROM users WHERE phone = ? AND status = 'Active'");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Clear failed attempts for this phone on successful login
                    $delStmt = $conn->prepare("DELETE FROM login_attempts WHERE phone = ?");
                    $delStmt->bind_param("s", $phone);
                    $delStmt->execute();
                    $delStmt->close();

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_phone'] = $user['phone'];

                    if ($user['role'] === 'admin') {
                        header("Location: " . BASE_URL . "/admin/dashboard.php");
                    } else {
                        header("Location: " . BASE_URL . "/worker/dashboard.php");
                    }
                    exit;
                } else {
                    // Record failed attempt in DB
                    $logStmt = $conn->prepare("INSERT INTO login_attempts (phone, ip_address) VALUES (?, ?)");
                    $logStmt->bind_param("ss", $phone, $clientIP);
                    $logStmt->execute();
                    $logStmt->close();
                    $error = 'Invalid credentials.';
                }
            } else {
                // Record failed attempt even for non-existent accounts
                $logStmt = $conn->prepare("INSERT INTO login_attempts (phone, ip_address) VALUES (?, ?)");
                $logStmt->bind_param("ss", $phone, $clientIP);
                $logStmt->execute();
                $logStmt->close();
                $error = 'Invalid credentials.';
            }
            $stmt->close();
        }
    }

    // Periodically clean up old attempts (1% chance per request to avoid overhead)
    if (mt_rand(1, 100) === 1) {
        $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Party Worker Management System - Manage campaigns, tasks, and workers efficiently.">
    <title>Login - Party Worker Management System</title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>

<body>
    <div class="login-wrapper">
        <div class="login-particles">
            <span></span><span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span><span></span>
        </div>
        <div class="login-card">
            <div class="login-logo-wrap">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="PWMS Logo" class="login-logo-img">
            </div>
            <h2>Party Worker MS</h2>
            <p class="subtitle">Login to manage your campaign</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php
                // CSRF protection for login
                if (empty($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
                ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group form-group-icon">
                    <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control" placeholder="Enter phone number"
                        value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required maxlength="15">
                </div>
                <div class="form-group form-group-icon">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="Enter password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="pwd-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-login" id="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

        </div>
    </div>
    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const eye = document.getElementById('pwd-eye');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                eye.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pwd.type = 'password';
                eye.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>

</html>