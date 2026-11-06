<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Announcements';
$activeNav = 'announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($title !== '' && $body !== '') {
            Database::insert('announcements', ['title' => $title, 'body' => $body, 'is_active' => 1, 'created_by' => (int) current_user()['id']]);
            // Broadcast a notification to every tenant.
            foreach (Tenant::all(null, 'id ASC', 500) as $t) {
                notify((int) $t['id'], null, $title, $body, 'info');
            }
            flash('success', 'Announcement published to all tenants.');
        } else {
            flash('error', 'Title and body are required.');
        }
    } elseif ($action === 'toggle') {
        $row = Database::fetch('SELECT * FROM announcements WHERE id = ?', [(int) $_POST['id']]);
        if ($row) Database::update('announcements', ['is_active' => $row['is_active'] ? 0 : 1], 'id = :wid', ['wid' => $row['id']]);
        flash('success', 'Announcement updated.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM announcements WHERE id = ?', [(int) $_POST['id']]);
        flash('success', 'Announcement deleted.');
    }
    redirect(url('admin/announcements.php'));
}

$rows = Database::fetchAll('SELECT * FROM announcements ORDER BY id DESC LIMIT 50');
require __DIR__ . '/../views/admin/header.php';
?>
<div class="row g-3">
  <div class="col-lg-7">
    <?php foreach ($rows as $a): ?>
    <div class="panel mb-3">
      <div class="d-flex justify-content-between"><h6 class="fw-bold mb-1"><?= e($a['title']) ?></h6>
        <span class="status-pill <?= $a['is_active'] ? 'st-active' : 'st-inactive' ?>"><?= $a['is_active'] ? 'Live' : 'Hidden' ?></span></div>
      <p class="mb-2" style="color:var(--ff-muted)"><?= nl2br(e($a['body'])) ?></p>
      <small style="color:var(--ff-muted)"><?= e(substr((string) $a['created_at'], 0, 16)) ?></small>
      <form method="post" class="d-inline-flex gap-1 ms-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
        <button name="action" value="toggle" class="btn btn-sm btn-outline-warning">Toggle</button>
        <button name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete announcement?')">Delete</button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="panel" style="color:var(--ff-muted)">No announcements yet.</div><?php endif; ?>
  </div>
  <div class="col-lg-5"><div class="panel">
    <h5>New announcement</h5>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required></div>
      <div class="mb-2"><label class="form-label">Message</label><textarea class="form-control" name="body" rows="4" required></textarea></div>
      <button class="btn btn-ff w-100">Publish to all gyms</button>
    </form>
  </div></div>
</div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
