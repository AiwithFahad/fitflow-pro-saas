<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer', 'Receptionist', 'Accountant'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Gym Overview';
$activeNav = 'overview';

Membership::sweepExpired($tid);
$ms = Member::stats($tid);
$sub = active_subscription($tid);
$payTotals = Payment::totals($tid);
$expiring = Member::expiringSoon($tid, 7);
$trainersCount = Trainer::count($tid, ' AND status = "active"');
$classesCount = GymClass::count($tid, ' AND status = "active"');
$attSeries = Attendance::series($tid, 14);
$revSeries = Database::fetchAll(
    'SELECT DATE_FORMAT(paid_at, "%Y-%m") AS month, COALESCE(SUM(amount),0) AS total FROM payments
     WHERE tenant_id = ? AND status = "verified" AND paid_at IS NOT NULL AND paid_at > (NOW() - INTERVAL 6 MONTH)
     GROUP BY month ORDER BY month ASC',
    [$tid]
);
$recentMembers = Member::withMembership($tid, 6);
$extraScripts = '<script>
function mk(id, labels, data, label, type) {
  const el = document.getElementById(id); if (!el) return;
  new Chart(el, { type: type || "line", data: { labels: labels, datasets: [{ label: label, data: data, borderColor: "#f97316", backgroundColor: "rgba(249,115,22,.35)", fill: true, tension: .35 }] },
    options: { plugins: { legend: { labels: { color: "#e4e4e7" } } }, scales: { x: { ticks: { color: "#a1a1aa" } }, y: { ticks: { color: "#a1a1aa" }, beginAtZero: true } } } });
}
mk("attChart", ' . json_encode(array_column($attSeries, 'day')) . ', ' . json_encode(array_map('intval', array_column($attSeries, 'total'))) . ', "Check-ins per day");
mk("revChart", ' . json_encode(array_column($revSeries, 'month')) . ', ' . json_encode(array_map('floatval', array_column($revSeries, 'total'))) . ', "Verified revenue ($)", "bar");
</script>';
require __DIR__ . '/../views/tenant/header.php';
?>
<?php if ($sub): ?>
<div class="alert alert-dark d-flex flex-wrap justify-content-between align-items-center" style="border:1px solid var(--ff-border)">
  <span><i class="bi bi-gem text-orange me-2"></i>Current plan: <strong><?= e($sub['plan_name']) ?></strong> · status <strong><?= e($sub['status']) ?></strong>
  <?php if (!empty($sub['ends_at'])): ?> · renews/ends <?= e(substr((string) $sub['ends_at'], 0, 10)) ?><?php endif; ?></span>
  <a class="btn btn-ff btn-sm" href="<?= e(url('tenant/subscription.php')) ?>">Manage subscription</a>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-people"></i></span><div class="k-label">Total members</div><div class="k-value"><?= $ms['total'] ?></div><small style="color:var(--ff-muted)"><?= $ms['active'] ?> active</small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-person-plus"></i></span><div class="k-label">New (30 days)</div><div class="k-value"><?= $ms['new_month'] ?></div><small style="color:var(--ff-muted)">Member growth</small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-calendar-check"></i></span><div class="k-label">Check-ins today</div><div class="k-value"><?= $ms['present_today'] ?></div><small style="color:var(--ff-muted)">Members in the gym today</small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-cash-stack"></i></span><div class="k-label">Verified revenue</div><div class="k-value"><?= money($payTotals['verified']) ?></div><small style="color:var(--ff-muted)"><?= money($payTotals['pending']) ?> pending verification</small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-person-badge"></i></span><div class="k-label">Trainers</div><div class="k-value"><?= $trainersCount ?></div><small style="color:var(--ff-muted)">Active coaches</small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-collection"></i></span><div class="k-label">Active classes</div><div class="k-value"><?= $classesCount ?></div><small style="color:var(--ff-muted)">Weekly schedule</small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-alarm"></i></span><div class="k-label">Expiring in 7 days</div><div class="k-value"><?= count($expiring) ?></div><small style="color:var(--ff-muted)">Send renewal reminders</small></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="panel h-100"><h5>Attendance — last 14 days</h5><canvas id="attChart" class="chart"></canvas></div></div>
  <div class="col-lg-6"><div class="panel h-100"><h5>Verified revenue — last 6 months</h5><canvas id="revChart" class="chart"></canvas></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-7">
    <div class="panel h-100">
      <div class="d-flex justify-content-between"><h5>Newest members</h5><a href="<?= e(url('tenant/members.php')) ?>" class="small">View all</a></div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Member</th><th>Plan</th><th>Expires</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentMembers as $m): ?>
          <tr><td class="fw-bold"><?= e($m['name']) ?></td><td><?= e($m['membership_plan'] ?? '—') ?></td>
          <td><?= e($m['membership_end'] ?? '—') ?></td>
          <td><span class="status-pill st-<?= e($m['membership_status'] ?? $m['status']) ?>"><?= e($m['membership_status'] ?? $m['status']) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$recentMembers): ?><tr><td colspan="4" style="color:var(--ff-muted)">No members yet — enrol your first member.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel h-100">
      <div class="d-flex justify-content-between"><h5><i class="bi bi-alarm text-orange me-1"></i>Expiry alerts</h5><a href="<?= e(url('tenant/renewals.php')) ?>" class="small">Renewals</a></div>
      <?php if (!$expiring): ?><p class="mb-0" style="color:var(--ff-muted)">No memberships expire in the next 7 days.</p><?php endif; ?>
      <?php foreach ($expiring as $x): ?>
        <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--ff-border)">
          <span><strong><?= e($x['name']) ?></strong><br><small style="color:var(--ff-muted)"><?= e($x['plan_name']) ?> · ends <?= e($x['end_date']) ?></small></span>
          <a class="btn btn-sm btn-ff" href="<?= e(url('tenant/renewals.php')) ?>">Renew</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
