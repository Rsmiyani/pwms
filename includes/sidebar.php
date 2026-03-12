<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isWorker = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'worker';

// Auto-generate breadcrumbs from current page
$_bcParentMap = [
    'worker_add'         => ['Workers',              BASE_URL . '/admin/workers.php'],
    'worker_edit'        => ['Workers',              BASE_URL . '/admin/workers.php'],
    'task_add'           => ['Tasks',                BASE_URL . '/admin/tasks.php'],
    'task_view'          => ['Tasks',                BASE_URL . '/admin/tasks.php'],
    'user_add'           => ['User Management',      BASE_URL . '/admin/users.php'],
    'report_view'        => ['Performance Reports',  BASE_URL . '/admin/performance_report.php'],
    'my_tasks'           => ['Dashboard',            BASE_URL . '/worker/dashboard.php'],
    'checkin'            => ['Dashboard',            BASE_URL . '/worker/dashboard.php'],
    'route'              => ['Dashboard',            BASE_URL . '/worker/dashboard.php'],
    'feedback'           => ['Dashboard',            BASE_URL . '/worker/dashboard.php'],
    'profile'            => ['Dashboard',            BASE_URL . '/worker/dashboard.php'],
];
$_bcLabelMap = [
    'dashboard'          => 'Dashboard',
    'workers'            => 'Workers',
    'worker_add'         => 'Add Worker',
    'worker_edit'        => 'Edit Worker',
    'tasks'              => 'Tasks',
    'task_add'           => isset($_GET['edit']) ? 'Edit Task' : 'Create Task',
    'task_view'          => 'Task Detail',
    'users'              => 'User Management',
    'user_add'           => 'Add User',
    'reports'            => 'Reports',
    'export'             => 'Export',
    'areas'              => 'Areas',
    'checkins'           => 'Check-ins',
    'map'                => 'Live Map',
    'leaderboard'        => 'Leaderboard',
    'sentiment'          => 'Sentiment Analysis',
    'smart_assign'       => 'Smart Assignment',
    'performance_report' => 'Performance Reports',
    'report_view'        => 'Report View',
    'my_tasks'           => 'My Tasks',
    'checkin'            => 'Check-In',
    'route'              => 'Route Planner',
    'feedback'           => 'Voter Feedback',
    'profile'            => 'My Profile & Badges',
];
$_autoBreadcrumbs = [];
if (isset($_bcParentMap[$currentPage])) {
    $_autoBreadcrumbs[] = ['label' => $_bcParentMap[$currentPage][0], 'url' => $_bcParentMap[$currentPage][1]];
}
$_pageLabel = $_bcLabelMap[$currentPage] ?? ucfirst(str_replace('_', ' ', $currentPage));
$_autoBreadcrumbs[] = ['label' => $_pageLabel, 'url' => null]; // current — no link
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo-icon">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="PWMS Logo" class="sidebar-logo-img">
        </div>
        <h3>PWMS</h3>
        <p class="sidebar-tagline">Party Worker Management</p>
    </div>
    <nav class="sidebar-nav">
        <?php if (!$isWorker): ?>
            <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/workers.php"
                class="<?php echo in_array($currentPage, ['workers','worker_add','worker_edit']) ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Workers
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/users.php"
                class="<?php echo in_array($currentPage, ['users','user_add']) ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> User Management
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/tasks.php"
                class="<?php echo in_array($currentPage, ['tasks','task_add','task_view']) ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i> Tasks
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/reports.php"
                class="<?php echo in_array($currentPage, ['reports','export']) ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <div class="nav-divider"></div>
            <a href="<?php echo BASE_URL; ?>/admin/areas.php" class="<?php echo $currentPage === 'areas' ? 'active' : ''; ?>">
                <i class="fas fa-map-marker-alt"></i> Areas
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/checkins.php" class="<?php echo $currentPage === 'checkins' ? 'active' : ''; ?>">
                <i class="fas fa-map-pin"></i> Check-ins
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/map.php" class="<?php echo $currentPage === 'map' ? 'active' : ''; ?>">
                <i class="fas fa-globe-asia"></i> Live Map
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/leaderboard.php" class="<?php echo $currentPage === 'leaderboard' ? 'active' : ''; ?>">
                <i class="fas fa-trophy"></i> Leaderboard
            </a>
            <div class="nav-divider"></div>
            <span class="nav-section-label">AI &amp; Analytics</span>
            <a href="<?php echo BASE_URL; ?>/admin/sentiment.php" class="<?php echo $currentPage === 'sentiment' ? 'active' : ''; ?>">
                <i class="fas fa-brain"></i> Sentiment Analysis
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/smart_assign.php" class="<?php echo $currentPage === 'smart_assign' ? 'active' : ''; ?>">
                <i class="fas fa-magic"></i> Smart Assignment
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/performance_report.php"
                class="<?php echo in_array($currentPage, ['performance_report','report_view']) ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Performance Reports
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/worker/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="<?php echo BASE_URL; ?>/worker/my_tasks.php" class="<?php echo $currentPage === 'my_tasks' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i> My Tasks
            </a>
            <a href="<?php echo BASE_URL; ?>/worker/checkin.php" class="<?php echo $currentPage === 'checkin' ? 'active' : ''; ?>">
                <i class="fas fa-map-marker-alt"></i> Check-In
            </a>
            <a href="<?php echo BASE_URL; ?>/worker/route.php" class="<?php echo $currentPage === 'route' ? 'active' : ''; ?>">
                <i class="fas fa-route"></i> Route Planner
            </a>
            <a href="<?php echo BASE_URL; ?>/worker/feedback.php" class="<?php echo $currentPage === 'feedback' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> Voter Feedback
            </a>
            <div class="nav-divider"></div>
            <a href="<?php echo BASE_URL; ?>/worker/profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-trophy"></i> My Profile &amp; Badges
            </a>
        <?php endif; ?>
        <div class="nav-divider"></div>
        <a href="<?php echo BASE_URL; ?>/logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>
