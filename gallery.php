<?php
require_once __DIR__ . "/shared/bootstrap.php";

$active_page = "gallery";
$page_title = "Gallery - Forge Fitness Gym";
$body_class = "inner-page";
require __DIR__ . "/shared/header.php";
?>

<section class="full-gallery">
    <h1>OUR GALLERY</h1>
    <p>Explore everything Forge Fitness Gym has to offer.</p>

    <div class="full-gallery-grid">
        <?php foreach (gallery_items() as $slug => $item): ?>
            <a class="gallery-tile" href="<?php echo e(url("gallery_view.php?item=" . $slug)); ?>">
                <img src="<?php echo e(url($item["cover"])); ?>" alt="<?php echo e($item["alt"]); ?>">
                <h3><?php echo e($item["title"]); ?></h3>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . "/shared/footer.php"; ?>
