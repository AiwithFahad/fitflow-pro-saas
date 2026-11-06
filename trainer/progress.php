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
$dashTitle = 'Member Progress';
$activeNav = 'progress';
$assigned = Database::fetchAll('SELECT * FROM members WHERE tenant_id = ? AND trainer_id = ? ORDER BY name', [$tid, $trainerId]);
$assignedIds = array_map('intval', array_column($assigned, 'id'));

$member = null;
$memberId = (int) ($_GET['member'] ?? ($_POST['member_id'] ?? 0));
if ($memberId > 0 && in_array($memberId, $assignedIds, true)) {
    $member = Member::find($memberId, $tid);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($member !== null && $action === 'log_progress') {
        $weight = (float) ($_POST['weight_kg'] ?? 0);
        if ($weight > 0) {
            Member::addProgress($tid, $memberId, $weight, isset($_POST['body_fat_percent']) && $_POST['body_fat_percent'] !== '' ? (float) $_POST['body_fat_percent'] : null, trim($_POST['notes'] ?? ''));
            flash('success', 'Progress recorded; member BMI refreshed.');
        } else {
            flash('error', 'Enter a valid weight.');
        }
    } elseif ($member !== null && $action === 'goal_create') {
        FitnessGoal::create([
            'tenant_id' => $tid, 'member_id' => $memberId,
            'title' => trim($_POST['title'] ?? ''),
            'metric' => trim($_POST['metric'] ?? 'weight_kg') ?: 'weight_kg',
            'start_value' => (float) ($_POST['start_value'] ?? 0),
            'target_value' => (float) ($_POST['target_value'] ?? 0),
            'current_value' => (float) ($_POST['start_value'] ?? 0),
            'target_date' => ($_POST['target_date'] ?? '') ?: null,
            'status' => 'active',
        ]);
        flash('success', 'Goal set for ' . $member['name'] . '.');
    } elseif ($member !== null && $action === 'goal_update' && !empty($_POST['goal_id'])) {
        $goal = FitnessGoal::find((int) $_POST['goal_id'], $tid);
        if ($goal && (int) $goal['member_id'] === $memberId) {
            $current = (float) ($_POST['current_value'] ?? $goal['current_value']);
            $status = ($goal['target_value'] !== null && abs($current - (float) $goal['target_value']) < 0.001) ? 'achieved' : (string) ($_POST['status'] ?? $goal['status']);
            FitnessGoal::updateById((int) $goal['id'], ['current_value' => $current, 'status' => $status], $tid);
            flash('success', 'Goal updated.');
        }
    } elseif ($member !== null && $action === 'goal_delete' && !empty($_POST['goal_id'])) {
        $goal = FitnessGoal::find((int) $_POST['goal_id'], $tid);
        if ($goal && (int) $goal['member_id'] === $memberId) {
            FitnessGoal::deleteById((int) $goal['id'], $tid);
            flash('success', 'Goal removed.');
        }
    }
    redirect(url('trainer/progress.php') . '?member=' . $memberId);
}

