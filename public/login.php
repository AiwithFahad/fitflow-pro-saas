<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (current_user() !== null) {
    redirect(Auth::homeFor(current_user()));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $user = Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($user !== null) {
        redirect(Auth::homeFor($user));
    }
    redirect(url('public/login.php'));
}
$pageTitle = 'Login — FitFlow Pro';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="container" style="max-width:460px">
    <div class="text-center mb-4"><a class="brand-mark" href="<?= e(url('public/index.php')) ?>"><span class="bm-icon"><i class="bi bi-lightning-charge-fill"></i></span>FitFlow<span class="text-orange">Pro</span></a></div>
    <div class="auth-card">
      <h3 class="fw-bold mb-1">Welcome back</h3>
      <p class="small" style="color:var(--ff-muted)">Login to your gym workspace, platform admin or member portal.</p>
      <?= render_flash() ?>
      <form method="post" action="">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required autofocus placeholder="you@yourgym.com"></div>
        <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required placeholder="••••••••"></div>
        <button type="submit" class="btn btn-ff w-100">Login</button>
      </form>
      <div class="d-flex justify-content-between mt-3 small">
        <a href="<?= e(url('public/forgot-password.php')) ?>">Forgot password?</a>
        <a href="<?= e(url('public/register.php')) ?>">Register your gym</a>
      </div>
      <hr style="border-color:var(--ff-border)">
      <div class="small" style="color:var(--ff-muted)">
        <strong class="text-white">Demo accounts</strong> (password: <code>password</code>)<br>
        Super Admin: super@fitflow.test<br>
        Gym Admin: admin@demo.com · Trainer: trainer@demo.com<br>
        Member: member@demo.com
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
