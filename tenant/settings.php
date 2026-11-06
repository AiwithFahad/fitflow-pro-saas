<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Gym Settings';
$activeNav = 'settings';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        Tenant::updateById($tid, [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'city' => trim($_POST['city'] ?? '') ?: null,
            'country' => trim($_POST['country'] ?? '') ?: null,
            'logo_url' => trim($_POST['logo_url'] ?? '') ?: null,
        ]);
        // Logo upload (validated, random filename, stored outside public root listing).
        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $mime = mime_content_type($_FILES['logo']['tmp_name']);
            if (isset($allowed[$mime]) && $_FILES['logo']['size'] <= APP_CONFIG['upload_max']) {
                $file = 'logo-' . $tid . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
                $dest = __DIR__ . '/../storage/uploads/' . $file;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    Tenant::updateById($tid, ['logo_url' => 'storage/uploads/' . $file]);
                }
            } else {
                flash('error', 'Logo must be a PNG, JPG or WebP image under 5 MB.');
            }
        }
        flash('success', 'Gym profile saved.');
        log_activity($tid, 'settings.profile_updated', 'tenants', $tid);
    } elseif ($action === 'branding') {
        Database::query(
            'INSERT INTO tenant_settings (tenant_id, brand_color, currency, timezone, settings)
             VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE brand_color = VALUES(brand_color), currency = VALUES(currency), timezone = VALUES(timezone)',
            [$tid, $_POST['brand_color'] ?? '#f97316', $_POST['currency'] ?? 'USD', $_POST['timezone'] ?? 'UTC', json_encode(['checkin_method' => $_POST['checkin_method'] ?? 'qr'])]
        );
        flash('success', 'Branding and preferences saved.');
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $row = Database::fetch('SELECT password_hash FROM users WHERE id = ?', [(int) $user['id']]);
        if ($row === null || !password_verify($current, $row['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } else {
            Database::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = :wid', ['wid' => (int) $user['id']]);
            flash('success', 'Your password has been changed.');
        }
    } elseif ($action === 'twofa') {
        // Optional 2FA: store a TOTP secret placeholder generated server-side.
        $secret = strtoupper(bin2hex(random_bytes(10)));
        Database::update('users', ['two_factor_secret' => $secret], 'id = :wid', ['wid' => (int) $user['id']]);
        $_SESSION['new_2fa_secret'] = $secret;
        flash('success', 'Two-factor secret generated. Save it in your authenticator app.');
    }
    redirect(url('tenant/settings.php'));
}

$tenant = Tenant::find($tid);
$settings = Database::fetch('SELECT * FROM tenant_settings WHERE tenant_id = ?', [$tid]) ?: [];
$twofaSecret = $_SESSION['new_2fa_secret'] ?? null;
unset($_SESSION['new_2fa_secret']);
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="panel mb-3">
      <h5>Gym profile</h5>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="mb-2"><label class="form-label">Gym name</label><input class="form-control" name="name" required value="<?= e($tenant['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($tenant['email'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($tenant['phone'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">City</label><input class="form-control" name="city" value="<?= e($tenant['city'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Country</label><input class="form-control" name="country" value="<?= e($tenant['country'] ?? '') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Logo URL</label><input class="form-control" name="logo_url" value="<?= e($tenant['logo_url'] ?? '') ?>" placeholder="https://…"></div>
        <div class="mb-2"><label class="form-label">Or upload a logo (PNG/JPG/WebP, max 5 MB)</label><input type="file" class="form-control" name="logo" accept="image/png,image/jpeg,image/webp"></div>
        <button class="btn btn-ff">Save profile</button>
      </form>
    </div>
    <div class="panel">
      <h5>Branding &amp; preferences</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="branding">
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Brand colour</label><input type="color" class="form-control form-control-color w-100" name="brand_color" value="<?= e($settings['brand_color'] ?? '#f97316') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Currency</label><input class="form-control" name="currency" value="<?= e($settings['currency'] ?? 'USD') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="<?= e($settings['timezone'] ?? 'UTC') ?>" placeholder="e.g. America/Chicago"></div>
          <div class="col-6 mb-2"><label class="form-label">Check-in method</label><select class="form-select" name="checkin_method"><option value="qr">QR token</option><option value="manual">Manual</option><option value="both">Both</option></select></div>
        </div>
        <button class="btn btn-ff">Save preferences</button>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel mb-3">
      <h5>Change my password</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="mb-2"><label class="form-label">Current password</label><input type="password" class="form-control" name="current_password" required></div>
        <div class="mb-2"><label class="form-label">New password</label><input type="password" class="form-control" name="new_password" minlength="8" required></div>
        <button class="btn btn-ff">Update password</button>
      </form>
    </div>
    <div class="panel">
      <h5>Two-factor authentication (optional)</h5>
      <p class="small" style="color:var(--ff-muted)">Generate a TOTP secret for your account and store it in an authenticator app. Enforcement at login can be enabled by your platform administrator.</p>
      <?php if ($twofaSecret): ?><div class="alert alert-info small">Your new secret: <code><?= e($twofaSecret) ?></code><br>Save it now — it is not shown again.</div><?php endif; ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="twofa"><button class="btn btn-ff-outline">Generate 2FA secret</button></form>
      <hr style="border-color:var(--ff-border)">
      <h6 class="fw-bold">Account</h6>
      <p class="small mb-1">Signed in as <strong><?= e($user['name']) ?></strong> (<?= e($user['role_name']) ?>)</p>
      <p class="small mb-0" style="color:var(--ff-muted)">Tenant slug: <?= e($tenant['slug'] ?? '') ?> · Status: <?= e($tenant['status'] ?? '') ?></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
