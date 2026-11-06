<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Member'], Auth::homeFor(current_user()));
$tid = tenant_id();
$user = current_user();
$member = Database::fetch('SELECT * FROM members WHERE user_id = ? AND tenant_id = ?', [(int) $user['id'], $tid]);
if ($member === null) {
    redirect(url('public/logout.php'));
}
$dashTitle = 'My Membership';
$activeNav = 'membership';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $plan = MembershipPlan::find((int) ($_POST['plan_id'] ?? 0), $tid);
    if ($plan !== null) {
        $coupon = null;
        $code = trim($_POST['coupon'] ?? '');
        if ($code !== '') {
            $coupon = Coupon::validateCode($code);
            if ($coupon === null) {
                flash('error', 'Coupon "' . $code . '" is not valid.');
                redirect(url('member/membership.php'));
            }
        }
        $msId = Membership::enrol($tid, (int) $member['id'], $plan, $coupon ? (int) $coupon['id'] : null);
        $ms = Membership::find($msId, $tid);
        $pid = Payment::recordManual($tid, [
            'tenant_id' => $tid, 'user_id' => (int) $user['id'], 'member_id' => (int) $member['id'],
            'purpose' => 'membership', 'description' => 'Self-service renewal — ' . $plan['name'],
            'amount' => (float) $ms['price'], 'method' => $_POST['method'] ?? 'card', 'reference' => 'SELF-' . $msId,
        ]);
        $inv = Invoice::generate($tid, $pid, 'Membership — ' . $plan['name'], (float) $ms['price'], 'unpaid');
        notify($tid, null, 'Member self-renewed', $member['name'] . ' renewed online (' . $plan['name'] . '). Payment is pending verification. Invoice ' . $inv['number'] . '.', 'info');
        flash('success', 'Membership renewed until ' . $ms['end_date'] . '. Your payment was recorded as PENDING — the gym verifies it shortly. Invoice: ' . $inv['number']);
    }
    redirect(url('member/membership.php'));
}

$history = Database::fetchAll('SELECT * FROM memberships WHERE member_id = ? AND tenant_id = ? ORDER BY id DESC', [$member['id'], $tid]);
$plans = MembershipPlan::active($tid);
require __DIR__ . '/../views/member/header.php';
?>
<div class="panel mb-3">
  <h5>Membership history</h5>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Plan</th><th>Price</th><th>Period</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
      <tr><td class="fw-bold"><?= e($h['plan_name']) ?></td><td><?= money($h['price']) ?></td>
        <td><?= e($h['start_date']) ?> → <?= e($h['end_date']) ?></td>
        <td><span class="status-pill st-<?= e($h['status']) ?>"><?= e($h['status']) ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$history): ?><tr><td colspan="4" style="color:var(--ff-muted)">No memberships yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<h5 class="fw-bold mb-3">Renew or switch plan</h5>
<div class="row g-3">
  <?php foreach ($plans as $p): ?>
  <div class="col-md-6 col-lg-3">
    <div class="price-card h-100">
      <h5 class="fw-bold"><?= e($p['name']) ?></h5>
      <div class="amount text-orange"><?= money($p['price']) ?><span class="fs-6" style="color:var(--ff-muted)"> / <?= (int) $p['duration_days'] ?> days</span></div>
      <p class="small" style="color:var(--ff-muted)"><?= e($p['description'] ?? '') ?></p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
        <select class="form-select form-select-sm mb-2" name="method"><option value="card">Card</option><option value="cash">Cash at desk</option><option value="bank_transfer">Bank transfer</option></select>
        <input class="form-control form-control-sm mb-2" name="coupon" placeholder="Coupon (WELCOME10)">
        <button class="btn btn-ff w-100">Renew with <?= e($p['name']) ?></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<p class="small mt-3" style="color:var(--ff-muted)">Online renewals are recorded as pending payments until the gym verifies them — your membership period starts immediately so your training never stops.</p>
<?php require __DIR__ . '/../views/member/footer.php'; ?>
