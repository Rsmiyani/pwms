<!DOCTYPE html>
<html lang="en" id="app-root">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Party Worker Management System - Manage campaigns, tasks, and workers efficiently.">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - PWMS' : 'Party Worker Management System'; ?></title>
    <!-- Anti-flash dark mode: apply stored theme before render -->
    <script>(function () { var t = localStorage.getItem('pwms_theme'); if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark'); })();</script>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/images/logo.png">
    <?php if (function_exists('csrfToken')): ?>
        <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <?php if (isset($extraCSS))
        echo $extraCSS; ?>
    <!-- Expose BASE_URL to JavaScript -->
    <script>window.BASE_URL = '<?php echo BASE_URL; ?>';</script>
</head>

<body>
    <div class="app-wrapper">