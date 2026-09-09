<?php
$admin_page = $admin_page ?? "";
$page_title = $page_title ?? "Admin - Forge Fitness Gym";
$admin_display_name = $_SESSION["admin_username"] ?? $_SESSION["user_name"] ?? "Admin";
$admin_display_email = $_SESSION["user_email"] ?? "admin@gmail.com";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?></title>
    <link rel="icon" href="<?php echo e(url("assets/images/logo.png")); ?>">
    <link rel="stylesheet" href="<?php echo e(url("style.css")); ?>">
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
            <a href="<?php echo e(url("admin/")); ?>" class="<?php echo $admin_page === "dashboard" ? "active" : ""; ?>">DASHBOARD</a>
            <a href="<?php echo e(url("admin/messages.php")); ?>" class="<?php echo $admin_page === "messages" ? "active" : ""; ?>">MESSAGES</a>
            <span class="admin-who">
                <strong><?php echo e($admin_display_name); ?></strong>
                <small><?php echo e($admin_display_email); ?></small>
            </span>
            <a href="<?php echo e(url("admin/logout.php")); ?>" class="logout-btn">LOGOUT</a>
        </nav>
    </div>
</header>
