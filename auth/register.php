<?php
require_once __DIR__ . "/../shared/bootstrap.php";

if (isset($_SESSION["user_id"])) {
    if (is_admin()) {
        redirect("admin/");
    }
    redirect("membership.php");
}

$error = "";
$name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) {
        $error = "Your session expired. Please try again.";
    } else {
        $name = post_string("name", 100);
        $email = post_string("email", 150);
        $password = (string) ($_POST["password"] ?? "");
        $confirm_password = (string) ($_POST["confirm_password"] ?? "");

        if ($name === "" || $email === "" || $password === "" || $confirm_password === "") {
            $error = "Please fill in all fields.";
        } elseif (strlen($name) < 2) {
            $error = "Please enter your full name.";
        } elseif (!is_valid_email($email)) {
            $error = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (find_user_by_email($conn, $email)) {
            $error = "This email is already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_id = create_user($conn, $name, $email, $hashed_password);

            if ($user_id > 0) {
                login_user([
                    "id" => $user_id,
                    "name" => $name,
                    "email" => $email,
                    "role" => "user",
                ]);
                unset($_SESSION["redirect_after_login"]);
                redirect("membership.php");
            }

            $error = "Registration failed. Please try again.";
        }
    }
}

$active_page = "";
$page_title = "Register - Forge Fitness Gym";
$body_class = "auth-body";
require __DIR__ . "/../shared/header.php";
?>

<div class="auth-wrap">
    <div class="auth-box">
        <a href="<?php echo e(url("index.php")); ?>" class="auth-logo">
            <img src="<?php echo e(url("assets/images/logo.png?v=2")); ?>" alt="Forge Fitness">
        </a>
        <h1>CREATE ACCOUNT</h1>
        <p>Start your fitness journey today.</p>

        <?php if ($error): ?>
            <div class="error-message"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="js-validate">
            <?php echo csrf_field(); ?>
            <input type="text" name="name" placeholder="Full Name" value="<?php echo e($name); ?>" minlength="2" maxlength="100" required>
            <input type="email" name="email" placeholder="Email Address" value="<?php echo e($email); ?>" maxlength="150" required>
            <input type="password" name="password" placeholder="Password" minlength="6" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" minlength="6" data-match="password" required>
            <button type="submit">REGISTER</button>
        </form>

        <p class="auth-switch">
            Already have an account?
            <a href="<?php echo e(url("auth/login.php")); ?>">LOGIN</a>
        </p>
    </div>
</div>

<?php require __DIR__ . "/../shared/footer.php"; ?>
