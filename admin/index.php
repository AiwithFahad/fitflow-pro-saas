<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));

$dashTitle = 'Platform Overview';
$activeNav = 'overview';

$t = Tenant::stats();
$s = Subscription::stats();
$p = Payment::totals(null);
$txCount = (int) Database::scalar('SELECT COUNT(*) FROM payments');
$openTickets = SupportTicket::openCount();
$recentTenants = Tenant::all(null, 'id DESC', 8);
$revenue = Subscription::revenueSeries(6);
$revLabels = array_column($revenue, 'month');
$revValues = array_map('floatval', array_column($revenue, 'total'));
$planDist = Subscription::byPlan();
$extraScripts = '<script>
const revCtx = document.getElementById("revChart");
if (revCtx) { new Chart(revCtx, { type: "line", data: { labels: ' . json_encode($revLabels) . ', datasets: [{ label: "Verified revenue ($)", data: ' . json_encode($revValues) . ', borderColor: "#f97316", backgroundColor: "rgba(249,115,22,.18)", fill: true, tension: .35 }] }, options: { plugins: { legend: { labels: { color: "#e4e4e7" } } }, scales: { x: { ticks: { color: "#a1a1aa" } }, y: { ticks: { color: "#a1a1aa" } } } } }); }
const planCtx = document.getElementById("planChart");
if (planCtx) { new Chart(planCtx, { type: "doughnut", data: { labels: ' . json_encode(array_column($planDist, 'plan_name')) . ', datasets: [{ data: ' . json_encode(array_map('intval', array_column($planDist, 'total'))) . ', backgroundColor: ["#f97316", "#fbbf24", "#38bdf8", "#22c55e", "#a78bfa"] }] }, options: { plugins: { legend: { labels: { color: "#e4e4e7" } } } } }); }
</script>';

require __DIR__ . '/../views/admin/header.php';
?>
<div class="row g-3">
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-buildings"></i></span><div class="k-label">Total tenants</div><div class="k-value"><?= $t['total'] ?></div><small style="color:var(--ff-muted)"><?= $t['active'] ?> active · <?= $t['suspended'] ?> suspended · <?= $t['trial'] ?> trial</small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-people"></i></span><div class="k-label">Total users</div><div class="k-value"><?= $t['users'] ?></div><small style="color:var(--ff-muted)">Across all gyms</small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-arrow-repeat"></i></span><div class="k-label">MRR</div><div class="k-value"><?= money($s['mrr']) ?></div><small style="color:var(--ff-muted)">ARR <?= money($s['arr']) ?></small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-credit-card"></i></span><div class="k-label">Transactions</div><div class="k-value"><?= $txCount ?></div><small style="color:var(--ff-muted)"><?= money($p['verified']) ?> verified · <?= money($p['pending']) ?> pending</small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-gem"></i></span><div class="k-label">Active subscriptions</div><div class="k-value"><?= $s['active'] ?></div><small style="color:var(--ff-muted)"><?= $s['expired'] ?> expired / cancelled</small></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-life-preserver"></i></span><div class="k-label">Open tickets</div><div class="k-value"><?= $openTickets ?></div><small style="color:var(--ff-muted)">Awaiting platform reply</small></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-8"><div class="panel h-100"><h5>Verified revenue — last 6 months</h5><canvas id="revChart" class="chart"></canvas></div></div>
  <div class="col-lg-4"><div class="panel h-100"><h5>Subscriptions by plan</h5><canvas id="planChart" class="chart"></canvas></div></div>
</div>

<div class="panel mt-3">
  <h5>Newest tenants</h5>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Gym</th><th>Slug</th><th>Location</th><th>Status</th><th>Joined</th></tr></thead>
    <tbody>
    <?php foreach ($recentTenants as $row): ?>
      <tr><td class="fw-bold"><?= e($row['name']) ?></td><td><?= e($row['slug']) ?></td><td><?= e(trim(($row['city'] ?? '') . ', ' . ($row['country'] ?? ''), ', ')) ?></td>
      <td><span class="status-pill st-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td><td><?= e(substr((string) $row['created_at'], 0, 10)) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$recentTenants): ?><tr><td colspan="5" style="color:var(--ff-muted)">No tenants yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
