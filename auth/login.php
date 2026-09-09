<?php
require_once __DIR__ . "/../shared/bootstrap.php";

if (isset($_SESSION["user_id"])) {
    redirect_after_login("index.php");
}

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) {
        $error = "Your session expired. Please try again.";
    } else {
        $email = post_string("email", 150);
        $password = (string) ($_POST["password"] ?? "");

        if ($email === "" || $password === "") {
            $error = "Please enter your email and password.";
        } elseif (!is_valid_email($email)) {
            $error = "Please enter a valid email address.";
        } else {
            $user = find_user_by_email($conn, $email);
            if ($user && password_verify($password, $user["password"])) {
                login_user($user);
                redirect_after_login("index.php");
            }

            $error = "Invalid email or password.";
        }
    }
}

$active_page = "login";
$page_title = "Login - Forge Fitness Gym";
$body_class = "auth-body";
require __DIR__ . "/../shared/header.php";
?>

<div class="auth-wrap">
    <div class="auth-box">
        <a href="<?php echo e(url("index.php")); ?>" class="auth-logo">
            <img src="<?php echo e(url("assets/images/logo.png?v=2")); ?>" alt="Forge Fitness">
        </a>
        <h1>LOGIN</h1>
        <p>Welcome back to Forge Fitness.</p>

        <?php if ($error): ?>
            <div class="error-message"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="js-validate">
            <?php echo csrf_field(); ?>
            <input type="email" name="email" placeholder="Email Address" value="<?php echo e($email); ?>" maxlength="150" required>
            <input type="password" name="password" placeholder="Password" minlength="6" required>
            <button type="submit">LOGIN</button>
        </form>

        <p class="auth-switch">
            Don't have an account?
            <a href="<?php echo e(url("auth/register.php")); ?>">REGISTER</a>
        </p>
    </div>
</div>

<?php require __DIR__ . "/../shared/footer.php"; ?>
