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
$dashTitle = 'My Attendance';
$activeNav = 'attendance';

$rows = Attendance::forMember($tid, (int) $member['id'], 60);
$total = count($rows);
$thisWeek = (int) Database::scalar('SELECT COUNT(*) FROM attendance WHERE member_id = ? AND check_in_at > (NOW() - INTERVAL 7 DAY)', [$member['id']]);
$streak = (int) Database::scalar('SELECT COUNT(DISTINCT DATE(check_in_at)) FROM attendance WHERE member_id = ? AND check_in_at > (NOW() - INTERVAL 30 DAY)', [$member['id']]);
require __DIR__ . '/../views/member/header.php';
?>
<div class="row g-3 mb-1">
  <div class="col-md-4"><div class="kpi"><div class="k-label">Visits shown</div><div class="k-value"><?= $total ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Visits this week</div><div class="k-value"><?= $thisWeek ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Active days (30d)</div><div class="k-value"><?= $streak ?></div></div></div>
</div>
<div class="panel">
  <h5>Visit history</h5>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Date</th><th>Check-in</th><th>Check-out</th><th>Duration</th><th>Method</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="fw-bold"><?= e(substr((string) $r['check_in_at'], 0, 10)) ?></td>
        <td><?= e(substr((string) $r['check_in_at'], 11, 5)) ?></td>
        <td><?= $r['check_out_at'] ? e(substr((string) $r['check_out_at'], 11, 5)) : '<span class="text-orange">In gym</span>' ?></td>
        <td><?= $r['check_out_at'] ? round((strtotime($r['check_out_at']) - strtotime($r['check_in_at'])) / 60) . ' min' : '—' ?></td>
        <td><?= e($r['method']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" style="color:var(--ff-muted)">No visits recorded yet — scan your QR token at the front desk.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/../views/member/footer.php'; ?>
