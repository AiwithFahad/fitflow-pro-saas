<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Platform Settings';
$activeNav = 'settings';

$settingsFile = __DIR__ . '/../storage/settings.json';
$settings = is_file($settingsFile) ? (json_decode((string) file_get_contents($settingsFile), true) ?: []) : [];
$settings += [
    'platform_name' => APP_CONFIG['name'],
    'support_email' => 'support@fitflowpro.example',
    'trial_days' => (int) (APP_CONFIG['trial_days'] ?? 14),
    'grace_days' => (int) (APP_CONFIG['grace_days'] ?? 7),
    'maintenance_mode' => 0,
    'allow_registration' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $settings['platform_name'] = trim($_POST['platform_name'] ?? $settings['platform_name']);
    $settings['support_email'] = trim($_POST['support_email'] ?? $settings['support_email']);
    $settings['trial_days'] = max(1, (int) ($_POST['trial_days'] ?? 14));
    $settings['grace_days'] = max(0, (int) ($_POST['grace_days'] ?? 7));
    $settings['maintenance_mode'] = isset($_POST['maintenance_mode']) ? 1 : 0;
    $settings['allow_registration'] = isset($_POST['allow_registration']) ? 1 : 0;
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    log_activity(null, 'platform.settings_updated', 'settings', null, 'Platform settings saved');
    flash('success', 'Platform settings saved to storage/settings.json.');
    redirect(url('admin/settings.php'));
}

require __DIR__ . '/../views/admin/header.php';
?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel">
      <h5>General settings</h5>
      <form method="post">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Platform name</label><input class="form-control" name="platform_name" value="<?= e($settings['platform_name']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Support email</label><input type="email" class="form-control" name="support_email" value="<?= e($settings['support_email']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Free trial length (days)</label><input type="number" class="form-control" name="trial_days" value="<?= (int) $settings['trial_days'] ?>"></div>
          <div class="col-md-6"><label class="form-label">Subscription grace period (days)</label><input type="number" class="form-control" name="grace_days" value="<?= (int) $settings['grace_days'] ?>"></div>
          <div class="col-md-6"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="allow_registration" id="allowReg" <?= $settings['allow_registration'] ? 'checked' : '' ?>><label class="form-check-label" for="allowReg">Allow new gym registrations</label></div></div>
          <div class="col-md-6"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintMode" <?= $settings['maintenance_mode'] ? 'checked' : '' ?>><label class="form-check-label" for="maintMode">Maintenance mode</label></div></div>
        </div>
        <button class="btn btn-ff mt-4">Save settings</button>
      </form>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <h5>Runtime configuration</h5>
      <table class="table table-sm mb-0">
        <tr><th>Environment</th><td><?= e(APP_CONFIG['env']) ?></td></tr>
        <tr><th>Debug mode</th><td><?= APP_CONFIG['debug'] ? 'On (disable in production)' : 'Off' ?></td></tr>
        <tr><th>Database</th><td><?= e((require __DIR__ . '/../config/database.php')['database']) ?></td></tr>
        <tr><th>PHP version</th><td><?= e(PHP_VERSION) ?></td></tr>
        <tr><th>Settings store</th><td>storage/settings.json</td></tr>
      </table>
      <p class="small mt-3 mb-0" style="color:var(--ff-muted)">Secrets are never shown here. Database and mail credentials live in your server environment / .env file only.</p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
