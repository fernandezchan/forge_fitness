<?php
require_once "shared/bootstrap.php";

$logged_in = isset($_SESSION["user_id"]);
$user_name = ($logged_in && isset($_SESSION["user_name"])) ? $_SESSION["user_name"] : "";

$contact_success = "";
$contact_error = "";
$form_name = "";
$form_email = "";
$form_inquiry = "Membership Inquiry";
$form_message = "";

if ($logged_in) {
    if ($user_name !== "") {
        $form_name = $user_name;
    }
    if (!empty($_SESSION["user_email"])) {
        $form_email = $_SESSION["user_email"];
    }
}

if (get_string("sent", 8) === "1" && $logged_in && !is_admin()) {
    $contact_success = "Your message has been sent. Watch your Inbox for our reply.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["contact_form"])) {
    if (is_admin()) {
        redirect("admin/");
    } elseif (!$logged_in) {
        $_SESSION["redirect_after_login"] = "index.php#contact";
        $contact_error = "Please log in to send a message.";
    } elseif (!csrf_verify()) {
        $contact_error = "Your session expired. Please try again.";
    } else {
        $form_name = post_string("name", 100);
        $form_email = strtolower(post_string("email", 150));
        $form_inquiry = post_string("inquiry_type", 100);
        $form_message = post_string("message", 2000);
        $account_email = strtolower(trim((string) ($_SESSION["user_email"] ?? "")));

        if (!in_array($form_inquiry, inquiry_types(), true)) {
            $form_inquiry = "Membership Inquiry";
        }

        if ($form_name === "" || $form_email === "" || $form_message === "") {
            $contact_error = "Please fill in all required fields.";
        } elseif (strlen($form_name) < 2) {
            $contact_error = "Please enter your full name.";
        } elseif (!is_valid_email($form_email)) {
            $contact_error = "Please enter a valid email address.";
        } elseif ($account_email === "" || !hash_equals($account_email, $form_email)) {
            $contact_error = "Please use the email on your logged-in account.";
        } elseif (!create_contact_message($conn, $form_name, $form_email, $form_inquiry, $form_message)) {
            $contact_error = "Could not send your message. Please try again.";
        } else {
            redirect("index.php?sent=1#contact");
        }
    }
}

$pin_contact = (get_string("sent", 8) === "1" && $logged_in && !is_admin())
    || ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["contact_form"]));

$active_page = $pin_contact ? "contact" : "home";
$page_title = "Forge Fitness Gym";
require "shared/header.php";
?>

<section id="home" class="hero">
    <div class="hero-overlay"></div>

    <div class="hero-content">
        <div class="hero-text">
            <p class="hero-kicker">Forge Fitness Gym</p>
            <h1>
                TRAIN HARD.
                <span>STAY STRONG.</span>
            </h1>
            <h3>FITNESS • STRENGTH • DISCIPLINE</h3>
            <p>
                Welcome to Forge Fitness Gym, where strength and dedication
                build a better version of yourself.
            </p>
            <div class="hero-buttons">
                <a href="<?php echo e($logged_in ? (is_admin() ? url("admin/") : url("membership.php")) : url("auth/register.php")); ?>" class="btn btn-primary"><span>JOIN NOW</span></a>
                <a href="<?php echo e(url("gallery.php")); ?>" class="btn btn-secondary"><span>VIEW GALLERY</span></a>
            </div>
        </div>
    </div>

    <div class="services-box">
        <div class="services-head">
            <h3>OUR SERVICES</h3>
            <div></div>
        </div>
        <div class="services-grid">
            <div class="service">
                <div class="service-icon" aria-hidden="true">
                    <svg viewBox="0 0 64 32" fill="none" stroke="currentColor" stroke-width="2.2">
                        <rect x="2" y="10" width="8" height="12" rx="1"/>
                        <rect x="10" y="7" width="6" height="18" rx="1"/>
                        <line x1="16" y1="16" x2="48" y2="16"/>
                        <rect x="48" y="7" width="6" height="18" rx="1"/>
                        <rect x="54" y="10" width="8" height="12" rx="1"/>
                    </svg>
                </div>
                <div class="service-copy">
                    <p>STRENGTH TRAINING</p>
                    <span>Free weights, racks, and machines.</span>
                </div>
            </div>
            <div class="service">
                <div class="service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 21s-6.7-4.4-9.3-8.2C.7 10.2 1.1 6.8 3.6 5.2 5.6 3.9 8.2 4.4 12 8c3.8-3.6 6.4-4.1 8.4-2.8 2.5 1.6 2.9 5 1 7.6C18.7 16.6 12 21 12 21z"/>
                    </svg>
                </div>
                <div class="service-copy">
                    <p>CARDIO</p>
                    <span>Treadmills, bikes, and heart-rate work.</span>
                </div>
            </div>
            <div class="service">
                <div class="service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <circle cx="12" cy="7" r="3.2"/>
                        <path d="M5.5 20c.8-3.6 3.4-5.5 6.5-5.5S17.7 16.4 18.5 20"/>
                    </svg>
                </div>
                <div class="service-copy">
                    <p>PERSONAL TRAINING</p>
                    <span>Coaches who push your form and pace.</span>
                </div>
            </div>
            <div class="service">
                <div class="service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round">
                        <path d="M12 8.2c.8-1.6 2.4-2.6 3.6-2.7-.1 1.5-1 2.8-2.4 3.5"/>
                        <path d="M14.8 10.2c1.9.4 3.4 2.6 3.4 5 0 2.8-2 5.3-4.4 5.3-1 0-1.6-.4-1.8-.4s-.8.4-1.8.4C7.8 20.5 5.8 18 5.8 15.2c0-2.5 1.6-4.7 3.6-5 1 .5 2 .8 2.6.8s1.6-.3 2.8-.8z"/>
                    </svg>
                </div>
                <div class="service-copy">
                    <p>NUTRITION PLAN</p>
                    <span>Fuel that matches your training.</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="about" class="about-section">
    <div class="about-container">
        <div class="about-image">
            <img src="<?php echo e(url("assets/images/gym4.jpg")); ?>" alt="Forge Fitness Gym interior">
            <div class="about-logo">
                <img src="<?php echo e(url("assets/images/logo.png?v=2")); ?>" alt="Forge Fitness">
            </div>
        </div>

        <div class="about-content">
            <div class="about-heading">
                <h2>ABOUT US</h2>
                <div class="gold-line"></div>
            </div>
            <h1>
                WE'RE HERE TO HELP YOU BECOME THE
                <span>STRONGEST VERSION</span>
                OF YOURSELF.
            </h1>
            <p>
                Forge Fitness Gym was created to inspire people to become
                stronger, healthier, and more confident through proper
                training and discipline.
            </p>
        </div>
    </div>
