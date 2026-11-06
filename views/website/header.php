<?php
/** Website layout header. Expects $pageTitle (optional). */
$pageTitle = $pageTitle ?? 'FitFlow Pro — Gym Management SaaS';
$activePage = $activePage ?? '';
function nav_active(string $slug, string $activePage): string
{
    return $slug === $activePage ? ' style="color:var(--ff-orange)!important"' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="FitFlow Pro — the multi-tenant SaaS platform for men's gyms: members, trainers, QR check-ins, classes, payments and subscriptions.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg ff-nav fixed-top">
  <div class="container">
    <a class="brand-mark" href="<?= e(url('public/index.php')) ?>"><span class="bm-icon"><i class="bi bi-lightning-charge-fill"></i></span>FitFlow<span class="text-orange">Pro</span></a>
    <button class="navbar-toggler border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon" style="filter:invert(1)"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="<?= e(url('public/index.php')) ?>"<?= nav_active('home', $activePage) ?>>Home</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('public/about.php')) ?>"<?= nav_active('about', $activePage) ?>>About</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('public/features.php')) ?>"<?= nav_active('features', $activePage) ?>>Features</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('public/plans.php')) ?>"<?= nav_active('plans', $activePage) ?>>Membership</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('public/trainers.php')) ?>"<?= nav_active('trainers', $activePage) ?>>Trainers</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('public/programs.php')) ?>"<?= nav_active('programs', $activePage) ?>>Programs</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('public/pricing.php')) ?>"<?= nav_active('pricing', $activePage) ?>>Pricing</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('public/blog.php')) ?>"<?= nav_active('blog', $activePage) ?>>Blog</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('public/contact.php')) ?>"<?= nav_active('contact', $activePage) ?>>Contact</a></li>
        <li class="nav-item ms-lg-2"><a class="btn btn-ff-outline btn-sm" href="<?= e(url('public/login.php')) ?>">Login</a></li>
        <li class="nav-item ms-lg-2 mt-2 mt-lg-0"><a class="btn btn-ff btn-sm" href="<?= e(url('public/register.php')) ?>">Start Free Trial</a></li>
      </ul>
    </div>
  </div>
</nav>
<div style="height:64px"></div>
