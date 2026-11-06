<?php
require_once __DIR__ . '/../app/bootstrap.php';

$resetLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = (string) ($_POST['email'] ?? '');
    $token = Auth::createResetToken($email);
    // In production the token is emailed. For the demo build it is shown once here.
    if ($token !== null) {
        $resetLink = url('public/reset-password.php') . '?token=' . $token;
    }
    flash('success', 'If that email matches an active account, a password reset link has been created.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot password — FitFlow Pro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="container" style="max-width:460px">
    <div class="auth-card">
      <h3 class="fw-bold mb-1">Forgot password</h3>
      <p class="small" style="color:var(--ff-muted)">Enter your account email and we'll create a secure, one-hour reset link.</p>
      <?= render_flash() ?>
      <?php if ($resetLink): ?>
        <div class="alert alert-info small">Demo mode — your reset link:<br><a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a></div>
      <?php endif; ?>
      <form method="post" action="">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
        <button type="submit" class="btn btn-ff w-100">Create reset link</button>
      </form>
      <div class="small mt-3 text-center"><a href="<?= e(url('public/login.php')) ?>">Back to login</a></div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
