<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Gym Settings';
$activeNav = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!can('settings.manage')) {
        flash('error', 'Your role cannot change gym settings.');
        redirect(url('admin/settings.php'));
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'branding') {
        Database::query(
            'INSERT INTO tenant_settings (tenant_id, brand_color, currency, timezone, settings)
             VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE brand_color = VALUES(brand_color), currency = VALUES(currency), timezone = VALUES(timezone), settings = VALUES(settings)',
            [$tid, $_POST['brand_color'] ?? '#f97316', $_POST['currency'] ?? 'USD', $_POST['timezone'] ?? 'UTC',
             json_encode(['checkin_method' => $_POST['checkin_method'] ?? 'qr', 'gym_tagline' => trim($_POST['gym_tagline'] ?? '')])]
        );
        flash('success', 'Branding and preferences saved.');
    } elseif ($action === 'profile') {
        Database::update('tenants', [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'address' => trim($_POST['address'] ?? '') ?: null,
            'city' => trim($_POST['city'] ?? '') ?: null,
            'country' => trim($_POST['country'] ?? '') ?: null,
        ], 'id = :wid', ['wid' => $tid]);
        flash('success', 'Gym profile saved.');
    } elseif ($action === 'notify') {
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            notify($tid, null, $title, trim($_POST['body'] ?? ''), 'info');
            flash('success', 'Notification sent to this gym\'s dashboards and member portals.');
        } else {
            flash('error', 'A notification title is required.');
        }
    }
    redirect(url('admin/settings.php'));
}

$tenant = Tenant::find($tid);
$settings = Database::fetch('SELECT * FROM tenant_settings WHERE tenant_id = ?', [$tid]) ?: [];
$prefs = json_decode((string) ($settings['settings'] ?? '{}'), true) ?: [];
$sub = active_subscription($tid);
$branches = Branch::withCounts($tid);
require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="panel mb-3">
      <h5>Gym profile</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="mb-2"><label class="form-label">Gym name</label><input class="form-control" name="name" required value="<?= e($tenant['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($tenant['email'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($tenant['phone'] ?? '') ?>"></div>
          <div class="col-12 mb-2"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= e($tenant['address'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">City</label><input class="form-control" name="city" value="<?= e($tenant['city'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Country</label><input class="form-control" name="country" value="<?= e($tenant['country'] ?? '') ?>"></div>
        </div>
        <button class="btn btn-ff">Save profile</button>
      </form>
    </div>
    <div class="panel">
      <h5>Branches</h5>
      <?php foreach ($branches as $b): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)">
          <span><strong><?= e($b['name']) ?></strong><br><small style="color:var(--ff-muted)"><?= e($b['city'] ?? '') ?> · <?= e($b['phone'] ?? '') ?></small></span>
          <span class="small" style="color:var(--ff-muted)"><?= (int) ($b['member_count'] ?? 0) ?> members</span>
        </div>
      <?php endforeach; ?>
      <a class="btn btn-ff-outline w-100 mt-3" href="<?= e(url('tenant/branches.php')) ?>">Manage branches (classic workspace)</a>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel mb-3">
      <h5>Branding & preferences</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="branding">
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Brand colour</label><input type="color" class="form-control form-control-color w-100" name="brand_color" value="<?= e($settings['brand_color'] ?? '#f97316') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Currency</label><input class="form-control" name="currency" value="<?= e($settings['currency'] ?? 'USD') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="<?= e($settings['timezone'] ?? 'UTC') ?>" placeholder="e.g. America/New_York"></div>
          <div class="col-6 mb-2"><label class="form-label">Default check-in method</label><select class="form-select" name="checkin_method">
            <option value="qr" <?= ($prefs['checkin_method'] ?? 'qr') === 'qr' ? 'selected' : '' ?>>QR scan</option>
            <option value="manual" <?= ($prefs['checkin_method'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual</option>
            <option value="both" <?= ($prefs['checkin_method'] ?? '') === 'both' ? 'selected' : '' ?>>Both</option>
          </select></div>
          <div class="col-12 mb-2"><label class="form-label">Gym tagline</label><input class="form-control" name="gym_tagline" value="<?= e($prefs['gym_tagline'] ?? '') ?>" placeholder="Stronger every day"></div>
        </div>
        <button class="btn btn-ff">Save branding</button>
      </form>
    </div>
    <div class="panel mb-3">
      <h5>Notify this gym</h5>
      <form data-ajax data-endpoint="notifications.php" data-reset>
        <input type="hidden" name="action" value="send">
        <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required placeholder="e.g. Gym closed Sunday for maintenance"></div>
        <div class="mb-2"><label class="form-label">Message</label><textarea class="form-control" name="body" rows="2"></textarea></div>
        <button class="btn btn-ff">Send notification</button>
        <p class="small mt-2 mb-0" style="color:var(--ff-muted)">Appears in the notification bell for staff and in member portals.</p>
      </form>
    </div>
    <div class="panel">
      <h5>SaaS subscription</h5>
      <?php if ($sub): ?>
      <table class="table table-sm mb-3">
        <tr><th>Plan</th><td><?= e($sub['plan_name']) ?></td></tr>
        <tr><th>Status</th><td><span class="status-pill st-<?= e($sub['status']) ?>"><?= e($sub['status']) ?></span></td></tr>
        <tr><th>Billing cycle</th><td><?= e($sub['billing_cycle']) ?></td></tr>
        <tr><th>Period ends</th><td><?= e($sub['ends_at'] ? substr((string) $sub['ends_at'], 0, 10) : '—') ?></td></tr>
        <tr><th>Limits</th><td>Members <?= (int) $sub['max_members'] < 0 ? '∞' : (int) $sub['max_members'] ?> · Trainers <?= (int) $sub['max_trainers'] < 0 ? '∞' : (int) $sub['max_trainers'] ?> · Branches <?= (int) $sub['max_branches'] < 0 ? '∞' : (int) $sub['max_branches'] ?></td></tr>
      </table>
      <?php else: ?><p class="small" style="color:var(--ff-muted)">No active subscription found.</p><?php endif; ?>
      <a class="btn btn-ff-outline w-100" href="<?= e(url('tenant/subscription.php')) ?>">Manage subscription & billing</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
