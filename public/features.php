<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle = 'Features — FitFlow Pro';
$activePage = 'features';
require __DIR__ . '/../views/website/header.php';
$groups = [
    'Member operations' => [
        ['bi-people', 'Member Management', 'Complete member profiles with branch, assigned trainer, BMI, status and join history.'],
        ['bi-card-list', 'Membership Plans & Enrolment', 'Create monthly, quarterly and annual plans; enrol in seconds with coupon support.'],
        ['bi-arrow-repeat', 'Renewals & Expiry Alerts', 'Dashboards flag memberships expiring within 7 days and list lapsed members for follow-up.'],
        ['bi-qr-code-scan', 'QR Check-in', 'Unique QR token per member. One scan checks in, the next checks out — attendance writes itself.'],
    ],
    'Coaching' => [
        ['bi-person-badge', 'Trainers & Schedules', 'Trainer profiles, specialties, weekly schedules and member assignments.'],
        ['bi-activity', 'Workout Programs', 'A reusable program library plus personalised assigned blocks per member.'],
        ['bi-bicycle', 'Exercise Library', 'Categorised exercises with muscle group, equipment and difficulty.'],
        ['bi-egg-fried', 'Diet Plans', 'Macro targets and meal plans assigned by trainers, visible in the member portal.'],
        ['bi-bullseye', 'Goals & Progress', 'Goal tracking with live progress bars and weigh-in progress records with BMI.'],
    ],
    'Business & billing' => [
        ['bi-credit-card', 'Payments & Invoices', 'Record membership and subscription payments; invoices and receipts generated automatically. Manual payments stay pending until verified.'],
        ['bi-gem', 'Subscription Plans', 'Free Trial, Starter, Professional, Business and Enterprise — monthly or yearly, with upgrades, downgrades, cancellation and grace periods.'],
        ['bi-ticket-percent', 'Coupons', 'Percent or fixed discounts with usage caps and expiry, e.g. WELCOME10.'],
        ['bi-buildings', 'Multi-branch', 'Branches with per-branch members, equipment and attendance on higher plans.'],
        ['bi-tools', 'Equipment Maintenance', 'Inventory with maintenance schedules, due alerts and repair history.'],
        ['bi-graph-up', 'Reports & Analytics', 'Revenue, attendance and membership analytics computed from live data.'],
    ],
    'Platform (Super Admin)' => [
        ['bi-shield-lock', 'Multi-tenant isolation', 'Every business table carries tenant_id and every query is tenant-scoped.'],
        ['bi-speedometer2', 'Platform dashboard', 'Tenants, MRR, ARR, subscriptions, payments and support tickets at a glance.'],
        ['bi-life-preserver', 'Support tickets', 'Tenants raise tickets; the platform team replies from the Super Admin panel.'],
        ['bi-list-columns', 'Audit & activity logs', 'Logins, enrolments, payments and changes are recorded for accountability.'],
    ],
];
?>
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Features</span>
      <h1 class="display-title">One platform.<br><span class="accent">Every department.</span></h1></div>
    <?php foreach ($groups as $group => $items): ?>
    <h3 class="fw-bold mt-5 mb-3"><i class="bi bi-lightning-charge-fill text-orange me-2"></i><?= e($group) ?></h3>
    <div class="row g-4">
      <?php foreach ($items as [$icon, $t, $d]): ?>
      <div class="col-md-6 col-lg-4"><div class="ff-card p-4 h-100"><div class="icon-wrap mb-3"><i class="bi <?= $icon ?>"></i></div><h5 class="fw-bold"><?= e($t) ?></h5><p class="mb-0" style="color:var(--ff-muted)"><?= e($d) ?></p></div></div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <div class="text-center mt-5"><a href="<?= e(url('public/pricing.php')) ?>" class="btn btn-ff btn-lg">See plans &amp; pricing</a></div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
