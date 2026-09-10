<?php
$admin_page = $admin_page ?? "";
$page_title = $page_title ?? "Admin - Forge Fitness Gym";
$admin_display_name = $_SESSION["admin_username"] ?? $_SESSION["user_name"] ?? "Admin";
$admin_display_email = $_SESSION["user_email"] ?? "admin@gmail.com";
$admin_unread = $admin_unread ?? (
    isset($conn) && $conn instanceof mysqli
        ? count_table(
            $conn,
            "SELECT COUNT(*) AS total FROM messages m
             WHERE (m.admin_reply IS NULL OR m.admin_reply = '')
               AND NOT EXISTS (
                   SELECT 1 FROM message_replies r
                   WHERE r.message_id = m.id AND r.sender = 'admin'
               )"
        )
        : 0
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?></title>
    <link rel="icon" href="<?php echo e(url("assets/images/logo.png")); ?>">
    <link rel="stylesheet" href="<?php echo e(url("style.css?v=29")); ?>">
    <script>
        if ("scrollRestoration" in history) {
            history.scrollRestoration = "manual";
        }
    </script>
</head>
<body class="admin-body">

<header class="admin-navbar">
    <div class="admin-nav-container">
        <a href="<?php echo e(url("admin/")); ?>" class="admin-brand">
            <img src="<?php echo e(url("assets/images/logo.png?v=2")); ?>" alt="Forge Fitness">
            <h2>FORGE FITNESS <span>ADMIN</span></h2>
        </a>
        <nav>
            <a href="<?php echo e(url("index.php")); ?>">VIEW SITE</a>
            <span class="admin-who">
                <strong><?php echo e($admin_display_name); ?></strong>
                <small><?php echo e($admin_display_email); ?></small>
            </span>
            <a href="<?php echo e(url("admin/logout.php")); ?>" class="logout-btn">LOGOUT</a>
        </nav>
    </div>
</header>

<div class="admin-shell">
    <aside class="admin-sidebar" aria-label="Admin pages">
        <p class="admin-sidebar-kicker">Menu</p>
        <a href="<?php echo e(url("admin/")); ?>" class="<?php echo $admin_page === "dashboard" ? "active" : ""; ?>">Dashboard</a>
        <a href="<?php echo e(url("admin/?view=accounts")); ?>" class="<?php echo $admin_page === "accounts" ? "active" : ""; ?>">Members</a>
        <a href="<?php echo e(url("admin/?view=memberships")); ?>" class="<?php echo $admin_page === "memberships" ? "active" : ""; ?>">Membership records</a>
        <a href="<?php echo e(url("admin/?view=grant")); ?>" class="<?php echo $admin_page === "grant" ? "active" : ""; ?>">Walk-in grant</a>
        <a href="<?php echo e(url("admin/messages.php")); ?>" class="<?php echo $admin_page === "messages" ? "active" : ""; ?>">
            Inbox
            <?php if ((int) $admin_unread > 0): ?>
                <span class="admin-nav-badge"><?php echo (int) $admin_unread; ?></span>
            <?php endif; ?>
        </a>
    </aside>
    <div class="admin-main">
