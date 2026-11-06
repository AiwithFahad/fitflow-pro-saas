<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Member'], Auth::homeFor(current_user()));
$tid = tenant_id();
$user = current_user();
$member = Database::fetch('SELECT * FROM members WHERE user_id = ? AND tenant_id = ?', [(int) $user['id'], $tid]);
if ($member === null) {
    redirect(url('public/logout.php'));
}
$dashTitle = 'BMI & Fitness Goals';
$activeNav = 'goals';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'weighin') {
        $weight = (float) ($_POST['weight_kg'] ?? 0);
        if ($weight > 0) {
            $height = (float) ($_POST['height_cm'] ?? 0);
            if ($height > 0) {
                Database::update('members', ['height_cm' => $height], 'id = :wid AND tenant_id = :wtid', ['wid' => $member['id'], 'wtid' => $tid]);
                $member['height_cm'] = $height;
            }
            Member::addProgress($tid, (int) $member['id'], $weight, ($_POST['body_fat'] ?? '') === '' ? null : (float) $_POST['body_fat'], 'Self weigh-in from portal');
            flash('success', 'Weigh-in saved. Your BMI and progress chart are updated.');
        }
    } elseif ($action === 'goal_add') {
        FitnessGoal::create([
            'tenant_id' => $tid, 'member_id' => (int) $member['id'],
            'title' => trim($_POST['title'] ?? ''), 'metric' => trim($_POST['metric'] ?? 'weight_kg'),
            'start_value' => (float) ($_POST['start_value'] ?? 0), 'target_value' => (float) ($_POST['target_value'] ?? 0),
            'current_value' => (float) ($_POST['start_value'] ?? 0),
            'target_date' => ($_POST['target_date'] ?? '') ?: null, 'status' => 'active',
        ]);
        flash('success', 'Goal created. Let\'s get to work.');
    } elseif ($action === 'goal_update') {
        $goal = FitnessGoal::find((int) ($_POST['goal_id'] ?? 0), $tid);
        if ($goal !== null && (int) $goal['member_id'] === (int) $member['id']) {
            $current = (float) ($_POST['current_value'] ?? $goal['current_value']);
            $status = ($goal['target_value'] !== null && abs($current - (float) $goal['target_value']) < 0.001) ? 'achieved' : $goal['status'];
            FitnessGoal::updateById((int) $goal['id'], ['current_value' => $current, 'status' => $status], $tid);
            flash('success', $status === 'achieved' ? 'Goal achieved — congratulations!' : 'Progress updated.');
        }
    } elseif ($action === 'goal_delete') {
        $goal = FitnessGoal::find((int) ($_POST['goal_id'] ?? 0), $tid);
        if ($goal !== null && (int) $goal['member_id'] === (int) $member['id']) {
            FitnessGoal::deleteById((int) $goal['id'], $tid);
            flash('success', 'Goal removed.');
        }
    }
    redirect(url('member/goals.php'));
}

