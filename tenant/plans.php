<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Membership Plans (sold to members)';
$activeNav = 'plans';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        MembershipPlan::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Plan deleted.');
    } elseif ($action === 'toggle') {
        $p = MembershipPlan::find((int) $_POST['id'], $tid);
        if ($p) MembershipPlan::updateById((int) $p['id'], ['is_active' => $p['is_active'] ? 0 : 1], $tid);
        flash('success', 'Plan visibility updated.');
    } else {
        $data = [
            'tenant_id' => $tid,
            'name' => trim($_POST['name'] ?? ''),
            'price' => (float) ($_POST['price'] ?? 0),
            'duration_days' => max(1, (int) ($_POST['duration_days'] ?? 30)),
            'description' => trim($_POST['description'] ?? ''),
            'features' => trim($_POST['features'] ?? ''),
            'is_active' => 1,
        ];
        if ($data['name'] === '') {
            flash('error', 'Plan name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            MembershipPlan::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Plan updated.');
        } else {
            MembershipPlan::create($data);
            flash('success', 'Membership plan created.');
        }
    }
    redirect(url('tenant/plans.php'));
}

$plans = MembershipPlan::all($tid, 'price ASC', 100);
$edit = !empty($_GET['edit']) ? MembershipPlan::find((int) $_GET['edit'], $tid) : null;
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="row g-3">
      <?php foreach ($plans as $p): ?>
      <div class="col-md-6">
        <div class="price-card h-100">
          <div class="d-flex justify-content-between"><h5 class="fw-bold"><?= e($p['name']) ?></h5>
            <span class="status-pill <?= $p['is_active'] ? 'st-active' : 'st-inactive' ?>"><?= $p['is_active'] ? 'On sale' : 'Hidden' ?></span></div>
          <div class="amount text-orange"><?= money($p['price']) ?><span class="fs-6" style="color:var(--ff-muted)"> / <?= (int) $p['duration_days'] ?> days</span></div>
          <p class="small" style="color:var(--ff-muted)"><?= e($p['description'] ?? '') ?></p>
          <ul class="list-unstyled small">
            <?php foreach (array_map('trim', explode(',', (string) ($p['features'] ?? ''))) as $f): if ($f === '') continue; ?><li class="mb-1"><i class="bi bi-check-circle-fill text-orange me-2"></i><?= e($f) ?></li><?php endforeach; ?>
          </ul>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $p['id'] ?>">Edit</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button name="action" value="toggle" class="btn btn-sm btn-outline-warning">Toggle</button></form>
            <form method="post" onsubmit="return confirm('Delete this plan?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button></form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$plans): ?><div class="col-12"><div class="panel" style="color:var(--ff-muted)">No membership plans yet. Create your first plan.</div></div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit plan' : 'Create membership plan' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Quarterly"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Price ($)</label><input type="number" step="0.01" class="form-control" name="price" value="<?= e($edit['price'] ?? '') ?>" required></div>
          <div class="col-6 mb-2"><label class="form-label">Duration (days)</label><input type="number" class="form-control" name="duration_days" value="<?= e($edit['duration_days'] ?? '30') ?>" required></div>
        </div>
        <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label">Features (comma separated)</label><textarea class="form-control" name="features" rows="3"><?= e($edit['features'] ?? '') ?></textarea></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save changes' : 'Create plan' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="<?= e(url('tenant/plans.php')) ?>">Cancel edit</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
