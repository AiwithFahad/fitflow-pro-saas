<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Accountant'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Reports';
$activeNav = 'reports';

$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

// CSV exports ---------------------------------------------------------
if (($_GET['export'] ?? '') === 'finance') {
    $rev = (float) (Database::scalar('SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = "verified" AND DATE(COALESCE(paid_at, created_at)) BETWEEN ? AND ?', [$tid, $from, $to]) ?? 0);
    $exp = Expense::totalInRange($tid, $from, $to);
    $sal = StaffSalary::totalForPeriod($tid, substr($from, 0, 7));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fitflow-finance-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['From', 'To', 'Verified revenue', 'Expenses', 'Salaries (month of From)', 'Net']);
    fputcsv($out, [$from, $to, $rev, $exp, $sal, $rev - $exp - $sal]);
    fputcsv($out, []);
    fputcsv($out, ['Expense category', 'Entries', 'Total']);
    foreach (Expense::byCategory($tid, $from, $to) as $c) {
        fputcsv($out, [$c['category'], $c['entries'], $c['total']]);
    }
    fclose($out);
    exit;
}
if (($_GET['export'] ?? '') === 'attendance') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fitflow-attendance-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Member', 'Check-in', 'Check-out', 'Method']);
    $rows = Database::fetchAll(
        'SELECT m.name, a.check_in_at, a.check_out_at, a.method FROM attendance a JOIN members m ON m.id = a.member_id
         WHERE a.tenant_id = ? AND DATE(a.check_in_at) BETWEEN ? AND ? ORDER BY a.check_in_at',
        [$tid, $from, $to]
    );
    foreach ($rows as $r) {
        fputcsv($out, [$r['name'], $r['check_in_at'], $r['check_out_at'], $r['method']]);
    }
    fclose($out);
    exit;
}

