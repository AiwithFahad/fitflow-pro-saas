<?php
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $gymName = trim($_POST['gym_name'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');

    $errors = [];
    if ($gymName === '' || $ownerName === '') $errors[] = 'Gym name and your name are required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if (!$errors && User::findByEmail($email) !== null) $errors[] = 'That email is already registered. Please login instead.';

    if ($errors) {
        foreach ($errors as $e1) flash('error', $e1);
        redirect(url('public/register.php'));
    }
    try {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $gymName));
        $slug = trim($slug, '-') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $trial = SubscriptionPlan::trial();
        $tenantId = Tenant::register(
            ['name' => $gymName, 'slug' => $slug, 'email' => $email, 'phone' => $phone, 'city' => $city, 'country' => $country],
            ['name' => $ownerName, 'email' => $email, 'password' => $password],
            (int) $trial['id'],
            (int) (APP_CONFIG['trial_days'] ?? 14)
        );
        // Default branch + starter membership plans for the new gym.
        Database::insert('branches', ['tenant_id' => $tenantId, 'name' => 'Main Branch', 'city' => $city, 'status' => 'active']);
        foreach ([['Monthly', 29, 30], ['Quarterly', 79, 90], ['Annual', 290, 365]] as [$n, $pr, $d]) {
            Database::insert('membership_plans', ['tenant_id' => $tenantId, 'name' => $n, 'price' => $pr, 'duration_days' => $d, 'is_active' => 1]);
        }
        notify($tenantId, null, 'Welcome to FitFlow Pro', 'Your 14-day free trial is active. Add trainers, plans and your first members.', 'success');
        log_activity($tenantId, 'tenant.registered', 'tenants', $tenantId, $gymName);
        flash('success', 'Your gym is ready. Login with the email and password you just created.');
        redirect(url('public/login.php'));
    } catch (Throwable $e) {
        flash('error', 'Registration failed: ' . $e->getMessage());
        redirect(url('public/register.php'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register your gym — FitFlow Pro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrap py-5">
  <div class="container" style="max-width:640px">
    <div class="text-center mb-4"><a class="brand-mark" href="<?= e(url('public/index.php')) ?>"><span class="bm-icon"><i class="bi bi-lightning-charge-fill"></i></span>FitFlow<span class="text-orange">Pro</span></a></div>
    <div class="auth-card">
      <h3 class="fw-bold mb-1">Register your gym</h3>
      <p class="small" style="color:var(--ff-muted)">Start a 14-day free trial. Your gym gets its own isolated workspace, a main branch and starter membership plans out of the box.</p>
      <?= render_flash() ?>
      <form method="post" action="">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Gym / business name</label><input class="form-control" name="gym_name" required placeholder="e.g. Iron Paradise Gym"></div>
          <div class="col-md-6"><label class="form-label">Your full name</label><input class="form-control" name="owner_name" required placeholder="Owner name"></div>
          <div class="col-md-6"><label class="form-label">Work email</label><input type="email" class="form-control" name="email" required placeholder="you@yourgym.com"></div>
          <div class="col-md-6"><label class="form-label">Password</label><input type="password" class="form-control" name="password" minlength="8" required placeholder="Min. 8 characters"></div>
          <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" placeholder="+1 555 ..."></div>
          <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" placeholder="City"></div>
          <div class="col-md-4"><label class="form-label">Country</label><input class="form-control" name="country" placeholder="Country"></div>
        </div>
        <button type="submit" class="btn btn-ff w-100 mt-4">Create gym &amp; start trial</button>
      </form>
      <div class="small mt-3 text-center" style="color:var(--ff-muted)">Already have an account? <a href="<?= e(url('public/login.php')) ?>">Login</a></div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
