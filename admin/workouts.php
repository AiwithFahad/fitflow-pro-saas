<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Workout Programs';
$activeNav = 'workouts';
$canManage = can('workouts.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'workout_save' && $canManage) {
        // Structured exercise rows → JSON (sets/reps/weight/duration/day).
        $exercises = [];
        $names = $_POST['ex_name'] ?? [];
        foreach ((array) $names as $i => $n) {
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
            'tenant_id' => $tid,
            'member_id' => (int) ($_POST['member_id'] ?? 0) ?: null,
            'trainer_id' => (int) ($_POST['trainer_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'category' => $_POST['category'] ?? 'Strength',
            'level' => $_POST['level'] ?? 'Beginner',
            'duration_weeks' => max(1, (int) ($_POST['duration_weeks'] ?? 4)),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'exercises' => json_encode($exercises),
            'status' => $_POST['status'] ?? 'active',
        ];
        if ($data['name'] === '') {
            flash('error', 'Workout name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            Workout::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Workout program updated.');
        } else {
            Workout::create($data);
            flash('success', 'Workout program created.');
        }
    } elseif ($action === 'workout_delete' && $canManage && !empty($_POST['id'])) {
        Workout::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Workout program deleted.');
    } elseif ($action === 'workout_complete' && $canManage && !empty($_POST['id'])) {
        Workout::updateById((int) $_POST['id'], ['status' => 'completed'], $tid);
        flash('success', 'Workout program marked completed.');
    } elseif ($action === 'exercise_save' && $canManage) {
        $data = [
            'tenant_id' => $tid,
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? 'Strength') ?: 'Strength',
            'muscle_group' => trim($_POST['muscle_group'] ?? '') ?: null,
            'equipment' => trim($_POST['equipment'] ?? '') ?: null,
            'difficulty' => $_POST['difficulty'] ?? 'Beginner',
            'description' => trim($_POST['description'] ?? '') ?: null,
            'video_url' => trim($_POST['video_url'] ?? '') ?: null,
        ];
        if ($data['name'] === '') {
            flash('error', 'Exercise name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            Exercise::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Exercise updated.');
        } else {
            Exercise::create($data);
            flash('success', 'Exercise added to the library.');
        }
    } elseif ($action === 'exercise_delete' && $canManage && !empty($_POST['id'])) {
        Exercise::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Exercise deleted.');
    } elseif ($action === 'log_save' && can('workouts.view')) {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $exercise = trim($_POST['exercise_name'] ?? '');
        if ($memberId > 0 && $exercise !== '') {
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
            flash('success', 'Workout completion logged.');
        } else {
            flash('error', 'Choose a member and an exercise to log.');
        }
    }
    redirect(url('admin/workouts.php'));
}

