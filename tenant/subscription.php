<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Accountant'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Subscription & Billing';
$activeNav = 'subscription';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'change') {
        $plan = SubscriptionPlan::find((int) ($_POST['plan_id'] ?? 0));
        $cycle = ($_POST['cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        if ($plan !== null) {
            $price = $cycle === 'yearly' ? (float) $plan['price_yearly'] : (float) $plan['price_monthly'];
            $coupon = null;
            $code = trim($_POST['coupon'] ?? '');
            if ($code !== '') {
                $coupon = Coupon::validateCode($code);
                if ($coupon === null) {
                    flash('error', 'Coupon "' . $code . '" is not valid.');
                    redirect(url('tenant/subscription.php'));
                }
                $price = Coupon::applyDiscount($price, $coupon);
            }
            Subscription::changePlan($tid, (int) $plan['id'], $cycle);
            if ($coupon) Coupon::redeem((int) $coupon['id']);
            if ($price > 0) {
                $pid = Payment::recordManual($tid, [
                    'tenant_id' => $tid, 'user_id' => (int) current_user()['id'],
                    'purpose' => 'subscription', 'description' => $plan['name'] . ' plan — ' . $cycle,
                    'amount' => $price, 'method' => $_POST['method'] ?? 'manual', 'reference' => 'SUB-' . date('Ymd'),
                ]);
                Invoice::generate($tid, $pid, $plan['name'] . ' subscription (' . $cycle . ')', $price, 'unpaid');
            }
            Database::update('tenants', ['status' => 'active'], 'id = :wid', ['wid' => $tid]);
            log_activity($tid, 'subscription.changed', 'subscriptions', (int) $plan['id'], $plan['name'] . ' ' . $cycle);
            flash('success', 'Subscription changed to ' . $plan['name'] . ' (' . $cycle . ').' . ($price > 0 ? ' A payment of ' . money($price) . ' was recorded as PENDING and an invoice generated — it activates financially once verified by the platform.' : ''));
        }
    } elseif ($action === 'cancel') {
        Subscription::cancel($tid);
        log_activity($tid, 'subscription.cancelled', 'subscriptions', null);
        flash('success', 'Subscription cancelled. Your data stays available until the period ends, then the grace period applies.');
    } elseif ($action === 'renew') {
        $cur = Subscription::current($tid);
        if ($cur !== null) {
            Subscription::changePlan($tid, (int) $cur['plan_id'], (string) $cur['billing_cycle']);
            flash('success', 'Subscription renewed for a new ' . $cur['billing_cycle'] . ' period.');
        }
    }
    redirect(url('tenant/subscription.php'));
}

$sub = active_subscription($tid);
$plans = SubscriptionPlan::active();
$history = Database::fetchAll('SELECT s.*, p.name AS plan_name FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.tenant_id = ? ORDER BY s.id DESC LIMIT 10', [$tid]);
$payments = Database::fetchAll('SELECT * FROM payments WHERE tenant_id = ? AND purpose = "subscription" ORDER BY id DESC LIMIT 10', [$tid]);
$usage = [
    'Members' => [Member::count($tid, ' AND status != "cancelled"'), (int) ($sub['max_members'] ?? 0)],
    'Trainers' => [Trainer::count($tid, ' AND status = "active"'), (int) ($sub['max_trainers'] ?? 0)],
    'Branches' => [Branch::count($tid, ' AND status = "active"'), (int) ($sub['max_branches'] ?? 0)],
    'Classes' => [GymClass::count($tid, ' AND status = "active"'), (int) ($sub['max_classes'] ?? 0)],
];
require __DIR__ . '/../views/tenant/header.php';
?>
<?php if ($sub): ?>
<div class="panel mb-3">
  <div class="row align-items-center">
    <div class="col-lg-6">
      <h4 class="fw-bold mb-1">Current plan: <span class="text-orange"><?= e($sub['plan_name']) ?></span></h4>
      <p class="mb-1" style="color:var(--ff-muted)">Status: <strong class="text-white"><?= e($sub['status']) ?></strong> · Cycle: <?= e($sub['billing_cycle']) ?>
      <?php if (!empty($sub['ends_at'])): ?> · Period ends <?= e(substr((string) $sub['ends_at'], 0, 10)) ?><?php endif; ?>
      <?php if ($sub['status'] === 'trial' && !empty($sub['trial_ends_at'])): ?> · Trial ends <?= e(substr((string) $sub['trial_ends_at'], 0, 10)) ?><?php endif; ?></p>
      <p class="small mb-0" style="color:var(--ff-muted)">After expiry a <?= (int) (APP_CONFIG['grace_days'] ?? 7) ?>-day grace period applies before the workspace is restricted.</p>
    </div>
    <div class="col-lg-6">
      <?php foreach ($usage as $label => [$used, $limit]): ?>
      <div class="mb-2"><div class="d-flex justify-content-between small"><span><?= $label ?></span><span><?= $used ?> / <?= $limit < 0 ? '∞' : $limit ?></span></div>
        <div class="progress"><div class="progress-bar" style="width:<?= $limit > 0 ? min(100, round($used / $limit * 100)) : 8 ?>%"></div></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="renew"><button class="btn btn-ff btn-sm">Renew current plan</button></form>
  <form method="post" class="d-inline" onsubmit="return confirm('Cancel your subscription?')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-sm btn-outline-danger">Cancel subscription</button></form>
