<?php
require_once __DIR__ . "/../shared/bootstrap.php";
require_admin();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_message"])) {
    if (!csrf_verify()) {
        flash_set("Your session expired. Please try again.", "error");
    } else {
        $message_id = (int) ($_POST["message_id"] ?? 0);
        if ($message_id > 0 && delete_message_thread($conn, $message_id)) {
            flash_set("Message thread deleted.");
        } else {
            flash_set("Could not delete that message.", "error");
        }
    }
    redirect("admin/messages.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reply_message"])) {
    if (!csrf_verify()) {
        flash_set("Your session expired. Please try again.", "error");
    } else {
        $message_id = (int) ($_POST["message_id"] ?? 0);
        $reply = post_string("admin_reply", 2000);

        if ($message_id < 1 || $reply === "") {
            flash_set("Please type a reply before sending.", "error");
        } else {
            $find = $conn->prepare("SELECT id, name, email, inquiry_type FROM messages WHERE id = ?");
            $find->bind_param("i", $message_id);
            $find->execute();
            $original = $find->get_result()->fetch_assoc();
            $find->close();

            if (!$original) {
                flash_set("Message not found.", "error");
            } elseif (add_message_reply($conn, $message_id, "admin", $reply)) {
                $update = $conn->prepare(
                    "UPDATE messages SET admin_reply = ?, replied_at = NOW() WHERE id = ?"
                );
                $update->bind_param("si", $reply, $message_id);
                $update->execute();
                $update->close();

                $email_subject = "Reply from Forge Fitness Gym - " . $original["inquiry_type"];
                $email_body =
                    "Hi " . $original["name"] . ",\n\n" .
                    "Forge Fitness Gym replied to your message.\n\n" .
                    $reply . "\n\n" .
                    "You can also reply in your Inbox after logging in.\n\n" .
                    "Forge Fitness Gym";

                $headers =
                    "From: Forge Fitness Gym <forgefitness@gmail.com>\r\n" .
                    "Reply-To: forgefitness@gmail.com\r\n" .
                    "Content-Type: text/plain; charset=UTF-8";

                $mailed = @mail($original["email"], $email_subject, $email_body, $headers);
                flash_set(
                    $mailed
                        ? "Reply sent to " . $original["email"] . "."
                        : "Reply saved. The member can read it in Inbox."
                );
            } else {
                flash_set("Could not save the reply. Please try again.", "error");
            }
        }
    }
    redirect("admin/messages.php");
}

[$notice, $notice_type] = flash_take();

$messages = $conn->query(
    "SELECT id, name, email, inquiry_type, message, created_at
     FROM messages
     ORDER BY created_at DESC"
);

$admin_page = "messages";
$page_title = "Messages - Forge Fitness";
require __DIR__ . "/../shared/admin_header.php";
?>

<div class="admin-container">
    <div class="admin-hero">
        <div>
            <p class="admin-kicker">Forge Fitness Gym</p>
            <h1>CUSTOMER MESSAGES</h1>
            <p class="admin-welcome">Chat with members. They can reply from their Inbox after you send a reply.</p>
        </div>
        <div class="admin-hero-meta">
            <a href="<?php echo e(url("admin/")); ?>" class="admin-quick-btn admin-quick-btn-ghost">Back to dashboard</a>
        </div>
    </div>

    <?php if ($notice): ?>
        <div class="<?php echo $notice_type === "error" ? "contact-error" : "contact-success"; ?>">
            <?php echo e($notice); ?>
        </div>
    <?php endif; ?>

    <?php if ($messages && $messages->num_rows > 0): ?>
        <div class="admin-message-list">
            <?php while ($message = $messages->fetch_assoc()): ?>
                <?php
                $thread = get_message_replies($conn, (int) $message["id"]);
                $last = $thread ? $thread[count($thread) - 1] : null;
                $has_admin = thread_has_admin($thread);
                ?>
                <article class="admin-message-card">
                    <div class="admin-message-top">
                        <div>
                            <h3><?php echo e($message["name"]); ?></h3>
                            <p>
                                <a href="mailto:<?php echo e($message["email"]); ?>">
                                    <?php echo e($message["email"]); ?>
                                </a>
                            </p>
                        </div>
                        <div class="admin-message-meta">
                            <span class="gold-text"><?php echo e($message["inquiry_type"]); ?></span>
                            <small><?php echo date("M d, Y h:i A", strtotime($message["created_at"])); ?></small>
                            <?php if (!$has_admin): ?>
                                <span class="status-pending">PENDING</span>
                            <?php elseif ($last && $last["sender"] === "user"): ?>
                                <span class="status-pending">MEMBER REPLIED</span>
                            <?php else: ?>
                                <span class="status-active">REPLIED</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="thread">
                        <div class="thread-item thread-user">
                            <strong>Member</strong>
                            <small><?php echo date("M d, Y h:i A", strtotime($message["created_at"])); ?></small>
                            <p><?php echo nl2br(e($message["message"])); ?></p>
                        </div>

                        <?php foreach ($thread as $item): ?>
                            <div class="thread-item <?php echo $item["sender"] === "admin" ? "thread-admin" : "thread-user"; ?>">
                                <strong><?php echo $item["sender"] === "admin" ? "You (Admin)" : "Member"; ?></strong>
                                <small><?php echo date("M d, Y h:i A", strtotime($item["created_at"])); ?></small>
                                <p><?php echo nl2br(e($item["body"])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="admin-message-actions">
                    <form method="POST" class="admin-reply-form js-validate">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="message_id" value="<?php echo (int) $message["id"]; ?>">
                        <input type="hidden" name="reply_message" value="1">
                        <label for="reply-<?php echo (int) $message["id"]; ?>">Write a reply</label>
                        <textarea
                            id="reply-<?php echo (int) $message["id"]; ?>"
                            name="admin_reply"
                            rows="4"
                            minlength="2"
                            maxlength="2000"
                            required
                            placeholder="Type your reply to <?php echo e($message["name"]); ?>..."
                        ></textarea>
                        <button type="submit" name="reply_message" value="1">SEND REPLY</button>
                    </form>

                    <form
                        method="POST"
                        class="inline-form js-admin-confirm"
                        data-title="DELETE THREAD"
                        data-message="Delete the whole conversation with <?php echo e($message["name"]); ?>?"
                        data-ok="DELETE"
                        data-danger="1"
                    >
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="message_id" value="<?php echo (int) $message["id"]; ?>">
                        <input type="hidden" name="delete_message" value="1">
                        <button type="submit" class="danger-btn">DELETE THREAD</button>
                    </form>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="admin-empty-card">
            <strong>No messages yet</strong>
            <p>Customer inquiries from the contact form will appear here. You can also open the public site from the top bar.</p>
            <a href="<?php echo e(url("admin/")); ?>" class="admin-quick-btn">Back to dashboard</a>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . "/../shared/admin_footer.php"; ?>
