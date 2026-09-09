<?php
require_once __DIR__ . "/shared/bootstrap.php";
require_member();

$user_id = (int) $_SESSION["user_id"];
$user_email = $_SESSION["user_email"] ?? "";

if ($user_email === "") {
    $lookup = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
    $lookup->bind_param("i", $user_id);
    $lookup->execute();
    $account = $lookup->get_result()->fetch_assoc();
    $lookup->close();

    if ($account) {
        $user_email = $account["email"];
        $_SESSION["user_email"] = $user_email;
        if (empty($_SESSION["user_name"])) {
            $_SESSION["user_name"] = $account["name"];
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_reply"])) {
    if (!csrf_verify()) {
        flash_set("Your session expired. Please try again.", "error");
    } else {
        $message_id = (int) ($_POST["message_id"] ?? 0);
        $reply = post_string("user_reply_text", 2000);

        $check = $conn->prepare("SELECT id FROM messages WHERE id = ? AND email = ?");
        $check->bind_param("is", $message_id, $user_email);
        $check->execute();
        $owns = $check->get_result()->fetch_assoc();
        $check->close();

        $thread = $owns ? get_message_replies($conn, $message_id) : [];
        $has_admin = thread_has_admin($thread);

        if (!$owns) {
            flash_set("Message not found.", "error");
        } elseif (!$has_admin) {
            flash_set("Please wait for the gym to reply first.", "error");
        } elseif ($reply === "" || strlen($reply) < 2) {
            flash_set("Please type a reply before sending.", "error");
        } elseif (add_message_reply($conn, $message_id, "user", $reply)) {
            flash_set("Your reply was sent to Forge Fitness Gym.");
        } else {
            flash_set("Could not send your reply. Please try again.", "error");
        }
    }
    redirect("my_messages.php");
}

[$notice, $notice_type] = flash_take();

$inbox = [];
if ($user_email !== "") {
    $stmt = $conn->prepare(
        "SELECT id, inquiry_type, message, created_at
         FROM messages
         WHERE email = ?
         ORDER BY created_at DESC"
    );
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $inbox[] = $row;
    }
    $stmt->close();
}

$active_page = "inbox";
$page_title = "Inbox - Forge Fitness Gym";
$body_class = "inner-page";
require __DIR__ . "/shared/header.php";
?>

<section class="inbox-page">
    <h1>MY INBOX</h1>
    <p>Read gym replies and send a follow-up message.</p>

    <?php if ($notice): ?>
        <div class="<?php echo $notice_type === "error" ? "contact-error" : "contact-success"; ?>">
            <?php echo e($notice); ?>
        </div>
    <?php endif; ?>

    <?php if (count($inbox) === 0): ?>
        <div class="empty-data">
            <strong>No messages yet.</strong>
            Send a question from <a href="<?php echo e(url("index.php#contact")); ?>">Contact Us</a> using this account email.
        </div>
    <?php else: ?>
        <div class="inbox-list">
            <?php foreach ($inbox as $item): ?>
                <?php
                $thread = get_message_replies($conn, (int) $item["id"]);
                $has_admin = thread_has_admin($thread);
                ?>
                <article class="inbox-card">
                    <div class="inbox-card-top">
                        <span class="gold-text"><?php echo e($item["inquiry_type"]); ?></span>
                        <small><?php echo date("M d, Y h:i A", strtotime($item["created_at"])); ?></small>
                    </div>

                    <div class="thread">
                        <div class="thread-item thread-user">
                            <strong>You</strong>
                            <small><?php echo date("M d, Y h:i A", strtotime($item["created_at"])); ?></small>
                            <p><?php echo nl2br(e($item["message"])); ?></p>
                        </div>

                        <?php foreach ($thread as $reply): ?>
                            <div class="thread-item <?php echo $reply["sender"] === "admin" ? "thread-admin" : "thread-user"; ?>">
                                <strong><?php echo $reply["sender"] === "admin" ? "Forge Fitness Gym" : "You"; ?></strong>
                                <small><?php echo date("M d, Y h:i A", strtotime($reply["created_at"])); ?></small>
                                <p><?php echo nl2br(e($reply["body"])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($has_admin): ?>
                        <form method="POST" class="admin-reply-form js-validate">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="message_id" value="<?php echo (int) $item["id"]; ?>">
                            <label for="user-reply-<?php echo (int) $item["id"]; ?>">Reply to admin</label>
                            <textarea
                                id="user-reply-<?php echo (int) $item["id"]; ?>"
                                name="user_reply_text"
                                rows="4"
                                minlength="2"
                                maxlength="2000"
                                required
                                placeholder="Type your reply..."
                            ></textarea>
                            <button type="submit" name="user_reply" value="1">SEND REPLY</button>
                        </form>
                    <?php else: ?>
                        <p class="inbox-waiting">No reply yet. You’ll be able to answer here after the gym replies.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . "/shared/footer.php"; ?>
