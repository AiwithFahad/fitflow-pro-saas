<?php
require_once __DIR__ . '/../app/bootstrap.php';

$token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $pw = (string) ($_POST['password'] ?? '');
    if (strlen($pw) < 8) {
        flash('error', 'Password must be at least 8 characters.');
    } elseif (Auth::resetPassword($token, $pw)) {
        flash('success', 'Password updated. Please login with your new password.');
        redirect(url('public/login.php'));
    } else {
        flash('error', 'That reset link is invalid or has expired.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset password — FitFlow Pro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="container" style="max-width:460px">
    <div class="auth-card">
      <h3 class="fw-bold mb-1">Set a new password</h3>
      <?= render_flash() ?>
      <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mb-3"><label class="form-label">New password</label><input type="password" class="form-control" name="password" minlength="8" required></div>
        <button type="submit" class="btn btn-ff w-100">Update password</button>
      </form>
      <div class="small mt-3 text-center"><a href="<?= e(url('public/login.php')) ?>">Back to login</a></div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
