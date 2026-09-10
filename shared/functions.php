<?php

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function redirect(string $url): void
{
    if ($url !== "" && !preg_match('#^(https?:)?//#i', $url) && !str_starts_with($url, "/")) {
        $url = url($url);
    }
    header("Location: " . $url);
    exit();
}

function post_string(string $key, int $max = 255): string
{
    $value = trim((string) ($_POST[$key] ?? ""));
    if (function_exists("mb_substr")) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function get_string(string $key, int $max = 100): string
{
    $value = trim((string) ($_GET[$key] ?? ""));
    if (function_exists("mb_substr")) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST["csrf_token"] ?? "";
    return is_string($token) && $token !== "" && hash_equals(csrf_token(), $token);
}

function normalize_role(?string $role): string
{
    $role = strtolower(trim((string) $role));
    if ($role === "admin") {
        return "admin";
    }
    if ($role === "member") {
        return "member";
    }
    return "user";
}

function is_admin(): bool
{
    return normalize_role($_SESSION["user_role"] ?? "") === "admin";
}

function require_user(?string $login_page = "auth/login.php"): void
{
    if (!isset($_SESSION["user_id"])) {
        $_SESSION["redirect_after_login"] = $_SERVER["REQUEST_URI"] ?? "index.php";
        redirect($login_page);
    }
}

function require_member(?string $login_page = "auth/login.php"): void
{
    require_user($login_page);
    if (is_admin()) {
        redirect("admin/");
    }
}

function flash_set(string $message, string $type = "success"): void
{
    $_SESSION["flash_message"] = $message;
    $_SESSION["flash_type"] = $type;
}

function flash_take(): array
{
    $message = (string) ($_SESSION["flash_message"] ?? "");
    $type = (string) ($_SESSION["flash_type"] ?? "success");
    unset($_SESSION["flash_message"], $_SESSION["flash_type"]);
    return [$message, $type];
}

function require_admin(): void
{
    if (!isset($_SESSION["user_id"])) {
        $_SESSION["redirect_after_login"] = "admin/";
        redirect("auth/login.php");
    }

    if (!is_admin()) {
        redirect("index.php");
    }
}

function membership_plans(): array
{
    return [
        "Basic" => 799.00,
        "Premium" => 999.00,
        "Elite" => 1399.00,
    ];
}

function inquiry_types(): array
{
    return ["Membership Inquiry", "General Inquiry", "Personal Training"];
}

function payment_methods(): array
{
    return ["GCash", "Credit Card", "Cash"];
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $role = normalize_role($user["role"] ?? "user");

    $_SESSION["user_id"] = (int) $user["id"];
    $_SESSION["user_name"] = $user["name"];
    $_SESSION["user_email"] = $user["email"];
    $_SESSION["user_role"] = $role;

    if ($role === "admin") {
        $_SESSION["admin_id"] = (int) $user["id"];
        $_SESSION["admin_username"] = $user["name"];
    } else {
        unset($_SESSION["admin_id"], $_SESSION["admin_username"]);
    }
}

function redirect_after_login(string $fallback = "index.php"): void
{
    if (is_admin()) {
        unset($_SESSION["redirect_after_login"]);
        redirect("admin/");
    }

    $target = (string) ($_SESSION["redirect_after_login"] ?? "");
    unset($_SESSION["redirect_after_login"]);

    if ($target === "" || preg_match('#^(https?:)?//#i', $target) || str_contains($target, "..")) {
        redirect($fallback);
    }

    $path = strtolower(str_replace("\\", "/", (string) (parse_url($target, PHP_URL_PATH) ?: strtok($target, "?"))));
    $path = trim($path, "/");
    $page = basename($path);
    $admin_files = ["admin.php", "admin_dashboard.php", "admin_messages.php", "admin_logout.php", "auth.php", "admin_login.php"];
    if (
        in_array($page, $admin_files, true)
        || $page === "admin"
        || $path === "admin"
        || str_starts_with($path, "admin/")
        || str_contains($path, "/admin/")
    ) {
        redirect($fallback);
    }

    redirect($target);
}

function logout_session(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

function clear_login_session(): void
{
    unset(
        $_SESSION["user_id"],
        $_SESSION["user_name"],
        $_SESSION["user_email"],
        $_SESSION["user_role"],
        $_SESSION["admin_id"],
        $_SESSION["admin_username"]
    );
}

function forget_deleted_login(mysqli $conn): void
{
    $user_id = (int) ($_SESSION["user_id"] ?? 0);
    if ($user_id < 1) {
        return;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user === null) {
        clear_login_session();
    }
}

function find_user_by_email(mysqli $conn, string $email): ?array
{
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function find_member_user(mysqli $conn, int $user_id): ?array
{
    if ($user_id < 1) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT id, name, email, role FROM users WHERE id = ? AND role <> 'admin' LIMIT 1"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function create_user(mysqli $conn, string $name, string $email, string $password_hash, string $role = "user"): int
{
    $role = normalize_role($role);
    if ($role === "admin") {
        $role = "user";
    }
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password_hash, $role);
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

function get_latest_membership(mysqli $conn, int $user_id): ?array
{
    $stmt = $conn->prepare(
        "SELECT * FROM memberships
         WHERE user_id = ? AND payment_status = 'Paid'
         ORDER BY (end_date > NOW()) DESC, id DESC
         LIMIT 1"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $membership = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $membership ?: null;
}

function membership_end_ts(?array $membership): int
{
    if (!$membership || empty($membership["end_date"])) {
        return 0;
    }
    $ts = strtotime((string) $membership["end_date"]);
    return $ts === false ? 0 : $ts;
}

function membership_is_active(?array $membership): bool
{
    if (!$membership || ($membership["payment_status"] ?? "") !== "Paid") {
        return false;
    }
    return membership_end_ts($membership) > time();
}

function membership_is_pending_cash(?array $membership): bool
{
    return $membership !== null
        && ($membership["payment_status"] ?? "") === "Pending"
        && ($membership["payment_method"] ?? "") === "Cash";
}

function get_pending_cash_membership(mysqli $conn, int $user_id): ?array
{
    if ($user_id < 1) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM memberships
         WHERE user_id = ? AND payment_status = 'Pending' AND payment_method = 'Cash'
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $membership = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $membership ?: null;
}

function cancel_pending_cash_memberships(mysqli $conn, int $user_id): bool
{
    if ($user_id < 1) {
        return false;
    }

    $stmt = $conn->prepare(
        "DELETE FROM memberships WHERE user_id = ? AND payment_status = 'Pending'"
    );
    $stmt->bind_param("i", $user_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function membership_seconds_left(?array $membership): int
{
    if (!membership_is_active($membership)) {
        return 0;
    }
    return max(0, membership_end_ts($membership) - time());
}

function membership_days_left(?array $membership): int
{
    $seconds = membership_seconds_left($membership);
    if ($seconds < 1) {
        return 0;
    }
    return (int) ceil($seconds / 86400);
}

function expire_active_memberships(mysqli $conn, int $user_id): bool
{
    $ended = date("Y-m-d H:i:s", time() - 1);
    $now = date("Y-m-d H:i:s");
    $stmt = $conn->prepare(
        "UPDATE memberships
         SET end_date = ?
         WHERE user_id = ? AND payment_status = 'Paid' AND end_date > ?"
    );
    $stmt->bind_param("sis", $ended, $user_id, $now);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function create_membership(
    mysqli $conn,
    int $user_id,
    string $plan,
    float $price,
    string $payment_method,
    string $start_date,
    string $end_date,
    string $payment_status = "Paid"
): bool {
    if (!in_array($payment_status, ["Paid", "Pending"], true)) {
        $payment_status = "Paid";
    }

    $stmt = $conn->prepare(
        "INSERT INTO memberships
        (user_id, plan, price, payment_method, payment_status, start_date, end_date)
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("isdssss", $user_id, $plan, $price, $payment_method, $payment_status, $start_date, $end_date);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function request_cash_membership(mysqli $conn, int $user_id, string $plan, float $price): bool
{
    if ($user_id < 1 || get_pending_cash_membership($conn, $user_id) !== null) {
        return false;
    }

    $requested_at = date("Y-m-d H:i:s");
    return create_membership($conn, $user_id, $plan, $price, "Cash", $requested_at, $requested_at, "Pending");
}

function confirm_cash_membership(mysqli $conn, int $membership_id): bool
{
    if ($membership_id < 1) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT id, user_id, plan, payment_method, payment_status
         FROM memberships
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $membership_id);
    $stmt->execute();
    $membership = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!membership_is_pending_cash($membership)) {
        return false;
    }

    $user_id = (int) $membership["user_id"];
    if (find_member_user($conn, $user_id) === null) {
        return false;
    }

    $start_date = date("Y-m-d H:i:s");
    $end_date = date("Y-m-d H:i:s", strtotime("+30 days"));

    $conn->begin_transaction();

    if (!expire_active_memberships($conn, $user_id)) {
        $conn->rollback();
        return false;
    }

    $update = $conn->prepare(
        "UPDATE memberships
         SET payment_status = 'Paid', start_date = ?, end_date = ?
         WHERE id = ? AND payment_status = 'Pending'"
    );
    $update->bind_param("ssi", $start_date, $end_date, $membership_id);
    $ok = $update->execute() && $update->affected_rows === 1;
    $update->close();

    if (!$ok) {
        $conn->rollback();
        return false;
    }

    $conn->commit();
    return true;
}

function record_membership_payment(
    mysqli $conn,
    int $user_id,
    string $plan,
    float $price,
    string $payment_method,
    string $start_date,
    string $end_date
): bool {
    $conn->begin_transaction();

    if (
        !expire_active_memberships($conn, $user_id)
        || !create_membership($conn, $user_id, $plan, $price, $payment_method, $start_date, $end_date)
        || !cancel_pending_cash_memberships($conn, $user_id)
    ) {
        $conn->rollback();
        return false;
    }

    $conn->commit();
    return true;
}

function delete_membership(mysqli $conn, int $membership_id): bool
{
    $stmt = $conn->prepare("DELETE FROM memberships WHERE id = ?");
    $stmt->bind_param("i", $membership_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function delete_user_account(mysqli $conn, int $user_id): bool
{
    if ($user_id < 1) {
        return false;
    }

    $stmt = $conn->prepare("SELECT role, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || normalize_role($user["role"] ?? "") === "admin") {
        return false;
    }

    $email = (string) ($user["email"] ?? "");
    if ($email !== "") {
        $replies = $conn->prepare(
            "DELETE r FROM message_replies r
             INNER JOIN messages m ON m.id = r.message_id
             WHERE m.email = ?"
        );
        $replies->bind_param("s", $email);
        $replies->execute();
        $replies->close();

        $messages = $conn->prepare("DELETE FROM messages WHERE email = ?");
        $messages->bind_param("s", $email);
        $messages->execute();
        $messages->close();
    }

    $memberships = $conn->prepare("DELETE FROM memberships WHERE user_id = ?");
    $memberships->bind_param("i", $user_id);
    $memberships->execute();
    $memberships->close();

    $delete = $conn->prepare("DELETE FROM users WHERE id = ? AND role <> 'admin'");
    $delete->bind_param("i", $user_id);
    $ok = $delete->execute() && $delete->affected_rows > 0;
    $delete->close();
    return $ok;
}

function create_contact_message(mysqli $conn, string $name, string $email, string $inquiry, string $message): bool
{
    $stmt = $conn->prepare(
        "INSERT INTO messages (name, email, inquiry_type, message) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("ssss", $name, $email, $inquiry, $message);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function get_message_replies(mysqli $conn, int $message_id): array
{
    $rows = [];
    $stmt = $conn->prepare(
        "SELECT id, message_id, sender, body, created_at
         FROM message_replies
         WHERE message_id = ?
         ORDER BY created_at ASC, id ASC"
    );
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function add_message_reply(mysqli $conn, int $message_id, string $sender, string $body): bool
{
    $stmt = $conn->prepare(
        "INSERT INTO message_replies (message_id, sender, body) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iss", $message_id, $sender, $body);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function delete_message_thread(mysqli $conn, int $message_id): bool
{
    $replies = $conn->prepare("DELETE FROM message_replies WHERE message_id = ?");
    $replies->bind_param("i", $message_id);
    $replies->execute();
    $replies->close();

    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function count_table(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int) ($row["total"] ?? 0);
}

function thread_has_admin(array $thread): bool
{
    foreach ($thread as $item) {
        if (($item["sender"] ?? "") === "admin") {
            return true;
        }
    }
    return false;
}

function gallery_items(): array
{
    return [
        "gym-equipment" => [
            "title" => "GYM EQUIPMENT",
            "cover" => "assets/images/gallery/machines.jpg",
            "alt" => "Cable machines and strength equipment",
            "summary" => "Explore our strength machines, racks, and training floor.",
            "description" => "Forge Fitness Gym is equipped for serious training. Use our machines, benches, racks, and free-weight stations to build strength with proper form and enough space to work.",
            "photos" => [
                "assets/images/gallery/squat-rack.jpg",
                "assets/images/gallery/interior-wide.jpg",
                "assets/images/gallery/weight-plates.jpg",
            ],
        ],
        "chest-workout" => [
            "title" => "CHEST WORKOUT",
            "cover" => "assets/images/gallery/chest-bench-press.jpg",
            "alt" => "Barbell bench press",
            "summary" => "Presses, push-ups, and chest-focused training zones.",
            "description" => "Train chest with benches, dumbbells, and floor work. This area is set up for pressing strength, control, and a full range of motion.",
            "photos" => [
                "assets/images/gallery/chest-incline.jpg",
                "assets/images/gallery/pt-coaching.jpg",
            ],
        ],
        "leg-day" => [
            "title" => "LEG DAY",
            "cover" => "assets/images/gallery/squat-barbell.jpg",
            "alt" => "Barbell squat",
            "summary" => "Squats, lunges, and lower-body strength work.",
            "description" => "Leg day has room for squats, leg press, and posterior-chain lifts. Build power in your quads, glutes, and hamstrings with focused lower-body sessions.",
            "photos" => [
                "assets/images/gallery/squat-action.jpg",
                "assets/images/gallery/leg-press.jpg",
                "assets/images/gallery/deadlift.jpg",
            ],
        ],
        "gym-interior" => [
            "title" => "GYM INTERIOR",
            "cover" => "assets/images/gallery/interior-wide.jpg",
            "alt" => "Wide view of the gym floor",
            "summary" => "A look inside the Forge Fitness training floor.",
            "description" => "The gym interior is built for focus: open floor space, clear stations, and a training atmosphere that matches the Forge Fitness brand.",
            "photos" => [
                "assets/images/gallery/machines.jpg",
                "assets/images/gallery/squat-rack.jpg",
            ],
        ],
        "dumbbell-area" => [
            "title" => "DUMBBELL AREA",
            "cover" => "assets/images/gallery/dumbbell-rack.jpg",
            "alt" => "Dumbbell rack",
            "summary" => "Free weights for accessory and full-body work.",
            "description" => "The dumbbell racks support isolation work and full-body sessions. Grab a pair and train arms, shoulders, back, or conditioning with controlled reps.",
            "photos" => [
                "assets/images/gallery/dumbbell-session.jpg",
                "assets/images/gallery/dumbbell-curl.jpg",
            ],
        ],
        "cardio-area" => [
            "title" => "CARDIO AREA",
            "cover" => "assets/images/gallery/cardio-treadmills.jpg",
            "alt" => "Treadmills in the cardio area",
            "summary" => "Conditioning space to build endurance and stamina.",
            "description" => "Use the cardio area to warm up, cut, or finish a session. Mix steady work with short bursts to improve endurance without leaving the gym floor.",
            "photos" => [
                "assets/images/gallery/cardio-runner.jpg",
                "assets/images/gallery/interior-wide.jpg",
            ],
        ],
        "strength-training" => [
            "title" => "STRENGTH TRAINING",
            "cover" => "assets/images/gallery/deadlift.jpg",
            "alt" => "Barbell deadlift",
            "summary" => "Heavy compound lifts and progressive overload.",
            "description" => "Strength training at Forge Fitness is built around progressive overload. Work the big lifts, then finish with accessories using our racks and free weights.",
            "photos" => [
                "assets/images/gallery/overhead-press.jpg",
                "assets/images/gallery/loading-bar.jpg",
                "assets/images/gallery/strength-deadlift.jpg",
            ],
        ],
        "personal-training" => [
            "title" => "PERSONAL TRAINING",
            "cover" => "assets/images/gallery/pt-coaching.jpg",
            "alt" => "Trainer coaching a client",
            "summary" => "Coached sessions tailored to your goals.",
            "description" => "Personal training gives you a coach, a plan, and accountability. Whether you want strength, fat loss, or better form, we build the session around you.",
            "photos" => [
                "assets/images/gallery/pt-trx.jpg",
            ],
        ],
    ];
}

function get_gallery_item(string $slug): ?array
{
    $items = gallery_items();
    return $items[$slug] ?? null;
}
