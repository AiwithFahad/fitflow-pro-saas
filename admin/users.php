<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Platform Users';
$activeNav = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0 && $id !== (int) current_user()['id']) {
        if ($action === 'suspend') {
            User::updateById($id, ['status' => 'suspended'], null);
            flash('success', 'User suspended.');
        } elseif ($action === 'activate') {
            User::updateById($id, ['status' => 'active'], null);
            flash('success', 'User activated.');
        }
        log_activity(null, 'user.' . $action, 'users', $id);
    }
    redirect(url('admin/users.php'));
}

$users = Database::fetchAll(
    'SELECT u.*, r.name AS role_name, t.name AS tenant_name FROM users u
     JOIN roles r ON r.id = u.role_id LEFT JOIN tenants t ON t.id = u.tenant_id
     ORDER BY u.id DESC LIMIT 300'
);
require __DIR__ . '/../views/admin/header.php';
?>
<div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>User</th><th>Role</th><th>Gym</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($users as $row): ?>
    <tr>
      <td><span class="fw-bold"><?= e($row['name']) ?></span><br><small style="color:var(--ff-muted)"><?= e($row['email']) ?></small></td>
      <td><?= e($row['role_name']) ?></td>
      <td><?= e($row['tenant_name'] ?? 'Platform') ?></td>
      <td><span class="status-pill st-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
      <td><?= e($row['last_login_at'] ? substr((string) $row['last_login_at'], 0, 16) : 'Never') ?></td>
      <td class="text-end">
        <?php if ((int) $row['id'] !== (int) current_user()['id']): ?>
        <form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
          <?php if ($row['status'] === 'active'): ?><button name="action" value="suspend" class="btn btn-sm btn-outline-danger">Suspend</button>
          <?php elseif ($row['status'] === 'suspended'): ?><button name="action" value="activate" class="btn btn-sm btn-outline-success">Activate</button><?php endif; ?>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