$revenue = (float) (Database::scalar('SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = "verified" AND DATE(COALESCE(paid_at, created_at)) BETWEEN ? AND ?', [$tid, $from, $to]) ?? 0);
$expensesTotal = Expense::totalInRange($tid, $from, $to);
$expensesByCat = Expense::byCategory($tid, $from, $to);
$salariesTotal = StaffSalary::totalForPeriod($tid, substr($from, 0, 7));
$attReport = Attendance::report($tid, 'daily');
$memberStats = Member::stats($tid);
$expiring30 = Member::expiringSoon($tid, 30);
$trainerPerf = Database::fetchAll(
    'SELECT t.name,
            (SELECT COUNT(*) FROM members m WHERE m.trainer_id = t.id AND m.tenant_id = t.tenant_id) AS members,
            (SELECT COUNT(*) FROM classes c WHERE c.trainer_id = t.id AND c.tenant_id = t.tenant_id AND c.status = "active") AS classes,
            (SELECT COUNT(*) FROM attendance a JOIN members m2 ON m2.id = a.member_id WHERE m2.trainer_id = t.id AND a.tenant_id = t.tenant_id AND a.check_in_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS member_checkins,
            (SELECT COUNT(*) FROM trainer_attendance ta WHERE ta.trainer_id = t.id AND ta.tenant_id = t.tenant_id AND DATE(ta.check_in_at) BETWEEN ? AND ?) AS shifts
     FROM trainers t WHERE t.tenant_id = ? AND t.status = "active" ORDER BY members DESC',
    [$from, $to, $tid]
);

$extraScripts = '<script>
(function(){
  if (!window.Chart) return;
  Chart.defaults.color = "#a1a1aa"; Chart.defaults.borderColor = "#2b2b30";
  FF.api("charts.php").then(function(res){
    new Chart(document.getElementById("repRev"), {type:"bar", data:{labels:res.revenue_monthly.labels, datasets:[{label:"Verified revenue ($)", data:res.revenue_monthly.data, backgroundColor:"rgba(249,115,22,.6)", borderColor:"#f97316", borderWidth:1}]}, options:{scales:{y:{beginAtZero:true}}}});
    new Chart(document.getElementById("repPlan"), {type:"doughnut", data:{labels:res.plan_distribution.labels, datasets:[{data:res.plan_distribution.data, backgroundColor:["#f97316","#fbbf24","#38bdf8","#22c55e","#a78bfa"]}]}});
    new Chart(document.getElementById("repGrowth"), {type:"line", data:{labels:res.membership_growth.labels, datasets:[{label:"New members", data:res.membership_growth.data, borderColor:"#38bdf8", backgroundColor:"rgba(56,189,248,.15)", fill:true, tension:.35}]}, options:{scales:{y:{beginAtZero:true, ticks:{precision:0}}}}});
  }).catch(function(e){ FF.toast(e.message, "error"); });
  var att = ' . json_encode($attReport) . ';
  new Chart(document.getElementById("repAtt"), {type:"bar", data:{labels: att.map(function(r){return r.label;}), datasets:[{label:"Check-ins", data: att.map(function(r){return parseInt(r.checkins,10);}), backgroundColor:"rgba(34,197,94,.55)", borderColor:"#22c55e", borderWidth:1}]}, options:{scales:{y:{beginAtZero:true, ticks:{precision:0}}}}});
})();
</script>';

require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="panel mb-3">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?= e($from) ?>"></div>
    <div class="col-md-3"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?= e($to) ?>"></div>
    <div class="col-md-6 d-flex gap-2 flex-wrap">
      <button class="btn btn-ff">Apply range</button>
      <a class="btn btn-ff-outline" href="?export=finance&from=<?= e($from) ?>&to=<?= e($to) ?>">Finance CSV</a>
      <a class="btn btn-ff-outline" href="?export=attendance&from=<?= e($from) ?>&to=<?= e($to) ?>">Attendance CSV</a>
      <a class="btn btn-ff-outline" href="<?= e(url('api/members-export.php')) ?>">Members CSV</a>
    </div>
  </form>
</div>

<div class="row g-3 mb-1">
  <div class="col-6 col-xl-3"><div class="kpi"><div class="k-label">Verified revenue (range)</div><div class="k-value"><?= money($revenue) ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="k-label">Expenses (range)</div><div class="k-value"><?= money($expensesTotal) ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="k-label">Salaries (month)</div><div class="k-value"><?= money($salariesTotal) ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="k-label">Net (range)</div><div class="k-value"><?= money($revenue - $expensesTotal - $salariesTotal) ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel mb-3"><h5>Monthly revenue (verified)</h5><canvas id="repRev" class="chart"></canvas></div>
    <div class="row g-3">
      <div class="col-md-6"><div class="panel"><h5>Daily attendance</h5><canvas id="repAtt" class="chart"></canvas></div></div>
      <div class="col-md-6"><div class="panel"><h5>Membership growth</h5><canvas id="repGrowth" class="chart"></canvas></div></div>
    </div>
    <div class="panel mt-3">
      <h5>Trainer performance</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Trainer</th><th>Assigned members</th><th>Active classes</th><th>Member check-ins (30d)</th><th>Shifts in range</th></tr></thead>
        <tbody>
        <?php foreach ($trainerPerf as $t): ?>
          <tr><td class="fw-bold"><?= e($t['name']) ?></td><td><?= (int) $t['members'] ?></td><td><?= (int) $t['classes'] ?></td><td><?= (int) $t['member_checkins'] ?></td><td><?= (int) $t['shifts'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$trainerPerf): ?><tr><td colspan="5" style="color:var(--ff-muted)">No trainers yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="panel mb-3"><h5>Members by plan</h5><canvas id="repPlan" class="chart"></canvas></div>
    <div class="panel mb-3">
      <h5>Expenses by category (range)</h5>
      <?php foreach ($expensesByCat as $c): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)"><span><?= e($c['category']) ?> <small style="color:var(--ff-muted)">(<?= (int) $c['entries'] ?>)</small></span><strong><?= money($c['total']) ?></strong></div>
      <?php endforeach; ?>
      <?php if (!$expensesByCat): ?><p class="small mb-0" style="color:var(--ff-muted)">No expenses in range.</p><?php endif; ?>
    </div>
    <div class="panel">
      <h5>Membership snapshot</h5>
      <table class="table table-sm mb-0">
        <tr><th>Total members</th><td><?= $memberStats['total'] ?></td></tr>
        <tr><th>Active members</th><td><?= $memberStats['active'] ?></td></tr>
        <tr><th>New (30 days)</th><td><?= $memberStats['new_month'] ?></td></tr>
        <tr><th>Expiring ≤30 days</th><td><?= count($expiring30) ?></td></tr>
        <tr><th>Present today</th><td><?= $memberStats['present_today'] ?></td></tr>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
