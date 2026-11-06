<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Equipment';
$activeNav = 'equipment';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        Equipment::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Equipment removed.');
    } else {
        $data = [
            'tenant_id' => $tid,
            'branch_id' => (int) ($_POST['branch_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? 'Strength'),
            'brand' => trim($_POST['brand'] ?? '') ?: null,
            'serial_number' => trim($_POST['serial_number'] ?? '') ?: null,
            'quantity' => max(1, (int) ($_POST['quantity'] ?? 1)),
            'status' => $_POST['status'] ?? 'working',
            'purchased_at' => ($_POST['purchased_at'] ?? '') ?: null,
            'last_maintenance_at' => ($_POST['last_maintenance_at'] ?? '') ?: null,
            'next_maintenance_at' => ($_POST['next_maintenance_at'] ?? '') ?: null,
        ];
        if ($data['name'] === '') {
            flash('error', 'Equipment name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            Equipment::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Equipment updated.');
        } else {
            Equipment::create($data);
            flash('success', 'Equipment added.');
        }
    }
    redirect(url('tenant/equipment.php'));
}

$items = Equipment::withBranch($tid);
$due = Equipment::maintenanceDue($tid);
$branches = Branch::all($tid, 'name ASC', 50);
$edit = !empty($_GET['edit']) ? Equipment::find((int) $_GET['edit'], $tid) : null;
require __DIR__ . '/../views/tenant/header.php';
?>
<?php if ($due): ?>
<div class="alert alert-warning"><i class="bi bi-tools me-1"></i><strong><?= count($due) ?> item(s)</strong> need maintenance within 14 days (or are overdue): <?= e(implode(', ', array_column($due, 'name'))) ?>. <a href="<?= e(url('tenant/maintenance.php')) ?>" class="alert-link">Open maintenance</a></div>
<?php endif; ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Equipment</th><th>Category</th><th>Branch</th><th>Qty</th><th>Status</th><th>Next maintenance</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($items as $eq): ?>
        <tr><td><span class="fw-bold"><?= e($eq['name']) ?></span><br><small style="color:var(--ff-muted)"><?= e($eq['brand'] ?? '') ?> <?= $eq['serial_number'] ? '· ' . e($eq['serial_number']) : '' ?></small></td>
          <td><?= e($eq['category']) ?></td><td><?= e($eq['branch_name'] ?? '—') ?></td><td><?= (int) $eq['quantity'] ?></td>
          <td><span class="status-pill st-<?= $eq['status'] === 'working' ? 'active' : ($eq['status'] === 'maintenance' ? 'pending' : ($eq['status'] === 'broken' ? 'broken' : 'inactive')) ?>"><?= e($eq['status']) ?></span></td>
          <td><?= e($eq['next_maintenance_at'] ?? '—') ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $eq['id'] ?>">Edit</a>
            <form method="post" class="d-inline" onsubmit="return confirm('Remove this equipment?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $eq['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button></form>
          </td></tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="7" style="color:var(--ff-muted)">No equipment recorded yet.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit equipment' : 'Add equipment' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Category</label><input class="form-control" name="category" list="eqCats" value="<?= e($edit['category'] ?? 'Strength') ?>"><datalist id="eqCats"><option>Strength</option><option>Cardio</option><option>Free Weights</option><option>Functional</option></datalist></div>
          <div class="col-6 mb-2"><label class="form-label">Brand</label><input class="form-control" name="brand" value="<?= e($edit['brand'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Serial #</label><input class="form-control" name="serial_number" value="<?= e($edit['serial_number'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Quantity</label><input type="number" class="form-control" name="quantity" value="<?= e($edit['quantity'] ?? '1') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) ($edit['branch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['working', 'maintenance', 'broken', 'retired'] as $s): ?><option <?= ($edit['status'] ?? 'working') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Purchased</label><input type="date" class="form-control" name="purchased_at" value="<?= e($edit['purchased_at'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Next maintenance</label><input type="date" class="form-control" name="next_maintenance_at" value="<?= e($edit['next_maintenance_at'] ?? '') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Last maintenance</label><input type="date" class="form-control" name="last_maintenance_at" value="<?= e($edit['last_maintenance_at'] ?? '') ?>"></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save changes' : 'Add equipment' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="<?= e(url('tenant/equipment.php')) ?>">Cancel edit</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
