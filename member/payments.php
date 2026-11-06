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
$dashTitle = 'My Payments';
$activeNav = 'payments';

$payments = Database::fetchAll('SELECT * FROM payments WHERE member_id = ? AND tenant_id = ? ORDER BY id DESC', [$member['id'], $tid]);
$invoices = Database::fetchAll(
    'SELECT i.* FROM invoices i JOIN payments p ON p.id = i.payment_id WHERE p.member_id = ? AND i.tenant_id = ? ORDER BY i.id DESC',
    [$member['id'], $tid]
);
$totalPaid = (float) Database::scalar('SELECT COALESCE(SUM(amount),0) FROM payments WHERE member_id = ? AND status = "verified"', [$member['id']]);
require __DIR__ . '/../views/member/header.php';
?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel">
      <h5>Payment history <small style="color:var(--ff-muted)">· verified total <?= money($totalPaid) ?></small></h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Description</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr><td><?= e($p['description'] ?? '') ?><br><small style="color:var(--ff-muted)"><?= e($p['reference'] ?? '') ?></small></td>
            <td class="fw-bold"><?= money($p['amount']) ?></td><td><?= e($p['method']) ?></td>
            <td><span class="status-pill st-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
            <td><?= e(substr((string) ($p['paid_at'] ?? $p['created_at']), 0, 10)) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="5" style="color:var(--ff-muted)">No payments recorded yet.</td></tr><?php endif; ?>
        </tbody></table></div>
      <p class="small mt-3 mb-0" style="color:var(--ff-muted)">"Pending" means the gym has recorded your payment but has not finished verifying it yet. Only verified payments are final receipts.</p>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <h5>My invoices</h5>
      <table class="table align-middle mb-0"><thead><tr><th>Number</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr><td class="fw-bold"><?= e($inv['number']) ?></td><td><?= money($inv['amount']) ?></td><td><span class="status-pill st-<?= e($inv['status']) ?>"><?= e($inv['status']) ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$invoices): ?><tr><td colspan="3" style="color:var(--ff-muted)">No invoices yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/member/footer.php'; ?>
