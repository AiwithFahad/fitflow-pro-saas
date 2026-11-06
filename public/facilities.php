<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle = 'Facilities — FitFlow Pro';
$activePage = 'facilities';
require __DIR__ . '/../views/website/header.php';
$facilities = [
    ['Strength Zone', 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=900&q=80&auto=format&fit=crop', 'Eight power racks, competition benches, calibrated plates and platforms for heavy compound work.'],
    ['Cardio Deck', 'https://images.unsplash.com/photo-1538805060514-97d9cc17730c?w=900&q=80&auto=format&fit=crop', 'Treadmills, assault bikes, rowers and ski ergs — all tracked in the member app.'],
    ['Class Studio', 'https://images.unsplash.com/photo-1518611012118-696072aa579a?w=900&q=80&auto=format&fit=crop', 'A dedicated studio for HIIT, conditioning and mobility classes, bookable from the portal.'],
    ['Recovery Area', 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=900&q=80&auto=format&fit=crop', 'Sauna, cold plunge, foam-rolling and stretching corner to keep you training injury-free.'],
    ['Free Weights Arena', 'https://images.unsplash.com/photo-1599058917212-d750089bc07e?w=900&q=80&auto=format&fit=crop', 'Dumbbells from 5 to 50 kg, kettlebells, and open floor space for strongman work.'],
    ['PT Coaching Corner', 'https://images.unsplash.com/photo-1571731956672-f2b94d7dd0cb?w=900&q=80&auto=format&fit=crop', 'One-to-one coaching space with assessment kit: scales, calipers and movement screens.'],
];
?>
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Facilities</span>
      <h1 class="display-title">Tour the<br><span class="accent">battlefield</span></h1></div>
    <div id="facilityCarousel" class="carousel slide mb-5" data-bs-ride="carousel">
      <div class="carousel-inner rounded-4">
        <?php foreach ($facilities as $i => [$name, $img, $desc]): ?>
        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
          <img class="d-block w-100" style="height:420px;object-fit:cover" src="<?= e($img) ?>" alt="<?= e($name) ?>">
          <div class="carousel-caption d-none d-md-block" style="background:rgba(10,10,11,.75);border-radius:.8rem;padding:.8rem">
            <h5 class="fw-bold"><?= e($name) ?></h5><p class="mb-0 small"><?= e($desc) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="carousel-control-prev" type="button" data-bs-target="#facilityCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
      <button class="carousel-control-next" type="button" data-bs-target="#facilityCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
    </div>
    <div class="row g-4">
      <?php foreach ($facilities as [$name, $img, $desc]): ?>
      <div class="col-md-6 col-lg-4"><div class="ff-card h-100"><img class="card-photo" src="<?= e($img) ?>" alt="<?= e($name) ?>"><div class="p-4"><h5 class="fw-bold"><?= e($name) ?></h5><p class="mb-0" style="color:var(--ff-muted)"><?= e($desc) ?></p></div></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
