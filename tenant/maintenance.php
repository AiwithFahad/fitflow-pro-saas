<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Equipment Maintenance';
$activeNav = 'maintenance';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'add';
    if ($action === 'delete') {
        Database::query('DELETE FROM equipment_maintenance WHERE id = ? AND tenant_id = ?', [(int) $_POST['id'], $tid]);
        flash('success', 'Maintenance record deleted.');
    } else {
        $eqId = (int) ($_POST['equipment_id'] ?? 0);
        Database::insert('equipment_maintenance', [
            'tenant_id' => $tid,
            'equipment_id' => $eqId,
            'type' => $_POST['type'] ?? 'routine',
            'description' => trim($_POST['description'] ?? '') ?: null,
            'cost' => (float) ($_POST['cost'] ?? 0),
            'performed_at' => ($_POST['performed_at'] ?? '') ?: date('Y-m-d'),
            'next_due_at' => ($_POST['next_due_at'] ?? '') ?: null,
        ]);
        // Keep the equipment record in sync.
        Database::update('equipment', [
            'last_maintenance_at' => ($_POST['performed_at'] ?? '') ?: date('Y-m-d'),
            'next_maintenance_at' => ($_POST['next_due_at'] ?? '') ?: null,
            'status' => $_POST['mark_working'] ?? '' ? 'working' : (Equipment::find($eqId, $tid)['status'] ?? 'working'),
        ], 'id = :wid AND tenant_id = :wtid', ['wid' => $eqId, 'wtid' => $tid]);
        flash('success', 'Maintenance recorded and equipment schedule updated.');
    }
    redirect(url('tenant/maintenance.php'));
}

$records = Database::fetchAll(
    'SELECT em.*, eq.name AS equipment_name FROM equipment_maintenance em
     JOIN equipment eq ON eq.id = em.equipment_id
     WHERE em.tenant_id = ? ORDER BY em.performed_at DESC, em.id DESC LIMIT 100',
    [$tid]
);
$due = Equipment::maintenanceDue($tid);
$items = Equipment::all($tid, 'name ASC', 200);
$totalCost = (float) Database::scalar('SELECT COALESCE(SUM(cost),0) FROM equipment_maintenance WHERE tenant_id = ?', [$tid]);
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3 mb-1">
  <div class="col-md-6"><div class="kpi"><div class="k-label">Due within 14 days / overdue</div><div class="k-value"><?= count($due) ?></div></div></div>
  <div class="col-md-6"><div class="kpi"><div class="k-label">Total maintenance spend</div><div class="k-value"><?= money($totalCost) ?></div></div></div>
</div>
<?php if ($due): ?>
<div class="panel mb-3"><h5>Needs attention</h5>
  <?php foreach ($due as $d): ?>
  <div class="d-flex justify-content-between py-1" style="border-bottom:1px solid var(--ff-border)"><span><strong><?= e($d['name']) ?></strong> <small style="color:var(--ff-muted)">· <?= e($d['branch_name'] ?? '') ?></small></span><span class="status-pill <?= strtotime((string) $d['next_maintenance_at']) < time() ? 'st-broken' : 'st-pending' ?>"><?= strtotime((string) $d['next_maintenance_at']) < time() ? 'Overdue' : 'Due' ?> <?= e($d['next_maintenance_at']) ?></span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Equipment</th><th>Type</th><th>Description</th><th>Cost</th><th>Performed</th><th>Next due</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($records as $r): ?>
        <tr><td class="fw-bold"><?= e($r['equipment_name']) ?></td><td><?= e($r['type']) ?></td><td><?= e($r['description'] ?? '') ?></td>
          <td><?= money($r['cost']) ?></td><td><?= e($r['performed_at'] ?? '') ?></td><td><?= e($r['next_due_at'] ?? '—') ?></td>
          <td class="text-end"><form method="post" onsubmit="return confirm('Delete this record?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$records): ?><tr><td colspan="7" style="color:var(--ff-muted)">No maintenance records yet.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5>Log maintenance</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="mb-2"><label class="form-label">Equipment</label><select class="form-select" name="equipment_id" required><?php foreach ($items as $eq): ?><option value="<?= (int) $eq['id'] ?>"><?= e($eq['name']) ?></option><?php endforeach; ?></select></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Type</label><select class="form-select" name="type"><option>routine</option><option>repair</option><option>inspection</option></select></div>
          <div class="col-6 mb-2"><label class="form-label">Cost ($)</label><input type="number" step="0.01" class="form-control" name="cost" value="0"></div>
          <div class="col-6 mb-2"><label class="form-label">Performed</label><input type="date" class="form-control" name="performed_at" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Next due</label><input type="date" class="form-control" name="next_due_at"></div>
        </div>
        <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="mark_working" value="1" id="mkW"><label class="form-check-label" for="mkW">Mark equipment as working</label></div>
        <button class="btn btn-ff w-100">Save record</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
