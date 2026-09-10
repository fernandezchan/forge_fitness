<?php
require_once __DIR__ . "/shared/bootstrap.php";
require_member();

$plans = membership_plans();
$plan = get_string("plan", 20);

if ($plan === "" || !array_key_exists($plan, $plans)) {
    redirect("membership.php");
}

$price = $plans[$plan];
$user_id = (int) $_SESSION["user_id"];
$success = "";
$error = "";
$end_date = "";

$current_membership = get_latest_membership($conn, $user_id);
$current_plan = $current_membership ? $current_membership["plan"] : "";
$payment_method = "GCash";
$gcash_number = "";
$card_name = "";
$card_number = "";
$card_expiry = "";
$card_cvv = "";

$already_active = membership_is_active($current_membership) && $current_plan === $plan;
$pending_cash = get_pending_cash_membership($conn, $user_id);
$pending_this_plan = membership_is_pending_cash($pending_cash) && ($pending_cash["plan"] ?? "") === $plan;

$paid = get_string("paid", 4) === "1";
$cash_requested = get_string("cash", 4) === "1";

if ($already_active && ($paid || $cash_requested)) {
    $success = "Payment successful! Your " . $plan . " membership is now active.";
    $end_date = (string) $current_membership["end_date"];
} elseif ($already_active && !$pending_this_plan) {
    $error = "You already have an active " . $plan . " membership.";
}

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && !$already_active) {
    if (!csrf_verify()) {
        $error = "Your session expired. Please try again.";
    } else {
        $payment_method = post_string("payment_method", 50);
        $gcash_number = post_string("gcash_number", 20);
        $card_name = post_string("card_name", 80);
        $card_number = post_string("card_number", 30);
        $card_expiry = post_string("card_expiry", 7);
        $card_cvv = post_string("card_cvv", 4);
        $card_error = demo_card_error($card_name, $card_number, $card_expiry, $card_cvv);

        if (!in_array($payment_method, payment_methods(), true)) {
            $error = "Please choose a valid payment method.";
        } elseif ($payment_method === "GCash" && !is_valid_gcash_number($gcash_number)) {
            $error = "Please enter a valid GCash number (09XXXXXXXXX).";
        } elseif ($payment_method === "Credit Card" && $card_error !== "") {
            $error = $card_error;
        } elseif ($payment_method === "Cash") {
            if ($pending_this_plan) {
                redirect("payment.php?plan=" . urlencode($plan) . "&cash=1");
            } elseif ($pending_cash !== null) {
                $error = "You already have a cash payment waiting for your " . $pending_cash["plan"] . " plan.";
            } elseif (request_cash_membership($conn, $user_id, $plan, $price)) {
                redirect("payment.php?plan=" . urlencode($plan) . "&cash=1");
            } else {
                $error = "Something went wrong. Please try again.";
            }
        } else {
            $start_date = date("Y-m-d H:i:s");
            $end_date = date("Y-m-d H:i:s", strtotime("+30 days"));

            $saved = record_membership_payment(
                $conn,
                $user_id,
                $plan,
                $price,
                $payment_method,
                $start_date,
                $end_date
            );

            if ($saved) {
                redirect("payment.php?plan=" . urlencode($plan) . "&paid=1");
            }

            $error = "Something went wrong. Please try again.";
        }
    }
}

if ($cash_requested || $pending_this_plan) {
    $pending_cash = get_pending_cash_membership($conn, $user_id);
    $pending_this_plan = membership_is_pending_cash($pending_cash) && ($pending_cash["plan"] ?? "") === $plan;
}

$active_page = "membership";
$page_title = "Payment - Forge Fitness Gym";
$body_class = "auth-body";
require __DIR__ . "/shared/header.php";
?>

