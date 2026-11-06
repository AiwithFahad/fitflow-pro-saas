<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Member'], Auth::homeFor(current_user()));
$tid = tenant_id();
$user = current_user();
$member = Database::fetch('SELECT * FROM members WHERE user_id = ? AND tenant_id = ?', [(int) $user['id'], $tid]);
if ($member === null) {
    flash('error', 'No member profile is linked to this account yet. Please ask your gym front desk.');
    redirect(url('public/logout.php'));
}
$memberId = (int) $member['id'];
$dashTitle = 'My Dashboard';
$activeNav = 'dashboard';

$membership = Database::fetch('SELECT * FROM memberships WHERE member_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1', [$memberId, $tid]);
$msStatus = $membership ? Membership::computedStatus($membership) : null;
$daysLeft = $membership ? Membership::daysLeft($membership) : null;
$msPct = 0;
if ($membership) {
    $total = (strtotime((string) $membership['end_date']) - strtotime((string) $membership['start_date'])) / 86400;
    $msPct = $total > 0 ? (int) max(0, min(100, round($daysLeft / $total * 100))) : 0;
}
$trainer = !empty($member['trainer_id']) ? Database::fetch('SELECT * FROM trainers WHERE id = ? AND tenant_id = ?', [(int) $member['trainer_id'], $tid]) : null;
$visitsMonth = (int) (Database::scalar('SELECT COUNT(*) FROM attendance WHERE member_id = ? AND tenant_id = ? AND check_in_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)', [$memberId, $tid]) ?? 0);
$visitsTotal = (int) (Database::scalar('SELECT COUNT(*) FROM attendance WHERE member_id = ? AND tenant_id = ?', [$memberId, $tid]) ?? 0);
$attendance = Attendance::forMember($tid, $memberId, 12);
$workouts = Workout::forMember($tid, $memberId);
$todayName = date('l');
$dietPlans = DietPlan::forMember($tid, $memberId);
$diet = $dietPlans[0] ?? null;
$dietMeals = $diet ? (json_decode((string) ($diet['meals'] ?? '{}'), true) ?: []) : [];
$goals = FitnessGoal::forMember($tid, $memberId);
$bmiInfo = ($member['height_cm'] && $member['weight_kg']) ? bmi((float) $member['weight_kg'], (float) $member['height_cm']) : null;
$payments = Database::fetchAll('SELECT * FROM payments WHERE tenant_id = ? AND member_id = ? ORDER BY id DESC LIMIT 5', [$tid, $memberId]);
$bookings = ClassBooking::forMember($tid, $memberId);
$logs = WorkoutLog::forMember($tid, $memberId, 8);
$pbs = WorkoutLog::personalBests($tid, $memberId);
$notifs = Database::fetchAll('SELECT * FROM notifications WHERE (tenant_id = ? OR user_id = ?) ORDER BY id DESC LIMIT 5', [$tid, (int) $user['id']]);
require __DIR__ . '/../views/member/header.php';
?>
<div class="row g-3 mb-1">
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-fire"></i></span><div class="k-label">Visits (30 days)</div><div class="k-value"><?= $visitsMonth ?></div><div class="small" style="color:var(--ff-muted)"><?= $visitsTotal ?> all time</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-activity"></i></span><div class="k-label">Training days this month</div><div class="k-value"><?= WorkoutLog::sessionsThisMonth($tid, $memberId) ?></div><div class="small" style="color:var(--ff-muted)"><?= count($logs) ?> recent exercise logs</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-speedometer2"></i></span><div class="k-label">BMI</div><div class="k-value"><?= $bmiInfo ? e($bmiInfo['value']) : '—' ?></div><div class="small" style="color:var(--ff-muted)"><?= $bmiInfo ? e($bmiInfo['category']) : 'Add height/weight at the front desk' ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-bullseye"></i></span><div class="k-label">Active goals</div><div class="k-value"><?= count(array_filter($goals, fn ($g) => $g['status'] === 'active')) ?></div><div class="small" style="color:var(--ff-muted)"><?= count($goals) ?> total</div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel mb-3">
      <div class="d-flex flex-wrap justify-content-between gap-2">
        <h5 class="mb-0"><i class="bi bi-card-checklist text-orange me-1"></i>My membership</h5>
        <?php if ($membership): ?><span class="status-pill st-<?= e($msStatus) ?>"><?= e($msStatus) ?></span><?php endif; ?>
      </div>
      <?php if ($membership): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-end mt-2">
          <div>
            <div class="fs-4 fw-bold"><?= e($membership['plan_name']) ?></div>
            <div style="color:var(--ff-muted)"><?= e($membership['start_date']) ?> → <?= e($membership['end_date']) ?> · <?= money($membership['price']) ?></div>
          </div>
          <div class="text-end">
            <div class="fs-2 fw-bold <?= $daysLeft !== null && $daysLeft <= 7 ? 'text-warning' : '' ?>"><?= $daysLeft !== null && $daysLeft >= 0 ? $daysLeft : 0 ?></div>
            <div class="small" style="color:var(--ff-muted)">days left</div>
          </div>
        </div>
        <div class="progress mt-2" style="height:10px;background:#26262b"><div class="progress-bar" style="width:<?= $msPct ?>%;background:var(--ff-orange)"></div></div>
        <?php if ($daysLeft !== null && $daysLeft <= 7 && $daysLeft >= 0): ?><div class="alert alert-warning mt-3 mb-0">Your membership ends in <?= $daysLeft ?> day(s). Renew at the front desk to keep training without a break.</div><?php endif; ?>
        <?php if ($msStatus === 'expired'): ?><div class="alert alert-danger mt-3 mb-0">Your membership has expired. Please renew at the front desk.</div><?php endif; ?>
        <?php if ($msStatus === 'suspended'): ?><div class="alert alert-dark mt-3 mb-0">Your membership is currently suspended. Please contact the gym.</div><?php endif; ?>
      <?php else: ?>
        <p class="mb-0 mt-2" style="color:var(--ff-muted)">No membership on file yet — visit the front desk to enrol.</p>
      <?php endif; ?>
      <div class="small mt-2" style="color:var(--ff-muted)">Member ID <strong class="text-white"><?= e($member['member_code'] ?? '') ?></strong> · QR token <strong class="text-white"><?= e($member['qr_token']) ?></strong> — show it at the front-desk scanner.</div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="panel h-100">
          <h5>Today's workout — <?= e($todayName) ?></h5>
          <?php $todayExercises = []; foreach ($workouts as $w) { foreach ((json_decode((string) ($w['exercises'] ?? '[]'), true) ?: []) as $ex) { if (is_array($ex) && ($ex['day'] ?? '') === $todayName) { $todayExercises[] = ['plan' => $w['name']] + $ex; } } } ?>
          <?php foreach ($todayExercises as $ex): ?>
            <div class="d-flex justify-content-between py-1 border-bottom" style="border-color:var(--ff-border)">
              <span><strong><?= e($ex['name'] ?? '') ?></strong><br><small style="color:var(--ff-muted)"><?= e($ex['plan']) ?></small></span>
              <span class="small"><?= e($ex['sets'] ?? '—') ?> × <?= e($ex['reps'] ?? '—') ?><?= isset($ex['weight_kg']) && $ex['weight_kg'] !== null ? ' · ' . e($ex['weight_kg']) . ' kg' : '' ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (!$todayExercises): ?><p class="small mb-0" style="color:var(--ff-muted)">No exercises scheduled for today. <a href="<?= e(url('member/workouts.php')) ?>">See my full programs</a>.</p><?php endif; ?>
          <?php if ($pbs): ?><h6 class="mt-3">Personal bests</h6><div class="d-flex flex-wrap gap-1"><?php foreach (array_slice($pbs, 0, 6) as $pb): ?><span class="status-pill st-info"><?= e($pb['exercise_name']) ?>: <?= e($pb['best_kg']) ?> kg</span><?php endforeach; ?></div><?php endif; ?>
        </div>
      </div>
      <div class="col-md-6">
        <div class="panel h-100">
          <h5>My diet today</h5>
          <?php if ($diet): ?>
            <div class="small mb-2" style="color:var(--ff-muted)"><?= e($diet['name']) ?> · <?= e($diet['goal']) ?><?= $diet['calories'] ? ' · ' . (int) $diet['calories'] . ' kcal' : '' ?><?= $diet['protein_g'] ? ' · P ' . (int) $diet['protein_g'] . 'g' : '' ?><?= $diet['carbs_g'] ? ' · C ' . (int) $diet['carbs_g'] . 'g' : '' ?><?= $diet['fats_g'] ? ' · F ' . (int) $diet['fats_g'] . 'g' : '' ?></div>
            <?php foreach ($dietMeals as $mealName => $meal): ?>
              <div class="py-1 border-bottom" style="border-color:var(--ff-border)"><strong><?= e((string) $mealName) ?></strong> <small style="color:var(--ff-muted)"><?= e($meal['time'] ?? '') ?></small><br><span class="small"><?= e($meal['items'] ?? (is_string($meal) ? $meal : '')) ?></span></div>
            <?php endforeach; ?>
            <?php if (!$dietMeals): ?><p class="small mb-0" style="color:var(--ff-muted)">Your plan has no meals listed yet.</p><?php endif; ?>
          <?php else: ?><p class="small mb-0" style="color:var(--ff-muted)">No diet plan assigned yet — your trainer can create one for you.</p><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="panel mt-3">
      <h5>My goals</h5>
      <?php foreach ($goals as $g): ?>
        <div class="py-2 border-bottom" style="border-color:var(--ff-border)">
          <div class="d-flex justify-content-between"><strong><?= e($g['title']) ?></strong><span class="status-pill st-<?= e($g['status']) ?>"><?= e($g['status']) ?></span></div>
          <div class="progress my-1" style="height:8px;background:#26262b"><div class="progress-bar" style="width:<?= (int) $g['progress_percent'] ?>%;background:var(--ff-orange)"></div></div>
          <small style="color:var(--ff-muted)"><?= e($g['current_value']) ?> / <?= e($g['target_value']) ?> <?= e($g['metric']) ?> (<?= (int) $g['progress_percent'] ?>%)</small>
        </div>
      <?php endforeach; ?>
      <?php if (!$goals): ?><p class="small mb-0" style="color:var(--ff-muted)">No goals yet — set one under BMI &amp; Goals.</p><?php endif; ?>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="panel mb-3">
      <h5>My trainer</h5>
      <?php if ($trainer): ?>
        <div class="fw-bold fs-5"><?= e($trainer['name']) ?></div>
        <div style="color:var(--ff-muted)"><?= e($trainer['specialty'] ?? '') ?></div>
        <?php if (!empty($trainer['qualifications'])): ?><div class="small mt-1"><i class="bi bi-award text-orange me-1"></i><?= e($trainer['qualifications']) ?></div><?php endif; ?>
        <div class="small mt-1"><?= e($trainer['email'] ?? '') ?><?= $trainer['phone'] ? ' · ' . e($trainer['phone']) : '' ?></div>
      <?php else: ?><p class="small mb-0" style="color:var(--ff-muted)">No trainer assigned yet.</p><?php endif; ?>
    </div>

    <div class="panel mb-3">
      <h5>Upcoming class bookings</h5>
      <?php foreach (array_slice($bookings, 0, 5) as $b): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)">
          <span><strong><?= e($b['class_name'] ?? 'Class') ?></strong><br><small style="color:var(--ff-muted)"><?= e($b['day_of_week'] ?? '') ?></small></span>
          <span class="small"><?= e(isset($b['start_time']) ? substr((string) $b['start_time'], 0, 5) : '') ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$bookings): ?><p class="small mb-0" style="color:var(--ff-muted)">No class bookings yet — ask the front desk to book you in.</p><?php endif; ?>
    </div>

    <div class="panel mb-3">
      <h5>Attendance history</h5>
      <?php foreach ($attendance as $a): ?>
        <div class="d-flex justify-content-between py-1 small border-bottom" style="border-color:var(--ff-border)">
          <span><?= e(substr((string) $a['check_in_at'], 0, 16)) ?></span>
          <span style="color:var(--ff-muted)"><?= $a['check_out_at'] ? 'out ' . e(substr((string) $a['check_out_at'], 11, 5)) : 'in gym / no out' ?> · <?= e($a['method']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$attendance): ?><p class="small mb-0" style="color:var(--ff-muted)">No visits recorded yet.</p><?php endif; ?>
      <a class="btn btn-ff-outline btn-sm w-100 mt-2" href="<?= e(url('member/attendance.php')) ?>">Full attendance</a>
    </div>

    <div class="panel mb-3">
      <h5>Recent payments</h5>
      <?php foreach ($payments as $p): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)">
          <span class="small"><?= e($p['description'] ?? $p['purpose']) ?><br><span style="color:var(--ff-muted)"><?= e(substr((string) $p['created_at'], 0, 10)) ?></span></span>
          <span class="text-end"><strong><?= money($p['amount']) ?></strong><br><span class="status-pill st-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$payments): ?><p class="small mb-0" style="color:var(--ff-muted)">No payments recorded yet.</p><?php endif; ?>
      <a class="btn btn-ff-outline btn-sm w-100 mt-2" href="<?= e(url('member/payments.php')) ?>">All payments & receipts</a>
    </div>

    <div class="panel">
      <h5>Notifications</h5>
      <?php foreach ($notifs as $n): ?>
        <div class="py-2 border-bottom" style="border-color:var(--ff-border)"><strong class="small"><?= e($n['title']) ?></strong><br><span class="small" style="color:var(--ff-muted)"><?= e($n['body'] ?? '') ?></span></div>
      <?php endforeach; ?>
      <?php if (!$notifs): ?><p class="small mb-0" style="color:var(--ff-muted)">No notifications.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/member/footer.php'; ?>
