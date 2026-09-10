<?php
require_once __DIR__ . "/bootstrap.php";

$logged_in = isset($_SESSION["user_id"]);
$user_name = ($logged_in && isset($_SESSION["user_name"])) ? $_SESSION["user_name"] : "";
$active_page = $active_page ?? "home";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title ?? "Forge Fitness Gym"); ?></title>
    <link rel="icon" href="<?php echo e(url("assets/images/logo.png")); ?>">
    <link rel="stylesheet" href="<?php echo e(url("style.css?v=31")); ?>">
    <script>
        if ("scrollRestoration" in history) {
            history.scrollRestoration = "manual";
        }
    </script>
</head>
<body class="<?php echo e($body_class ?? ""); ?>">

<header class="navbar">
    <div class="nav-container">
        <a href="<?php echo e(url("index.php")); ?>" class="logo">
            <img src="<?php echo e(url("assets/images/logo.png?v=2")); ?>" alt="Forge Fitness Gym">
        </a>

        <button class="menu-toggle" type="button" aria-label="Toggle navigation">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="site-nav">
            <a href="<?php echo e(url("index.php")); ?>" data-nav="home" class="<?php echo $active_page === "home" ? "active" : ""; ?>">HOME</a>
            <a href="<?php echo e(url("index.php#about")); ?>" data-nav="about" class="<?php echo $active_page === "about" ? "active" : ""; ?>">ABOUT US</a>
            <a href="<?php echo e(url("gallery.php")); ?>" data-nav="gallery" class="<?php echo $active_page === "gallery" ? "active" : ""; ?>">GALLERY</a>
            <?php if (!is_admin()): ?>
            <a href="<?php echo e($logged_in ? url("membership.php") : url("index.php#membership")); ?>" data-nav="membership" class="<?php echo $active_page === "membership" ? "active" : ""; ?>">MEMBERSHIP</a>
            <?php endif; ?>
            <?php if (!$logged_in || !is_admin()): ?>
                <a href="<?php echo e(url("index.php#contact")); ?>" data-nav="contact" class="<?php echo $active_page === "contact" ? "active" : ""; ?>">CONTACT US</a>
            <?php endif; ?>

            <?php if ($logged_in): ?>
                <span class="welcome-user">HI, <?php echo e(ucwords(strtolower($user_name))); ?></span>
                <?php if (is_admin()): ?>
                    <a href="<?php echo e(url("admin/")); ?>" class="nav-account">ADMIN</a>
                <?php else: ?>
                    <a href="<?php echo e(url("membership.php")); ?>" class="nav-account">MY PLAN</a>
                    <a href="<?php echo e(url("my_messages.php")); ?>" data-nav="inbox" class="<?php echo $active_page === "inbox" ? "active" : ""; ?>">INBOX</a>
                <?php endif; ?>
                <a href="<?php echo e(url("auth/logout.php")); ?>" class="logout-btn">LOGOUT</a>
            <?php else: ?>
                <a href="<?php echo e(url("auth/login.php")); ?>" data-nav="login" class="nav-login <?php echo $active_page === "login" ? "active" : ""; ?>">LOGIN</a>
                <a href="<?php echo e(url("auth/register.php")); ?>" class="join-nav-btn">JOIN</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
