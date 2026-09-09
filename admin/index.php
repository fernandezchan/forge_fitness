<?php
require_once __DIR__ . "/../shared/bootstrap.php";
require_admin();

$notice = "";
$notice_type = "success";
$search = get_string("q", 80);
$status_filter = get_string("status", 20);
if (!in_array($status_filter, ["active", "expired", "pending"], true)) {
    $status_filter = "all";
}

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && isset($_POST["delete_membership"])) {
    if (!csrf_verify()) {
        flash_set("Your session expired. Please try again.", "error");
    } else {
        $membership_id = (int) ($_POST["membership_id"] ?? 0);
        if ($membership_id > 0 && delete_membership($conn, $membership_id)) {
            flash_set("Membership record deleted.");
        } else {
            flash_set("Could not delete that membership.", "error");
        }
    }
    redirect("admin/?stay=memberships");
}

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && isset($_POST["delete_user"])) {
    if (!csrf_verify()) {
        flash_set("Your session expired. Please try again.", "error");
    } else {
        $account_id = (int) ($_POST["user_id"] ?? 0);
        if ($account_id > 0 && delete_user_account($conn, $account_id)) {
            flash_set("Member account removed. If they were logged in, they will be signed out.");
        } else {
            flash_set("Could not remove that account.", "error");
        }
    }
    redirect("admin/?stay=accounts");
}

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && isset($_POST["grant_membership"])) {
    if (!csrf_verify()) {
        flash_set("Your session expired. Please try again.", "error");
    } else {
        $account_id = (int) ($_POST["grant_user_id"] ?? 0);
        $plan = post_string("grant_plan", 20);
        $method = post_string("grant_payment_method", 50);
        $plans = membership_plans();

        if (!in_array($method, payment_methods(), true)) {
            $method = "Cash";
        }

        if ($account_id < 1 || !array_key_exists($plan, $plans)) {
            flash_set("Please choose a member and a plan.", "error");
        } else {
            $member = find_member_user($conn, $account_id);
            if ($member === null) {
                flash_set("That member account was not found.", "error");
            } elseif ($method === "Cash") {
                if (get_pending_cash_membership($conn, $account_id) !== null) {
                    flash_set("This member already has a walk-in payment waiting. Confirm it in Membership Records.", "error");
                } elseif (request_cash_membership($conn, $account_id, $plan, $plans[$plan])) {
                    flash_set("Walk-in " . $plan . " payment recorded for " . $member["name"] . ". Confirm it in Membership Records to activate.");
                } else {
                    flash_set("Could not record that walk-in payment.", "error");
                }
            } else {
                $start_date = date("Y-m-d H:i:s");
                $end_date = date("Y-m-d H:i:s", strtotime("+30 days"));
                if (record_membership_payment($conn, $account_id, $plan, $plans[$plan], $method, $start_date, $end_date)) {
                    flash_set($plan . " membership granted to " . $member["name"] . " for 30 days.");
                } else {
                    flash_set("Could not grant that membership.", "error");
                }
            }
        }
    }
    redirect("admin/?stay=memberships");
}

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && isset($_POST["confirm_cash"])) {
    if (!csrf_verify()) {
        flash_set("Your session expired. Please try again.", "error");
    } else {
        $membership_id = (int) ($_POST["membership_id"] ?? 0);
        $pending_stmt = $conn->prepare(
            "SELECT m.plan, m.payment_status, m.payment_method, u.name
             FROM memberships m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.id = ?
             LIMIT 1"
        );
        $pending_stmt->bind_param("i", $membership_id);
        $pending_stmt->execute();
        $pending_row = $pending_stmt->get_result()->fetch_assoc();
        $pending_stmt->close();

        if (!membership_is_pending_cash($pending_row)) {
            flash_set("That cash payment is not waiting for confirmation.", "error");
        } elseif (confirm_cash_membership($conn, $membership_id)) {
            flash_set("Cash payment confirmed. " . $pending_row["plan"] . " is now active for " . $pending_row["name"] . ".");
        } else {
            flash_set("Could not confirm that cash payment.", "error");
        }
    }
    redirect("admin/?stay=memberships");
}

[$notice, $notice_type] = flash_take();
$stay = get_string("stay", 20);
if (!in_array($stay, ["records", "grant", "accounts", "memberships"], true)) {
    $stay = "";
}

