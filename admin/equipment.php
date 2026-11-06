<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Receptionist'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Equipment & Maintenance';
$activeNav = 'equipment';
$canManage = can('equipment.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save' && $canManage) {
        $data = [
            'tenant_id' => $tid,
            'branch_id' => (int) ($_POST['branch_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? 'Cardio') ?: 'Cardio',
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
    } elseif ($action === 'delete' && $canManage && !empty($_POST['id'])) {
        Equipment::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Equipment deleted.');
    } elseif ($action === 'maintenance_save' && $canManage) {
        $eqId = (int) ($_POST['equipment_id'] ?? 0);
        Database::insert('equipment_maintenance', [
            'tenant_id' => $tid,
            'equipment_id' => $eqId,
            'type' => $_POST['type'] ?? 'routine',
            'description' => trim($_POST['description'] ?? '') ?: null,
            'cost' => (float) ($_POST['cost'] ?? 0),
            'performed_at' => ($_POST['performed_at'] ?? '') ?: date('Y-m-d'),
            'next_due_at' => ($_POST['next_due_at'] ?? '') ?: null,
            'vendor' => trim($_POST['vendor'] ?? '') ?: null,
        ]);
        $eq = Equipment::find($eqId, $tid);
        if ($eq) {
            $upd = ['last_maintenance_at' => ($_POST['performed_at'] ?? '') ?: date('Y-m-d')];
            if (!empty($_POST['next_due_at'])) {
                $upd['next_maintenance_at'] = $_POST['next_due_at'];
            }
            if ($eq['status'] === 'maintenance' || $eq['status'] === 'broken') {
                $upd['status'] = 'working';
            }
            Equipment::updateById($eqId, $upd, $tid);
        }
        flash('success', 'Maintenance recorded; equipment schedule updated.');
    } elseif ($action === 'maintenance_delete' && $canManage && !empty($_POST['id'])) {
        Database::query('DELETE FROM equipment_maintenance WHERE id = ? AND tenant_id = ?', [(int) $_POST['id'], $tid]);
        flash('success', 'Maintenance record deleted.');
    }
    redirect(url('admin/equipment.php'));
}

$items = Equipment::withBranch($tid);
$alerts = Equipment::maintenanceDue($tid);
$branches = Branch::all($tid, 'name ASC', 100);
$maintenance = Database::fetchAll(
    'SELECT em.*, eq.name AS equipment_name FROM equipment_maintenance em
     JOIN equipment eq ON eq.id = em.equipment_id
     WHERE em.tenant_id = ? ORDER BY em.performed_at DESC, em.id DESC LIMIT 50',
    [$tid]
);
$edit = !empty($_GET['edit']) ? Equipment::find((int) $_GET['edit'], $tid) : null;
require __DIR__ . '/../views/gym-admin/header.php';
?>
<?php if ($alerts): ?>
<div class="panel mb-3" style="border-color:#92400e">
  <h5 class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Maintenance alerts (<?= count($alerts) ?>)</h5>
  <?php foreach ($alerts as $a): ?>
    <div class="d-flex justify-content-between py-1 small"><span><strong><?= e($a['name']) ?></strong> · <?= e($a['branch_name'] ?? '') ?> · status <?= e($a['status']) ?></span>
    <span class="<?= strtotime((string) $a['next_maintenance_at']) < time() ? 'text-danger fw-bold' : 'text-warning' ?>">due <?= e($a['next_maintenance_at']) ?></span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel mb-3">
      <h5>Inventory</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Equipment</th><th>Branch</th><th>Qty</th><th>Status</th><th>Next maintenance</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($items as $eq): ?>
          <tr><td class="fw-bold"><?= e($eq['name']) ?><br><small style="color:var(--ff-muted)"><?= e($eq['category']) ?><?= $eq['brand'] ? ' · ' . e($eq['brand']) : '' ?><?= $eq['serial_number'] ? ' · SN ' . e($eq['serial_number']) : '' ?></small></td>
            <td><?= e($eq['branch_name'] ?? '—') ?></td><td><?= (int) $eq['quantity'] ?></td>
            <td><span class="status-pill st-<?= e($eq['status']) ?>"><?= e($eq['status']) ?></span></td>
            <td><?= e($eq['next_maintenance_at'] ?? '—') ?></td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-sm btn-ff-outline" data-bs-toggle="modal" data-bs-target="#maintModal" data-eq="<?= (int) $eq['id'] ?>" data-name="<?= e($eq['name']) ?>">Log maintenance</button>
              <?php if ($canManage): ?>
              <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $eq['id'] ?>">Edit</a>
              <form method="post" class="d-inline" data-confirm="Delete this equipment item?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $eq['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
              <?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="6" style="color:var(--ff-muted)">No equipment yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>

    <div class="panel">
      <h5>Maintenance history</h5>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Date</th><th>Equipment</th><th>Type</th><th>Description</th><th>Cost</th><th>Next due</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($maintenance as $mn): ?>
          <tr><td><?= e($mn['performed_at']) ?></td><td class="fw-bold"><?= e($mn['equipment_name']) ?></td><td><?= e($mn['type']) ?></td>
            <td class="small"><?= e($mn['description'] ?? '') ?><?= $mn['vendor'] ? '<br><span style="color:var(--ff-muted)">' . e($mn['vendor']) . '</span>' : '' ?></td>
            <td><?= money($mn['cost']) ?></td><td><?= e($mn['next_due_at'] ?? '—') ?></td>
            <td class="text-end"><?php if ($canManage): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="maintenance_delete"><input type="hidden" name="id" value="<?= (int) $mn['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$maintenance): ?><tr><td colspan="7" style="color:var(--ff-muted)">No maintenance records yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>

  <div class="col-xl-4">
    <?php if ($canManage): ?>
    <div class="panel">
      <h5><?= $edit ? 'Edit equipment' : 'Add equipment' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Category</label><input class="form-control" name="category" value="<?= e($edit['category'] ?? 'Cardio') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Brand</label><input class="form-control" name="brand" value="<?= e($edit['brand'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Serial number</label><input class="form-control" name="serial_number" value="<?= e($edit['serial_number'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Quantity</label><input type="number" class="form-control" name="quantity" value="<?= e($edit['quantity'] ?? '1') ?>" min="1"></div>
          <div class="col-6 mb-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= ($edit['branch_id'] ?? null) == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['working', 'maintenance', 'broken', 'retired'] as $s): ?><option <?= ($edit['status'] ?? 'working') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Purchased</label><input type="date" class="form-control" name="purchased_at" value="<?= e($edit['purchased_at'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Next maintenance</label><input type="date" class="form-control" name="next_maintenance_at" value="<?= e($edit['next_maintenance_at'] ?? '') ?>"></div>
        </div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save equipment' : 'Add equipment' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="equipment.php">Cancel edit</a><?php endif; ?>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="maintModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post"><div class="modal-header"><h5 class="modal-title">Log maintenance — <span id="maintEqName"></span></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><?= csrf_field() ?><input type="hidden" name="action" value="maintenance_save"><input type="hidden" name="equipment_id" id="maintEqId">
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Type</label><select class="form-select" name="type"><option value="routine">Routine</option><option value="repair">Repair</option><option value="inspection">Inspection</option></select></div>
        <div class="col-6 mb-2"><label class="form-label">Performed on</label><input type="date" class="form-control" name="performed_at" value="<?= e(date('Y-m-d')) ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Cost ($)</label><input type="number" step="0.01" class="form-control" name="cost" value="0"></div>
        <div class="col-6 mb-2"><label class="form-label">Next due</label><input type="date" class="form-control" name="next_due_at"></div>
        <div class="col-12 mb-2"><label class="form-label">Vendor</label><input class="form-control" name="vendor"></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
      </div>
      <p class="small mb-0" style="color:var(--ff-muted)">Logging maintenance updates the item's last/next maintenance dates and returns broken or in-maintenance items to “working”.</p>
    </div>
    <div class="modal-footer"><button class="btn btn-ff">Save maintenance</button></div>
  </form></div></div></div>
<script>
document.getElementById('maintModal').addEventListener('show.bs.modal', function (ev) {
  var b = ev.relatedTarget;
  document.getElementById('maintEqId').value = b.getAttribute('data-eq');
  document.getElementById('maintEqName').textContent = b.getAttribute('data-name');
});
</script>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
