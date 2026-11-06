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
$dashTitle = 'Attendance';
$activeNav = 'attendance';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (($_POST['action'] ?? '') === 'my_attendance') {
        $res = TrainerAttendance::toggle($tid, $trainerId);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'You are now ' . ($res['action'] === 'checked_in' ? 'checked IN.' : 'checked OUT.') : $res['error']);
    }
    redirect(url('trainer/attendance.php'));
}

$myOpen = TrainerAttendance::openSession($tid, $trainerId);
$myHistory = TrainerAttendance::between($tid, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
$memberToday = Database::fetchAll(
    'SELECT a.*, m.name AS member_name FROM attendance a JOIN members m ON m.id = a.member_id
     WHERE a.tenant_id = ? AND m.trainer_id = ? AND DATE(a.check_in_at) = CURDATE()
     ORDER BY a.check_in_at DESC',
    [$tid, $trainerId]
);
$memberRecent = Database::fetchAll(
    'SELECT a.*, m.name AS member_name FROM attendance a JOIN members m ON m.id = a.member_id
     WHERE a.tenant_id = ? AND m.trainer_id = ? AND a.check_in_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     ORDER BY a.check_in_at DESC LIMIT 40',
    [$tid, $trainerId]
);
require __DIR__ . '/../views/trainer/header.php';
?>
<div class="row g-3">
  <div class="col-xl-5">
    <div class="panel mb-3">
      <h5>My attendance</h5>
      <p class="mb-2">Status: <?= $myOpen ? '<span class="status-pill st-active">On shift since ' . e(substr((string) $myOpen['check_in_at'], 11, 5)) . '</span>' : '<span class="status-pill st-inactive">Off shift</span>' ?></p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="my_attendance">
        <button class="btn <?= $myOpen ? 'btn-outline-warning' : 'btn-ff' ?> w-100"><?= $myOpen ? 'Check out of shift' : 'Check in for shift' ?></button></form>
      <p class="small mt-2 mb-0" style="color:var(--ff-muted)">Checking in twice is impossible — an open shift always checks out first.</p>
    </div>
    <div class="panel">
      <h5>My last 30 days</h5>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Date</th><th>In</th><th>Out</th><th>Hours</th></tr></thead>
        <tbody>
        <?php foreach ($myHistory as $r): $hrs = $r['check_out_at'] ? round((strtotime((string) $r['check_out_at']) - strtotime((string) $r['check_in_at'])) / 3600, 1) : null; ?>
          <tr><td><?= e(substr((string) $r['check_in_at'], 0, 10)) ?></td><td><?= e(substr((string) $r['check_in_at'], 11, 5)) ?></td>
            <td><?= $r['check_out_at'] ? e(substr((string) $r['check_out_at'], 11, 5)) : '—' ?></td><td><?= $hrs !== null ? $hrs . ' h' : 'open' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$myHistory): ?><tr><td colspan="4" style="color:var(--ff-muted)">No shifts recorded.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-xl-7">
    <div class="panel mb-3">
      <h5>My members — today</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Member</th><th>In</th><th>Out</th><th>Method</th><th>State</th></tr></thead>
        <tbody>
        <?php foreach ($memberToday as $r): ?>
          <tr><td class="fw-bold"><?= e($r['member_name']) ?></td><td><?= e(substr((string) $r['check_in_at'], 11, 5)) ?></td>
            <td><?= $r['check_out_at'] ? e(substr((string) $r['check_out_at'], 11, 5)) : '—' ?></td><td><?= e($r['method']) ?></td>
            <td><?= $r['check_out_at'] ? '<span class="status-pill st-inactive">Checked out</span>' : '<span class="status-pill st-active">In gym</span>' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$memberToday): ?><tr><td colspan="5" style="color:var(--ff-muted)">None of your members have checked in today yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
    <div class="panel">
      <h5>My members — last 7 days</h5>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Member</th><th>Check-in</th><th>Check-out</th><th>Method</th></tr></thead>
        <tbody>
        <?php foreach ($memberRecent as $r): ?>
          <tr><td class="fw-bold"><?= e($r['member_name']) ?></td><td><?= e(substr((string) $r['check_in_at'], 0, 16)) ?></td>
            <td><?= $r['check_out_at'] ? e(substr((string) $r['check_out_at'], 11, 5)) : '—' ?></td><td><?= e($r['method']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$memberRecent): ?><tr><td colspan="4" style="color:var(--ff-muted)">No member attendance in the last 7 days.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/trainer/footer.php'; ?>