$total_users = count_table($conn, "SELECT COUNT(*) AS total FROM users WHERE role <> 'admin'");
$total_memberships = count_table($conn, "SELECT COUNT(*) AS total FROM memberships");
$active_memberships = count_table(
    $conn,
    "SELECT COUNT(*) AS total FROM memberships WHERE end_date > NOW() AND payment_status = 'Paid'"
);
$expired_memberships = count_table(
    $conn,
    "SELECT COUNT(*) AS total FROM memberships WHERE payment_status = 'Paid' AND end_date <= NOW()"
);
$expiring_soon = count_table(
    $conn,
    "SELECT COUNT(*) AS total FROM memberships
     WHERE payment_status = 'Paid'
       AND end_date > NOW()
       AND end_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)"
);
$new_this_month = count_table(
    $conn,
    "SELECT COUNT(*) AS total FROM users
     WHERE role <> 'admin' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
);
$total_messages = count_table($conn, "SELECT COUNT(*) AS total FROM messages");
$pending_messages = count_table(
    $conn,
    "SELECT COUNT(*) AS total FROM messages m
     WHERE (m.admin_reply IS NULL OR m.admin_reply = '')
       AND NOT EXISTS (
           SELECT 1 FROM message_replies r
           WHERE r.message_id = m.id AND r.sender = 'admin'
       )"
);
$pending_cash_count = count_table(
    $conn,
    "SELECT COUNT(*) AS total FROM memberships WHERE payment_status = 'Pending' AND payment_method = 'Cash'"
);

$revenue_row = $conn->query("SELECT COALESCE(SUM(price), 0) AS total FROM memberships WHERE payment_status = 'Paid'");
$total_revenue = $revenue_row ? (float) $revenue_row->fetch_assoc()["total"] : 0.0;

$plan_counts = ["Basic" => 0, "Premium" => 0, "Elite" => 0];
$plan_result = $conn->query(
    "SELECT plan, COUNT(*) AS total
     FROM memberships
     WHERE payment_status = 'Paid' AND end_date > NOW()
     GROUP BY plan"
);
if ($plan_result) {
    while ($row = $plan_result->fetch_assoc()) {
        $plan_name = $row["plan"];
        if (isset($plan_counts[$plan_name])) {
            $plan_counts[$plan_name] = (int) $row["total"];
        }
    }
}
$plan_max = max($plan_counts) ?: 1;

$pending_list = $conn->query(
    "SELECT id, name, email, inquiry_type, message, created_at
     FROM messages m
     WHERE (m.admin_reply IS NULL OR m.admin_reply = '')
       AND NOT EXISTS (
           SELECT 1 FROM message_replies r
           WHERE r.message_id = m.id AND r.sender = 'admin'
       )
     ORDER BY created_at DESC
     LIMIT 5"
);

$expiring_list = $conn->query(
    "SELECT memberships.end_date, users.name, users.email, memberships.plan
     FROM memberships
     INNER JOIN users ON memberships.user_id = users.id
     WHERE memberships.payment_status = 'Paid'
       AND memberships.end_date > NOW()
       AND memberships.end_date <= DATE_ADD(NOW(), INTERVAL 14 DAY)
     ORDER BY memberships.end_date ASC
     LIMIT 5"
);

$pending_cash_rows = [];
$pending_cash_result = $conn->query(
    "SELECT memberships.id, memberships.plan, memberships.price, memberships.start_date,
            users.name, users.email
     FROM memberships
     INNER JOIN users ON memberships.user_id = users.id
     WHERE memberships.payment_status = 'Pending' AND memberships.payment_method = 'Cash'
     ORDER BY memberships.id DESC"
);
if ($pending_cash_result) {
    while ($cash_row = $pending_cash_result->fetch_assoc()) {
        $pending_cash_rows[] = $cash_row;
    }
}

$like = "%" . $search . "%";
$accounts_sql =
    "SELECT u.id, u.name, u.email, u.created_at,
            m.plan, m.payment_status, m.payment_method, m.end_date
     FROM users u
     LEFT JOIN memberships m ON m.id = (
         SELECT m2.id FROM memberships m2
         WHERE m2.user_id = u.id
         ORDER BY (m2.payment_status = 'Paid' AND m2.end_date > NOW()) DESC,
                  (m2.payment_status = 'Pending') DESC,
                  m2.id DESC
         LIMIT 1
     )
     WHERE u.role <> 'admin'";
