<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$user = current_user();
$trainer = Database::fetch('SELECT * FROM trainers WHERE user_id = ? AND tenant_id = ?', [(int) $user['id'], $tid]);
if ($trainer === null) {
    flash('error', 'No trainer profile is linked to this account yet. Please ask your gym admin to link it under Staff & Salaries.');
    redirect(url('public/logout.php'));
}
$trainerId = (int) $trainer['id'];
$dashTitle = 'Trainer Dashboard';
$activeNav = 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (($_POST['action'] ?? '') === 'my_attendance') {
        $res = TrainerAttendance::toggle($tid, $trainerId);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'You are now ' . ($res['action'] === 'checked_in' ? 'checked IN for your shift.' : 'checked OUT.') : $res['error']);
    }
    redirect(url('trainer/dashboard.php'));
}

$assignedCount = (int) (Database::scalar('SELECT COUNT(*) FROM members WHERE tenant_id = ? AND trainer_id = ?', [$tid, $trainerId]) ?? 0);
$checkinsToday = (int) (Database::scalar(
    'SELECT COUNT(DISTINCT a.member_id) FROM attendance a JOIN members m ON m.id = a.member_id
     WHERE a.tenant_id = ? AND m.trainer_id = ? AND DATE(a.check_in_at) = CURDATE()',
    [$tid, $trainerId]
) ?? 0);
$myOpen = TrainerAttendance::openSession($tid, $trainerId);
$todayName = date('l');
$classesToday = Database::fetchAll(
    'SELECT c.*, b.name AS branch_name, (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = c.id AND cb.status = "booked") AS booked_count
     FROM classes c LEFT JOIN branches b ON b.id = c.branch_id
     WHERE c.tenant_id = ? AND c.trainer_id = ? AND c.day_of_week = ? AND c.status = "active" ORDER BY c.start_time',
    [$tid, $trainerId, $todayName]
);
$weekClasses = Database::fetchAll(
    'SELECT c.*, (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = c.id AND cb.status = "booked") AS booked_count
     FROM classes c WHERE c.tenant_id = ? AND c.trainer_id = ? AND c.status = "active"
     ORDER BY FIELD(c.day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), c.start_time',
    [$tid, $trainerId]
);
$expiring = Database::fetchAll(
    'SELECT m.name, ms.plan_name, ms.end_date FROM memberships ms JOIN members m ON m.id = ms.member_id
     WHERE ms.tenant_id = ? AND m.trainer_id = ? AND ms.status = "active"
       AND ms.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) ORDER BY ms.end_date',
    [$tid, $trainerId]
);
$recentAssigned = Database::fetchAll(
    'SELECT m.*, ms.plan_name AS membership_plan, ms.end_date AS membership_end, ms.status AS membership_status
     FROM members m
     LEFT JOIN memberships ms ON ms.id = (SELECT id FROM memberships WHERE member_id = m.id ORDER BY id DESC LIMIT 1)
     WHERE m.tenant_id = ? AND m.trainer_id = ? ORDER BY m.id DESC LIMIT 6',
    [$tid, $trainerId]
);
require __DIR__ . '/../views/trainer/header.php';
?>
<div class="row g-3 mb-1">
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-people"></i></span><div class="k-label">Assigned members</div><div class="k-value"><?= $assignedCount ?></div><div class="small" style="color:var(--ff-muted)"><?= e($trainer['specialty'] ?? '') ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-calendar-check"></i></span><div class="k-label">My members checked in today</div><div class="k-value"><?= $checkinsToday ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-collection"></i></span><div class="k-label">Classes this week</div><div class="k-value"><?= count($weekClasses) ?></div><div class="small" style="color:var(--ff-muted)"><?= count($classesToday) ?> today (<?= e($todayName) ?>)</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-stopwatch"></i></span><div class="k-label">My shift</div>
    <div class="k-value fs-5 pt-1"><?= $myOpen ? '<span class="status-pill st-active">On shift since ' . e(substr((string) $myOpen['check_in_at'], 11, 5)) . '</span>' : '<span class="status-pill st-inactive">Off shift</span>' ?></div>
    <form method="post" class="mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="my_attendance">
      <button class="btn btn-sm <?= $myOpen ? 'btn-outline-warning' : 'btn-ff' ?>"><?= $myOpen ? 'Check out' : 'Check in for shift' ?></button></form></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel mb-3">
      <h5>Today's sessions — <?= e($todayName) ?></h5>
      <?php foreach ($classesToday as $c): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)">
          <span><strong><?= e($c['name']) ?></strong> · <?= e($c['category']) ?><br><small style="color:var(--ff-muted)"><?= e($c['branch_name'] ?? '') ?></small></span>
          <span class="text-end"><?= e(substr((string) $c['start_time'], 0, 5)) ?> – <?= e(substr((string) $c['end_time'], 0, 5)) ?><br><small style="color:var(--ff-muted)"><?= (int) $c['booked_count'] ?> / <?= (int) $c['capacity'] ?> booked</small></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$classesToday): ?><p class="small mb-0" style="color:var(--ff-muted)">No classes scheduled for you today. Your full week is under My Schedule.</p><?php endif; ?>
    </div>
    <div class="panel">
      <h5>My newest assigned members</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Member</th><th>Plan</th><th>Expires</th><th>BMI</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentAssigned as $m): ?>
          <tr><td class="fw-bold"><?= e($m['name']) ?><br><small style="color:var(--ff-muted)"><?= e($m['member_code'] ?? '') ?></small></td>
            <td><?= e($m['membership_plan'] ?? '—') ?></td><td><?= e($m['membership_end'] ?? '—') ?></td><td><?= e($m['bmi'] ?? '—') ?></td>
            <td><span class="status-pill st-<?= e($m['status']) ?>"><?= e($m['status']) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$recentAssigned): ?><tr><td colspan="5" style="color:var(--ff-muted)">No members assigned yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="panel mb-3">
      <h5>My week</h5>
      <?php foreach ($weekClasses as $c): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)">
          <span><strong><?= e($c['day_of_week']) ?></strong> · <?= e($c['name']) ?></span>
          <span class="small" style="color:var(--ff-muted)"><?= e(substr((string) $c['start_time'], 0, 5)) ?> · <?= (int) $c['booked_count'] ?>/<?= (int) $c['capacity'] ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$weekClasses): ?><p class="small mb-0" style="color:var(--ff-muted)">No classes assigned this week.</p><?php endif; ?>
    </div>
    <div class="panel">
      <h5 class="text-warning"><i class="bi bi-alarm me-1"></i>Member memberships expiring ≤14d</h5>
      <?php foreach ($expiring as $x): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)"><span><strong><?= e($x['name']) ?></strong> · <?= e($x['plan_name']) ?></span><span class="text-warning fw-bold"><?= e($x['end_date']) ?></span></div>
      <?php endforeach; ?>
      <?php if (!$expiring): ?><p class="small mb-0" style="color:var(--ff-muted)">None of your members expire in the next two weeks.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/trainer/footer.php'; ?>
