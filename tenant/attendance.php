<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer', 'Receptionist'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Attendance Tracking';
$activeNav = 'attendance';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'manual_in') {
        $member = Member::find((int) ($_POST['member_id'] ?? 0), $tid);
        if ($member !== null) {
            Database::insert('attendance', ['tenant_id' => $tid, 'branch_id' => $member['branch_id'], 'member_id' => $member['id'], 'check_in_at' => date('Y-m-d H:i:s'), 'method' => 'manual']);
            flash('success', $member['name'] . ' checked in manually.');
        }
    } elseif ($action === 'checkout') {
        Database::query('UPDATE attendance SET check_out_at = NOW() WHERE id = ? AND tenant_id = ?', [(int) $_POST['attendance_id'], $tid]);
        flash('success', 'Member checked out.');
    } elseif ($action === 'delete') {
        Attendance::deleteById((int) $_POST['attendance_id'], $tid);
        flash('success', 'Attendance record deleted.');
    }
    redirect(url('tenant/attendance.php'));
}

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$rows = Database::fetchAll(
    'SELECT a.*, m.name AS member_name, b.name AS branch_name FROM attendance a
     JOIN members m ON m.id = a.member_id LEFT JOIN branches b ON b.id = a.branch_id
     WHERE a.tenant_id = ? AND DATE(a.check_in_at) = ? ORDER BY a.check_in_at DESC LIMIT 300',
    [$tid, $date]
);
$members = Member::all($tid, 'name ASC', 300);
$todayCount = (int) Database::scalar('SELECT COUNT(DISTINCT member_id) FROM attendance WHERE tenant_id = ? AND DATE(check_in_at) = CURDATE()', [$tid]);
$weekCount = (int) Database::scalar('SELECT COUNT(*) FROM attendance WHERE tenant_id = ? AND check_in_at > (NOW() - INTERVAL 7 DAY)', [$tid]);
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3 mb-1">
  <div class="col-md-4"><div class="kpi"><div class="k-label">Members in today</div><div class="k-value"><?= $todayCount ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Visits (7 days)</div><div class="k-value"><?= $weekCount ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Records on <?= e($date) ?></div><div class="k-value"><?= count($rows) ?></div></div></div>
</div>
<div class="panel mb-3">
  <form method="post" class="row g-2 align-items-end">
    <?= csrf_field() ?><input type="hidden" name="action" value="manual_in">
    <div class="col-md-6"><label class="form-label">Manual check-in</label><select class="form-select" name="member_id" required><option value="">Select member…</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['qr_token']) ?>)</option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-ff w-100">Check in now</button></div>
    <div class="col-md-3"><a class="btn btn-ff-outline w-100" href="<?= e(url('tenant/checkin.php')) ?>"><i class="bi bi-qr-code-scan me-1"></i>Open QR scanner</a></div>
  </form>
</div>
<div class="panel">
  <form method="get" class="d-flex gap-2 mb-3"><input type="date" class="form-control" style="max-width:220px" name="date" value="<?= e($date) ?>"><button class="btn btn-ff">View day</button></form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Member</th><th>Branch</th><th>Check-in</th><th>Check-out</th><th>Duration</th><th>Method</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php $dur = $r['check_out_at'] ? round((strtotime($r['check_out_at']) - strtotime($r['check_in_at'])) / 60) . ' min' : '<span class="text-orange fw-bold">In gym</span>'; ?>
      <tr><td class="fw-bold"><?= e($r['member_name']) ?></td><td><?= e($r['branch_name'] ?? '—') ?></td>
        <td><?= e(substr((string) $r['check_in_at'], 11, 5)) ?></td><td><?= $r['check_out_at'] ? e(substr((string) $r['check_out_at'], 11, 5)) : '—' ?></td>
        <td><?= $dur ?></td><td><?= e($r['method']) ?></td>
        <td class="text-end">
          <?php if (!$r['check_out_at']): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="checkout"><input type="hidden" name="attendance_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-success">Check out</button></form><?php endif; ?>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this record?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="attendance_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
        </td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" style="color:var(--ff-muted)">No attendance recorded for this date.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
