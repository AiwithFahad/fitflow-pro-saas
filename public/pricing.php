<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle = 'Pricing — FitFlow Pro';
$activePage = 'pricing';
$plans = [];
try {
    $plans = SubscriptionPlan::active();
} catch (Throwable $ignored) {
}
require __DIR__ . '/../views/website/header.php';
?>
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-4"><span class="section-tag">SaaS pricing</span>
      <h1 class="display-title">Pricing for<br><span class="accent">gym owners</span></h1>
      <p style="color:var(--ff-muted)">Start with a 14-day free trial. Upgrade, downgrade or cancel anytime. Use a coupon at checkout — try <strong class="text-orange">WELCOME10</strong>.</p>
      <div class="form-check form-switch d-inline-flex align-items-center gap-2 mt-2">
        <label class="form-check-label fw-bold" for="billingToggle">Monthly</label>
        <input class="form-check-input" type="checkbox" id="billingToggle" style="width:3rem;height:1.5rem">
        <label class="form-check-label fw-bold" for="billingToggle">Yearly <span class="text-orange">(2 months free)</span></label>
      </div>
    </div>
    <div class="row g-4 justify-content-center">
      <?php foreach ($plans as $i => $p): ?>
      <div class="col-md-6 col-lg-4 col-xl-3">
        <div class="price-card h-100 <?= ($p['slug'] ?? '') === 'professional' ? 'featured' : '' ?>">
          <?php if (($p['slug'] ?? '') === 'professional'): ?><span class="badge badge-ff mb-2">Most Popular</span><?php endif; ?>
          <h4 class="fw-bold"><?= e($p['name']) ?></h4>
          <p class="small" style="color:var(--ff-muted)"><?= e($p['description'] ?? '') ?></p>
          <div class="amount text-orange">$<span data-price-monthly="<?= number_format((float) $p['price_monthly']) ?>" data-price-yearly="<?= number_format((float) $p['price_yearly']) ?>"><?= number_format((float) $p['price_monthly']) ?></span><span class="fs-6 cycle-label" style="color:var(--ff-muted)">/month</span></div>
          <ul class="list-unstyled mt-3 small">
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i>Members: <?= (int) $p['max_members'] < 0 ? 'Unlimited' : number_format((int) $p['max_members']) ?></li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i>Trainers: <?= (int) $p['max_trainers'] < 0 ? 'Unlimited' : (int) $p['max_trainers'] ?></li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i>Branches: <?= (int) $p['max_branches'] < 0 ? 'Unlimited' : (int) $p['max_branches'] ?></li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i>Classes: <?= (int) $p['max_classes'] < 0 ? 'Unlimited' : (int) $p['max_classes'] ?></li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i>Staff seats: <?= (int) $p['max_staff'] < 0 ? 'Unlimited' : (int) $p['max_staff'] ?></li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i>Storage: <?= number_format((int) $p['max_storage_mb']) ?> MB</li>
          </ul>
          <a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff w-100"><?= (float) $p['price_monthly'] == 0.0 ? 'Start free trial' : 'Choose ' . e($p['name']) ?></a>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$plans): ?><div class="col-12 text-center" style="color:var(--ff-muted)">Plans load from the database once installed. See the README for setup.</div><?php endif; ?>
    </div>
    <div class="panel mt-5">
      <h4 class="fw-bold">Every plan includes</h4>
      <div class="row small mt-3" style="color:var(--ff-muted)">
        <div class="col-md-4"><ul class="list-unstyled"><li class="mb-2"><i class="bi bi-check-lg text-orange me-2"></i>Multi-tenant isolation &amp; RBAC security</li><li class="mb-2"><i class="bi bi-check-lg text-orange me-2"></i>QR check-ins &amp; attendance</li><li class="mb-2"><i class="bi bi-check-lg text-orange me-2"></i>Member portal</li></ul></div>
        <div class="col-md-4"><ul class="list-unstyled"><li class="mb-2"><i class="bi bi-check-lg text-orange me-2"></i>Invoices, receipts &amp; coupons</li><li class="mb-2"><i class="bi bi-check-lg text-orange me-2"></i>Expiry alerts &amp; renewal reminders</li><li class="mb-2"><i class="bi bi-check-lg text-orange me-2"></i>Reports from live data</li></ul></div>
        <div class="col-md-4"><ul class="list-unstyled"><li class="mb-2"><i class="bi bi-check-lg text-orange me-2"></i>Support tickets</li><li class="mb-2"><i class="bi bi-check-lg text-orange me-2"></i>Audit &amp; activity logs</li><li class="mb-2"><i class="bi bi-check-lg text-orange me-2"></i>CSV exports &amp; backups guidance</li></ul></div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
