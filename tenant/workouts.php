<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Workout Programs';
$activeNav = 'workouts';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        Workout::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Program deleted.');
    } elseif ($action === 'complete') {
        Workout::updateById((int) $_POST['id'], ['status' => 'completed'], $tid);
        flash('success', 'Program marked completed.');
    } else {
        $exList = array_values(array_filter(array_map('trim', explode("\n", (string) ($_POST['exercises'] ?? '')))));
        $data = [
            'tenant_id' => $tid,
            'member_id' => (int) ($_POST['member_id'] ?? 0) ?: null,
            'trainer_id' => (int) ($_POST['trainer_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'category' => $_POST['category'] ?? 'Strength',
            'level' => $_POST['level'] ?? 'Beginner',
            'duration_weeks' => max(1, (int) ($_POST['duration_weeks'] ?? 4)),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'exercises' => json_encode($exList),
            'status' => 'active',
        ];
        if ($data['name'] === '') {
            flash('error', 'Program name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            Workout::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Program updated.');
        } else {
            $wid = Workout::create($data);
            if ($data['member_id']) {
                $member = Member::find((int) $data['member_id'], $tid);
                if ($member) notify($tid, $member['user_id'] ?? null, 'New workout assigned', 'Your trainer assigned "' . $data['name'] . '" — open your portal to view it.', 'info');
            }
            log_activity($tid, 'workout.created', 'workouts', $wid, $data['name']);
            flash('success', 'Workout program saved' . ($data['member_id'] ? ' and assigned to the member.' : ' to the library.'));
        }
    }
    redirect(url('tenant/workouts.php'));
}

$library = Workout::library($tid);
$assigned = Database::fetchAll(
    'SELECT w.*, m.name AS member_name, t.name AS trainer_name FROM workouts w
     JOIN members m ON m.id = w.member_id LEFT JOIN trainers t ON t.id = w.trainer_id
     WHERE w.tenant_id = ? ORDER BY w.id DESC',
    [$tid]
);
$members = Member::all($tid, 'name ASC', 300);
$trainers = Trainer::all($tid, 'name ASC', 100);
$exerciseNames = array_column(Exercise::all($tid, 'name ASC', 300), 'name');
$edit = !empty($_GET['edit']) ? Workout::find((int) $_GET['edit'], $tid) : null;
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel mb-3">
      <h5>Program library <small style="color:var(--ff-muted)">(templates any trainer can assign)</small></h5>
      <div class="row g-3">
        <?php foreach ($library as $w): ?>
        <div class="col-md-6"><div class="ff-card p-3 h-100">
          <div class="d-flex justify-content-between"><h6 class="fw-bold mb-1"><?= e($w['name']) ?></h6><span class="status-pill st-info"><?= e($w['category']) ?></span></div>
          <small style="color:var(--ff-muted)"><?= e($w['level']) ?> · <?= (int) $w['duration_weeks'] ?> weeks</small>
          <p class="small mt-2 mb-2" style="color:var(--ff-muted)"><?= e($w['description'] ?? '') ?></p>
          <?php $exs = json_decode((string) ($w['exercises'] ?? '[]'), true) ?: []; ?>
          <div><?php foreach ($exs as $ex): ?><span class="badge bg-dark border me-1 mb-1"><?= e($ex) ?></span><?php endforeach; ?></div>
          <div class="d-flex gap-2 mt-2">
            <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $w['id'] ?>">Edit</a>
            <form method="post" onsubmit="return confirm('Delete this program?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $w['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button></form>
          </div>
        </div></div>
        <?php endforeach; ?>
        <?php if (!$library): ?><div class="col-12" style="color:var(--ff-muted)">Library is empty — create your first template.</div><?php endif; ?>
      </div>
    </div>
    <div class="panel">
      <h5>Assigned to members</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Program</th><th>Member</th><th>Trainer</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($assigned as $w): ?>
          <tr><td class="fw-bold"><?= e($w['name']) ?></td><td><?= e($w['member_name']) ?></td><td><?= e($w['trainer_name'] ?? '—') ?></td>
            <td><span class="status-pill st-<?= e($w['status']) ?>"><?= e($w['status']) ?></span></td>
            <td class="text-end">
              <?php if ($w['status'] === 'active'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $w['id'] ?>"><button name="action" value="complete" class="btn btn-sm btn-outline-success">Complete</button></form><?php endif; ?>
              <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $w['id'] ?>">Edit</a>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$assigned): ?><tr><td colspan="5" style="color:var(--ff-muted)">No programs assigned yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit program' : 'Create / assign program' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Program name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach (['Strength', 'Bodybuilding', 'Cardio', 'Weight Loss', 'Flexibility', 'Sports'] as $c): ?><option <?= ($edit['category'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Level</label><select class="form-select" name="level"><?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $l): ?><option <?= ($edit['level'] ?? '') === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Duration (weeks)</label><input type="number" class="form-control" name="duration_weeks" value="<?= e($edit['duration_weeks'] ?? '4') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Trainer</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($edit['trainer_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-2"><label class="form-label">Assign to member <small style="color:var(--ff-muted)">(leave empty = library template)</small></label>
          <select class="form-select" name="member_id"><option value="">— Library template —</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) ($edit['member_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></div>
        <div class="mb-2"><label class="form-label">Exercises <small style="color:var(--ff-muted)">(one per line)</small></label>
          <textarea class="form-control" name="exercises" rows="5" placeholder="<?= e(implode("\n", array_slice($exerciseNames, 0, 3))) ?>"><?= e($edit ? implode("\n", json_decode((string) ($edit['exercises'] ?? '[]'), true) ?: []) : '') ?></textarea></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save changes' : 'Save program' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="<?= e(url('tenant/workouts.php')) ?>">Cancel edit</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
