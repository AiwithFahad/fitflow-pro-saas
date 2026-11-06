<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Platform Users';
$activeNav = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $uid = (int) ($_POST['user_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'set_status' && $uid > 0 && $uid !== (int) current_user()['id']) {
        $status = in_array($_POST['status'] ?? '', ['active', 'suspended'], true) ? $_POST['status'] : 'active';
        Database::update('users', ['status' => $status], 'id = :wid', ['wid' => $uid]);
        log_activity(null, 'user.status_' . $status, 'users', $uid);
        flash('success', 'User status updated.');
    }
    redirect(url('super-admin/users.php'));
}

$q = trim((string) ($_GET['q'] ?? ''));
$role = trim((string) ($_GET['role'] ?? ''));
$where = 'WHERE 1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
}
if ($role !== '') {
    $where .= ' AND r.name = ?';
    $params[] = $role;
}
$users = Database::fetchAll(
    "SELECT u.*, r.name AS role_name, t.name AS tenant_name
     FROM users u JOIN roles r ON r.id = u.role_id
     LEFT JOIN tenants t ON t.id = u.tenant_id
     {$where} ORDER BY u.id DESC LIMIT 300",
    $params
);
$roles = Database::fetchAll('SELECT name FROM roles ORDER BY id');
require __DIR__ . '/../views/super-admin/header.php';
?>
<div class="panel">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h5 class="mb-0">All user accounts</h5>
    <form method="get" class="d-flex gap-2">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name or email…">
      <select class="form-select" name="role" style="max-width:190px"><option value="">All roles</option>
        <?php foreach ($roles as $r): ?><option value="<?= e($r['name']) ?>" <?= $role === $r['name'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-ff">Filter</button>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>User</th><th>Role</th><th>Gym</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr><td class="fw-bold"><?= e($u['name']) ?><br><small style="color:var(--ff-muted)"><?= e($u['email']) ?></small></td>
        <td><?= e($u['role_name']) ?></td>
        <td><?= e($u['tenant_name'] ?? '— Platform —') ?></td>
        <td><span class="status-pill st-<?= e($u['status']) ?>"><?= e($u['status']) ?></span></td>
        <td><?= e($u['last_login_at'] ? substr((string) $u['last_login_at'], 0, 16) : 'Never') ?></td>
        <td class="text-end">
          <?php if ((int) $u['id'] !== (int) current_user()['id']): ?>
          <form method="post" class="d-inline" data-confirm="<?= $u['status'] === 'active' ? 'Suspend this user account? They will no longer be able to log in.' : 'Reactivate this user account?' ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
            <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>">
            <button class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>"><?= $u['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button>
          </form>
          <?php else: ?><span class="small" style="color:var(--ff-muted)">You</span><?php endif; ?>
        </td></tr>
    <?php endforeach; ?>
    <?php if (!$users): ?><tr><td colspan="6" style="color:var(--ff-muted)">No users match.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/../views/super-admin/footer.php'; ?>
