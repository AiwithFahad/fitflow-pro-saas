<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Subscription Plans';
$activeNav = 'plans';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'toggle') {
        $id = (int) $_POST['id'];
        $plan = SubscriptionPlan::find($id);
        if ($plan) {
            SubscriptionPlan::updateById($id, ['is_active' => $plan['is_active'] ? 0 : 1]);
            flash('success', 'Plan visibility updated.');
        }
    } else {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => strtolower(preg_replace('/[^a-z0-9]+/', '-', trim($_POST['name'] ?? ''))),
            'description' => trim($_POST['description'] ?? ''),
            'price_monthly' => (float) ($_POST['price_monthly'] ?? 0),
            'price_yearly' => (float) ($_POST['price_yearly'] ?? 0),
            'max_members' => (int) ($_POST['max_members'] ?? 0),
            'max_trainers' => (int) ($_POST['max_trainers'] ?? 0),
            'max_branches' => (int) ($_POST['max_branches'] ?? 0),
            'max_classes' => (int) ($_POST['max_classes'] ?? 0),
            'max_staff' => (int) ($_POST['max_staff'] ?? 0),
            'max_storage_mb' => (int) ($_POST['max_storage_mb'] ?? 0),
        ];
        if ($data['name'] === '') {
            flash('error', 'Plan name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['slug']);
            SubscriptionPlan::updateById((int) $_POST['id'], $data);
            flash('success', 'Plan updated.');
        } else {
            SubscriptionPlan::create($data);
            flash('success', 'Plan created.');
        }
    }
    redirect(url('admin/plans.php'));
}

$plans = SubscriptionPlan::all(null, 'price_monthly ASC', 50);
$edit = !empty($_GET['edit']) ? SubscriptionPlan::find((int) $_GET['edit']) : null;
require __DIR__ . '/../views/admin/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Plan</th><th>Monthly</th><th>Yearly</th><th>Members</th><th>Branches</th><th>Active</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($plans as $p): ?>
        <tr><td><span class="fw-bold"><?= e($p['name']) ?></span><br><small style="color:var(--ff-muted)"><?= e($p['description'] ?? '') ?></small></td>
          <td><?= money($p['price_monthly']) ?></td><td><?= money($p['price_yearly']) ?></td>
          <td><?= (int) $p['max_members'] < 0 ? '∞' : (int) $p['max_members'] ?></td>
          <td><?= (int) $p['max_branches'] < 0 ? '∞' : (int) $p['max_branches'] ?></td>
          <td><?= $p['is_active'] ? '<span class="status-pill st-active">Active</span>' : '<span class="status-pill st-inactive">Hidden</span>' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $p['id'] ?>">Edit</a>
            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-outline-warning">Toggle</button></form>
          </td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
      <p class="small mt-3 mb-0" style="color:var(--ff-muted)">Limit of <strong>-1</strong> means unlimited. Limits are enforced live when tenants add members, trainers, branches, classes and staff.</p>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit plan' : 'Create plan' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Monthly $</label><input type="number" step="0.01" class="form-control" name="price_monthly" value="<?= e($edit['price_monthly'] ?? '0') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Yearly $</label><input type="number" step="0.01" class="form-control" name="price_yearly" value="<?= e($edit['price_yearly'] ?? '0') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Max members</label><input type="number" class="form-control" name="max_members" value="<?= e($edit['max_members'] ?? '100') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Max trainers</label><input type="number" class="form-control" name="max_trainers" value="<?= e($edit['max_trainers'] ?? '5') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Max branches</label><input type="number" class="form-control" name="max_branches" value="<?= e($edit['max_branches'] ?? '1') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Max classes</label><input type="number" class="form-control" name="max_classes" value="<?= e($edit['max_classes'] ?? '10') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Max staff</label><input type="number" class="form-control" name="max_staff" value="<?= e($edit['max_staff'] ?? '5') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Storage MB</label><input type="number" class="form-control" name="max_storage_mb" value="<?= e($edit['max_storage_mb'] ?? '1000') ?>"></div>
        </div>
        <button class="btn btn-ff w-100 mt-2"><?= $edit ? 'Save changes' : 'Create plan' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="<?= e(url('admin/plans.php')) ?>">Cancel edit</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
