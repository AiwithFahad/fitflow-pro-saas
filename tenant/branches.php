<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Branches';
$activeNav = 'branches';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        Branch::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Branch deleted.');
    } else {
        $data = [
            'tenant_id' => $tid,
            'name' => trim($_POST['name'] ?? ''),
            'address' => trim($_POST['address'] ?? '') ?: null,
            'city' => trim($_POST['city'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'status' => $_POST['status'] ?? 'active',
        ];
        if ($data['name'] === '') {
            flash('error', 'Branch name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            Branch::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Branch updated.');
        } else {
            if (!plan_allows($tid, 'branches')) {
                flash('error', plan_limit_message('branches'));
                redirect(url('tenant/branches.php'));
            }
            Branch::create($data);
            flash('success', 'Branch created.');
        }
    }
    redirect(url('tenant/branches.php'));
}

$branches = Branch::withCounts($tid);
$edit = !empty($_GET['edit']) ? Branch::find((int) $_GET['edit'], $tid) : null;
$sub = active_subscription($tid);
require __DIR__ . '/../views/tenant/header.php';
?>
<?php if ($sub): ?><div class="alert alert-dark" style="border:1px solid var(--ff-border)">Your <strong><?= e($sub['plan_name']) ?></strong> plan allows <?= (int) $sub['max_branches'] < 0 ? 'unlimited' : (int) $sub['max_branches'] ?> branch(es). You have <?= count($branches) ?>.</div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="row g-3">
      <?php foreach ($branches as $b): ?>
      <div class="col-md-6"><div class="ff-card p-4 h-100">
        <div class="d-flex justify-content-between"><h5 class="fw-bold mb-1"><i class="bi bi-building text-orange me-1"></i><?= e($b['name']) ?></h5><span class="status-pill st-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></div>
        <p class="small mb-2" style="color:var(--ff-muted)"><?= e($b['address'] ?? '') ?><?= $b['city'] ? ', ' . e($b['city']) : '' ?><br><?= e($b['phone'] ?? '') ?></p>
        <div class="d-flex gap-4 small"><span><strong><?= (int) $b['member_count'] ?></strong> members</span><span><strong><?= (int) $b['equipment_count'] ?></strong> equipment</span></div>
        <div class="d-flex gap-2 mt-3">
          <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $b['id'] ?>">Edit</a>
          <form method="post" onsubmit="return confirm('Delete this branch? Members keep their accounts; branch links are cleared.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button></form>
        </div>
      </div></div>
      <?php endforeach; ?>
      <?php if (!$branches): ?><div class="col-12"><div class="panel" style="color:var(--ff-muted)">No branches yet.</div></div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit branch' : 'Add branch' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= e($edit['address'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">City</label><input class="form-control" name="city" value="<?= e($edit['city'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save changes' : 'Add branch' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="<?= e(url('tenant/branches.php')) ?>">Cancel edit</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