$workouts = Database::fetchAll(
    'SELECT w.*, m.name AS member_name, t.name AS trainer_name
     FROM workouts w
     LEFT JOIN members m ON m.id = w.member_id
     LEFT JOIN trainers t ON t.id = w.trainer_id
     WHERE w.tenant_id = ? ORDER BY w.id DESC LIMIT 200',
    [$tid]
);
$exercises = Exercise::all($tid, 'name ASC', 300);
$members = Member::all($tid, 'name ASC', 500);
$trainers = Trainer::all($tid, 'name ASC', 100);
$logs = Database::fetchAll(
    'SELECT l.*, m.name AS member_name FROM workout_logs l JOIN members m ON m.id = l.member_id
     WHERE l.tenant_id = ? ORDER BY l.completed_at DESC LIMIT 25',
    [$tid]
);
$edit = !empty($_GET['edit']) ? Workout::find((int) $_GET['edit'], $tid) : null;
$editEx = $edit ? (json_decode((string) ($edit['exercises'] ?? '[]'), true) ?: []) : [];
$editExercise = !empty($_GET['edit_exercise']) ? Exercise::find((int) $_GET['edit_exercise'], $tid) : null;
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <?php foreach ($workouts as $w): $wex = json_decode((string) ($w['exercises'] ?? '[]'), true) ?: []; ?>
    <div class="panel mb-3">
      <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
          <h5 class="mb-0"><?= e($w['name']) ?> <span class="status-pill st-<?= e($w['status']) ?>"><?= e($w['status']) ?></span></h5>
          <div class="small" style="color:var(--ff-muted)"><?= e($w['category']) ?> · <?= e($w['level']) ?> · <?= (int) $w['duration_weeks'] ?> weeks
            <?= !empty($w['member_name']) ? ' · Member: ' . e($w['member_name']) : ' · Template' ?>
            <?= !empty($w['trainer_name']) ? ' · Coach: ' . e($w['trainer_name']) : '' ?></div>
        </div>
        <?php if ($canManage): ?>
        <div class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $w['id'] ?>">Edit</a>
          <?php if ($w['status'] === 'active'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="workout_complete"><input type="hidden" name="id" value="<?= (int) $w['id'] ?>"><button class="btn btn-sm btn-outline-success">Complete</button></form><?php endif; ?>
          <form method="post" class="d-inline" data-confirm="Delete this workout program?"><?= csrf_field() ?><input type="hidden" name="action" value="workout_delete"><input type="hidden" name="id" value="<?= (int) $w['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($wex): ?>
      <div class="table-responsive mt-2"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Day</th><th>Exercise</th><th>Sets</th><th>Reps</th><th>Weight</th><th>Duration</th></tr></thead>
        <tbody>
        <?php foreach ($wex as $ex): ?>
          <?php if (is_array($ex)): ?>
          <tr><td><?= e($ex['day'] ?? '—') ?></td><td class="fw-bold"><?= e($ex['name'] ?? '') ?></td><td><?= e($ex['sets'] ?? '—') ?></td><td><?= e($ex['reps'] ?? '—') ?></td><td><?= isset($ex['weight_kg']) && $ex['weight_kg'] !== null ? e($ex['weight_kg']) . ' kg' : '—' ?></td><td><?= isset($ex['duration_min']) && $ex['duration_min'] !== null ? e($ex['duration_min']) . ' min' : '—' ?></td></tr>
          <?php else: ?>
          <tr><td>—</td><td class="fw-bold" colspan="5"><?= e((string) $ex) ?></td></tr>
          <?php endif; ?>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$workouts): ?><div class="panel">No workout programs yet.</div><?php endif; ?>

    <div class="panel">
      <h5>Recent completion logs</h5>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>When</th><th>Member</th><th>Exercise</th><th>Sets × Reps</th><th>Weight</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr><td class="small"><?= e(substr((string) $l['completed_at'], 0, 16)) ?></td><td class="fw-bold"><?= e($l['member_name']) ?></td><td><?= e($l['exercise_name']) ?></td>
            <td><?= e($l['sets'] ?? '—') ?> × <?= e($l['reps'] ?? '—') ?></td><td><?= $l['weight_kg'] !== null ? e($l['weight_kg']) . ' kg' : '—' ?></td><td class="small"><?= e($l['notes'] ?? '') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="6" style="color:var(--ff-muted)">No completions logged yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>

  <div class="col-xl-4">
    <?php if ($canManage): ?>
    <div class="panel mb-3">
      <h5><?= $edit ? 'Edit workout program' : 'Workout builder' ?></h5>
      <form method="post" id="workoutForm">
        <?= csrf_field() ?><input type="hidden" name="action" value="workout_save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Program name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach (['Strength', 'Bodybuilding', 'Cardio', 'Weight Loss', 'Flexibility', 'Sports'] as $c): ?><option <?= ($edit['category'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Level</label><select class="form-select" name="level"><?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $l): ?><option <?= ($edit['level'] ?? '') === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Assign member</label><select class="form-select" name="member_id"><option value="">Template (unassigned)</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>" <?= ($edit['member_id'] ?? null) == $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Coach</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= ($edit['trainer_id'] ?? null) == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Duration (weeks)</label><input type="number" class="form-control" name="duration_weeks" value="<?= e($edit['duration_weeks'] ?? '4') ?>" min="1"></div>
          <div class="col-6 mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['active', 'completed', 'archived'] as $s): ?><option <?= ($edit['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></div>
        <label class="form-label">Exercises (weekly schedule)</label>
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
        <datalist id="exerciseNames"><?php foreach ($exercises as $ex): ?><option value="<?= e($ex['name']) ?>"><?php endforeach; ?></datalist>
        <button type="button" class="btn btn-sm btn-ff-outline mb-3" id="addExRow"><i class="bi bi-plus-lg me-1"></i>Add exercise</button>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save program' : 'Create program' ?></button>
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
    <?php endif; ?>

    <div class="panel mb-3">
      <h5>Log a completion</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="log_save">
        <div class="mb-2"><label class="form-label">Member</label><select class="form-select" name="member_id" required><option value="">Choose…</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Program (optional)</label><select class="form-select" name="workout_id"><option value="">—</option><?php foreach ($workouts as $w): ?><option value="<?= (int) $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Exercise</label><input class="form-control" name="exercise_name" list="exerciseNames" required></div>
        <div class="row g-2">
          <div class="col-3 mb-2"><label class="form-label">Sets</label><input type="number" class="form-control" name="sets"></div>
          <div class="col-3 mb-2"><label class="form-label">Reps</label><input type="number" class="form-control" name="reps"></div>
          <div class="col-3 mb-2"><label class="form-label">kg</label><input type="number" step="0.5" class="form-control" name="weight_kg"></div>
          <div class="col-3 mb-2"><label class="form-label">Min</label><input type="number" class="form-control" name="duration_min"></div>
        </div>
        <div class="mb-2"><label class="form-label">Notes</label><input class="form-control" name="notes" placeholder="e.g. New personal best"></div>
        <button class="btn btn-ff w-100">Log completion</button>
      </form>
    </div>

    <div class="panel">
      <h5><?= $editExercise ? 'Edit exercise' : 'Exercise library' ?></h5>
      <?php if ($canManage): ?>
      <form method="post" class="mb-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="exercise_save">
        <?php if ($editExercise): ?><input type="hidden" name="id" value="<?= (int) $editExercise['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($editExercise['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Category</label><input class="form-control" name="category" value="<?= e($editExercise['category'] ?? 'Strength') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Muscle group</label><input class="form-control" name="muscle_group" value="<?= e($editExercise['muscle_group'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Equipment</label><input class="form-control" name="equipment" value="<?= e($editExercise['equipment'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Difficulty</label><select class="form-select" name="difficulty"><?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $d): ?><option <?= ($editExercise['difficulty'] ?? 'Beginner') === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-2"><label class="form-label">Video URL</label><input class="form-control" name="video_url" value="<?= e($editExercise['video_url'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label">Instructions</label><textarea class="form-control" name="description" rows="2"><?= e($editExercise['description'] ?? '') ?></textarea></div>
        <button class="btn btn-ff w-100"><?= $editExercise ? 'Save exercise' : 'Add exercise' ?></button>
        <?php if ($editExercise): ?><a class="btn btn-ff-outline w-100 mt-2" href="workouts.php">Cancel edit</a><?php endif; ?>
      </form>
      <?php endif; ?>
      <div style="max-height:300px;overflow:auto">
      <?php foreach ($exercises as $ex): ?>
        <div class="d-flex justify-content-between py-1 border-bottom" style="border-color:var(--ff-border)">
          <span class="small"><strong><?= e($ex['name']) ?></strong><br><span style="color:var(--ff-muted)"><?= e($ex['category']) ?> · <?= e($ex['muscle_group'] ?? '') ?> · <?= e($ex['difficulty']) ?></span></span>
          <?php if ($canManage): ?><span class="text-nowrap"><a class="btn btn-sm btn-outline-light" href="?edit_exercise=<?= (int) $ex['id'] ?>">Edit</a>
          <form method="post" class="d-inline" data-confirm="Delete this exercise from the library?"><?= csrf_field() ?><input type="hidden" name="action" value="exercise_delete"><input type="hidden" name="id" value="<?= (int) $ex['id'] ?>"><button class="btn btn-sm btn-outline-danger">×</button></form></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$exercises): ?><p class="small mb-0" style="color:var(--ff-muted)">Library is empty.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
