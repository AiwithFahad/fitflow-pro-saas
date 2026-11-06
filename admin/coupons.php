<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Coupons';
$activeNav = 'coupons';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'create';
    if ($action === 'toggle') {
        $c = Coupon::find((int) $_POST['id']);
        if ($c) Coupon::updateById((int) $c['id'], ['is_active' => $c['is_active'] ? 0 : 1]);
        flash('success', 'Coupon updated.');
    } elseif ($action === 'delete') {
        Coupon::deleteById((int) $_POST['id']);
        flash('success', 'Coupon deleted.');
    } else {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($code === '') {
            flash('error', 'Coupon code is required.');
        } else {
            Coupon::create([
                'code' => $code,
                'type' => $_POST['type'] === 'fixed' ? 'fixed' : 'percent',
                'value' => (float) ($_POST['value'] ?? 0),
                'max_uses' => ($_POST['max_uses'] ?? '') === '' ? null : (int) $_POST['max_uses'],
                'expires_at' => ($_POST['expires_at'] ?? '') === '' ? null : $_POST['expires_at'] . ' 23:59:59',
                'is_active' => 1,
            ]);
            flash('success', 'Coupon ' . $code . ' created.');
        }
    }
    redirect(url('admin/coupons.php'));
}

$coupons = Coupon::all(null, 'id DESC', 100);
require __DIR__ . '/../views/admin/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8"><div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Code</th><th>Discount</th><th>Used</th><th>Expires</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($coupons as $c): ?>
      <tr><td class="fw-bold"><?= e($c['code']) ?></td>
        <td><?= $c['type'] === 'percent' ? e(rtrim(rtrim(number_format((float) $c['value'], 2), '0'), '.')) . '%' : money($c['value']) ?></td>
        <td><?= (int) $c['used_count'] ?><?= $c['max_uses'] ? ' / ' . (int) $c['max_uses'] : '' ?></td>
        <td><?= e($c['expires_at'] ? substr((string) $c['expires_at'], 0, 10) : 'Never') ?></td>
        <td><?= $c['is_active'] ? '<span class="status-pill st-active">Active</span>' : '<span class="status-pill st-inactive">Off</span>' ?></td>
        <td class="text-end">
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button name="action" value="toggle" class="btn btn-sm btn-outline-warning">Toggle</button></form>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this coupon?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button></form>
        </td></tr>
    <?php endforeach; ?>
    <?php if (!$coupons): ?><tr><td colspan="6" style="color:var(--ff-muted)">No coupons yet.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <div class="col-lg-4"><div class="panel">
    <h5>Create coupon</h5>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="mb-2"><label class="form-label">Code</label><input class="form-control" name="code" required placeholder="WELCOME10"></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Type</label><select class="form-select" name="type"><option value="percent">Percent %</option><option value="fixed">Fixed $</option></select></div>
        <div class="col-6 mb-2"><label class="form-label">Value</label><input type="number" step="0.01" class="form-control" name="value" required></div>
        <div class="col-6 mb-2"><label class="form-label">Max uses</label><input type="number" class="form-control" name="max_uses" placeholder="Unlimited"></div>
        <div class="col-6 mb-2"><label class="form-label">Expires</label><input type="date" class="form-control" name="expires_at"></div>
      </div>
      <button class="btn btn-ff w-100 mt-2">Create coupon</button>
    </form>
  </div></div>
</div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
