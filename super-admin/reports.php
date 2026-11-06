<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Platform Reports & Analytics';
$activeNav = 'reports';

// CSV export of verified revenue by month.
if (($_GET['export'] ?? '') === 'revenue') {
    $rows = Subscription::revenueSeries(12);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fitflow-platform-revenue.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Month', 'Verified revenue']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['month'], $r['total']]);
    }
    fclose($out);
    exit;
}

$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

$revSeries = Subscription::revenueSeries(6);
$byPlan = Subscription::byPlan();
$rangeRevenue = (float) (Database::scalar(
    'SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = "verified" AND DATE(COALESCE(paid_at, created_at)) BETWEEN ? AND ?',
    [$from, $to]
) ?? 0);
$rangeSignups = (int) (Database::scalar('SELECT COUNT(*) FROM tenants WHERE DATE(created_at) BETWEEN ? AND ?', [$from, $to]) ?? 0);
$rangeMembers = (int) (Database::scalar('SELECT COUNT(*) FROM members WHERE DATE(created_at) BETWEEN ? AND ?', [$from, $to]) ?? 0);
$topGyms = Database::fetchAll(
    'SELECT t.name, COUNT(DISTINCT m.id) AS members, COUNT(DISTINCT tr.id) AS trainers,
            COALESCE(SUM(CASE WHEN p.status = "verified" THEN p.amount ELSE 0 END),0) AS revenue
     FROM tenants t
     LEFT JOIN members m ON m.tenant_id = t.id
     LEFT JOIN trainers tr ON tr.tenant_id = t.id
     LEFT JOIN payments p ON p.tenant_id = t.id
     GROUP BY t.id ORDER BY revenue DESC LIMIT 10'
);
$churn = Database::fetchAll(
    'SELECT t.name, s.status, s.cancelled_at FROM subscriptions s JOIN tenants t ON t.id = s.tenant_id
     WHERE s.status IN ("cancelled","expired") ORDER BY s.id DESC LIMIT 10'
);

$extraScripts = '<script>
(function(){
  if (!window.Chart) return;
  var rev = document.getElementById("repRevChart");
  if (rev) FF.registerChart("repRev", new Chart(rev, {type:"line", data:{labels:' . json_encode(array_column($revSeries, 'month')) . ', datasets:[{label:"Verified revenue", data:' . json_encode(array_map('floatval', array_column($revSeries, 'total'))) . ', borderColor:"#f97316", backgroundColor:"rgba(249,115,22,.18)", fill:true, tension:.35}]}, options:{plugins:{legend:{labels:{color:"#e4e4e7"}}}, scales:{y:{beginAtZero:true}}}}));
  var plan = document.getElementById("repPlanChart");
  if (plan) FF.registerChart("repPlan", new Chart(plan, {type:"bar", data:{labels:' . json_encode(array_column($byPlan, 'plan_name')) . ', datasets:[{label:"Subscriptions", data:' . json_encode(array_map('intval', array_column($byPlan, 'total'))) . ', backgroundColor:"rgba(249,115,22,.65)", borderColor:"#f97316", borderWidth:1}]}, options:{plugins:{legend:{labels:{color:"#e4e4e7"}}}, scales:{y:{beginAtZero:true, ticks:{precision:0}}}}}));
})();
</script>';

require __DIR__ . '/../views/super-admin/header.php';
?>
<div class="panel mb-3">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?= e($from) ?>"></div>
    <div class="col-md-3"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?= e($to) ?>"></div>
    <div class="col-md-3"><button class="btn btn-ff">Apply range</button>
      <a class="btn btn-ff-outline" href="?export=revenue">Export revenue CSV (12 mo)</a></div>
    <div class="col-md-3 text-md-end small" style="color:var(--ff-muted)">All figures come from live database rows.</div>
  </form>
</div>

<div class="row g-3 mb-1">
  <div class="col-md-4"><div class="kpi"><div class="k-label">Verified revenue in range</div><div class="k-value"><?= money($rangeRevenue) ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">New gyms in range</div><div class="k-value"><?= $rangeSignups ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">New members (all gyms) in range</div><div class="k-value"><?= $rangeMembers ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-7"><div class="panel mb-3"><h5>Revenue trend — 6 months</h5><canvas id="repRevChart" class="chart"></canvas></div></div>
  <div class="col-xl-5"><div class="panel mb-3"><h5>Subscriptions by plan</h5><canvas id="repPlanChart" class="chart"></canvas></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel"><h5>Top gyms by verified revenue</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Gym</th><th>Members</th><th>Trainers</th><th>Verified revenue</th></tr></thead>
        <tbody>
        <?php foreach ($topGyms as $g): ?>
          <tr><td class="fw-bold"><?= e($g['name']) ?></td><td><?= (int) $g['members'] ?></td><td><?= (int) $g['trainers'] ?></td><td class="fw-bold"><?= money($g['revenue']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$topGyms): ?><tr><td colspan="4" style="color:var(--ff-muted)">No gyms yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel"><h5>Recent churn (cancelled / expired)</h5>
      <?php foreach ($churn as $c): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)">
          <span><?= e($c['name']) ?></span>
          <span><span class="status-pill st-<?= e($c['status']) ?>"><?= e($c['status']) ?></span>
          <small style="color:var(--ff-muted)"><?= e($c['cancelled_at'] ? substr((string) $c['cancelled_at'], 0, 10) : '') ?></small></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$churn): ?><p class="small mb-0" style="color:var(--ff-muted)">No cancellations recorded.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/super-admin/footer.php'; ?>
