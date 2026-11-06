<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer', 'Receptionist', 'Accountant'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Gym Dashboard';
$activeNav = 'dashboard';

Membership::sweepExpired($tid);
$memberStats = Member::stats($tid);
$payTotals = Payment::totals($tid);
$expiring = Member::expiringSoon($tid, 7);
$expiredCount = (int) (Database::scalar('SELECT COUNT(*) FROM memberships WHERE tenant_id = ? AND status = "expired"', [$tid]) ?? 0);
$alerts = Equipment::maintenanceDue($tid);
$recentMembers = Member::withMembership($tid, 5);
$recentPayments = Payment::recent($tid, 5);
$sub = active_subscription($tid);

// Server-side initial chart data (same queries the API serves).
$chartInit = (function () use ($tid) {
    $revRows = Database::fetchAll('SELECT DATE_FORMAT(COALESCE(paid_at, created_at), "%Y-%m") AS ym, COALESCE(SUM(amount),0) AS total FROM payments WHERE tenant_id = ? AND status = "verified" AND COALESCE(paid_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym', [$tid]);
    $revMap = array_column($revRows, 'total', 'ym');
    $growRows = Database::fetchAll('SELECT DATE_FORMAT(created_at, "%Y-%m") AS ym, COUNT(*) AS total FROM members WHERE tenant_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym', [$tid]);
    $growMap = array_column($growRows, 'total', 'ym');
    $labels = $rev = $grow = [];
    for ($i = 5; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-{$i} months"));
        $labels[] = date('M Y', strtotime($ym . '-01'));
        $rev[] = (float) ($revMap[$ym] ?? 0);
        $grow[] = (int) ($growMap[$ym] ?? 0);
    }
    $attMap = [];
    foreach (Attendance::series($tid, 14) as $row) {
        $attMap[$row['day']] = (int) $row['total'];
    }
    $attLabels = $att = [];
    for ($i = 13; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $attLabels[] = date('D j', strtotime($day));
        $att[] = $attMap[$day] ?? 0;
    }
    $planRows = Database::fetchAll('SELECT plan_name, COUNT(*) AS total FROM memberships WHERE tenant_id = ? AND status IN ("active","suspended") GROUP BY plan_name ORDER BY total DESC', [$tid]);
    $trainerRows = Database::fetchAll('SELECT t.name, (SELECT COUNT(*) FROM members m WHERE m.trainer_id = t.id AND m.tenant_id = t.tenant_id) AS members FROM trainers t WHERE t.tenant_id = ? AND t.status = "active" ORDER BY members DESC', [$tid]);
    return [
        'labels' => $labels, 'revenue' => $rev, 'growth' => $grow,
        'att_labels' => $attLabels, 'attendance' => $att,
        'plan_labels' => array_column($planRows, 'plan_name'), 'plan_data' => array_map('intval', array_column($planRows, 'total')),
        'trainer_labels' => array_column($trainerRows, 'name'), 'trainer_members' => array_map('intval', array_column($trainerRows, 'members')),
    ];
})();

