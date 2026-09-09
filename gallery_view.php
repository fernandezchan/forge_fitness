<?php
require_once __DIR__ . "/shared/bootstrap.php";

$slug = get_string("item", 40);
$item = get_gallery_item($slug);

if ($item === null) {
    redirect("gallery.php");
}

$active_page = "gallery";
$page_title = $item["title"] . " - Forge Fitness Gym";
$body_class = "inner-page";
require __DIR__ . "/shared/header.php";
?>

<section class="gallery-detail">
    <a class="gallery-back" href="<?php echo e(url("gallery.php")); ?>">&larr; BACK TO GALLERY</a>

    <h1><?php echo e($item["title"]); ?></h1>
    <p><?php echo e($item["description"]); ?></p>

    <div class="gallery-detail-hero">
        <img src="<?php echo e(url($item["cover"])); ?>" alt="<?php echo e($item["alt"]); ?>">
    </div>

    <h2>MORE PHOTOS</h2>
    <div class="gallery-detail-grid">
        <?php foreach ($item["photos"] as $photo): ?>
            <img src="<?php echo e(url($photo)); ?>" alt="<?php echo e($item["alt"]); ?>">
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . "/shared/footer.php"; ?>