<div class="auth-wrap">
    <div class="payment-box">
        <a href="<?php echo e(url("index.php")); ?>" class="auth-logo">
            <img src="<?php echo e(url("assets/images/logo.png?v=2")); ?>" alt="Forge Fitness">
        </a>

        <?php if ($success): ?>
            <div class="payment-success">
                <h1>PAYMENT SUCCESSFUL</h1>
                <p><?php echo e($success); ?></p>
                <h2><?php echo e(strtoupper($plan)); ?> MEMBER</h2>
                <h3>₱<?php echo number_format($price); ?> / MONTH</h3>
                <p>Membership Status: <strong>ACTIVE</strong></p>
                <p>Membership Duration: <strong>30 DAYS</strong></p>
                <p>
                    Expires:
                    <strong><?php echo date("F j, Y", strtotime($end_date)); ?></strong>
                </p>
                <a href="<?php echo e(url("membership.php")); ?>" class="payment-home">VIEW MY MEMBERSHIP</a>
            </div>
        <?php elseif ($pending_this_plan): ?>
            <div class="payment-success">
                <h1>PAY AT THE GYM</h1>
                <p>Your cash payment is waiting for staff confirmation.</p>
                <h2><?php echo e(strtoupper($plan)); ?> MEMBER</h2>
                <h3>₱<?php echo number_format($price); ?> / MONTH</h3>
                <p>Membership Status: <strong>PENDING</strong></p>
                <p>Pay <strong>₱<?php echo number_format($price); ?></strong> at the front desk.</p>
                <p>Your 30-day plan starts after an admin confirms the payment.</p>
                <a href="<?php echo e(url("membership.php")); ?>" class="payment-home">VIEW MY MEMBERSHIP</a>
            </div>
        <?php elseif ($error && $already_active): ?>
            <h1>PAYMENT</h1>
            <div class="error-message"><?php echo e($error); ?></div>
            <a href="<?php echo e(url("membership.php")); ?>" class="payment-home">BACK TO MEMBERSHIPS</a>
        <?php else: ?>
            <h1>PAYMENT</h1>
            <p>Complete your membership.</p>

            <?php if ($error): ?>
                <div class="error-message"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="payment-summary">
                <h2><?php echo e($plan); ?></h2>
                <h3>₱<?php echo number_format($price); ?> / MONTH</h3>
                <p>Membership Duration: <strong>30 DAYS</strong></p>
            </div>

            <form method="POST" class="js-validate" id="payment-form">
                <?php echo csrf_field(); ?>
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method" required>
                    <?php foreach (payment_methods() as $method): ?>
                        <option value="<?php echo e($method); ?>" <?php echo $payment_method === $method ? "selected" : ""; ?>>
                            <?php echo $method === "Cash" ? "Cash at Gym" : e($method); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="pay-fields" id="pay-gcash" data-method="GCash">
                    <label for="gcash_number">GCash Number</label>
                    <input
                        type="tel"
                        id="gcash_number"
                        name="gcash_number"
                        placeholder="09XXXXXXXXX"
                        value="<?php echo e($gcash_number); ?>"
                        maxlength="13"
                        inputmode="numeric"
                        autocomplete="tel"
                        data-needed="1"
                        data-kind="gcash"
                    >
                </div>

                <div class="pay-fields" id="pay-card" data-method="Credit Card" hidden>
                    <label for="card_name">Name on Card</label>
                    <input
                        type="text"
                        id="card_name"
                        name="card_name"
                        placeholder="Name on Card"
                        value="<?php echo e($card_name); ?>"
                        maxlength="80"
                        autocomplete="cc-name"
                        data-needed="1"
                        minlength="2"
                    >
                    <label for="card_number">Card Number</label>
                    <input
                        type="text"
                        id="card_number"
                        name="card_number"
                        placeholder="ACCT-000015"
                        value="<?php echo e($card_number); ?>"
                        maxlength="23"
                        inputmode="numeric"
                        autocomplete="cc-number"
                        data-needed="1"
                        data-kind="card"
                    >
                    <div class="pay-row">
                        <div>
                            <label for="card_expiry">Expiry (MM/YY)</label>
                            <input
                                type="text"
                                id="card_expiry"
                                name="card_expiry"
                                placeholder="MM/YY"
                                value="<?php echo e($card_expiry); ?>"
                                maxlength="5"
                                inputmode="numeric"
                                autocomplete="cc-exp"
                                data-needed="1"
                                data-kind="expiry"
                            >
                        </div>
                        <div>
                            <label for="card_cvv">CVV</label>
                            <input
                                type="password"
                                id="card_cvv"
                                name="card_cvv"
                                placeholder="123"
                                value="<?php echo e($card_cvv); ?>"
                                maxlength="4"
                                inputmode="numeric"
                                autocomplete="cc-csc"
                                data-needed="1"
                                data-kind="cvv"
                            >
                        </div>
                    </div>
                </div>

                <div class="pay-fields" id="pay-cash" data-method="Cash" hidden>
                    <p class="pay-note">Pay in cash at the front desk. Staff will confirm it on the admin dashboard, then your 30-day plan starts.</p>
                </div>

                <button type="submit">COMPLETE PAYMENT</button>
            </form>

            <p class="fake-payment">Demo payment system. No real money will be charged.</p>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . "/shared/footer.php"; ?>
