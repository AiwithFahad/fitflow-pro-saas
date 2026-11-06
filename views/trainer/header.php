<?php
/** Trainer portal shell. Expects $dashTitle, $activeNav. */
$user = current_user();
$dashTitle = $dashTitle ?? 'Trainer';
$activeNav = $activeNav ?? '';
$nav = function (string $slug, string $file, string $icon, string $label) use ($activeNav): string {
    $cls = $slug === $activeNav ? ' class="active"' : '';
    return '<a href="' . e(url('trainer/' . $file)) . '"' . $cls . '><i class="bi ' . $icon . '"></i>' . e($label) . '</a>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($dashTitle) ?> · FitFlow Pro Trainer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="dash">
  <aside class="sidebar" id="appSidebar">
    <div class="brand">
      <a class="brand-mark" href="<?= e(url('trainer/dashboard.php')) ?>"><span class="bm-icon"><i class="bi bi-lightning-charge-fill"></i></span>FitFlow<span class="text-orange">Pro</span></a>
      <div class="small mt-1" style="color:var(--ff-muted)"><?= e($user['name'] ?? 'Trainer') ?> · Coach</div>
    </div>
    <nav>
      <div class="nav-head">Coaching</div>
      <?= $nav('dashboard', 'dashboard.php', 'bi-speedometer2', 'Dashboard') ?>
      <?= $nav('members', 'members.php', 'bi-people', 'My Members') ?>
      <?= $nav('schedule', 'schedule.php', 'bi-clock-history', 'My Schedule') ?>
      <?= $nav('workouts', 'workouts.php', 'bi-activity', 'Workout Plans') ?>
      <?= $nav('diet-plans', 'diet-plans.php', 'bi-egg-fried', 'Diet Plans') ?>
      <div class="nav-head">Tracking</div>
      <?= $nav('attendance', 'attendance.php', 'bi-calendar-check', 'Attendance') ?>
      <?= $nav('progress', 'progress.php', 'bi-graph-up', 'Member Progress') ?>
    </nav>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <div class="main">
    <div class="topbar">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle" aria-label="Menu"><i class="bi bi-list"></i></button>
      <div class="search-box d-none d-md-flex"><i class="bi bi-search"></i><input id="dashSearch" data-target="<?= e(url('trainer/members.php')) ?>" placeholder="Search my members… (Enter)"></div>
      <div class="ms-auto">
        <a class="btn btn-dark" href="<?= e(url('public/logout.php')) ?>"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
      </div>
    </div>
    <div class="content">
      <h3 class="mb-3" style="font-weight:900"><?= e($dashTitle) ?></h3>
      <?= render_flash() ?>
