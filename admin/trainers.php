<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Trainers';
$activeNav = 'trainers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save' && can('trainers.manage')) {
        $data = [
            'tenant_id' => $tid,
            'branch_id' => (int) ($_POST['branch_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'specialty' => trim($_POST['specialty'] ?? '') ?: null,
            'qualifications' => trim($_POST['qualifications'] ?? '') ?: null,
            'experience_years' => max(0, (int) ($_POST['experience_years'] ?? 0)),
            'bio' => trim($_POST['bio'] ?? '') ?: null,
            'status' => in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active',
        ];
        if ($data['name'] === '') {
            flash('error', 'Trainer name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            Trainer::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Trainer updated.');
        } else {
            if (!plan_allows($tid, 'trainers')) {
                flash('error', plan_limit_message('trainers'));
                redirect(url('admin/trainers.php'));
            }
            Trainer::create($data);
            flash('success', 'Trainer added.');
        }
    } elseif ($action === 'delete' && can('trainers.manage') && !empty($_POST['id'])) {
        Trainer::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Trainer deleted.');
    } elseif ($action === 'schedule_save' && can('trainers.manage')) {
        Database::insert('trainer_schedules', [
            'tenant_id' => $tid,
            'trainer_id' => (int) $_POST['trainer_id'],
            'day_of_week' => $_POST['day_of_week'] ?? 'Monday',
            'start_time' => $_POST['start_time'] ?? '06:00',
            'end_time' => $_POST['end_time'] ?? '14:00',
            'shift_type' => trim($_POST['shift_type'] ?? '') ?: null,
        ]);
        flash('success', 'Schedule shift added.');
    } elseif ($action === 'schedule_delete' && can('trainers.manage') && !empty($_POST['id'])) {
        Database::query('DELETE FROM trainer_schedules WHERE id = ? AND tenant_id = ?', [(int) $_POST['id'], $tid]);
        flash('success', 'Schedule shift removed.');
    }
    redirect(url('admin/trainers.php'));
}

$trainers = Trainer::withSchedule($tid);
$branches = Branch::all($tid, 'name ASC', 100);
$edit = !empty($_GET['edit']) ? Trainer::find((int) $_GET['edit'], $tid) : null;
$assigned = [];
foreach (Database::fetchAll('SELECT trainer_id, COUNT(*) AS c FROM members WHERE tenant_id = ? AND trainer_id IS NOT NULL GROUP BY trainer_id', [$tid]) as $r) {
    $assigned[(int) $r['trainer_id']] = (int) $r['c'];
}
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <?php foreach ($trainers as $t): ?>
    <div class="panel mb-3">
      <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
          <h5 class="mb-0"><?= e($t['name']) ?> <span class="status-pill st-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></h5>
          <div class="small" style="color:var(--ff-muted)"><?= e($t['specialty'] ?? '') ?> · <?= (int) $t['experience_years'] ?> yrs experience · <?= $assigned[(int) $t['id']] ?? 0 ?> assigned members</div>
          <?php if (!empty($t['qualifications'])): ?><div class="small"><i class="bi bi-award text-orange me-1"></i><?= e($t['qualifications']) ?></div><?php endif; ?>
          <div class="small" style="color:var(--ff-muted)"><?= e($t['email'] ?? '') ?><?= $t['phone'] ? ' · ' . e($t['phone']) : '' ?></div>
        </div>
        <div class="text-end">
          <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $t['id'] ?>">Edit</a>
          <a class="btn btn-sm btn-ff-outline" href="<?= e(url('admin/members.php')) ?>">Members</a>
          <form method="post" class="d-inline" data-confirm="Delete this trainer? Assigned members keep their history but lose the trainer link.">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Delete</button></form>
        </div>
      </div>
      <?php if (!empty($t['bio'])): ?><p class="small mt-2 mb-2"><?= e($t['bio']) ?></p><?php endif; ?>
      <div class="table-responsive mt-2"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Day</th><th>Shift</th><th>Type</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($t['schedule'] as $s): ?>
          <tr><td><?= e($s['day_of_week']) ?></td><td><?= e(substr((string) $s['start_time'], 0, 5)) ?> – <?= e(substr((string) $s['end_time'], 0, 5)) ?></td><td><?= e($s['shift_type'] ?? 'Floor') ?></td>
            <td class="text-end"><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="schedule_delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$t['schedule']): ?><tr><td colspan="4" style="color:var(--ff-muted)">No shifts scheduled.</td></tr><?php endif; ?>
        </tbody></table></div>
      <form method="post" class="row g-2 mt-2 align-items-end">
        <?= csrf_field() ?><input type="hidden" name="action" value="schedule_save"><input type="hidden" name="trainer_id" value="<?= (int) $t['id'] ?>">
        <div class="col-md-3"><label class="form-label small">Day</label><select class="form-select form-select-sm" name="day_of_week"><?php foreach ($days as $d): ?><option><?= $d ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small">Start</label><input type="time" class="form-control form-control-sm" name="start_time" value="06:00"></div>
        <div class="col-md-2"><label class="form-label small">End</label><input type="time" class="form-control form-control-sm" name="end_time" value="14:00"></div>
        <div class="col-md-3"><label class="form-label small">Shift type</label><input class="form-control form-control-sm" name="shift_type" placeholder="Floor / PT / Classes"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-ff w-100">Add shift</button></div>
      </form>
    </div>
    <?php endforeach; ?>
    <?php if (!$trainers): ?><div class="panel">No trainers yet — add the first one.</div><?php endif; ?>
  </div>
  <div class="col-xl-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit trainer' : 'Add trainer' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Specialization</label><input class="form-control" name="specialty" value="<?= e($edit['specialty'] ?? '') ?>" placeholder="Strength & Conditioning"></div>
        <div class="mb-2"><label class="form-label">Qualifications</label><input class="form-control" name="qualifications" value="<?= e($edit['qualifications'] ?? '') ?>" placeholder="NASM-CPT, FMS L2, …"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Experience (years)</label><input type="number" class="form-control" name="experience_years" value="<?= e($edit['experience_years'] ?? '0') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= ($edit['branch_id'] ?? null) == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
        <div class="mb-3"><label class="form-label">Bio</label><textarea class="form-control" name="bio" rows="3"><?= e($edit['bio'] ?? '') ?></textarea></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save trainer' : 'Add trainer' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="trainers.php">Cancel edit</a><?php endif; ?>
      </form>
      <p class="small mt-3 mb-0" style="color:var(--ff-muted)">Salaries for trainers are managed under Staff &amp; Salaries; trainer attendance under Attendance. A trainer gets portal access when a user account with the Trainer role is linked to their profile (Staff page).</p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
