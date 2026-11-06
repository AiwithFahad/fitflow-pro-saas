<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Accountant'], Auth::homeFor(current_user()));
$tid = tenant_id();

// CSV exports (members / payments / attendance) — generated from live data.
if (!empty($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="fitflow-' . $type . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if ($type === 'members') {
        fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Branch', 'Status', 'BMI', 'Joined']);
        foreach (Member::withMembership($tid, 1000) as $m) {
            fputcsv($out, [$m['id'], $m['name'], $m['email'], $m['phone'], $m['branch_name'], $m['status'], $m['bmi'], $m['joined_at']]);
        }
    } elseif ($type === 'payments') {
        fputcsv($out, ['ID', 'Description', 'Amount', 'Method', 'Status', 'Reference', 'Date']);
        foreach (Payment::recent($tid, 1000) as $p) {
            fputcsv($out, [$p['id'], $p['description'], $p['amount'], $p['method'], $p['status'], $p['reference'], $p['paid_at'] ?? $p['created_at']]);
        }
    } elseif ($type === 'attendance') {
        fputcsv($out, ['ID', 'Member', 'Check-in', 'Check-out', 'Method']);
        foreach (Database::fetchAll('SELECT a.*, m.name AS member_name FROM attendance a JOIN members m ON m.id = a.member_id WHERE a.tenant_id = ? ORDER BY a.id DESC LIMIT 1000', [$tid]) as $a) {
            fputcsv($out, [$a['id'], $a['member_name'], $a['check_in_at'], $a['check_out_at'], $a['method']]);
        }
    }
    fclose($out);
    exit;
}

$dashTitle = 'Reports & Analytics';
$activeNav = 'reports';

$revSeries = Database::fetchAll(
    'SELECT DATE_FORMAT(paid_at, "%Y-%m") AS month, COALESCE(SUM(amount),0) AS total FROM payments
     WHERE tenant_id = ? AND status = "verified" AND paid_at IS NOT NULL AND paid_at > (NOW() - INTERVAL 12 MONTH)
     GROUP BY month ORDER BY month ASC', [$tid]
);
$attSeries = Attendance::series($tid, 30);
$planDist = Database::fetchAll(
    'SELECT plan_name, COUNT(*) AS total FROM memberships WHERE tenant_id = ? AND status = "active" GROUP BY plan_name ORDER BY total DESC', [$tid]
);
$branchDist = Database::fetchAll(
    'SELECT b.name, COUNT(m.id) AS total FROM branches b LEFT JOIN members m ON m.branch_id = b.id AND m.tenant_id = b.tenant_id
     WHERE b.tenant_id = ? GROUP BY b.id ORDER BY total DESC', [$tid]
);
$totals = Payment::totals($tid);
$memberStats = Member::stats($tid);
$extraScripts = '<script>
function mk(id, labels, data, label, type) {
  const el = document.getElementById(id); if (!el) return;
  new Chart(el, { type: type, data: { labels: labels, datasets: [{ label: label, data: data, borderColor: "#f97316", backgroundColor: type === "doughnut" ? ["#f97316","#fbbf24","#38bdf8","#22c55e","#a78bfa"] : "rgba(249,115,22,.35)", fill: type === "line", tension: .35 }] },
    options: { plugins: { legend: { labels: { color: "#e4e4e7" }, display: type === "doughnut" } }, scales: type === "doughnut" ? {} : { x: { ticks: { color: "#a1a1aa" } }, y: { ticks: { color: "#a1a1aa" }, beginAtZero: true } } } });
}
mk("revChart", ' . json_encode(array_column($revSeries, 'month')) . ', ' . json_encode(array_map('floatval', array_column($revSeries, 'total'))) . ', "Verified revenue ($)", "bar");
mk("attChart", ' . json_encode(array_column($attSeries, 'day')) . ', ' . json_encode(array_map('intval', array_column($attSeries, 'total'))) . ', "Check-ins", "line");
mk("planChart", ' . json_encode(array_column($planDist, 'plan_name')) . ', ' . json_encode(array_map('intval', array_column($planDist, 'total'))) . ', "Members", "doughnut");
</script>';
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <span class="fw-bold me-2 align-self-center">Export CSV:</span>
  <a class="btn btn-sm btn-ff-outline" href="?export=members"><i class="bi bi-download me-1"></i>Members</a>
  <a class="btn btn-sm btn-ff-outline" href="?export=payments"><i class="bi bi-download me-1"></i>Payments</a>
  <a class="btn btn-sm btn-ff-outline" href="?export=attendance"><i class="bi bi-download me-1"></i>Attendance</a>
  <button class="btn btn-sm btn-ff-outline" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / PDF report</button>
</div>
<div class="row g-3">
  <div class="col-md-3"><div class="kpi"><div class="k-label">Verified revenue (all time)</div><div class="k-value"><?= money($totals['verified']) ?></div></div></div>
  <div class="col-md-3"><div class="kpi"><div class="k-label">Pending payments</div><div class="k-value"><?= money($totals['pending']) ?></div></div></div>
  <div class="col-md-3"><div class="kpi"><div class="k-label">Active members</div><div class="k-value"><?= $memberStats['active'] ?></div></div></div>
  <div class="col-md-3"><div class="kpi"><div class="k-label">Check-ins today</div><div class="k-value"><?= $memberStats['present_today'] ?></div></div></div>
</div>
<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="panel h-100"><h5>Revenue — last 12 months (verified)</h5><canvas id="revChart" class="chart"></canvas></div></div>
  <div class="col-lg-6"><div class="panel h-100"><h5>Attendance — last 30 days</h5><canvas id="attChart" class="chart"></canvas></div></div>
</div>
<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="panel h-100"><h5>Active memberships by plan</h5><canvas id="planChart" class="chart"></canvas></div></div>
  <div class="col-lg-6"><div class="panel h-100"><h5>Members by branch</h5>
    <table class="table align-middle mb-0"><thead><tr><th>Branch</th><th class="text-end">Members</th></tr></thead>
    <tbody><?php foreach ($branchDist as $b): ?><tr><td><?= e($b['name']) ?></td><td class="text-end fw-bold"><?= (int) $b['total'] ?></td></tr><?php endforeach; ?>
    <?php if (!$branchDist): ?><tr><td colspan="2" style="color:var(--ff-muted)">No branches yet.</td></tr><?php endif; ?></tbody></table>
  </div></div>
</div>
<p class="small mt-3" style="color:var(--ff-muted)">Every number on this page is a live query against your gym's database — exports, charts and totals always agree.</p>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
