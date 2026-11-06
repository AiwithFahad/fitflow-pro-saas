<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Receptionist'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Renewals & Expiry';
$activeNav = 'renewals';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'renew') {
        $msId = (int) ($_POST['membership_id'] ?? 0);
        $ms = Membership::find($msId, $tid);
        if ($ms !== null) {
            Membership::renew($tid, $msId);
            $paymentId = Payment::recordManual($tid, [
                'tenant_id' => $tid, 'user_id' => (int) current_user()['id'], 'member_id' => (int) $ms['member_id'],
                'purpose' => 'membership', 'description' => 'Membership renewal — ' . $ms['plan_name'],
                'amount' => (float) $ms['price'], 'method' => $_POST['method'] ?? 'cash', 'reference' => 'REN-' . $msId,
            ]);
            $inv = Invoice::generate($tid, $paymentId, 'Renewal — ' . $ms['plan_name'], (float) $ms['price'], 'unpaid');
            $member = Member::find((int) $ms['member_id'], $tid);
            notify($tid, $member['user_id'] ?? null, 'Membership renewed', 'Your ' . $ms['plan_name'] . ' membership has been renewed. Invoice ' . $inv['number'] . ' was generated.', 'success');
            flash('success', 'Membership renewed. New invoice ' . $inv['number'] . ' created; payment is pending verification.');
        }
    } elseif ($action === 'remind') {
        $member = Member::find((int) ($_POST['member_id'] ?? 0), $tid);
        if ($member !== null) {
            notify($tid, $member['user_id'] ?? null, 'Membership renewal reminder', 'Hi ' . $member['name'] . ', your membership is about to expire. Please visit the front desk or your portal to renew so your training is not interrupted.', 'warning');
            log_activity($tid, 'membership.reminder_sent', 'members', (int) $member['id']);
            flash('success', 'Renewal reminder sent to ' . $member['name'] . ' (in-app notification).');
        }
    }
    redirect(url('tenant/renewals.php'));
}

Membership::sweepExpired($tid);
$expiring = Member::expiringSoon($tid, 7);
$expiring30 = Member::expiringSoon($tid, 30);
$expired = Member::expired($tid);
$expiredRows = Database::fetchAll(
    'SELECT ms.id AS membership_id, m.id AS member_id, m.name, ms.plan_name, ms.end_date, ms.price
     FROM memberships ms JOIN members m ON m.id = ms.member_id
     WHERE ms.tenant_id = ? AND ms.status = "expired" ORDER BY ms.end_date DESC LIMIT 100',
    [$tid]
);
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="panel h-100">
      <h5><i class="bi bi-alarm text-orange me-1"></i>Expiring within 7 days (<?= count($expiring) ?>)</h5>
      <?php if (!$expiring): ?><p class="mb-0" style="color:var(--ff-muted)">Nothing expiring this week.</p><?php endif; ?>
      <?php foreach ($expiring as $x): ?>
        <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--ff-border)">
          <span><strong><?= e($x['name']) ?></strong><br><small style="color:var(--ff-muted)"><?= e($x['plan_name']) ?> · ends <?= e($x['end_date']) ?></small></span>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remind"><input type="hidden" name="member_id" value="<?= (int) $x['id'] ?>"><button class="btn btn-sm btn-ff-outline">Send reminder</button></form>
        </div>
      <?php endforeach; ?>
      <h6 class="fw-bold mt-4">Expiring within 30 days (<?= count($expiring30) ?>)</h6>
      <?php foreach ($expiring30 as $x): ?>
        <div class="d-flex justify-content-between py-1 small"><span><?= e($x['name']) ?></span><span style="color:var(--ff-muted)"><?= e($x['plan_name']) ?> · <?= e($x['end_date']) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel h-100">
      <h5><i class="bi bi-arrow-repeat text-orange me-1"></i>Expired memberships (<?= count($expiredRows) ?>)</h5>
      <?php if (!$expiredRows): ?><p class="mb-0" style="color:var(--ff-muted)">No expired memberships. Nice retention.</p><?php endif; ?>
      <?php foreach ($expiredRows as $x): ?>
        <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--ff-border)">
          <span><strong><?= e($x['name']) ?></strong><br><small style="color:var(--ff-muted)"><?= e($x['plan_name']) ?> · expired <?= e($x['end_date']) ?> · <?= money($x['price']) ?></small></span>
          <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="renew"><input type="hidden" name="membership_id" value="<?= (int) $x['membership_id'] ?>">
            <select class="form-select form-select-sm" name="method" style="width:auto"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Transfer</option></select>
            <button class="btn btn-sm btn-ff">Renew</button></form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
