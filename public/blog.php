<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../views/website/data.php';
$pageTitle = 'Blog — FitFlow Pro';
$activePage = 'blog';
require __DIR__ . '/../views/website/header.php';
?>
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Fitness blog</span>
      <h1 class="display-title">The iron<br><span class="accent">journal</span></h1></div>
    <div class="row g-4">
      <?php foreach (demo_posts() as $post): ?>
      <div class="col-md-6 col-lg-4">
        <div class="ff-card h-100">
          <img class="card-photo" src="<?= e($post['img']) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
          <div class="p-4">
            <span class="badge badge-ff"><?= e($post['category']) ?></span>
            <small class="ms-2" style="color:var(--ff-muted)"><?= e($post['date']) ?></small>
            <h5 class="fw-bold mt-2"><?= e($post['title']) ?></h5>
            <p style="color:var(--ff-muted)"><?= e($post['excerpt']) ?></p>
            <a href="<?= e(url('public/blog-details.php')) ?>?post=<?= e($post['slug']) ?>" class="fw-bold">Read article <i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
