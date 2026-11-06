<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../views/website/data.php';
$pageTitle = 'Workout Programs — FitFlow Pro';
$activePage = 'programs';
require __DIR__ . '/../views/website/header.php';
?>
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Workout programs</span>
      <h1 class="display-title">Choose your<br><span class="accent">discipline</span></h1></div>
    <?php foreach (demo_programs() as $i => $p): ?>
    <div class="row align-items-center g-5 mb-5">
      <div class="col-lg-6 <?= $i % 2 ? 'order-lg-2' : '' ?>"><img class="img-fluid rounded-4 w-100" style="height:340px;object-fit:cover" src="<?= e($p['img']) ?>" alt="<?= e($p['name']) ?>"></div>
      <div class="col-lg-6 <?= $i % 2 ? 'order-lg-1' : '' ?>">
        <h3 class="fw-bold"><i class="bi <?= $p['icon'] ?> text-orange me-2"></i><?= e($p['name']) ?></h3>
        <p style="color:var(--ff-muted)"><?= e($p['text']) ?></p>
        <ul class="list-unstyled">
          <?php foreach ($p['points'] as $pt): ?><li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i><?= e($pt) ?></li><?php endforeach; ?>
        </ul>
        <a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff mt-2">Start this program</a>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="panel">
      <div class="row align-items-center g-3">
        <div class="col-lg-8"><h4 class="fw-bold mb-1">Not sure where to start?</h4><p class="mb-0" style="color:var(--ff-muted)">Calculate your BMI, set a goal, and a trainer will assign the right program in your first week.</p></div>
        <div class="col-lg-4">
          <div class="row g-2">
            <div class="col-6"><input type="number" id="pgWeight" class="form-control" placeholder="Weight (kg)"></div>
            <div class="col-6"><input type="number" id="pgHeight" class="form-control" placeholder="Height (cm)"></div>
          </div>
          <button type="button" class="btn btn-ff w-100 mt-2" onclick="ffCalculateBMI('pgWeight','pgHeight','pgBmi')">Calculate BMI</button>
          <div id="pgBmi" class="mt-2"></div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