$extraScripts = '<script>
(function(){
  if (!window.Chart) return;
  Chart.defaults.color = "#a1a1aa"; Chart.defaults.borderColor = "#2b2b30";
  var init = ' . json_encode($chartInit) . ';
  FF.registerChart("rev", new Chart(document.getElementById("revChart"), {type:"bar", data:{labels:init.labels, datasets:[{label:"Verified revenue ($)", data:init.revenue, backgroundColor:"rgba(249,115,22,.6)", borderColor:"#f97316", borderWidth:1}]}, options:{scales:{y:{beginAtZero:true}}}}));
  FF.registerChart("growth", new Chart(document.getElementById("growthChart"), {type:"line", data:{labels:init.labels, datasets:[{label:"New members", data:init.growth, borderColor:"#38bdf8", backgroundColor:"rgba(56,189,248,.15)", fill:true, tension:.35}]}, options:{scales:{y:{beginAtZero:true, ticks:{precision:0}}}}}));
  FF.registerChart("att", new Chart(document.getElementById("attChart"), {type:"line", data:{labels:init.att_labels, datasets:[{label:"Check-ins", data:init.attendance, borderColor:"#f97316", backgroundColor:"rgba(249,115,22,.18)", fill:true, tension:.35}]}, options:{scales:{y:{beginAtZero:true, ticks:{precision:0}}}}}));
  FF.registerChart("plan", new Chart(document.getElementById("planChart"), {type:"doughnut", data:{labels:init.plan_labels, datasets:[{data:init.plan_data, backgroundColor:["#f97316","#fbbf24","#38bdf8","#22c55e","#a78bfa"]}]}}));
  FF.registerChart("trainer", new Chart(document.getElementById("trainerChart"), {type:"bar", data:{labels:init.trainer_labels, datasets:[{label:"Assigned members", data:init.trainer_members, backgroundColor:"rgba(56,189,248,.55)", borderColor:"#38bdf8", borderWidth:1}]}, options:{indexAxis:"y", scales:{x:{beginAtZero:true, ticks:{precision:0}}}}}));
  function refreshStats(){
    var from = document.getElementById("fltFrom").value, to = document.getElementById("fltTo").value;
    var p1 = FF.api("dashboard-stats.php", {data:{from:from, to:to}}).then(function(res){
      var k = res.kpis || {};
      document.querySelectorAll("[data-kpi]").forEach(function(el){
        var key = el.getAttribute("data-kpi");
        if (k[key] !== undefined) el.textContent = k[key];
      });
    });
    var p2 = FF.api("charts.php").then(function(res){
      FF.updateChart("rev", res.revenue_monthly.labels, [res.revenue_monthly.data]);
      FF.updateChart("growth", res.membership_growth.labels, [res.membership_growth.data]);
      FF.updateChart("att", res.attendance_daily.labels, [res.attendance_daily.data]);
      FF.updateChart("plan", res.plan_distribution.labels, [res.plan_distribution.data]);
      FF.updateChart("trainer", res.trainer_performance.labels, [res.trainer_performance.members]);
    });
    return Promise.all([p1, p2]);
  }
  FF.onRefresh(refreshStats);
  document.getElementById("fltApply").addEventListener("click", function(){
    refreshStats().then(function(){ FF.toast("Dashboard updated for the selected range."); })
      .catch(function(err){ FF.toast(err.message, "error"); });
  });
})();
</script>';