if ($search !== "") {
    $accounts_sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
}
$accounts_sql .= " ORDER BY u.id DESC";

$accounts_stmt = $conn->prepare($accounts_sql);
if ($search !== "") {
    $accounts_stmt->bind_param("ss", $like, $like);
}
$accounts_stmt->execute();
$accounts = $accounts_stmt->get_result();

$members_sql =
    "SELECT memberships.id, memberships.plan, memberships.price, memberships.payment_method,
            memberships.payment_status, memberships.start_date, memberships.end_date,
            users.name, users.email
     FROM memberships
     INNER JOIN users ON memberships.user_id = users.id
     WHERE 1 = 1";
$member_types = "";
$member_params = [];

if ($search !== "") {
    $members_sql .= " AND (users.name LIKE ? OR users.email LIKE ?)";
    $member_types .= "ss";
    $member_params[] = $like;
    $member_params[] = $like;
}

if ($status_filter === "active") {
    $members_sql .= " AND memberships.payment_status = 'Paid' AND memberships.end_date > NOW()";
} elseif ($status_filter === "expired") {
    $members_sql .= " AND memberships.payment_status = 'Paid' AND memberships.end_date <= NOW()";
} elseif ($status_filter === "pending") {
    $members_sql .= " AND memberships.payment_status = 'Pending'";
}

$members_sql .= " ORDER BY (memberships.payment_status = 'Pending') DESC, memberships.id DESC";
$members_stmt = $conn->prepare($members_sql);
if ($member_types !== "") {
    $members_stmt->bind_param($member_types, ...$member_params);
}
$members_stmt->execute();
$members = $members_stmt->get_result();

$grant_members = [];
$grant_result = $conn->query(
    "SELECT u.id, u.name, u.email,
            CASE WHEN m.payment_status = 'Paid' AND m.end_date > NOW() THEN 1 ELSE 0 END AS is_active
     FROM users u
     LEFT JOIN memberships m ON m.id = (
         SELECT m2.id FROM memberships m2
         WHERE m2.user_id = u.id
         ORDER BY (m2.payment_status = 'Paid' AND m2.end_date > NOW()) DESC, m2.id DESC
         LIMIT 1
     )
     WHERE u.role <> 'admin'
     ORDER BY u.name ASC, u.id DESC"
);
if ($grant_result) {
    while ($grant_row = $grant_result->fetch_assoc()) {
        $grant_members[] = $grant_row;
    }
}

$admin_page = "dashboard";
$page_title = "Admin Dashboard - Forge Fitness";
require __DIR__ . "/../shared/admin_header.php";
?>

