<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Tenants (Gyms)';
$activeNav = 'tenants';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['tenant_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0 && in_array($action, ['suspend', 'activate', 'trial'], true)) {
        $status = $action === 'suspend' ? 'suspended' : ($action === 'activate' ? 'active' : 'trial');
        Tenant::setStatus($id, $status);
        log_activity(null, 'tenant.' . $status, 'tenants', $id);
        flash('success', 'Tenant status updated to ' . $status . '.');
    }
    redirect(url('admin/tenants.php'));
}

$tenants = Database::fetchAll(
    'SELECT t.*, (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS user_count,
            (SELECT COUNT(*) FROM members m WHERE m.tenant_id = t.id) AS member_count,
            (SELECT p.name FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id
              WHERE s.tenant_id = t.id ORDER BY s.id DESC LIMIT 1) AS plan_name,
            (SELECT s.status FROM subscriptions s WHERE s.tenant_id = t.id ORDER BY s.id DESC LIMIT 1) AS sub_status
     FROM tenants t ORDER BY t.id DESC'
);
require __DIR__ . '/../views/admin/header.php';
?>
<div class="panel">
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Gym</th><th>Contact</th><th>Users</th><th>Members</th><th>Plan</th><th>Tenant status</th><th>Subscription</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($tenants as $row): ?>
      <tr>
        <td><span class="fw-bold"><?= e($row['name']) ?></span><br><small style="color:var(--ff-muted)"><?= e($row['slug']) ?> · <?= e($row['city'] ?? '') ?></small></td>
        <td><?= e($row['email']) ?><br><small style="color:var(--ff-muted)"><?= e($row['phone'] ?? '') ?></small></td>
        <td><?= (int) $row['user_count'] ?></td>
        <td><?= (int) $row['member_count'] ?></td>
        <td><?= e($row['plan_name'] ?? '—') ?></td>
        <td><span class="status-pill st-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
        <td><?php if ($row['sub_status']): ?><span class="status-pill st-<?= e($row['sub_status']) ?>"><?= e($row['sub_status']) ?></span><?php else: ?>—<?php endif; ?></td>
        <td class="text-end">
          <form method="post" class="d-inline-flex gap-1">
            <?= csrf_field() ?><input type="hidden" name="tenant_id" value="<?= (int) $row['id'] ?>">
            <?php if ($row['status'] !== 'suspended'): ?><button name="action" value="suspend" class="btn btn-sm btn-outline-danger">Suspend</button><?php endif; ?>
            <?php if ($row['status'] !== 'active'): ?><button name="action" value="activate" class="btn btn-sm btn-outline-success">Reactivate</button><?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$tenants): ?><tr><td colspan="8" style="color:var(--ff-muted)">No tenants registered yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
