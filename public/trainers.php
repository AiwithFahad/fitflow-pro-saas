<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../views/website/data.php';
$pageTitle = 'Trainers — FitFlow Pro';
$activePage = 'trainers';
// Prefer live trainers from the demo tenant; fall back to marketing data.
$trainers = [];
try {
    $rows = Trainer::all(1, 'name ASC', 20);
    foreach ($rows as $r) {
        if (($r['status'] ?? 'active') !== 'active') continue;
        $trainers[] = [
            'name' => $r['name'], 'specialty' => $r['specialty'] ?? 'General Fitness',
            'exp' => (int) ($r['experience_years'] ?? 0),
            'photo' => $r['photo_url'] ?: 'https://randomuser.me/api/portraits/men/' . (30 + (int) $r['id']) . '.jpg',
            'bio' => $r['bio'] ?? '',
        ];
    }
} catch (Throwable $ignored) {
}
if (!$trainers) {
    $trainers = demo_trainers();
}
require __DIR__ . '/../views/website/header.php';
?>
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Our trainers</span>
      <h1 class="display-title">Coaches who<br><span class="accent">actually coach</span></h1>
      <p style="color:var(--ff-muted)">Every trainer manages their members, schedules and programs inside FitFlow Pro — so your progress is never lost in a notebook.</p></div>
    <div class="row g-4">
      <?php foreach ($trainers as $t): ?>
      <div class="col-md-6 col-lg-3">
        <div class="ff-card trainer-card h-100">
          <img src="<?= e($t['photo']) ?>" alt="<?= e($t['name']) ?>">
          <div class="p-4">
            <h5 class="fw-bold mb-1"><?= e($t['name']) ?></h5>
            <div class="text-orange fw-bold small"><?= e($t['specialty']) ?></div>
            <div class="small mt-1" style="color:var(--ff-muted)"><i class="bi bi-award me-1"></i><?= (int) $t['exp'] ?> years experience</div>
            <p class="mb-0 mt-2 small" style="color:var(--ff-muted)"><?= e($t['bio']) ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5"><a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff btn-lg">Train with us</a></div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
