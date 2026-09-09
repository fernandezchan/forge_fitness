<?php
require_once __DIR__ . "/shared/bootstrap.php";
require_member();

$plans = membership_plans();
$selected_plan = get_string("plan", 20);

if ($selected_plan !== "" && array_key_exists($selected_plan, $plans)) {
    redirect("payment.php?plan=" . urlencode($selected_plan));
}

$user_id = (int) $_SESSION["user_id"];
$paid_membership = get_latest_membership($conn, $user_id);
$pending_cash = get_pending_cash_membership($conn, $user_id);
$active_membership = membership_is_active($paid_membership) ? $paid_membership : null;

$membership = $paid_membership;
$days_left = 0;
$status = "NO MEMBERSHIP";
$show_pending = false;

if (membership_is_pending_cash($pending_cash)) {
    $membership = $pending_cash;
    $show_pending = true;
    $status = "PENDING";
} elseif ($active_membership) {
    $membership = $active_membership;
    $days_left = membership_days_left($active_membership);
    $status = "ACTIVE";
} elseif ($paid_membership !== null) {
    $days_left = 0;
    $status = "EXPIRED";
}

$active_page = "membership";
$page_title = "Membership - Forge Fitness Gym";
$body_class = "inner-page";
require __DIR__ . "/shared/header.php";
?>

<section class="membership-page">
    <?php if ($membership !== null): ?>
        <div class="current-membership">
            <h2>YOUR MEMBERSHIP</h2>
            <h1><?php echo e(strtoupper($membership["plan"])); ?> MEMBER</h1>

            <div class="membership-info">
                <div>
                    <span>STATUS</span>
                    <strong><?php echo e($status); ?></strong>
                </div>
                <?php if ($show_pending): ?>
                    <div>
                        <span>PAYMENT</span>
                        <strong>CASH</strong>
                    </div>
                    <div>
                        <span>DURATION</span>
                        <strong>30 DAYS AFTER CONFIRM</strong>
                    </div>
                <?php else: ?>
                    <div>
                        <span>DAYS LEFT</span>
                        <strong><?php echo (int) $days_left; ?> <?php echo (int) $days_left === 1 ? "DAY" : "DAYS"; ?></strong>
                    </div>
                    <?php if (!empty($membership["end_date"])): ?>
                        <div>
                            <span>EXPIRES</span>
                            <strong><?php echo date("F j, Y", strtotime($membership["end_date"])); ?></strong>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($status === "EXPIRED"): ?>
                <p class="expired-message">Your membership has expired. Please renew your membership.</p>
            <?php elseif ($show_pending && $active_membership): ?>
                <p class="pending-cash-note">
                    Pay at the front desk. Your <?php echo e($active_membership["plan"]); ?> plan stays active
                    until an admin confirms this cash payment.
                </p>
            <?php elseif ($show_pending): ?>
                <p class="pending-cash-note">Pay at the front desk. An admin will confirm your cash payment to activate this plan.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="current-membership">
            <h2>YOUR MEMBERSHIP</h2>
            <h1>NO MEMBERSHIP</h1>
            <p class="expired-message">You don't have a plan yet. Choose a membership below to get started.</p>
        </div>
    <?php endif; ?>

    <h1>CHOOSE YOUR PLAN</h1>
    <p>Select the membership that fits your goals.</p>

    <?php
    $plan_link_base = url("payment.php");
    $plan_button_label = "SELECT PLAN";
    $plan_extra_class = "page-plans";
    require __DIR__ . "/shared/plan_cards.php";
    ?>
</section>

<?php require __DIR__ . "/shared/footer.php"; ?>
