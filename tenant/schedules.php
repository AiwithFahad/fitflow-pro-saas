<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Trainer Schedules';
$activeNav = 'schedules';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'add';
    if ($action === 'delete') {
        Database::query('DELETE FROM trainer_schedules WHERE id = ? AND tenant_id = ?', [(int) $_POST['id'], $tid]);
        flash('success', 'Schedule entry removed.');
    } else {
        Database::insert('trainer_schedules', [
            'tenant_id' => $tid,
            'trainer_id' => (int) ($_POST['trainer_id'] ?? 0),
            'day_of_week' => $_POST['day_of_week'] ?? 'Monday',
            'start_time' => $_POST['start_time'] ?? '09:00',
            'end_time' => $_POST['end_time'] ?? '17:00',
        ]);
        flash('success', 'Schedule entry added.');
    }
    redirect(url('tenant/schedules.php'));
}

$filterTrainer = (int) ($_GET['trainer'] ?? 0);
$rows = Database::fetchAll(
    'SELECT ts.*, t.name AS trainer_name FROM trainer_schedules ts
     JOIN trainers t ON t.id = ts.trainer_id
     WHERE ts.tenant_id = ? ' . ($filterTrainer > 0 ? 'AND ts.trainer_id = ' . $filterTrainer : '') . '
     ORDER BY t.name, FIELD(ts.day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), ts.start_time',
    [$tid]
);
$trainers = Trainer::all($tid, 'name ASC', 100);
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel">
      <form method="get" class="d-flex gap-2 mb-3">
        <select class="form-select" name="trainer" style="max-width:280px"><option value="0">All trainers</option>
          <?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $filterTrainer === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-ff">Filter</button>
      </form>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Trainer</th><th>Day</th><th>From</th><th>To</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td class="fw-bold"><?= e($r['trainer_name']) ?></td><td><?= e($r['day_of_week']) ?></td>
            <td><?= e(substr($r['start_time'], 0, 5)) ?></td><td><?= e(substr($r['end_time'], 0, 5)) ?></td>
            <td class="text-end"><form method="post" onsubmit="return confirm('Remove this entry?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" style="color:var(--ff-muted)">No schedule entries yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5>Add schedule entry</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="mb-2"><label class="form-label">Trainer</label><select class="form-select" name="trainer_id" required><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $filterTrainer === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Day</label><select class="form-select" name="day_of_week"><?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?><option><?= $d ?></option><?php endforeach; ?></select></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Start</label><input type="time" class="form-control" name="start_time" value="09:00" required></div>
          <div class="col-6 mb-2"><label class="form-label">End</label><input type="time" class="form-control" name="end_time" value="17:00" required></div>
        </div>
        <button class="btn btn-ff w-100">Add entry</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
