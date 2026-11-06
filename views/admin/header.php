<?php
/** Super Admin dashboard shell. Expects $dashTitle, $activeNav. */
$user = current_user();
$dashTitle = $dashTitle ?? 'Platform';
$activeNav = $activeNav ?? '';
$openTickets = 0;
try {
    $openTickets = SupportTicket::openCount();
} catch (Throwable $ignored) {
}
$nav = function (string $slug, string $file, string $icon, string $label, string $badge = '') use ($activeNav): string {
    $cls = $slug === $activeNav ? ' class="active"' : '';
    $b = $badge !== '' ? ' <span class="badge bg-danger ms-auto">' . e($badge) . '</span>' : '';
    return '<a href="' . e(url('admin/' . $file)) . '"' . $cls . '><i class="bi ' . $icon . '"></i>' . e($label) . $b . '</a>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($dashTitle) ?> · FitFlow Pro Platform</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="dash">
  <aside class="sidebar" id="appSidebar">
    <div class="brand">
      <a class="brand-mark" href="<?= e(url('admin/index.php')) ?>"><span class="bm-icon"><i class="bi bi-lightning-charge-fill"></i></span>FitFlow<span class="text-orange">Pro</span></a>
      <div class="small mt-1" style="color:var(--ff-muted)">Platform Super Admin</div>
    </div>
    <nav>
      <div class="nav-head">Platform</div>
      <?= $nav('overview', 'index.php', 'bi-speedometer2', 'Overview') ?>
      <?= $nav('analytics', 'analytics.php', 'bi-graph-up-arrow', 'Revenue Analytics') ?>
      <?= $nav('tenants', 'tenants.php', 'bi-buildings', 'Tenants (Gyms)') ?>
      <?= $nav('users', 'users.php', 'bi-people', 'Users') ?>
      <div class="nav-head">Billing</div>
      <?= $nav('plans', 'plans.php', 'bi-gem', 'Subscription Plans') ?>
      <?= $nav('coupons', 'coupons.php', 'bi-ticket-percent', 'Coupons') ?>
      <?= $nav('payments', 'payments.php', 'bi-credit-card', 'Payments') ?>
      <?= $nav('invoices', 'invoices.php', 'bi-receipt', 'Invoices') ?>
      <div class="nav-head">Operations</div>
      <?= $nav('tickets', 'tickets.php', 'bi-life-preserver', 'Support Tickets', $openTickets > 0 ? (string) $openTickets : '') ?>
      <?= $nav('announcements', 'announcements.php', 'bi-megaphone', 'Announcements') ?>
      <?= $nav('logs', 'logs.php', 'bi-list-columns', 'Activity Logs') ?>
      <?= $nav('settings', 'settings.php', 'bi-sliders', 'Platform Settings') ?>
    </nav>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <div class="main">
    <div class="topbar">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle" aria-label="Menu"><i class="bi bi-list"></i></button>
      <span class="fw-bold d-none d-md-inline"><i class="bi bi-shield-lock text-orange me-1"></i>Super Admin</span>
      <div class="ms-auto d-flex align-items-center gap-3">
        <div class="dropdown">
          <button class="btn btn-dark d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <span class="avatar-initials" style="width:34px;height:34px;font-size:.85rem"><?= e(strtoupper(substr($user['name'] ?? 'S', 0, 1))) ?></span>
            <span class="d-none d-md-inline text-start"><span class="d-block fw-bold small"><?= e($user['name'] ?? '') ?></span><span class="d-block small" style="color:var(--ff-muted)">Super Admin</span></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
            <li><a class="dropdown-item" href="<?= e(url('admin/settings.php')) ?>"><i class="bi bi-sliders me-2"></i>Platform Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= e(url('public/logout.php')) ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="content">
      <h3 class="mb-3" style="font-weight:900"><?= e($dashTitle) ?></h3>
      <?= render_flash() ?>