require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="panel mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Revenue / signups from</label><input type="date" class="form-control" id="fltFrom" value="<?= e(date('Y-m-01')) ?>"></div>
    <div class="col-md-3"><label class="form-label">To</label><input type="date" class="form-control" id="fltTo" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="col-md-6 d-flex gap-2">
      <button type="button" class="btn btn-ff" id="fltApply"><i class="bi bi-funnel me-1"></i>Apply filter</button>
      <button type="button" class="btn btn-ff-outline" data-ff-refresh><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
      <?php if ($sub): ?><span class="status-pill st-<?= e($sub['status']) ?> ms-auto align-self-center"><?= e($sub['plan_name']) ?> · <?= e($sub['status']) ?></span><?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-1">
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-people"></i></span><div class="k-label">Total members</div><div class="k-value" data-kpi="total_members"><?= $memberStats['total'] ?></div><div class="small" style="color:var(--ff-muted)"><span data-kpi="active_members"><?= $memberStats['active'] ?></span> active</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-person-plus"></i></span><div class="k-label">New registrations (range)</div><div class="k-value" data-kpi="new_registrations"><?= $memberStats['new_month'] ?></div><div class="small" style="color:var(--ff-muted)">last 30 days by default</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-calendar-check"></i></span><div class="k-label">Check-ins today</div><div class="k-value" data-kpi="todays_checkins"><?= $memberStats['present_today'] ?></div><div class="small" style="color:var(--ff-muted)"><span data-kpi="total_trainers">—</span> trainers · <span data-kpi="active_classes">—</span> active classes</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-cash-stack"></i></span><div class="k-label">Verified revenue (range)</div><div class="k-value" data-kpi="revenue_label"><?= money($payTotals['verified']) ?></div><div class="small text-warning"><span data-kpi="pending_label"><?= money($payTotals['pending']) ?></span> pending (<span data-kpi="pending_payments">0</span>)</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-alarm"></i></span><div class="k-label">Expiring within 7 days</div><div class="k-value" data-kpi="expiring_soon"><?= count($expiring) ?></div><div class="small"><a href="<?= e(url('admin/memberships.php')) ?>">Open renewals</a></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-x-octagon"></i></span><div class="k-label">Expired memberships</div><div class="k-value" data-kpi="expired_memberships"><?= $expiredCount ?></div><div class="small" style="color:var(--ff-muted)">need renewal outreach</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-tools"></i></span><div class="k-label">Equipment alerts</div><div class="k-value" data-kpi="equipment_alerts"><?= count($alerts) ?></div><div class="small"><a href="<?= e(url('admin/equipment.php')) ?>">Maintenance queue</a></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-qr-code-scan"></i></span><div class="k-label">Front desk</div><div class="k-value fs-5 pt-2"><a class="btn btn-ff btn-sm" href="<?= e(url('admin/attendance.php')) ?>">Open check-in desk</a></div><div class="small" style="color:var(--ff-muted)">QR + manual entry</div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel mb-3"><h5>Monthly revenue (verified)</h5><canvas id="revChart" class="chart"></canvas></div>
    <div class="row g-3">
      <div class="col-md-6"><div class="panel"><h5>Attendance — 14 days</h5><canvas id="attChart" class="chart"></canvas></div></div>
      <div class="col-md-6"><div class="panel"><h5>Membership growth</h5><canvas id="growthChart" class="chart"></canvas></div></div>
    </div>
    <div class="panel mt-3">
      <h5>Newest members</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Member</th><th>Plan</th><th>Expires</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentMembers as $m): ?>
          <tr><td class="fw-bold"><?= e($m['name']) ?><br><small style="color:var(--ff-muted)"><?= e($m['member_code'] ?? '') ?></small></td>
            <td><?= e($m['membership_plan'] ?? '—') ?></td><td><?= e($m['membership_end'] ?? '—') ?></td>
            <td><span class="status-pill st-<?= e($m['status']) ?>"><?= e($m['status']) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$recentMembers): ?><tr><td colspan="4" style="color:var(--ff-muted)">No members yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="panel mb-3"><h5>Members by plan</h5><canvas id="planChart" class="chart"></canvas></div>
    <div class="panel mb-3"><h5>Trainer performance (assigned members)</h5><canvas id="trainerChart" class="chart"></canvas></div>
    <div class="panel mb-3">
      <h5 class="text-warning"><i class="bi bi-alarm me-1"></i>Expiring within 7 days</h5>
      <?php foreach ($expiring as $x): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)"><span><strong><?= e($x['name']) ?></strong> · <?= e($x['plan_name']) ?></span><span class="text-warning fw-bold"><?= e($x['end_date']) ?></span></div>
      <?php endforeach; ?>
      <?php if (!$expiring): ?><p class="small mb-0" style="color:var(--ff-muted)">Nothing expiring this week.</p><?php endif; ?>
    </div>
    <div class="panel">
      <h5>Latest payments</h5>
      <?php foreach ($recentPayments as $p): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)">
          <span class="small"><?= e($p['description'] ?? $p['purpose']) ?><br><span style="color:var(--ff-muted)"><?= e(substr((string) $p['created_at'], 0, 10)) ?></span></span>
          <span class="text-end"><strong><?= money($p['amount']) ?></strong><br><span class="status-pill st-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$recentPayments): ?><p class="small mb-0" style="color:var(--ff-muted)">No payments yet.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
