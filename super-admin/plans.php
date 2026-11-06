<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Subscription Plans';
$activeNav = 'plans';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'tagline' => trim($_POST['tagline'] ?? '') ?: null,
            'price_monthly' => (float) ($_POST['price_monthly'] ?? 0),
            'price_yearly' => (float) ($_POST['price_yearly'] ?? 0),
            'trial_days' => max(0, (int) ($_POST['trial_days'] ?? 14)),
            'max_members' => (int) ($_POST['max_members'] ?? -1),
            'max_trainers' => (int) ($_POST['max_trainers'] ?? -1),
            'max_branches' => (int) ($_POST['max_branches'] ?? -1),
            'max_classes' => (int) ($_POST['max_classes'] ?? -1),
            'max_staff' => (int) ($_POST['max_staff'] ?? -1),
            'max_storage_mb' => (int) ($_POST['max_storage_mb'] ?? 1024),
            'features' => trim($_POST['features'] ?? '') ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($data['name'] === '') {
            flash('error', 'Plan name is required.');
        } elseif (!empty($_POST['id'])) {
            SubscriptionPlan::updateById((int) $_POST['id'], $data);
            flash('success', 'Plan updated.');
        } else {
            SubscriptionPlan::create($data);
            flash('success', 'Plan created.');
        }
    } elseif ($action === 'delete' && !empty($_POST['id'])) {
        $inUse = (int) (Database::scalar('SELECT COUNT(*) FROM subscriptions WHERE plan_id = ?', [(int) $_POST['id']]) ?? 0);
        if ($inUse > 0) {
            flash('error', 'This plan has subscriptions and cannot be deleted — deactivate it instead.');
        } else {
            SubscriptionPlan::deleteById((int) $_POST['id']);
            flash('success', 'Plan deleted.');
        }
    }
    redirect(url('super-admin/plans.php'));
}

$plans = SubscriptionPlan::all(null, 'price_monthly ASC', 50);
$counts = [];
foreach (Database::fetchAll('SELECT plan_id, COUNT(*) AS c FROM subscriptions WHERE status IN ("active","trial","grace") GROUP BY plan_id') as $row) {
    $counts[(int) $row['plan_id']] = (int) $row['c'];
}
$edit = !empty($_GET['edit']) ? SubscriptionPlan::find((int) $_GET['edit']) : null;
require __DIR__ . '/../views/super-admin/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel"><h5>SaaS plans</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Plan</th><th>Monthly</th><th>Yearly</th><th>Trial</th><th>Limits (members/trainers)</th><th>Active subs</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($plans as $p): ?>
          <tr><td class="fw-bold"><?= e($p['name']) ?><br><small style="color:var(--ff-muted)"><?= e($p['tagline'] ?? '') ?></small></td>
            <td><?= money($p['price_monthly']) ?></td><td><?= money($p['price_yearly']) ?></td><td><?= (int) $p['trial_days'] ?> days</td>
            <td><?= (int) $p['max_members'] < 0 ? '∞' : (int) $p['max_members'] ?> / <?= (int) $p['max_trainers'] < 0 ? '∞' : (int) $p['max_trainers'] ?></td>
            <td><?= $counts[(int) $p['id']] ?? 0 ?></td>
            <td><span class="status-pill st-<?= $p['is_active'] ? 'active' : 'inactive' ?>"><?= $p['is_active'] ? 'active' : 'inactive' ?></span></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $p['id'] ?>">Edit</a>
              <form method="post" class="d-inline" data-confirm="Delete this plan? Plans with subscriptions cannot be deleted.">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button></form>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$plans): ?><tr><td colspan="8" style="color:var(--ff-muted)">No plans yet.</td></tr><?php endif; ?>
        </tbody></table></div>
      <p class="small mt-3 mb-0" style="color:var(--ff-muted)">Limits of −1 mean unlimited. Usage limits are enforced when a gym adds members, trainers, branches, classes or staff.</p>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel"><h5><?= $edit ? 'Edit plan' : 'New plan' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label">Tagline</label><input class="form-control" name="tagline" value="<?= e($edit['tagline'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Monthly ($)</label><input type="number" step="0.01" class="form-control" name="price_monthly" value="<?= e($edit['price_monthly'] ?? '0') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Yearly ($)</label><input type="number" step="0.01" class="form-control" name="price_yearly" value="<?= e($edit['price_yearly'] ?? '0') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Trial days</label><input type="number" class="form-control" name="trial_days" value="<?= e($edit['trial_days'] ?? '14') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Storage (MB)</label><input type="number" class="form-control" name="max_storage_mb" value="<?= e($edit['max_storage_mb'] ?? '1024') ?>"></div>
          <?php foreach (['max_members' => 'Max members', 'max_trainers' => 'Max trainers', 'max_branches' => 'Max branches', 'max_classes' => 'Max classes', 'max_staff' => 'Max staff'] as $f => $label): ?>
          <div class="col-6 mb-2"><label class="form-label"><?= $label ?> (−1 = ∞)</label><input type="number" class="form-control" name="<?= $f ?>" value="<?= e($edit[$f] ?? '-1') ?>"></div>
          <?php endforeach; ?>
        </div>
        <div class="mb-2"><label class="form-label">Features (one per line)</label><textarea class="form-control" name="features" rows="4"><?= e($edit['features'] ?? '') ?></textarea></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="planActive" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="planActive">Active (offered at signup)</label></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save changes' : 'Create plan' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="plans.php">Cancel edit</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/super-admin/footer.php'; ?>
