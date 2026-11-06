<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Platform Settings';
$activeNav = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'announcement_create') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($title !== '') {
            Database::insert('announcements', [
                'title' => $title, 'body' => $body ?: null, 'is_active' => 1,
                'created_by' => (int) current_user()['id'],
            ]);
            log_activity(null, 'announcement.created', 'announcements', null, $title);
            flash('success', 'Announcement published to all dashboards.');
        } else {
            flash('error', 'Announcement title is required.');
        }
    } elseif ($action === 'announcement_toggle' && !empty($_POST['id'])) {
        Database::query('UPDATE announcements SET is_active = 1 - is_active WHERE id = ?', [(int) $_POST['id']]);
        flash('success', 'Announcement visibility updated.');
    } elseif ($action === 'announcement_delete' && !empty($_POST['id'])) {
        Database::query('DELETE FROM announcements WHERE id = ?', [(int) $_POST['id']]);
        flash('success', 'Announcement deleted.');
    }
    redirect(url('super-admin/settings.php'));
}

$app = APP_CONFIG;
$dbCfg = require __DIR__ . '/../config/database.php';
$announcements = Database::fetchAll('SELECT * FROM announcements ORDER BY id DESC LIMIT 20');
$counts = [
    'tenants' => (int) (Database::scalar('SELECT COUNT(*) FROM tenants') ?? 0),
    'users' => (int) (Database::scalar('SELECT COUNT(*) FROM users') ?? 0),
    'audit entries' => (int) (Database::scalar('SELECT COUNT(*) FROM audit_logs') ?? 0),
];
require __DIR__ . '/../views/super-admin/header.php';
?>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="panel mb-3">
      <h5>Platform configuration</h5>
      <p class="small" style="color:var(--ff-muted)">These values come from <code>config/app.php</code> and <code>config/database.php</code> (and the <code>.env</code> file when present). Edit those files on the server to change them — this page intentionally has no fake settings form.</p>
      <table class="table table-sm mb-0">
        <tr><th style="width:45%">Application</th><td><?= e($app['name'] ?? 'FitFlow Pro') ?></td></tr>
        <tr><th>Environment</th><td><?= e($app['env'] ?? 'production') ?></td></tr>
        <tr><th>Base URL</th><td><?= e($app['url'] ?? '') ?></td></tr>
        <tr><th>Timezone</th><td><?= e($app['timezone'] ?? 'UTC') ?></td></tr>
        <tr><th>Database</th><td><?= e($dbCfg['driver'] ?? 'mysql') ?> · <?= e($dbCfg['database'] ?? '') ?> @ <?= e($dbCfg['host'] ?? '') ?>:<?= e((string) ($dbCfg['port'] ?? '')) ?></td></tr>
        <tr><th>PHP version</th><td><?= e(PHP_VERSION) ?></td></tr>
        <?php foreach ($counts as $label => $n): ?><tr><th><?= e(ucfirst($label)) ?></th><td><?= $n ?></td></tr><?php endforeach; ?>
      </table>
    </div>
    <div class="panel">
      <h5>Platform tools</h5>
      <div class="d-grid gap-2">
        <a class="btn btn-ff-outline text-start" href="<?= e(url('admin/coupons.php')) ?>"><i class="bi bi-ticket-percent me-2 text-orange"></i>Coupons & discounts</a>
        <a class="btn btn-ff-outline text-start" href="<?= e(url('admin/invoices.php')) ?>"><i class="bi bi-receipt me-2 text-orange"></i>Invoices</a>
        <a class="btn btn-ff-outline text-start" href="<?= e(url('admin/tickets.php')) ?>"><i class="bi bi-life-preserver me-2 text-orange"></i>Support tickets</a>
        <a class="btn btn-ff-outline text-start" href="<?= e(url('admin/logs.php')) ?>"><i class="bi bi-list-columns me-2 text-orange"></i>Activity & audit logs</a>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel mb-3">
      <h5>New announcement</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="announcement_create">
        <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required placeholder="e.g. Scheduled maintenance Sunday 02:00 UTC"></div>
        <div class="mb-2"><label class="form-label">Body</label><textarea class="form-control" name="body" rows="3" placeholder="Details shown to every gym dashboard"></textarea></div>
        <button class="btn btn-ff">Publish announcement</button>
      </form>
    </div>
    <div class="panel">
      <h5>Announcements</h5>
      <?php foreach ($announcements as $a): ?>
        <div class="d-flex justify-content-between align-items-start py-2 border-bottom" style="border-color:var(--ff-border)">
          <span><strong><?= e($a['title']) ?></strong> <span class="status-pill st-<?= $a['is_active'] ? 'active' : 'inactive' ?>"><?= $a['is_active'] ? 'live' : 'hidden' ?></span><br>
          <small style="color:var(--ff-muted)"><?= e($a['body'] ?? '') ?></small></span>
          <span class="d-inline-flex gap-1 ms-2">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="announcement_toggle"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-outline-light"><?= $a['is_active'] ? 'Hide' : 'Show' ?></button></form>
            <form method="post" data-confirm="Delete this announcement?"><?= csrf_field() ?><input type="hidden" name="action" value="announcement_delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
          </span>
        </div>
      <?php endforeach; ?>
      <?php if (!$announcements): ?><p class="small mb-0" style="color:var(--ff-muted)">No announcements yet.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/super-admin/footer.php'; ?>
