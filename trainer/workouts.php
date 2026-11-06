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
$dashTitle = 'Workout Plans';
$activeNav = 'workouts';
$assigned = Database::fetchAll('SELECT id, name FROM members WHERE tenant_id = ? AND trainer_id = ? ORDER BY name', [$tid, $trainerId]);
$assignedIds = array_map('intval', array_column($assigned, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $memberId = (int) ($_POST['member_id'] ?? 0) ?: null;
        if ($memberId !== null && !in_array($memberId, $assignedIds, true)) {
            flash('error', 'You can only assign workouts to your own members.');
            redirect(url('trainer/workouts.php'));
        }
        $exercises = [];
        foreach ((array) ($_POST['ex_name'] ?? []) as $i => $n) {
            $n = trim((string) $n);
            if ($n === '') {
                continue;
            }
            $exercises[] = [
                'name' => $n,
                'day' => $_POST['ex_day'][$i] ?? 'Monday',
                'sets' => (int) ($_POST['ex_sets'][$i] ?? 0) ?: null,
                'reps' => (int) ($_POST['ex_reps'][$i] ?? 0) ?: null,
                'weight_kg' => (float) ($_POST['ex_weight'][$i] ?? 0) ?: null,
                'duration_min' => (int) ($_POST['ex_duration'][$i] ?? 0) ?: null,
            ];
        }
        $data = [
            'tenant_id' => $tid, 'trainer_id' => $trainerId, 'member_id' => $memberId,
            'name' => trim($_POST['name'] ?? ''),
            'category' => $_POST['category'] ?? 'Strength',
            'level' => $_POST['level'] ?? 'Beginner',
            'duration_weeks' => max(1, (int) ($_POST['duration_weeks'] ?? 4)),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'exercises' => json_encode($exercises),
            'status' => $_POST['status'] ?? 'active',
        ];
        $editId = (int) ($_POST['id'] ?? 0);
        if ($data['name'] === '') {
            flash('error', 'Workout name is required.');
        } elseif ($editId > 0) {
            $existing = Workout::find($editId, $tid);
            if ($existing && (int) ($existing['trainer_id'] ?? 0) === $trainerId) {
                unset($data['tenant_id']);
                Workout::updateById($editId, $data, $tid);
                flash('success', 'Workout plan updated.');
            } else {
                flash('error', 'You can only edit your own workout plans.');
            }
        } else {
            Workout::create($data);
            flash('success', 'Workout plan created.');
        }
    } elseif ($action === 'delete' && !empty($_POST['id'])) {
        $existing = Workout::find((int) $_POST['id'], $tid);
        if ($existing && (int) ($existing['trainer_id'] ?? 0) === $trainerId) {
            Workout::deleteById((int) $_POST['id'], $tid);
            flash('success', 'Workout plan deleted.');
        }
    } elseif ($action === 'log') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $exercise = trim($_POST['exercise_name'] ?? '');
        if (in_array($memberId, $assignedIds, true) && $exercise !== '') {
            WorkoutLog::create([
                'tenant_id' => $tid, 'member_id' => $memberId,
                'workout_id' => (int) ($_POST['workout_id'] ?? 0) ?: null,
                'exercise_name' => $exercise,
                'sets' => (int) ($_POST['sets'] ?? 0) ?: null,
                'reps' => (int) ($_POST['reps'] ?? 0) ?: null,
                'weight_kg' => (float) ($_POST['weight_kg'] ?? 0) ?: null,
                'duration_min' => (int) ($_POST['duration_min'] ?? 0) ?: null,
                'notes' => trim($_POST['notes'] ?? '') ?: null,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            flash('success', 'Completion logged for your member.');
        } else {
            flash('error', 'Choose one of your members and an exercise.');
        }
    }
    redirect(url('trainer/workouts.php'));
}

$workouts = Database::fetchAll(
    'SELECT w.*, m.name AS member_name FROM workouts w LEFT JOIN members m ON m.id = w.member_id
     WHERE w.tenant_id = ? AND w.trainer_id = ? ORDER BY w.id DESC LIMIT 100',
    [$tid, $trainerId]
);
$exerciseLib = Exercise::all($tid, 'name ASC', 300);
$edit = null;
if (!empty($_GET['edit'])) {
    $cand = Workout::find((int) $_GET['edit'], $tid);
    if ($cand && (int) ($cand['trainer_id'] ?? 0) === $trainerId) {
        $edit = $cand;
    }
}
$editEx = $edit ? (json_decode((string) ($edit['exercises'] ?? '[]'), true) ?: []) : [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
require __DIR__ . '/../views/trainer/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <?php foreach ($workouts as $w): $wex = json_decode((string) ($w['exercises'] ?? '[]'), true) ?: []; ?>
    <div class="panel mb-3">
      <div class="d-flex flex-wrap justify-content-between gap-2">
        <div><h5 class="mb-0"><?= e($w['name']) ?> <span class="status-pill st-<?= e($w['status']) ?>"><?= e($w['status']) ?></span></h5>
          <div class="small" style="color:var(--ff-muted)"><?= e($w['category']) ?> · <?= e($w['level']) ?> · <?= (int) $w['duration_weeks'] ?> weeks<?= $w['member_name'] ? ' · ' . e($w['member_name']) : ' · Template' ?></div></div>
        <div class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $w['id'] ?>">Edit</a>
          <form method="post" class="d-inline" data-confirm="Delete this workout plan?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $w['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
        </div>
      </div>
      <?php if ($wex): ?>
      <div class="table-responsive mt-2"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Day</th><th>Exercise</th><th>Sets</th><th>Reps</th><th>Weight</th><th>Duration</th></tr></thead>
        <tbody>
        <?php foreach ($wex as $ex): ?>
          <?php if (is_array($ex)): ?><tr><td><?= e($ex['day'] ?? '—') ?></td><td class="fw-bold"><?= e($ex['name'] ?? '') ?></td><td><?= e($ex['sets'] ?? '—') ?></td><td><?= e($ex['reps'] ?? '—') ?></td><td><?= isset($ex['weight_kg']) && $ex['weight_kg'] !== null ? e($ex['weight_kg']) . ' kg' : '—' ?></td><td><?= isset($ex['duration_min']) && $ex['duration_min'] !== null ? e($ex['duration_min']) . ' min' : '—' ?></td></tr>
          <?php else: ?><tr><td>—</td><td class="fw-bold" colspan="5"><?= e((string) $ex) ?></td></tr><?php endif; ?>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$workouts): ?><div class="panel">You have not created any workout plans yet.</div><?php endif; ?>
  </div>

  <div class="col-xl-4">
    <div class="panel mb-3">
      <h5><?= $edit ? 'Edit workout plan' : 'New workout plan' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Member</label><select class="form-select" name="member_id"><option value="">Template</option><?php foreach ($assigned as $m): ?><option value="<?= (int) $m['id'] ?>" <?= ($edit['member_id'] ?? null) == $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach (['Strength', 'Bodybuilding', 'Cardio', 'Weight Loss', 'Flexibility', 'Sports'] as $c): ?><option <?= ($edit['category'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Level</label><select class="form-select" name="level"><?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $l): ?><option <?= ($edit['level'] ?? '') === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Weeks</label><input type="number" class="form-control" name="duration_weeks" value="<?= e($edit['duration_weeks'] ?? '4') ?>" min="1"></div>
        </div>
        <label class="form-label">Exercises</label>
        <div id="exRows">
          <?php $rows = $editEx ?: [['name' => '', 'day' => 'Monday', 'sets' => '', 'reps' => '', 'weight_kg' => '', 'duration_min' => '']]; ?>
          <?php foreach ($rows as $ex): ?><?php if (is_array($ex)): ?>
          <div class="row g-1 mb-1 ex-row">
            <div class="col-4"><input class="form-control form-control-sm" name="ex_name[]" list="exerciseNames" placeholder="Exercise" value="<?= e($ex['name'] ?? '') ?>"></div>
            <div class="col-2"><select class="form-select form-select-sm" name="ex_day[]"><?php foreach ($days as $d): ?><option <?= ($ex['day'] ?? 'Monday') === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div>
            <div class="col-1"><input class="form-control form-control-sm" name="ex_sets[]" type="number" placeholder="Sets" value="<?= e($ex['sets'] ?? '') ?>"></div>
            <div class="col-1"><input class="form-control form-control-sm" name="ex_reps[]" type="number" placeholder="Reps" value="<?= e($ex['reps'] ?? '') ?>"></div>
            <div class="col-2"><input class="form-control form-control-sm" name="ex_weight[]" type="number" step="0.5" placeholder="kg" value="<?= e($ex['weight_kg'] ?? '') ?>"></div>
            <div class="col-1"><input class="form-control form-control-sm" name="ex_duration[]" type="number" placeholder="min" value="<?= e($ex['duration_min'] ?? '') ?>"></div>
            <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.ex-row').remove()">×</button></div>
          </div>
          <?php endif; ?><?php endforeach; ?>
        </div>
        <datalist id="exerciseNames"><?php foreach ($exerciseLib as $ex): ?><option value="<?= e($ex['name']) ?>"><?php endforeach; ?></datalist>
        <button type="button" class="btn btn-sm btn-ff-outline mb-3" id="addExRow"><i class="bi bi-plus-lg me-1"></i>Add exercise</button>
        <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save plan' : 'Create plan' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="workouts.php">Cancel edit</a><?php endif; ?>
      </form>
      <script>
      document.getElementById('addExRow').addEventListener('click', function(){
        var row = document.querySelector('.ex-row').cloneNode(true);
        row.querySelectorAll('input').forEach(function(i){ i.value = ''; });
        document.getElementById('exRows').appendChild(row);
      });
      </script>
    </div>

    <div class="panel">
      <h5>Log a member completion</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="log">
        <div class="mb-2"><label class="form-label">Member</label><select class="form-select" name="member_id" required><option value="">Choose…</option><?php foreach ($assigned as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Exercise</label><input class="form-control" name="exercise_name" list="exerciseNames" required></div>
        <div class="row g-2">
          <div class="col-3 mb-2"><label class="form-label">Sets</label><input type="number" class="form-control" name="sets"></div>
          <div class="col-3 mb-2"><label class="form-label">Reps</label><input type="number" class="form-control" name="reps"></div>
          <div class="col-3 mb-2"><label class="form-label">kg</label><input type="number" step="0.5" class="form-control" name="weight_kg"></div>
          <div class="col-3 mb-2"><label class="form-label">Min</label><input type="number" class="form-control" name="duration_min"></div>
        </div>
        <div class="mb-2"><label class="form-label">Notes</label><input class="form-control" name="notes"></div>
        <button class="btn btn-ff w-100">Log completion</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/trainer/footer.php'; ?>