<!-- Mobile overlay backdrop -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="main-content">
    <div class="top-bar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h4><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></h4>
        </div>
        <div class="user-info">
            <!-- Dark mode toggle -->
            <button class="dark-toggle" id="dark-toggle" onclick="toggleDarkMode()" title="Toggle dark mode">
                <i class="fas fa-moon" id="dark-icon"></i>
            </button>
            <!-- Notification Bell -->
            <div class="notif-bell" id="notif-bell" onclick="toggleNotifPanel()">
                <i class="fas fa-bell"></i>
                <span class="notif-count" id="notif-count" style="display:none;">0</span>
            </div>
            <div class="notif-panel" id="notif-panel">
                <div class="notif-header">
                    <strong>Notifications</strong>
                    <a href="#" onclick="markAllRead(); return false;" style="font-size:12px;color:var(--primary);">Mark
                        all read</a>
                </div>
                <div class="notif-list" id="notif-list">
                    <div class="notif-empty">No notifications</div>
                </div>
            </div>
            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?></div>
        </div>
    </div>
    <div class="content-area">
        <?php if (count($_autoBreadcrumbs) > 1): ?>
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
            <?php foreach ($_autoBreadcrumbs as $idx => $_bc): ?>
                <?php if ($idx > 0): ?><span class="bc-sep"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
                <?php if ($_bc['url']): ?>
                    <a href="<?php echo htmlspecialchars($_bc['url']); ?>" class="bc-link"><?php echo htmlspecialchars($_bc['label']); ?></a>
                <?php else: ?>
                    <span class="bc-current"><?php echo htmlspecialchars($_bc['label']); ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <?php
        $flash = getFlash();
        if ($flash):
            ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <i
                    class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'exclamation-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>