</div>
<?php endif; ?>

<h5 class="fw-bold mt-4 mb-3">Upgrade or downgrade</h5>
<div class="row g-3">
  <?php foreach ($plans as $p): ?>
  <div class="col-md-6 col-xl-4">
    <div class="price-card h-100 <?= $sub && (int) $sub['plan_id'] === (int) $p['id'] ? 'featured' : '' ?>">
      <h5 class="fw-bold"><?= e($p['name']) ?> <?= $sub && (int) $sub['plan_id'] === (int) $p['id'] ? '<span class="badge badge-ff">Current</span>' : '' ?></h5>
      <div class="amount text-orange"><?= money($p['price_monthly']) ?><span class="fs-6" style="color:var(--ff-muted)">/mo</span></div>
      <p class="small" style="color:var(--ff-muted)"><?= money($p['price_yearly']) ?>/yr · <?= (int) $p['max_members'] < 0 ? 'Unlimited' : (int) $p['max_members'] ?> members · <?= (int) $p['max_branches'] < 0 ? 'Unlimited' : (int) $p['max_branches'] ?> branches</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="change"><input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
        <div class="row g-2">
          <div class="col-6"><select class="form-select form-select-sm" name="cycle"><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></div>
          <div class="col-6"><select class="form-select form-select-sm" name="method"><option value="card">Card</option><option value="bank_transfer">Transfer</option><option value="manual">Manual</option></select></div>
          <div class="col-12"><input class="form-control form-control-sm" name="coupon" placeholder="Coupon code (optional)"></div>
        </div>
        <button class="btn btn-ff w-100 mt-2" <?= $sub && (int) $sub['plan_id'] === (int) $p['id'] ? 'disabled' : '' ?>>Switch to <?= e($p['name']) ?></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mt-2">
  <div class="col-lg-6"><div class="panel h-100"><h5>Subscription history</h5>
    <table class="table align-middle mb-0"><thead><tr><th>Plan</th><th>Cycle</th><th>Status</th><th>Period</th></tr></thead><tbody>
    <?php foreach ($history as $h): ?><tr><td class="fw-bold"><?= e($h['plan_name']) ?></td><td><?= e($h['billing_cycle']) ?></td><td><span class="status-pill st-<?= e($h['status']) ?>"><?= e($h['status']) ?></span></td><td class="small"><?= e(substr((string) $h['starts_at'], 0, 10)) ?> → <?= e(substr((string) ($h['ends_at'] ?? ''), 0, 10)) ?></td></tr><?php endforeach; ?>
    <?php if (!$history): ?><tr><td colspan="4" style="color:var(--ff-muted)">No history yet.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  <div class="col-lg-6"><div class="panel h-100"><h5>Subscription payments</h5>
    <table class="table align-middle mb-0"><thead><tr><th>Description</th><th>Amount</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($payments as $p): ?><tr><td><?= e($p['description'] ?? '') ?></td><td><?= money($p['amount']) ?></td><td><span class="status-pill st-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td></tr><?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="3" style="color:var(--ff-muted)">No subscription payments yet.</td></tr><?php endif; ?>
    </tbody></table>
    <p class="small mt-2 mb-0" style="color:var(--ff-muted)">Subscription payments recorded here stay <strong>pending</strong> until the platform verifies them.</p>
  </div></div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
