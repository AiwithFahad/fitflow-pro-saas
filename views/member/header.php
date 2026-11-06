<?php
/** Member portal shell. Expects $dashTitle, $activeNav, and $member (portal member row). */
$user = current_user();
$dashTitle = $dashTitle ?? 'My Portal';
$activeNav = $activeNav ?? '';
$nav = function (string $slug, string $file, string $icon, string $label) use ($activeNav): string {
    $cls = $slug === $activeNav ? ' class="active"' : '';
    return '<a href="' . e(url('member/' . $file)) . '"' . $cls . '><i class="bi ' . $icon . '"></i>' . e($label) . '</a>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($dashTitle) ?> · FitFlow Pro Member</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="dash">
  <aside class="sidebar" id="appSidebar">
    <div class="brand">
      <a class="brand-mark" href="<?= e(url('member/index.php')) ?>"><span class="bm-icon"><i class="bi bi-lightning-charge-fill"></i></span>FitFlow<span class="text-orange">Pro</span></a>
      <div class="small mt-1" style="color:var(--ff-muted)"><?= e($member['name'] ?? ($user['name'] ?? 'Member')) ?> · <?= e($user['tenant_name'] ?? '') ?></div>
    </div>
    <nav>
      <div class="nav-head">My Training</div>
      <?= $nav('home', 'index.php', 'bi-house', 'My Overview') ?>
      <?= $nav('membership', 'membership.php', 'bi-card-checklist', 'My Membership') ?>
      <?= $nav('workouts', 'workouts.php', 'bi-activity', 'My Workouts') ?>
      <?= $nav('diet', 'diet.php', 'bi-egg-fried', 'My Diet Plan') ?>
      <?= $nav('goals', 'goals.php', 'bi-bullseye', 'BMI & Goals') ?>
      <div class="nav-head">Records</div>
      <?= $nav('attendance', 'attendance.php', 'bi-calendar-check', 'My Attendance') ?>
      <?= $nav('payments', 'payments.php', 'bi-receipt', 'My Payments') ?>
    </nav>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <div class="main">
    <div class="topbar">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle" aria-label="Menu"><i class="bi bi-list"></i></button>
      <span class="fw-bold d-none d-md-inline">Member Portal</span>
      <div class="ms-auto">
        <a class="btn btn-dark" href="<?= e(url('public/logout.php')) ?>"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
      </div>
    </div>
    <div class="content">
      <h3 class="mb-3" style="font-weight:900"><?= e($dashTitle) ?></h3>
      <?= render_flash() ?>
