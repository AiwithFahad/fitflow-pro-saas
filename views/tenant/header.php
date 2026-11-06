<?php
/** Gym (tenant) dashboard shell. Expects $dashTitle, $activeNav. */
$user = current_user();
$tid = tenant_id();
$dashTitle = $dashTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$unread = 0;
$notifs = [];
try {
    $unread = (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND (tenant_id = ? OR user_id = ?)', [$tid, $user['id'] ?? 0]);
    $notifs = Database::fetchAll('SELECT * FROM notifications WHERE tenant_id = ? OR user_id = ? ORDER BY id DESC LIMIT 5', [$tid, $user['id'] ?? 0]);
} catch (Throwable $ignored) {
}
$nav = function (string $slug, string $file, string $icon, string $label) use ($activeNav): string {
    $cls = $slug === $activeNav ? ' class="active"' : '';
    return '<a href="' . e(url('tenant/' . $file)) . '"' . $cls . '><i class="bi ' . $icon . '"></i>' . e($label) . '</a>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($dashTitle) ?> · FitFlow Pro Gym</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="dash">
  <aside class="sidebar" id="appSidebar">
    <div class="brand">
      <a class="brand-mark" href="<?= e(url('tenant/index.php')) ?>"><span class="bm-icon"><i class="bi bi-lightning-charge-fill"></i></span>FitFlow<span class="text-orange">Pro</span></a>
      <div class="small mt-1" style="color:var(--ff-muted)"><?= e($user['tenant_name'] ?? 'My Gym') ?></div>
    </div>
    <nav>
      <div class="nav-head">Main</div>
      <?= $nav('overview', 'index.php', 'bi-speedometer2', 'Overview') ?>
      <?= $nav('members', 'members.php', 'bi-people', 'Members') ?>
      <?= $nav('plans', 'plans.php', 'bi-card-list', 'Membership Plans') ?>
      <?= $nav('enroll', 'enroll.php', 'bi-person-plus', 'Membership Registration') ?>
      <?= $nav('renewals', 'renewals.php', 'bi-arrow-repeat', 'Renewals & Expiry') ?>
      <div class="nav-head">Operations</div>
      <?= $nav('attendance', 'attendance.php', 'bi-calendar-check', 'Attendance') ?>
      <?= $nav('checkin', 'checkin.php', 'bi-qr-code-scan', 'QR Check-in') ?>
      <?= $nav('classes', 'classes.php', 'bi-collection', 'Fitness Classes') ?>
      <?= $nav('bookings', 'bookings.php', 'bi-bookmark-check', 'Class Bookings') ?>
      <?= $nav('equipment', 'equipment.php', 'bi-gear-wide-connected', 'Equipment') ?>
      <?= $nav('maintenance', 'maintenance.php', 'bi-tools', 'Maintenance') ?>
      <div class="nav-head">Coaching</div>
      <?= $nav('trainers', 'trainers.php', 'bi-person-badge', 'Trainers') ?>
      <?= $nav('schedules', 'schedules.php', 'bi-clock-history', 'Trainer Schedules') ?>
      <?= $nav('workouts', 'workouts.php', 'bi-activity', 'Workout Programs') ?>
      <?= $nav('exercises', 'exercises.php', 'bi-bicycle', 'Exercise Library') ?>
      <?= $nav('diet', 'diet.php', 'bi-egg-fried', 'Diet Plans') ?>
      <div class="nav-head">Business</div>
      <?= $nav('payments', 'payments.php', 'bi-credit-card', 'Payments & Invoices') ?>
      <?= $nav('staff', 'staff.php', 'bi-person-gear', 'Staff & Invitations') ?>
      <?= $nav('branches', 'branches.php', 'bi-buildings', 'Branches') ?>
      <?= $nav('reports', 'reports.php', 'bi-graph-up', 'Reports & Analytics') ?>
      <?= $nav('subscription', 'subscription.php', 'bi-gem', 'Subscription & Billing') ?>
      <?= $nav('support', 'support.php', 'bi-life-preserver', 'Support Tickets') ?>
      <?= $nav('settings', 'settings.php', 'bi-sliders', 'Settings') ?>
    </nav>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <div class="main">
    <div class="topbar">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle" aria-label="Menu"><i class="bi bi-list"></i></button>
      <div class="search-box d-none d-md-flex"><i class="bi bi-search"></i><input id="dashSearch" data-target="<?= e(url('tenant/members.php')) ?>" placeholder="Search members… (Enter)"></div>
      <div class="ms-auto d-flex align-items-center gap-3">
        <div class="dropdown">
          <button class="btn btn-dark position-relative" data-bs-toggle="dropdown" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <?php if ($unread > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $unread ?></span><?php endif; ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" style="min-width:300px">
            <li class="dropdown-header">Notifications</li>
            <?php if (!$notifs): ?><li><span class="dropdown-item-text small">No notifications yet.</span></li><?php endif; ?>
            <?php foreach ($notifs as $n): ?>
              <li><span class="dropdown-item-text small"><strong><?= e($n['title']) ?></strong><br><span style="color:var(--ff-muted)"><?= e($n['body'] ?? '') ?></span></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="dropdown">
          <button class="btn btn-dark d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <span class="avatar-initials" style="width:34px;height:34px;font-size:.85rem"><?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?></span>
            <span class="d-none d-md-inline text-start"><span class="d-block fw-bold small"><?= e($user['name'] ?? '') ?></span><span class="d-block small" style="color:var(--ff-muted)"><?= e($user['role_name'] ?? '') ?></span></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
            <li><a class="dropdown-item" href="<?= e(url('tenant/settings.php')) ?>"><i class="bi bi-sliders me-2"></i>Settings</a></li>
            <li><a class="dropdown-item" href="<?= e(url('tenant/subscription.php')) ?>"><i class="bi bi-gem me-2"></i>My Subscription</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= e(url('public/logout.php')) ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="content">
      <h3 class="fw-black mb-3" style="font-weight:900"><?= e($dashTitle) ?></h3>
      <?= render_flash() ?>
