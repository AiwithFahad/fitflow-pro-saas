<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Revenue & Subscription Analytics';
$activeNav = 'analytics';

$revenue = Subscription::revenueSeries(12);
$growth = Database::fetchAll(
    'SELECT DATE_FORMAT(created_at, "%Y-%m") AS month, COUNT(*) AS total FROM tenants
     WHERE created_at > (NOW() - INTERVAL 12 MONTH) GROUP BY month ORDER BY month ASC'
);
$topTenants = Database::fetchAll(
    'SELECT t.name, COALESCE(SUM(p.amount),0) AS revenue, COUNT(p.id) AS tx
     FROM tenants t LEFT JOIN payments p ON p.tenant_id = t.id AND p.status = "verified"
     GROUP BY t.id ORDER BY revenue DESC LIMIT 10'
);
$subStats = Subscription::stats();
$extraScripts = '<script>
function mk(id, type, labels, data, label) {
  const el = document.getElementById(id);
  if (!el) return;
  new Chart(el, { type: type, data: { labels: labels, datasets: [{ label: label, data: data, borderColor: "#f97316", backgroundColor: type === "bar" ? "rgba(249,115,22,.55)" : "rgba(249,115,22,.18)", fill: type === "line", tension: .35 }] },
    options: { plugins: { legend: { labels: { color: "#e4e4e7" } } }, scales: { x: { ticks: { color: "#a1a1aa" } }, y: { ticks: { color: "#a1a1aa" } } } } });
}
mk("revChart", "bar", ' . json_encode(array_column($revenue, 'month')) . ', ' . json_encode(array_map('floatval', array_column($revenue, 'total'))) . ', "Verified revenue ($)");
mk("growthChart", "line", ' . json_encode(array_column($growth, 'month')) . ', ' . json_encode(array_map('intval', array_column($growth, 'total'))) . ', "New tenants");
</script>';
require __DIR__ . '/../views/admin/header.php';
?>
<div class="row g-3">
  <div class="col-md-4"><div class="kpi"><div class="k-label">Monthly recurring revenue</div><div class="k-value"><?= money($subStats['mrr']) ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Annual recurring revenue</div><div class="k-value"><?= money($subStats['arr']) ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Active subscriptions</div><div class="k-value"><?= $subStats['active'] ?></div></div></div>
</div>
<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="panel h-100"><h5>Verified revenue — last 12 months</h5><canvas id="revChart" class="chart"></canvas></div></div>
  <div class="col-lg-6"><div class="panel h-100"><h5>Tenant growth — new gyms per month</h5><canvas id="growthChart" class="chart"></canvas></div></div>
</div>
<div class="panel mt-3">
  <h5>Top tenants by verified revenue</h5>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Gym</th><th>Transactions</th><th class="text-end">Verified revenue</th></tr></thead>
    <tbody>
    <?php foreach ($topTenants as $row): ?>
      <tr><td class="fw-bold"><?= e($row['name']) ?></td><td><?= (int) $row['tx'] ?></td><td class="text-end fw-bold"><?= money($row['revenue']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$topTenants): ?><tr><td colspan="3" style="color:var(--ff-muted)">No data yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