$member = Member::find((int) $member['id'], $tid) ?? $member;
$goals = FitnessGoal::forMember($tid, (int) $member['id']);
$progress = Database::fetchAll('SELECT * FROM progress_records WHERE member_id = ? AND tenant_id = ? ORDER BY recorded_at ASC LIMIT 24', [$member['id'], $tid]);
$bmiInfo = ($member['height_cm'] && $member['weight_kg']) ? bmi((float) $member['weight_kg'], (float) $member['height_cm']) : null;
$extraScripts = '<script>
const pc = document.getElementById("progressChart");
if (pc) { new Chart(pc, { type: "line", data: { labels: ' . json_encode(array_map(fn($r) => substr((string) $r['recorded_at'], 0, 10), $progress)) . ', datasets: [{ label: "Weight (kg)", data: ' . json_encode(array_map(fn($r) => $r['weight_kg'] === null ? null : (float) $r['weight_kg'], $progress)) . ', borderColor: "#f97316", backgroundColor: "rgba(249,115,22,.2)", fill: true, tension: .35 }] }, options: { plugins: { legend: { labels: { color: "#e4e4e7" } } }, scales: { x: { ticks: { color: "#a1a1aa" } }, y: { ticks: { color: "#a1a1aa" } } } } }); }
</script>';
require __DIR__ . '/../views/member/header.php';
?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="panel mb-3">
      <h5>BMI calculator &amp; weigh-in</h5>
      <?php if ($bmiInfo): ?><p>Current: <strong class="fs-4 text-orange"><?= e($bmiInfo['value']) ?></strong> — <?= e($bmiInfo['category']) ?> <small style="color:var(--ff-muted)">(<?= e($member['weight_kg']) ?> kg · <?= e($member['height_cm']) ?> cm)</small></p><?php endif; ?>
      <div class="row g-2">
        <div class="col-6"><label class="form-label">Weight (kg)</label><input type="number" step="0.1" id="bmiWeight" class="form-control" value="<?= e($member['weight_kg'] ?? '') ?>"></div>
        <div class="col-6"><label class="form-label">Height (cm)</label><input type="number" step="0.1" id="bmiHeight" class="form-control" value="<?= e($member['height_cm'] ?? '') ?>"></div>
      </div>
      <button type="button" class="btn btn-ff-outline mt-3" onclick="ffCalculateBMI('bmiWeight','bmiHeight','bmiResult')">Calculate BMI</button>
      <div id="bmiResult" class="mt-2"></div>
      <hr style="border-color:var(--ff-border)">
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="weighin">
        <div class="row g-2">
          <div class="col-4"><label class="form-label">Weight kg</label><input type="number" step="0.1" class="form-control" name="weight_kg" required></div>
          <div class="col-4"><label class="form-label">Height cm</label><input type="number" step="0.1" class="form-control" name="height_cm" value="<?= e($member['height_cm'] ?? '') ?>"></div>
          <div class="col-4"><label class="form-label">Body fat %</label><input type="number" step="0.1" class="form-control" name="body_fat"></div>
        </div>
        <button class="btn btn-ff mt-3 w-100">Save weigh-in</button>
      </form>
    </div>
    <div class="panel">
      <h5>Weight progress</h5>
      <?php if (count($progress) >= 2): ?><canvas id="progressChart" class="chart"></canvas>
      <?php else: ?><p class="mb-0" style="color:var(--ff-muted)">Log at least two weigh-ins to see your trend line.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="panel mb-3">
      <h5>My goals</h5>
      <?php foreach ($goals as $g): ?>
      <div class="mb-3 p-3 rounded" style="background:#101012">
        <div class="d-flex justify-content-between"><strong><?= e($g['title']) ?></strong>
          <span class="status-pill st-<?= e($g['status']) ?>"><?= e($g['status']) ?></span></div>
        <div class="progress mt-2"><div class="progress-bar" style="width:<?= (int) $g['progress_percent'] ?>%"></div></div>
        <small style="color:var(--ff-muted)"><?= e($g['start_value']) ?> → <?= e($g['current_value']) ?> / <?= e($g['target_value']) ?> <?= e($g['metric']) ?> (<?= (int) $g['progress_percent'] ?>%)<?= $g['target_date'] ? ' · target ' . e($g['target_date']) : '' ?></small>
        <form method="post" class="d-flex gap-2 mt-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="goal_update"><input type="hidden" name="goal_id" value="<?= (int) $g['id'] ?>">
          <input type="number" step="0.01" class="form-control form-control-sm" name="current_value" value="<?= e($g['current_value']) ?>" style="max-width:140px">
          <button class="btn btn-sm btn-ff">Update progress</button>
        </form>
        <form method="post" class="mt-1" onsubmit="return confirm('Remove this goal?')"><?= csrf_field() ?><input type="hidden" name="action" value="goal_delete"><input type="hidden" name="goal_id" value="<?= (int) $g['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0">Remove goal</button></form>
      </div>
      <?php endforeach; ?>
      <?php if (!$goals): ?><p class="mb-0" style="color:var(--ff-muted)">No goals yet — create your first one below.</p><?php endif; ?>
    </div>
    <div class="panel">
      <h5>Create a goal</h5>
      <form method="post" class="row g-2">
        <?= csrf_field() ?><input type="hidden" name="action" value="goal_add">
        <div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" required placeholder="e.g. Squat 140 kg"></div>
        <div class="col-md-6"><label class="form-label">Metric</label><select class="form-select" name="metric"><option value="weight_kg">Bodyweight (kg)</option><option value="squat_kg">Squat (kg)</option><option value="bench_kg">Bench press (kg)</option><option value="deadlift_kg">Deadlift (kg)</option><option value="body_fat_percent">Body fat (%)</option><option value="run_5k_min">5K run (minutes)</option></select></div>
        <div class="col-md-4"><label class="form-label">Start value</label><input type="number" step="0.01" class="form-control" name="start_value" required></div>
        <div class="col-md-4"><label class="form-label">Target value</label><input type="number" step="0.01" class="form-control" name="target_value" required></div>
        <div class="col-md-4"><label class="form-label">Target date</label><input type="date" class="form-control" name="target_date"></div>
        <div class="col-12"><button class="btn btn-ff">Create goal</button></div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/member/footer.php'; ?>
