<?php
require_once __DIR__ . '/../app/bootstrap.php';

$token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $pw = (string) ($_POST['password'] ?? '');
    if (strlen($pw) < 8) {
        flash('error', 'Password must be at least 8 characters.');
    } elseif (User::acceptInvitation($token, $pw)) {
        flash('success', 'Your account is active. Please login.');
        redirect(url('public/login.php'));
    } else {
        flash('error', 'That invitation is invalid, expired or already accepted.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Accept invitation — FitFlow Pro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="container" style="max-width:460px">
    <div class="auth-card">
      <h3 class="fw-bold mb-1">Accept your invitation</h3>
      <p class="small" style="color:var(--ff-muted)">Set a password to activate your FitFlow Pro staff account.</p>
      <?= render_flash() ?>
      <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mb-3"><label class="form-label">Create password</label><input type="password" class="form-control" name="password" minlength="8" required></div>
        <button type="submit" class="btn btn-ff w-100">Activate account</button>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
