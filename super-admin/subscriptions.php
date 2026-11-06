<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Subscriptions';
$activeNav = 'subscriptions';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $tenantId = (int) ($_POST['tenant_id'] ?? 0);
    if ($action === 'change_plan' && $tenantId > 0 && !empty($_POST['plan_id'])) {
        $cycle = ($_POST['billing_cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        Subscription::changePlan($tenantId, (int) $_POST['plan_id'], $cycle);
        Tenant::setStatus($tenantId, 'active');
        log_activity(null, 'subscription.plan_changed', 'subscriptions', $tenantId, 'Plan #' . (int) $_POST['plan_id'] . ' (' . $cycle . ')');
        flash('success', 'Subscription plan changed; new billing period started today.');
    } elseif ($action === 'cancel' && $tenantId > 0) {
        Subscription::cancel($tenantId);
        log_activity(null, 'subscription.cancelled', 'subscriptions', $tenantId);
        flash('success', 'Subscription cancelled.');
    } elseif ($action === 'extend' && $tenantId > 0) {
        $days = max(1, min(365, (int) ($_POST['days'] ?? 30)));
        Database::query('UPDATE subscriptions SET ends_at = DATE_ADD(COALESCE(ends_at, NOW()), INTERVAL ? DAY), status = IF(status IN ("expired","past_due"), "active", status) WHERE tenant_id = ? AND status IN ("active","trial","grace","expired","past_due")', [$days, $tenantId]);
        log_activity(null, 'subscription.extended', 'subscriptions', $tenantId, '+' . $days . ' days');
        flash('success', 'Subscription extended by ' . $days . ' days.');
    }
    redirect(url('super-admin/subscriptions.php'));
}

$stats = Subscription::stats();
$subs = Database::fetchAll(
    'SELECT s.*, t.name AS tenant_name, p.name AS plan_name, p.price_monthly, p.price_yearly
     FROM subscriptions s
     JOIN tenants t ON t.id = s.tenant_id
     JOIN subscription_plans p ON p.id = s.plan_id
     ORDER BY s.id DESC LIMIT 200'
);
$plans = SubscriptionPlan::active();
require __DIR__ . '/../views/super-admin/header.php';
?>
<div class="row g-3 mb-1">
  <div class="col-md-3"><div class="kpi"><div class="k-label">Active / trial subscriptions</div><div class="k-value"><?= (int) $stats['active'] ?></div></div></div>
  <div class="col-md-3"><div class="kpi"><div class="k-label">Expired / cancelled</div><div class="k-value"><?= (int) $stats['expired'] ?></div></div></div>
  <div class="col-md-3"><div class="kpi"><div class="k-label">MRR</div><div class="k-value"><?= money($stats['mrr']) ?></div></div></div>
  <div class="col-md-3"><div class="kpi"><div class="k-label">ARR</div><div class="k-value"><?= money($stats['arr']) ?></div></div></div>
</div>

<div class="panel">
  <h5>All subscriptions</h5>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Gym</th><th>Plan</th><th>Cycle</th><th>Price</th><th>Status</th><th>Period</th><th>Trial ends</th><th class="text-end">Manage</th></tr></thead>
    <tbody>
    <?php foreach ($subs as $s): ?>
      <tr><td class="fw-bold"><?= e($s['tenant_name']) ?></td>
        <td><?= e($s['plan_name']) ?></td><td><?= e($s['billing_cycle']) ?></td>
        <td><?= money($s['billing_cycle'] === 'yearly' ? $s['price_yearly'] : $s['price_monthly']) ?></td>
        <td><span class="status-pill st-<?= e($s['status']) ?>"><?= e($s['status']) ?></span></td>
        <td class="small"><?= e(substr((string) $s['starts_at'], 0, 10)) ?> → <?= e($s['ends_at'] ? substr((string) $s['ends_at'], 0, 10) : '—') ?></td>
        <td><?= e($s['trial_ends_at'] ? substr((string) $s['trial_ends_at'], 0, 10) : '—') ?></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#planModal" data-tenant="<?= (int) $s['tenant_id'] ?>" data-name="<?= e($s['tenant_name']) ?>">Change plan</button>
          <form method="post" class="d-inline-flex gap-1 ms-1"><?= csrf_field() ?><input type="hidden" name="tenant_id" value="<?= (int) $s['tenant_id'] ?>">
            <input type="number" name="days" value="30" min="1" max="365" class="form-control form-control-sm" style="width:74px" title="Days to extend">
            <button name="action" value="extend" class="btn btn-sm btn-outline-info">Extend</button>
            <?php if (in_array($s['status'], ['active', 'trial', 'grace'], true)): ?><button name="action" value="cancel" class="btn btn-sm btn-outline-danger" formnovalidate>Cancel</button><?php endif; ?>
          </form>
        </td></tr>
    <?php endforeach; ?>
    <?php if (!$subs): ?><tr><td colspan="8" style="color:var(--ff-muted)">No subscriptions yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>

<div class="modal fade" id="planModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post"><div class="modal-header"><h5 class="modal-title">Change plan — <span id="cpGym"></span></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><?= csrf_field() ?><input type="hidden" name="action" value="change_plan"><input type="hidden" name="tenant_id" id="cpTenant">
      <div class="mb-2"><label class="form-label">New plan</label><select class="form-select" name="plan_id">
        <?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> — <?= money($p['price_monthly']) ?>/mo</option><?php endforeach; ?>
      </select></div>
      <div class="mb-2"><label class="form-label">Billing cycle</label><select class="form-select" name="billing_cycle"><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></div>
      <p class="small mb-0" style="color:var(--ff-muted)">The current subscription is cancelled and a fresh period starts today on the new plan. No payment is taken automatically — record it under Payments and verify it there.</p>
    </div>
    <div class="modal-footer"><button class="btn btn-ff">Change plan</button></div>
  </form></div></div></div>
<script>
document.getElementById('planModal').addEventListener('show.bs.modal', function (ev) {
  var b = ev.relatedTarget;
  document.getElementById('cpTenant').value = b.getAttribute('data-tenant');
  document.getElementById('cpGym').textContent = b.getAttribute('data-name');
});
</script>
<?php require __DIR__ . '/../views/super-admin/footer.php'; ?>