<div class="admin-container">
    <div class="admin-hero">
        <div>
            <h1>ADMIN DASHBOARD</h1>
            <p class="admin-welcome">
                Welcome back, <?php echo e($_SESSION["admin_username"] ?? $_SESSION["user_name"] ?? "Admin"); ?>.
                Here’s what’s happening at Forge Fitness today.
            </p>
        </div>
        <div class="admin-hero-meta">
            <span><?php echo date("l, F j, Y"); ?></span>
            <a href="<?php echo e(url("admin/messages.php")); ?>" class="admin-quick-btn">Open inbox<?php echo $pending_messages > 0 ? " (" . $pending_messages . ")" : ""; ?></a>
        </div>
    </div>

    <div class="admin-stats">
        <div class="admin-stat-card">
            <h3>REGISTERED MEMBERS</h3>
            <h2><?php echo $total_users; ?></h2>
            <p><?php echo $new_this_month; ?> new this month</p>
        </div>
        <div class="admin-stat-card">
            <h3>ACTIVE PLANS</h3>
            <h2><?php echo $active_memberships; ?></h2>
            <p><?php echo $expired_memberships; ?> expired</p>
        </div>
        <div class="admin-stat-card">
            <h3>REVENUE</h3>
            <h2>₱<?php echo number_format($total_revenue, 0); ?></h2>
            <p><?php echo $total_memberships; ?> membership records</p>
        </div>
        <div class="admin-stat-card">
            <h3>NEEDS ATTENTION</h3>
            <h2><?php echo $pending_messages + $expiring_soon + $pending_cash_count; ?></h2>
            <p><?php echo $pending_messages; ?> unread · <?php echo $pending_cash_count; ?> cash · <?php echo $expiring_soon; ?> expiring in 7 days</p>
        </div>
    </div>

    <div class="admin-split">
        <section class="admin-panel">
            <div class="admin-panel-head">
                <h2>ACTIVE PLAN MIX</h2>
            </div>
            <div class="admin-plan-list">
                <?php foreach ($plan_counts as $plan_name => $plan_total): ?>
                    <?php $width = (int) round(($plan_total / $plan_max) * 100); ?>
                    <div class="admin-plan-row">
                        <div class="admin-plan-label">
                            <strong><?php echo e($plan_name); ?></strong>
                            <span><?php echo $plan_total; ?> active</span>
                        </div>
                        <div class="admin-plan-bar" aria-hidden="true">
                            <span style="width: <?php echo $width; ?>%"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head">
                <h2>NEEDS ATTENTION</h2>
                <a href="<?php echo e(url("admin/messages.php")); ?>">View all messages</a>
            </div>
            <?php if ($pending_list && $pending_list->num_rows > 0): ?>
                <ul class="admin-attention">
                    <?php while ($item = $pending_list->fetch_assoc()): ?>
                        <li>
                            <div>
                                <strong><?php echo e($item["name"]); ?></strong>
                                <span><?php echo e($item["inquiry_type"]); ?></span>
                            </div>
                            <small><?php echo date("M d", strtotime($item["created_at"])); ?></small>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="admin-empty-note">No unread member messages.</p>
            <?php endif; ?>

            <?php if ($pending_cash_rows !== []): ?>
                <h3 class="admin-subhead">Cash at gym</h3>
                <ul class="admin-attention">
                    <?php foreach (array_slice($pending_cash_rows, 0, 5) as $item): ?>
                        <li>
                            <div>
                                <strong><?php echo e($item["name"]); ?></strong>
                                <span><?php echo e($item["plan"]); ?> · ₱<?php echo number_format((float) $item["price"], 0); ?></span>
                            </div>
                            <a href="<?php echo e(url("admin/?status=pending&stay=memberships")); ?>" class="gold-text">Review</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($expiring_list && $expiring_list->num_rows > 0): ?>
                <h3 class="admin-subhead">Expiring soon</h3>
                <ul class="admin-attention">
                    <?php while ($item = $expiring_list->fetch_assoc()): ?>
                        <li>
                            <div>
                                <strong><?php echo e($item["name"]); ?></strong>
                                <span><?php echo e($item["plan"]); ?> · <?php echo date("M d", strtotime($item["end_date"])); ?></span>
                            </div>
                            <small class="gold-text">Soon</small>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <form id="records" class="admin-toolbar" method="GET" action="<?php echo e(url("admin/")); ?>#records">
        <input type="search" name="q" value="<?php echo e($search); ?>" placeholder="Search name or email" maxlength="80">
        <select name="status">
            <option value="all" <?php echo $status_filter === "all" ? "selected" : ""; ?>>All memberships</option>
            <option value="active" <?php echo $status_filter === "active" ? "selected" : ""; ?>>Active only</option>
            <option value="expired" <?php echo $status_filter === "expired" ? "selected" : ""; ?>>Expired only</option>
            <option value="pending" <?php echo $status_filter === "pending" ? "selected" : ""; ?>>Pending cash</option>
        </select>
        <button type="submit">FILTER</button>
        <?php if ($search !== "" || $status_filter !== "all"): ?>
            <a href="<?php echo e(url("admin/#records")); ?>" class="admin-clear">Clear</a>
        <?php endif; ?>
    </form>

    <div id="accounts" class="admin-table-box">
        <div class="admin-table-header">
            <h2>MEMBER ACCOUNTS</h2>
            <p><?php echo (int) $accounts->num_rows; ?> registered members</p>
            <?php if ($notice && $stay === "accounts"): ?>
                <div class="<?php echo $notice_type === "error" ? "contact-error" : "contact-success"; ?>">
                    <?php echo e($notice); ?>
                </div>
            <?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>MEMBER</th>
                    <th>EMAIL</th>
                    <th>JOINED</th>
                    <th>CURRENT PLAN</th>
                    <th>DAYS LEFT</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($accounts && $accounts->num_rows > 0): ?>
                    <?php while ($account = $accounts->fetch_assoc()): ?>
                        <?php
                        $has_plan = !empty($account["plan"]);
                        $is_pending = membership_is_pending_cash($account);
                        $is_active = membership_is_active($account);
                        $days_left = membership_days_left($account);
                        $seconds_left = membership_seconds_left($account);
                        ?>
                        <tr>
                            <td><?php echo e($account["name"]); ?></td>
                            <td><?php echo e($account["email"]); ?></td>
                            <td><?php echo date("M d, Y", strtotime($account["created_at"])); ?></td>
                            <td class="gold-text"><?php echo $has_plan ? e($account["plan"]) : "None"; ?></td>
                            <td>
                                <?php
                                if (!$has_plan) {
                                    echo "—";
                                } elseif ($is_pending) {
                                    echo "Awaiting";
                                } elseif (!$is_active) {
                                    echo "Expired";
                                } elseif ($seconds_left < 86400) {
                                    echo "Expires Today";
                                } else {
                                    echo (int) $days_left . ((int) $days_left === 1 ? " Day" : " Days");
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($is_active): ?>
                                    <span class="status-active">ACTIVE</span>
                                <?php elseif ($is_pending): ?>
                                    <span class="status-pending">PENDING</span>
                                <?php elseif ($has_plan): ?>
                                    <span class="status-expired">EXPIRED</span>
                                <?php else: ?>
                                    <span class="status-pending">NO PLAN</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form
                                    method="POST"
                                    class="inline-form js-admin-confirm"
                                    data-stay="accounts"
                                    data-title="REMOVE MEMBER"
                                    data-message="Remove <?php echo e($account["name"]); ?>’s account and membership records? If they are logged in, they will be signed out."
                                    data-ok="REMOVE"
                                    data-danger="1"
                                >
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="user_id" value="<?php echo (int) $account["id"]; ?>">
                                    <input type="hidden" name="delete_user" value="1">
                                    <button type="submit" class="danger-btn">REMOVE</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-table">No member accounts match this search.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="memberships" class="admin-table-box">
        <div class="admin-table-header">
            <h2>MEMBERSHIP RECORDS</h2>
            <p>
                Payments, dates, and plan history.
                <?php if ($pending_cash_count > 0): ?>
                    <?php echo (int) $pending_cash_count; ?> cash payment<?php echo $pending_cash_count === 1 ? "" : "s"; ?> waiting.
                <?php endif; ?>
            </p>
            <?php if ($notice && $stay === "memberships"): ?>
                <div class="<?php echo $notice_type === "error" ? "contact-error" : "contact-success"; ?>">
                    <?php echo e($notice); ?>
                </div>
            <?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>MEMBER</th>
                    <th>EMAIL</th>
                    <th>PLAN</th>
                    <th>PRICE</th>
                    <th>PAYMENT</th>
                    <th>START DATE</th>
                    <th>END DATE</th>
                    <th>DAYS LEFT</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($members && $members->num_rows > 0): ?>
                    <?php while ($member = $members->fetch_assoc()): ?>
                        <?php
                        $is_pending = membership_is_pending_cash($member);
                        $is_active = membership_is_active($member);
                        $days_left = membership_days_left($member);
                        $seconds_left = membership_seconds_left($member);
                        ?>
                        <tr>
                            <td><?php echo e($member["name"]); ?></td>
                            <td><?php echo e($member["email"]); ?></td>
                            <td class="gold-text"><?php echo e($member["plan"]); ?></td>
                            <td>₱<?php echo number_format((float) $member["price"], 2); ?></td>
                            <td><?php echo e($member["payment_method"]); ?></td>
                            <td><?php echo date("M d, Y", strtotime($member["start_date"])); ?></td>
                            <td><?php echo $is_pending ? "—" : date("M d, Y", strtotime($member["end_date"])); ?></td>
                            <td>
                                <?php
                                if ($is_pending) {
                                    echo "Awaiting";
                                } elseif (!$is_active) {
                                    echo "Expired";
                                } elseif ($seconds_left < 86400) {
                                    echo "Expires Today";
                                } else {
                                    echo (int) $days_left . ((int) $days_left === 1 ? " Day" : " Days");
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($is_active): ?>
                                    <span class="status-active">ACTIVE</span>
                                <?php elseif ($is_pending): ?>
                                    <span class="status-pending">PENDING</span>
                                <?php else: ?>
                                    <span class="status-expired">EXPIRED</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-row-actions">
                                    <?php if ($is_pending): ?>
                                        <form
                                            method="POST"
                                            class="inline-form js-admin-confirm"
                                            data-stay="memberships"
                                            data-title="CONFIRM PAYMENT"
                                            data-ok="CONFIRM PAYMENT"
                                            data-member="<?php echo e($member["name"]); ?>"
                                            data-plan="<?php echo e($member["plan"]); ?>"
                                        >
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="membership_id" value="<?php echo (int) $member["id"]; ?>">
                                            <input type="hidden" name="confirm_cash" value="1">
                                            <button type="submit" class="confirm-btn">CONFIRM PAYMENT</button>
                                        </form>
                                    <?php endif; ?>
                                    <form
                                        method="POST"
                                        class="inline-form js-admin-confirm"
                                        data-stay="memberships"
                                        data-title="DELETE RECORD"
                                        data-message="Delete this <?php echo e($member["plan"]); ?> membership record for <?php echo e($member["name"]); ?>?"
                                        data-ok="DELETE"
                                        data-danger="1"
                                    >
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="membership_id" value="<?php echo (int) $member["id"]; ?>">
                                        <input type="hidden" name="delete_membership" value="1">
                                        <button type="submit" class="danger-btn">DELETE</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="empty-table">No membership records match this filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <section id="grant" class="admin-panel admin-grant-panel">
        <div class="admin-panel-head">
            <h2>GRANT MEMBERSHIP</h2>
        </div>
        <p class="admin-empty-note">
            For Contact Us and walk-in members. Cash (walk-in) stays pending until you confirm payment in Membership Records.
            GCash or card starts the 30-day plan right away.
        </p>
        <?php if ($grant_members === []): ?>
            <p class="admin-empty-note">No registered members to grant a plan to.</p>
        <?php else: ?>
            <form method="POST" class="admin-grant-form">
                <?php echo csrf_field(); ?>
                <label>
                    Member
                    <select name="grant_user_id" id="grant_user_id" required>
                        <option value="">Select member</option>
                        <?php foreach ($grant_members as $grant_member): ?>
                            <?php $option_id = (int) $grant_member["id"]; ?>
                            <option value="<?php echo $option_id; ?>" data-active="<?php echo (int) $grant_member["is_active"]; ?>">
                                <?php echo e($grant_member["name"]); ?> (<?php echo e($grant_member["email"]); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Plan
                    <select name="grant_plan" id="grant_plan" required>
                        <option value="">Select plan</option>
                        <?php foreach (membership_plans() as $plan_name => $plan_price): ?>
                            <option value="<?php echo e($plan_name); ?>">
                                <?php echo e($plan_name); ?> — ₱<?php echo number_format((float) $plan_price, 0); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Payment
                    <select name="grant_payment_method" id="grant_payment_method" required>
                        <?php foreach (payment_methods() as $method_name): ?>
                            <option value="<?php echo e($method_name); ?>" <?php echo $method_name === "Cash" ? "selected" : ""; ?>>
                                <?php echo $method_name === "Cash" ? "Cash (walk-in)" : e($method_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" name="grant_membership" value="1">GRANT MEMBERSHIP</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<div class="admin-modal" id="admin-confirm-modal" hidden>
    <div class="admin-modal-backdrop" data-modal-close="1"></div>
    <div class="admin-modal-card" role="dialog" aria-modal="true" aria-labelledby="admin-confirm-title">
        <h2 id="admin-confirm-title">CONFIRM</h2>
        <p id="admin-confirm-text"></p>
        <div class="admin-modal-actions">
            <button type="button" class="admin-modal-cancel" data-modal-close="1">CANCEL</button>
            <button type="button" class="admin-modal-ok" id="admin-confirm-ok">CONFIRM</button>
        </div>
    </div>
</div>
<?php if ($stay !== ""): ?>
<script>
(function () {
    var nav = performance.getEntriesByType && performance.getEntriesByType("navigation")[0];
    if (nav && nav.type === "reload") {
        window.scrollTo(0, 0);
        return;
    }
    var target = document.getElementById(<?php echo json_encode($stay); ?>);
    if (target) {
        target.scrollIntoView({ behavior: "auto", block: "start" });
    }
})();
</script>
<?php endif; ?>

<?php
$accounts_stmt->close();
$members_stmt->close();
require __DIR__ . "/../shared/admin_footer.php";
?>
