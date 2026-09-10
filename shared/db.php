<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "forge_fitness";

$conn = @new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Database connection failed. Please start MySQL in XAMPP.");
}

$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");
$conn->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->select_db($database);

$conn->query("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$role_col = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($role_col && $role_col->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER password");
}

$conn->query("
    CREATE TABLE IF NOT EXISTS memberships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        plan VARCHAR(50) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        payment_status VARCHAR(50) NOT NULL DEFAULT 'Paid',
        start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        end_date DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
    CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        inquiry_type VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        admin_reply TEXT NULL,
        replied_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$reply_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'admin_reply'");
if ($reply_col && $reply_col->num_rows === 0) {
    $conn->query("ALTER TABLE messages ADD COLUMN admin_reply TEXT NULL");
    $conn->query("ALTER TABLE messages ADD COLUMN replied_at DATETIME NULL");
}

$email_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'email'");
if ($email_col && ($email_info = $email_col->fetch_assoc()) && stripos((string) ($email_info["Type"] ?? ""), "varchar(150)") === false) {
    $conn->query("ALTER TABLE messages MODIFY email VARCHAR(150) NOT NULL");
}

$admin_email = "admin@gmail.com";

$gmail_taken = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$gmail_taken->bind_param("s", $admin_email);
$gmail_taken->execute();
$gmail_exists = $gmail_taken->get_result();
$gmail_taken->close();

if ($gmail_exists && $gmail_exists->num_rows === 0) {
    $migrate = $conn->prepare("UPDATE users SET email = ? WHERE email = ? AND role = 'admin'");
    $migrate->bind_param("ss", $admin_email, $legacy_admin_email);
    $migrate->execute();
    $migrate->close();
}

$admin_user_check = $conn->prepare("SELECT id FROM users WHERE email = ? OR role = 'admin' LIMIT 1");
$admin_user_check->bind_param("s", $admin_email);
$admin_user_check->execute();
$has_admin_user = $admin_user_check->get_result();
$admin_user_check->close();

if ($has_admin_user && $has_admin_user->num_rows === 0) {
    $admin_name = "Admin";
    $admin_role = "admin";
    $admin_hash = password_hash("admin123", PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $admin_name, $admin_email, $admin_hash, $admin_role);
    $stmt->execute();
    $stmt->close();
}

if (session_status() === PHP_SESSION_ACTIVE && ($_SESSION["user_email"] ?? "") === $legacy_admin_email) {
    $_SESSION["user_email"] = $admin_email;
}

$conn->query("
    CREATE TABLE IF NOT EXISTS message_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        sender VARCHAR(20) NOT NULL,
        body TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$old_replies = $conn->query(
    "SELECT id, admin_reply, replied_at
     FROM messages
     WHERE admin_reply IS NOT NULL AND admin_reply != ''"
);
if ($old_replies) {
    while ($old = $old_replies->fetch_assoc()) {
        $exists = $conn->prepare(
            "SELECT id FROM message_replies WHERE message_id = ? AND sender = 'admin' LIMIT 1"
        );
        $exists->bind_param("i", $old["id"]);
        $exists->execute();
        $already = $exists->get_result();
        $exists->close();

        if ($already && $already->num_rows === 0) {
            $insert = $conn->prepare(
                "INSERT INTO message_replies (message_id, sender, body, created_at)
                 VALUES (?, 'admin', ?, ?)"
            );
            $created = $old["replied_at"] ?: date("Y-m-d H:i:s");
            $insert->bind_param("iss", $old["id"], $old["admin_reply"], $created);
            $insert->execute();
            $insert->close();
        }
    }
}

$conn->query("DROP TABLE IF EXISTS admins");
$conn->query("DELETE m FROM memberships m LEFT JOIN users u ON u.id = m.user_id WHERE u.id IS NULL");
$conn->query("DELETE r FROM message_replies r LEFT JOIN messages m ON m.id = r.message_id WHERE m.id IS NULL");

$fk_stmt = $conn->prepare(
    "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'
     LIMIT 1"
);

$fk_name = "fk_memberships_user";
$fk_stmt->bind_param("ss", $database, $fk_name);
$fk_stmt->execute();
$fk_found = $fk_stmt->get_result();
$has_membership_fk = $fk_found && $fk_found->num_rows > 0;
if ($fk_found) {
    $fk_found->free();
}
if (!$has_membership_fk) {
    $conn->query(
        "ALTER TABLE memberships
         ADD CONSTRAINT fk_memberships_user
         FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
    );
}

$fk_name = "fk_replies_message";
$fk_stmt->bind_param("ss", $database, $fk_name);
$fk_stmt->execute();
$fk_found = $fk_stmt->get_result();
$has_reply_fk = $fk_found && $fk_found->num_rows > 0;
if ($fk_found) {
    $fk_found->free();
}
if (!$has_reply_fk) {
    $conn->query(
        "ALTER TABLE message_replies
         ADD CONSTRAINT fk_replies_message
         FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE"
    );
}
$fk_stmt->close();


?>
