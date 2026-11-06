<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Platform Dashboard';
$activeNav = 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $tenantId = (int) ($_POST['tenant_id'] ?? 0);
    if ($action === 'approve' && $tenantId > 0) {
        Tenant::setStatus($tenantId, 'active');
        log_activity(null, 'tenant.approved', 'tenants', $tenantId);
        flash('success', 'Gym approved and activated.');
    } elseif ($action === 'suspend' && $tenantId > 0) {
        Tenant::setStatus($tenantId, 'suspended');
        log_activity(null, 'tenant.suspended', 'tenants', $tenantId);
        flash('success', 'Gym suspended.');
    }
    redirect(url('super-admin/dashboard.php'));
}

$tStats = Tenant::stats();
$subStats = Subscription::stats();
$payTotals = Payment::totals(null);
$pendingGyms = Database::fetchAll(
    'SELECT t.*, s.status AS sub_status, p.name AS plan_name
     FROM tenants t
     LEFT JOIN subscriptions s ON s.id = (SELECT id FROM subscriptions WHERE tenant_id = t.id ORDER BY id DESC LIMIT 1)
     LEFT JOIN subscription_plans p ON p.id = s.plan_id
     WHERE t.status IN ("trial","pending") ORDER BY t.created_at DESC LIMIT 10'
);
$recentTenants = Database::fetchAll(
    'SELECT t.*, p.name AS plan_name, s.status AS sub_status,
            (SELECT COUNT(*) FROM members m WHERE m.tenant_id = t.id) AS member_count
     FROM tenants t
     LEFT JOIN subscriptions s ON s.id = (SELECT id FROM subscriptions WHERE tenant_id = t.id ORDER BY id DESC LIMIT 1)
     LEFT JOIN subscription_plans p ON p.id = s.plan_id
     ORDER BY t.id DESC LIMIT 8'
);
$recentPayments = Payment::recent(null, 6);
$revSeries = Subscription::revenueSeries(6);
$byPlan = Subscription::byPlan();

$extraScripts = '<script>
(function(){
  function build(){
    var rev = document.getElementById("saRevChart");
    if (rev && window.Chart) {
      FF.registerChart("saRev", new Chart(rev, {type:"bar", data:{labels:' . json_encode(array_column($revSeries, 'month')) . ', datasets:[{label:"Verified revenue", data:' . json_encode(array_map('floatval', array_column($revSeries, 'total'))) . ', backgroundColor:"rgba(249,115,22,.65)", borderColor:"#f97316", borderWidth:1}]}, options:{plugins:{legend:{labels:{color:"#e4e4e7"}}}, scales:{y:{beginAtZero:true}}}}));
    }
    var plan = document.getElementById("saPlanChart");
    if (plan && window.Chart) {
      FF.registerChart("saPlan", new Chart(plan, {type:"doughnut", data:{labels:' . json_encode(array_column($byPlan, 'plan_name')) . ', datasets:[{data:' . json_encode(array_map('intval', array_column($byPlan, 'total'))) . ', backgroundColor:["#f97316","#fbbf24","#38bdf8","#22c55e","#a78bfa"]}]}, options:{plugins:{legend:{labels:{color:"#e4e4e7"}}}}}));
    }
  }
  function refresh(){
    return FF.api("super-stats.php").then(function(res){
      var k = res.kpis || {};
      document.querySelectorAll("[data-sa-stat]").forEach(function(el){
        var key = el.getAttribute("data-sa-stat");
        if (k[key] !== undefined) el.textContent = k[key];
      });
      var rev = res.revenue_series || [];
      FF.updateChart("saRev", rev.map(function(r){return r.month;}), [rev.map(function(r){return parseFloat(r.total);})]);
      var bp = res.subs_by_plan || [];
      FF.updateChart("saPlan", bp.map(function(r){return r.plan_name;}), [bp.map(function(r){return parseInt(r.total,10);})]);
    });
  }
  build();
  FF.onRefresh(refresh);
})();
</script>';

require __DIR__ . '/../views/super-admin/header.php';
?>
<div class="d-flex justify-content-end mb-3">
  <button type="button" class="btn btn-ff-outline btn-sm" data-ff-refresh><i class="bi bi-arrow-clockwise me-1"></i>Refresh live data</button>
</div>
<div class="row g-3 mb-1">
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-buildings"></i></span><div class="k-label">Gyms (tenants)</div><div class="k-value" data-sa-stat="tenants_total"><?= (int) $tStats['total'] ?></div><div class="small" style="color:var(--ff-muted)"><?= (int) $tStats['active'] ?> active · <?= (int) $tStats['trial'] ?> trial · <?= (int) $tStats['suspended'] ?> suspended</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-people"></i></span><div class="k-label">Platform users</div><div class="k-value" data-sa-stat="users_total"><?= (int) $tStats['users'] ?></div><div class="small" style="color:var(--ff-muted)"><span data-sa-stat="members_total"><?= (int) (Database::scalar('SELECT COUNT(*) FROM members') ?? 0) ?></span> gym members</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-graph-up-arrow"></i></span><div class="k-label">MRR / ARR</div><div class="k-value" data-sa-stat="mrr_label"><?= money($subStats['mrr']) ?></div><div class="small" style="color:var(--ff-muted)">ARR <span data-sa-stat="arr_label"><?= money($subStats['arr']) ?></span></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><span class="k-icon"><i class="bi bi-cash-stack"></i></span><div class="k-label">Verified revenue</div><div class="k-value" data-sa-stat="verified_label"><?= money($payTotals['verified']) ?></div><div class="small text-warning"><span data-sa-stat="pending_label"><?= money($payTotals['pending']) ?></span> pending verification</div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel mb-3"><h5>Verified revenue — last 6 months</h5><canvas id="saRevChart" class="chart"></canvas></div>
    <div class="panel">
      <h5>Pending gym approvals</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Gym</th><th>Plan</th><th>Status</th><th>Trial ends</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($pendingGyms as $t): ?>
          <tr><td class="fw-bold"><?= e($t['name']) ?><br><small style="color:var(--ff-muted)"><?= e($t['email'] ?? '') ?> · <?= e($t['city'] ?? '') ?></small></td>
            <td><?= e($t['plan_name'] ?? '—') ?></td>
            <td><span class="status-pill st-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
            <td><?= e($t['trial_ends_at'] ? substr((string) $t['trial_ends_at'], 0, 10) : '—') ?></td>
            <td class="text-end"><form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="tenant_id" value="<?= (int) $t['id'] ?>">
              <button name="action" value="approve" class="btn btn-sm btn-outline-success">Approve</button>
              <button name="action" value="suspend" class="btn btn-sm btn-outline-danger">Suspend</button></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$pendingGyms): ?><tr><td colspan="5" style="color:var(--ff-muted)">No gyms awaiting approval.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="panel mb-3"><h5>Subscriptions by plan</h5><canvas id="saPlanChart" class="chart"></canvas></div>
    <div class="panel mb-3">
      <h5>Newest gyms</h5>
      <?php foreach ($recentTenants as $t): ?>
        <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--ff-border)">
          <span><strong><?= e($t['name']) ?></strong><br><small style="color:var(--ff-muted)"><?= e($t['plan_name'] ?? 'No plan') ?> · <?= (int) $t['member_count'] ?> members</small></span>
          <span class="status-pill st-<?= e($t['status']) ?>"><?= e($t['status']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$recentTenants): ?><p class="small mb-0" style="color:var(--ff-muted)">No gyms yet.</p><?php endif; ?>
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
<?php require __DIR__ . '/../views/super-admin/footer.php'; ?>
