<?php
$plan_link_base = $plan_link_base ?? url("membership.php");
$plan_button_label = $plan_button_label ?? "JOIN NOW";
$plan_extra_class = $plan_extra_class ?? "";
?>
<div class="plans-container<?php echo $plan_extra_class !== "" ? " " . e($plan_extra_class) : ""; ?>">
    <article class="plan-card">
        <p class="plan-kicker">Starter</p>
        <h2>BASIC</h2>
        <h3>₱799 <span>/ MONTH</span></h3>
        <ul>
            <li>Gym access</li>
            <li>Locker access</li>
        </ul>
        <a href="<?php echo e($plan_link_base); ?>?plan=Basic" class="join-btn"><?php echo e($plan_button_label); ?></a>
    </article>

    <article class="plan-card featured">
        <p class="plan-badge">Most popular</p>
        <h2>PREMIUM</h2>
        <h3>₱999 <span>/ MONTH</span></h3>
        <ul>
            <li>Gym access</li>
            <li>Locker access</li>
            <li>Personal workout plan</li>
            <li>Group workout sessions</li>
        </ul>
        <a href="<?php echo e($plan_link_base); ?>?plan=Premium" class="join-btn"><?php echo e($plan_button_label); ?></a>
    </article>

    <article class="plan-card">
        <p class="plan-kicker">All access</p>
        <h2>ELITE</h2>
        <h3>₱1,399 <span>/ MONTH</span></h3>
        <ul>
            <li>Gym access</li>
            <li>Locker access</li>
            <li>Group workout sessions</li>
            <li>Personal workout plan</li>
            <li>Personal coach included</li>
        </ul>
        <a href="<?php echo e($plan_link_base); ?>?plan=Elite" class="join-btn"><?php echo e($plan_button_label); ?></a>
    </article>
</div>