$progress = $member ? Database::fetchAll('SELECT * FROM progress_records WHERE tenant_id = ? AND member_id = ? ORDER BY recorded_at ASC LIMIT 60', [$tid, $memberId]) : [];
$goals = $member ? FitnessGoal::forMember($tid, $memberId) : [];
$logs = $member ? WorkoutLog::forMember($tid, $memberId, 20) : [];
$pbs = $member ? WorkoutLog::personalBests($tid, $memberId) : [];
$extraScripts = '';
if ($member && $progress) {
    $extraScripts = '<script>
(function(){
  if (!window.Chart) return;
  var rows = ' . json_encode($progress) . ';
  new Chart(document.getElementById("weightChart"), {type:"line", data:{labels: rows.map(function(r){ return (r.recorded_at || "").substring(0,10); }), datasets:[
    {label:"Weight (kg)", data: rows.map(function(r){ return parseFloat(r.weight_kg); }), borderColor:"#f97316", backgroundColor:"rgba(249,115,22,.18)", fill:true, tension:.35},
    {label:"BMI", data: rows.map(function(r){ return r.bmi ? parseFloat(r.bmi) : null; }), borderColor:"#38bdf8", tension:.35}
  ]}, options:{scales:{y:{beginAtZero:false}}}});
})();
</script>';
}
require __DIR__ . '/../views/trainer/header.php';
?>
<div class="panel mb-3">
  <form method="get" class="d-flex gap-2 align-items-center">
    <label class="form-label mb-0">Member</label>
    <select class="form-select" name="member" style="max-width:320px" onchange="this.form.submit()">
      <option value="">Choose one of your members…</option>
      <?php foreach ($assigned as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $member && (int) $member['id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-ff">Open</button></noscript>
  </form>
</div>

<?php if ($member): ?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel mb-3">
      <h5><?= e($member['name']) ?> — weight & BMI trend</h5>
      <?php if ($progress): ?><canvas id="weightChart" class="chart"></canvas>
      <?php else: ?><p class="small mb-0" style="color:var(--ff-muted)">No progress records yet — log the first measurement on the right.</p><?php endif; ?>
    </div>
    <div class="panel mb-3">
      <h5>Fitness goals</h5>
      <?php foreach ($goals as $g): ?>
        <div class="py-2 border-bottom" style="border-color:var(--ff-border)">
          <div class="d-flex justify-content-between"><strong><?= e($g['title']) ?></strong><span class="status-pill st-<?= e($g['status']) ?>"><?= e($g['status']) ?></span></div>
          <div class="progress my-1" style="height:8px;background:#26262b"><div class="progress-bar" style="width:<?= (int) $g['progress_percent'] ?>%;background:var(--ff-orange)"></div></div>
          <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <small style="color:var(--ff-muted)"><?= e($g['start_value']) ?> → <?= e($g['current_value']) ?> / <?= e($g['target_value']) ?> <?= e($g['metric']) ?> (<?= (int) $g['progress_percent'] ?>%)<?= $g['target_date'] ? ' · target ' . e($g['target_date']) : '' ?></small>
            <form method="post" class="d-inline-flex gap-1 align-items-center">
              <?= csrf_field() ?><input type="hidden" name="action" value="goal_update"><input type="hidden" name="goal_id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="member_id" value="<?= $memberId ?>">
              <input type="number" step="0.01" class="form-control form-control-sm" name="current_value" value="<?= e($g['current_value']) ?>" style="width:110px">
              <button class="btn btn-sm btn-ff-outline">Update</button>
            </form>
            <form method="post" class="d-inline" data-confirm="Remove this goal?"><?= csrf_field() ?><input type="hidden" name="action" value="goal_delete"><input type="hidden" name="goal_id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="member_id" value="<?= $memberId ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$goals): ?><p class="small mb-0" style="color:var(--ff-muted)">No goals set yet.</p><?php endif; ?>
    </div>
    <div class="panel">
      <h5>Personal bests & recent training</h5>
      <?php if ($pbs): ?>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($pbs as $pb): ?><span class="status-pill st-info"><?= e($pb['exercise_name']) ?>: <?= e($pb['best_kg']) ?> kg × <?= (int) $pb['sessions'] ?></span><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>When</th><th>Exercise</th><th>Sets × Reps</th><th>Weight</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr><td class="small"><?= e(substr((string) $l['completed_at'], 0, 16)) ?></td><td class="fw-bold"><?= e($l['exercise_name']) ?></td>
            <td><?= e($l['sets'] ?? '—') ?> × <?= e($l['reps'] ?? '—') ?></td><td><?= $l['weight_kg'] !== null ? e($l['weight_kg']) . ' kg' : '—' ?></td><td class="small"><?= e($l['notes'] ?? '') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="5" style="color:var(--ff-muted)">No training logged yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="panel mb-3">
      <h5>Member snapshot</h5>
      <table class="table table-sm mb-0">
        <tr><th>Member ID</th><td><?= e($member['member_code'] ?? '') ?></td></tr>
        <tr><th>Height / Weight</th><td><?= e($member['height_cm'] ?? '—') ?> cm · <?= e($member['weight_kg'] ?? '—') ?> kg</td></tr>
        <tr><th>BMI</th><td><?= e($member['bmi'] ?? '—') ?></td></tr>
        <tr><th>Training days (this month)</th><td><?= WorkoutLog::sessionsThisMonth($tid, $memberId) ?></td></tr>
      </table>
    </div>
    <div class="panel mb-3">
      <h5>Log measurement</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="log_progress"><input type="hidden" name="member_id" value="<?= $memberId ?>">
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Weight (kg)</label><input type="number" step="0.1" class="form-control" name="weight_kg" required></div>
          <div class="col-6 mb-2"><label class="form-label">Body fat %</label><input type="number" step="0.1" class="form-control" name="body_fat_percent"></div>
        </div>
        <div class="mb-2"><label class="form-label">Notes</label><input class="form-control" name="notes"></div>
        <button class="btn btn-ff w-100">Log progress</button>
      </form>
    </div>
    <div class="panel">
      <h5>Set a new goal</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="goal_create"><input type="hidden" name="member_id" value="<?= $memberId ?>">
        <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required placeholder="e.g. Cut to 80 kg"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Metric</label><input class="form-control" name="metric" value="weight_kg"></div>
          <div class="col-6 mb-2"><label class="form-label">Target date</label><input type="date" class="form-control" name="target_date"></div>
          <div class="col-6 mb-2"><label class="form-label">Start value</label><input type="number" step="0.01" class="form-control" name="start_value" required></div>
          <div class="col-6 mb-2"><label class="form-label">Target value</label><input type="number" step="0.01" class="form-control" name="target_value" required></div>
        </div>
        <button class="btn btn-ff w-100">Set goal</button>
      </form>
    </div>
  </div>
</div>
<?php else: ?>
<div class="panel">Pick one of your assigned members to see progress charts, goals, personal bests and training history.</div>
<?php endif; ?>
<?php require __DIR__ . '/../views/trainer/footer.php'; ?>
