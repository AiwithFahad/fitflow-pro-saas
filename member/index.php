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
$dashTitle = 'My Overview';
$activeNav = 'home';

$membership = Database::fetch('SELECT * FROM memberships WHERE member_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1', [$member['id'], $tid]);
$daysLeft = $membership ? (int) floor((strtotime($membership['end_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
$visitsMonth = (int) Database::scalar('SELECT COUNT(*) FROM attendance WHERE member_id = ? AND check_in_at > (NOW() - INTERVAL 30 DAY)', [$member['id']]);
$visitsTotal = (int) Database::scalar('SELECT COUNT(*) FROM attendance WHERE member_id = ?', [$member['id']]);
$goals = FitnessGoal::forMember($tid, (int) $member['id']);
$workoutCount = Workout::count($tid, ' AND member_id = ?', [$member['id']]);
$bmiInfo = ($member['height_cm'] && $member['weight_kg']) ? bmi((float) $member['weight_kg'], (float) $member['height_cm']) : null;
require __DIR__ . '/../views/member/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel mb-3">
      <h5>Welcome back, <?= e($member['name']) ?> <i class="bi bi-fire text-orange"></i></h5>
      <?php if ($membership): ?>
        <p class="mb-1">Membership: <strong><?= e($membership['plan_name']) ?></strong> ·
          <span class="status-pill st-<?= e($membership['status']) ?>"><?= e($membership['status']) ?></span></p>
        <p class="mb-0" style="color:var(--ff-muted)">Valid <?= e($membership['start_date']) ?> → <?= e($membership['end_date']) ?>
        <?php if ($daysLeft !== null): ?> · <strong class="<?= $daysLeft <= 7 ? 'text-warning' : 'text-white' ?>"><?= $daysLeft ?> days left</strong><?php endif; ?></p>
        <?php if ($daysLeft !== null && $daysLeft <= 7): ?><div class="alert alert-warning mt-3 mb-0">Your membership expires soon. <a href="<?= e(url('member/membership.php')) ?>" class="alert-link">Renew now</a> to keep training without a break.</div><?php endif; ?>
      <?php else: ?>
        <p class="mb-0" style="color:var(--ff-muted)">No membership on file yet — visit the front desk to enrol.</p>
      <?php endif; ?>
    </div>
    <div class="row g-3">
      <div class="col-6 col-md-3"><div class="kpi"><div class="k-label">Visits (30d)</div><div class="k-value"><?= $visitsMonth ?></div></div></div>
      <div class="col-6 col-md-3"><div class="kpi"><div class="k-label">Total visits</div><div class="k-value"><?= $visitsTotal ?></div></div></div>
      <div class="col-6 col-md-3"><div class="kpi"><div class="k-label">BMI</div><div class="k-value"><?= $bmiInfo ? e($bmiInfo['value']) : '—' ?></div><small style="color:var(--ff-muted)"><?= $bmiInfo ? e($bmiInfo['category']) : 'Add height/weight' ?></small></div></div>
      <div class="col-6 col-md-3"><div class="kpi"><div class="k-label">Programs</div><div class="k-value"><?= $workoutCount ?></div><small style="color:var(--ff-muted)">Assigned workouts</small></div></div>
    </div>
    <div class="panel mt-3">
      <h5>My goals</h5>
      <?php if (!$goals): ?><p class="mb-0" style="color:var(--ff-muted)">No goals yet. <a href="<?= e(url('member/goals.php')) ?>">Set your first goal</a>.</p><?php endif; ?>
      <?php foreach (array_slice($goals, 0, 3) as $g): ?>
      <div class="mb-3"><div class="d-flex justify-content-between small fw-bold"><span><?= e($g['title']) ?></span><span class="text-orange"><?= (int) $g['progress_percent'] ?>%</span></div>
        <div class="progress mt-1"><div class="progress-bar" style="width:<?= (int) $g['progress_percent'] ?>%"></div></div>
        <small style="color:var(--ff-muted)"><?= e($g['current_value']) ?> / <?= e($g['target_value']) ?> <?= e($g['metric']) ?><?= $g['target_date'] ? ' · target ' . e($g['target_date']) : '' ?></small></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="qr-box">
      <h6 class="fw-bold text-dark">YOUR CHECK-IN TOKEN</h6>
      <i class="bi bi-qr-code" style="font-size:4.5rem;color:#111"></i>
      <div class="qr-token"><?= e($member['qr_token']) ?></div>
      <small class="text-muted">Show this at the front-desk scanner.<br>First scan = check in, second scan = check out.</small>
    </div>
    <div class="panel mt-3">
      <h5>Quick links</h5>
      <div class="d-grid gap-2">
        <a class="btn btn-ff" href="<?= e(url('member/workouts.php')) ?>"><i class="bi bi-activity me-1"></i>Today's workout</a>
        <a class="btn btn-ff-outline" href="<?= e(url('member/diet.php')) ?>"><i class="bi bi-egg-fried me-1"></i>My diet plan</a>
        <a class="btn btn-ff-outline" href="<?= e(url('member/membership.php')) ?>"><i class="bi bi-arrow-repeat me-1"></i>Renew membership</a>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/member/footer.php'; ?>