</section>

<section class="gallery-preview">
    <div class="section-title">
        <h1>OUR GALLERY</h1>
        <div></div>
    </div>

    <div class="gallery-grid">
        <?php
        $home_gallery = ["chest-workout", "leg-day", "gym-interior", "dumbbell-area"];
        $all_gallery = gallery_items();
        foreach ($home_gallery as $slug):
            $item = $all_gallery[$slug];
        ?>
            <a class="gallery-card" href="<?php echo e(url("gallery_view.php?item=" . $slug)); ?>">
                <img src="<?php echo e(url($item["cover"])); ?>" alt="<?php echo e($item["alt"]); ?>">
                <h3><?php echo e($item["title"]); ?></h3>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="view-all">
        <a href="<?php echo e(url("gallery.php")); ?>">View All</a>
    </div>
</section>

<section id="membership" class="membership-section">
    <div class="membership-content">
        <div class="section-title">
            <h1>MEMBERSHIP</h1>
            <div></div>
        </div>
        <h3 class="membership-subtitle">Choose the plan that fits your fitness goals.</h3>

        <?php
        $plan_link_base = url("membership.php");
        $plan_button_label = "JOIN NOW";
        require "shared/plan_cards.php";
        ?>
    </div>
</section>

<?php if (!is_admin()): ?>
<section id="contact" class="contact-section">
    <div class="contact-container">
        <div class="contact-info">
            <h2>CONTACT US</h2>

            <div class="contact-item">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6.5 3.5h3l1.2 3.2-2 1.2a12 12 0 0 0 5.4 5.4l1.2-2 3.2 1.2v3A2 2 0 0 1 16.5 17 13.5 13.5 0 0 1 3.5 4a2 2 0 0 1 3-0.5z"/></svg>
                </span>
                <p><a href="tel:09123456789">0912 345 6789</a></p>
            </div>

            <div class="contact-item">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                </span>
                <p><a href="mailto:forgefitness@gmail.com">forgefitness@gmail.com</a></p>
            </div>

            <div class="contact-item">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
                </span>
                <p>forge_fitness</p>
            </div>

            <div class="contact-item">
                <span>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 9h3V6h-3c-1.7 0-3 1.3-3 3v2H8v3h3v7h3v-7h3l1-3h-4V9c0-.6.4-1 1-1z"/></svg>
                </span>
                <p>Forge Fitness</p>
            </div>

            <div class="contact-item">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.3"/></svg>
                </span>
                <p>Rizal Street, Poblacion II<br>Tanjay City, Negros Oriental</p>
            </div>
        </div>

        <div class="contact-form">
            <h2>SEND US A MESSAGE</h2>

            <?php if ($contact_success): ?>
                <div class="contact-success"><?php echo e($contact_success); ?></div>
            <?php endif; ?>

            <?php if ($contact_error): ?>
                <div class="contact-error"><?php echo e($contact_error); ?></div>
            <?php endif; ?>

            <?php if (!$logged_in): ?>
                <p class="contact-login-note">
                    Please
                    <a href="<?php echo e(url("auth/login.php")); ?>">log in</a>
                    to send a message.
                </p>
            <?php else: ?>
                <form method="POST" action="<?php echo e(url("index.php#contact")); ?>" class="js-validate">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="contact_form" value="1">

                    <div class="form-row">
                        <input type="text" name="name" placeholder="Name" value="<?php echo e($form_name); ?>" minlength="2" maxlength="100" required>
                        <input type="email" name="email" placeholder="Email" value="<?php echo e($form_email); ?>" maxlength="150" required>
                    </div>

                    <select name="inquiry_type">
                        <?php foreach (inquiry_types() as $type): ?>
                            <option value="<?php echo e($type); ?>" <?php echo $form_inquiry === $type ? "selected" : ""; ?>>
                                <?php echo e($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <textarea name="message" placeholder="Your Message" maxlength="2000" required><?php echo e($form_message); ?></textarea>

                    <button type="submit">SEND MESSAGE</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php if (!empty($pin_contact)): ?>
<script>
    (function () {
        var contact = document.getElementById("contact");
        if (contact) {
            contact.scrollIntoView({ behavior: "auto", block: "start" });
        }
    })();
</script>
<?php endif; ?>
<?php endif; ?>

<?php require "shared/footer.php"; ?>
