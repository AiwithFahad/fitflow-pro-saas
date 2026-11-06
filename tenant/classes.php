<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Fitness Classes';
$activeNav = 'classes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        GymClass::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Class deleted.');
    } else {
        $data = [
            'tenant_id' => $tid,
            'branch_id' => (int) ($_POST['branch_id'] ?? 0) ?: null,
            'trainer_id' => (int) ($_POST['trainer_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? 'General'),
            'day_of_week' => $_POST['day_of_week'] ?? 'Monday',
            'start_time' => $_POST['start_time'] ?? '09:00',
            'end_time' => $_POST['end_time'] ?? '10:00',
            'capacity' => max(1, (int) ($_POST['capacity'] ?? 20)),
            'status' => $_POST['status'] ?? 'active',
        ];
        if ($data['name'] === '') {
            flash('error', 'Class name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            GymClass::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Class updated.');
        } else {
            if (!plan_allows($tid, 'classes')) {
                flash('error', plan_limit_message('classes'));
                redirect(url('tenant/classes.php'));
            }
            GymClass::create($data);
            flash('success', 'Class added to the weekly schedule.');
        }
    }
    redirect(url('tenant/classes.php'));
}

$classes = GymClass::upcoming($tid, 200);
$trainers = Trainer::all($tid, 'name ASC', 100);
$branches = Branch::all($tid, 'name ASC', 50);
$edit = !empty($_GET['edit']) ? GymClass::find((int) $_GET['edit'], $tid) : null;
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Class</th><th>Day & time</th><th>Trainer</th><th>Branch</th><th>Booked / capacity</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($classes as $c): ?>
        <tr>
          <td><span class="fw-bold"><?= e($c['name']) ?></span><br><small style="color:var(--ff-muted)"><?= e($c['category']) ?></small></td>
          <td><?= e($c['day_of_week']) ?><br><small style="color:var(--ff-muted)"><?= e(substr($c['start_time'], 0, 5)) ?> – <?= e(substr($c['end_time'], 0, 5)) ?></small></td>
          <td><?= e($c['trainer_name'] ?? '—') ?></td>
          <td><?= e($c['branch_name'] ?? '—') ?></td>
          <td>
            <div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="min-width:90px"><div class="progress-bar" style="width:<?= min(100, (int) $c['capacity'] > 0 ? round($c['booked_count'] / $c['capacity'] * 100) : 0) ?>%"></div></div>
            <span class="small fw-bold"><?= (int) $c['booked_count'] ?>/<?= (int) $c['capacity'] ?></span></div>
          </td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-light" href="<?= e(url('tenant/bookings.php')) ?>?class=<?= (int) $c['id'] ?>">Bookings</a>
            <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $c['id'] ?>">Edit</a>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this class?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$classes): ?><tr><td colspan="6" style="color:var(--ff-muted)">No classes scheduled yet.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit class' : 'Add class' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Class name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Category</label><input class="form-control" name="category" list="clsCats" value="<?= e($edit['category'] ?? 'Strength') ?>"><datalist id="clsCats"><option>Strength</option><option>Bodybuilding</option><option>Cardio</option><option>Weight Loss</option><option>Flexibility</option></datalist></div>
          <div class="col-6 mb-2"><label class="form-label">Day</label><select class="form-select" name="day_of_week"><?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?><option <?= ($edit['day_of_week'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Start</label><input type="time" class="form-control" name="start_time" value="<?= e($edit ? substr((string) $edit['start_time'], 0, 5) : '09:00') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">End</label><input type="time" class="form-control" name="end_time" value="<?= e($edit ? substr((string) $edit['end_time'], 0, 5) : '10:00') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Capacity</label><input type="number" class="form-control" name="capacity" value="<?= e($edit['capacity'] ?? '20') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
          <div class="col-6 mb-2"><label class="form-label">Trainer</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($edit['trainer_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) ($edit['branch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save changes' : 'Add class' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="<?= e(url('tenant/classes.php')) ?>">Cancel edit</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
