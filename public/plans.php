<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle = 'Membership Plans — FitFlow Pro';
$activePage = 'plans';
$memberPlans = [];
try {
    $memberPlans = MembershipPlan::active(1);
} catch (Throwable $ignored) {
}
if (!$memberPlans) {
    $memberPlans = [
        ['name' => 'Monthly', 'price' => 29, 'duration_days' => 30, 'description' => 'Full gym access, billed monthly', 'features' => 'Full gym access, 2 group classes / week, Locker room'],
        ['name' => 'Quarterly', 'price' => 79, 'duration_days' => 90, 'description' => '3 months of full access', 'features' => 'Full gym access, Unlimited classes, 1 PT session / month'],
        ['name' => 'Annual', 'price' => 290, 'duration_days' => 365, 'description' => '12 months, two months free', 'features' => 'Full gym access, Unlimited classes, 2 PT sessions / month, Diet plan included'],
        ['name' => 'Student Monthly', 'price' => 19, 'duration_days' => 30, 'description' => 'Discounted student plan', 'features' => 'Full gym access (off-peak), 1 group class / week'],
    ];
}
require __DIR__ . '/../views/website/header.php';
?>
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Membership plans</span>
      <h1 class="display-title">Memberships at<br><span class="accent">Iron Paradise Gym</span></h1>
      <p style="color:var(--ff-muted)">Demo gym plans, loaded live from the FitFlow Pro database. New members can use <strong class="text-orange">WELCOME10</strong> for 10% off.</p></div>
    <div class="row g-4 justify-content-center">
      <?php foreach ($memberPlans as $i => $p): ?>
      <div class="col-md-6 col-lg-3">
        <div class="price-card h-100 <?= $i === 1 ? 'featured' : '' ?>">
          <?php if ($i === 1): ?><span class="badge badge-ff mb-2">Best Value</span><?php endif; ?>
          <h4 class="fw-bold"><?= e($p['name']) ?></h4>
          <p class="small" style="color:var(--ff-muted)"><?= e($p['description'] ?? '') ?></p>
          <div class="amount text-orange">$<?= number_format((float) $p['price']) ?><span class="fs-6" style="color:var(--ff-muted)"> / <?= (int) $p['duration_days'] ?> days</span></div>
          <ul class="list-unstyled mt-3 small">
            <?php foreach (array_map('trim', explode(',', (string) ($p['features'] ?? ''))) as $f): if ($f === '') continue; ?>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i><?= e($f) ?></li>
            <?php endforeach; ?>
          </ul>
          <a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff w-100">Join now</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="panel mt-5">
      <div class="row align-items-center">
        <div class="col-lg-8"><h4 class="fw-bold mb-1">Own a gym instead?</h4><p class="mb-0" style="color:var(--ff-muted)">These member plans are created by gyms inside FitFlow Pro. Run your own gym on the platform with a 14-day free trial.</p></div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0"><a href="<?= e(url('public/pricing.php')) ?>" class="btn btn-ff-outline">Gym owner pricing</a></div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
