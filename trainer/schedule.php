<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$user = current_user();
$trainer = Database::fetch('SELECT * FROM trainers WHERE user_id = ? AND tenant_id = ?', [(int) $user['id'], $tid]);
if ($trainer === null) {
    flash('error', 'No trainer profile is linked to this account yet.');
    redirect(url('public/logout.php'));
}
$trainerId = (int) $trainer['id'];
$dashTitle = 'My Schedule';
$activeNav = 'schedule';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        Database::insert('trainer_schedules', [
            'tenant_id' => $tid, 'trainer_id' => $trainerId,
            'day_of_week' => $_POST['day_of_week'] ?? 'Monday',
            'start_time' => $_POST['start_time'] ?? '06:00',
            'end_time' => $_POST['end_time'] ?? '14:00',
            'shift_type' => trim($_POST['shift_type'] ?? '') ?: null,
        ]);
        flash('success', 'Shift added to your schedule.');
    } elseif ($action === 'delete' && !empty($_POST['id'])) {
        Database::query('DELETE FROM trainer_schedules WHERE id = ? AND tenant_id = ? AND trainer_id = ?', [(int) $_POST['id'], $tid, $trainerId]);
        flash('success', 'Shift removed.');
    }
    redirect(url('trainer/schedule.php'));
}

$shifts = Database::fetchAll(
    'SELECT * FROM trainer_schedules WHERE tenant_id = ? AND trainer_id = ?
     ORDER BY FIELD(day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), start_time',
    [$tid, $trainerId]
);
$classes = Database::fetchAll(
    'SELECT c.*, b.name AS branch_name, (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = c.id AND cb.status = "booked") AS booked_count
     FROM classes c LEFT JOIN branches b ON b.id = c.branch_id
     WHERE c.tenant_id = ? AND c.trainer_id = ? AND c.status = "active"
     ORDER BY FIELD(c.day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), c.start_time',
    [$tid, $trainerId]
);
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
require __DIR__ . '/../views/trainer/header.php';
?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="panel mb-3">
      <h5>My weekly shifts</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Day</th><th>Shift</th><th>Type</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($shifts as $s): ?>
          <tr><td class="fw-bold"><?= e($s['day_of_week']) ?></td><td><?= e(substr((string) $s['start_time'], 0, 5)) ?> – <?= e(substr((string) $s['end_time'], 0, 5)) ?></td><td><?= e($s['shift_type'] ?? 'Floor') ?></td>
            <td class="text-end"><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$shifts): ?><tr><td colspan="4" style="color:var(--ff-muted)">No shifts yet — add your availability below.</td></tr><?php endif; ?>
        </tbody></table></div>
      <form method="post" class="row g-2 mt-2 align-items-end">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="col-md-3"><label class="form-label small">Day</label><select class="form-select" name="day_of_week"><?php foreach ($days as $d): ?><option><?= $d ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small">Start</label><input type="time" class="form-control" name="start_time" value="06:00"></div>
        <div class="col-md-2"><label class="form-label small">End</label><input type="time" class="form-control" name="end_time" value="14:00"></div>
        <div class="col-md-3"><label class="form-label small">Type</label><input class="form-control" name="shift_type" placeholder="Floor / PT / Classes"></div>
        <div class="col-md-2"><button class="btn btn-ff w-100">Add shift</button></div>
      </form>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="panel">
      <h5>Classes I teach</h5>
      <?php foreach ($classes as $c): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)">
          <span><strong><?= e($c['name']) ?></strong><br><small style="color:var(--ff-muted)"><?= e($c['day_of_week']) ?> · <?= e($c['branch_name'] ?? '') ?></small></span>
          <span class="text-end"><?= e(substr((string) $c['start_time'], 0, 5)) ?> – <?= e(substr((string) $c['end_time'], 0, 5)) ?><br><small style="color:var(--ff-muted)"><?= (int) $c['booked_count'] ?>/<?= (int) $c['capacity'] ?> booked</small></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$classes): ?><p class="small mb-0" style="color:var(--ff-muted)">No classes assigned to you yet.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/trainer/footer.php'; ?>
