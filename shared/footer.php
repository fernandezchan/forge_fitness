<footer>
<?php $logged_in = $logged_in ?? isset($_SESSION["user_id"]); ?>
    <div class="footer-container">
        <div class="footer-brand">
            <img src="<?php echo e(url("assets/images/logo.png?v=2")); ?>" alt="Forge Fitness Gym">
            <p>We're here to help you start your fitness journey!</p>
        </div>

        <div class="quick-links">
            <h4>QUICK LINKS</h4>
            <div class="footer-links">
                <div>
                    <a href="<?php echo e(url("index.php")); ?>">Home</a>
                    <a href="<?php echo e(url("index.php#about")); ?>">About Us</a>
                    <a href="<?php echo e(url("gallery.php")); ?>">Gallery</a>
                </div>
                <div>
                    <?php if ($logged_in && !is_admin()): ?>
                        <a href="<?php echo e(url("membership.php")); ?>">Membership</a>
                    <?php elseif (!$logged_in): ?>
                        <a href="<?php echo e(url("index.php#membership")); ?>">Membership</a>
                    <?php endif; ?>
                    <?php if (!is_admin()): ?>
                        <a href="<?php echo e(url("index.php#contact")); ?>">Contact Us</a>
                    <?php endif; ?>
                    <?php if ($logged_in): ?>
                        <?php if (is_admin()): ?>
                            <a href="<?php echo e(url("admin/")); ?>">Admin</a>
                        <?php else: ?>
                            <a href="<?php echo e(url("my_messages.php")); ?>">Inbox</a>
                        <?php endif; ?>
                        <a href="<?php echo e(url("auth/logout.php")); ?>">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo e(url("auth/login.php")); ?>">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="socials">
            <h4>FOLLOW US</h4>
            <div class="social-icons">
                <a href="https://www.facebook.com" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 9h3V6h-3c-1.7 0-3 1.3-3 3v2H8v3h3v7h3v-7h3l1-3h-4V9c0-.6.4-1 1-1z"/></svg>
                </a>
                <a href="https://www.instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
                </a>
                <a href="https://www.youtube.com" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23 12.2s0-3.2-.4-4.6c-.2-.8-.9-1.5-1.7-1.7C19.2 5.5 12 5.5 12 5.5s-7.2 0-8.9.4c-.8.2-1.5.9-1.7 1.7C1 9 1 12.2 1 12.2s0 3.2.4 4.6c.2.8.9 1.5 1.7 1.7 1.7.4 8.9.4 8.9.4s7.2 0 8.9-.4c.8-.2 1.5-.9 1.7-1.7.4-1.4.4-4.6.4-4.6zM9.8 15.5V8.9l6.2 3.3-6.2 3.3z"/></svg>
                </a>
            </div>
        </div>
    </div>
    <div class="footer-copy">
        <p>&copy; <?php echo date("Y"); ?> Forge Fitness Gym. All rights reserved.</p>
    </div>
</footer>

<script src="<?php echo e(url("assets/js/script.js?v=17")); ?>"></script>
</body>
</html>
