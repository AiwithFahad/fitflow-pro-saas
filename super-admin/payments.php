<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Platform Payments';
$activeNav = 'payments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $pid = (int) ($_POST['payment_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'verify' && $pid > 0) {
        $payment = Payment::find($pid);
        if ($payment && $payment['status'] === 'pending') {
            Payment::verify($pid, (int) current_user()['id']);
            Database::query('UPDATE invoices SET status = "paid" WHERE payment_id = ? AND status = "unpaid"', [$pid]);
            log_activity($payment['tenant_id'] ? (int) $payment['tenant_id'] : null, 'payment.verified', 'payments', $pid, 'Verified by Super Admin');
            flash('success', 'Payment verified. It now counts as platform revenue.');
        } else {
            flash('error', 'Only pending payments can be verified.');
        }
    } elseif (($_POST['action'] ?? '') === 'fail' && $pid > 0) {
        Database::query('UPDATE payments SET status = "failed" WHERE id = ? AND status = "pending"', [$pid]);
        flash('success', 'Payment marked failed.');
    }
    redirect(url('super-admin/payments.php'));
}

$totals = Payment::totals(null);
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$where = $statusFilter !== '' ? 'WHERE p.status = ?' : '';
$params = $statusFilter !== '' ? [$statusFilter] : [];
$payments = Database::fetchAll(
    "SELECT p.*, t.name AS tenant_name, m.name AS member_name
     FROM payments p
     LEFT JOIN tenants t ON t.id = p.tenant_id
     LEFT JOIN members m ON m.id = p.member_id
     {$where} ORDER BY p.id DESC LIMIT 200",
    $params
);
require __DIR__ . '/../views/super-admin/header.php';
?>
<div class="row g-3 mb-1">
  <div class="col-md-4"><div class="kpi"><div class="k-label">Verified revenue (all gyms)</div><div class="k-value"><?= money($totals['verified']) ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Pending verification</div><div class="k-value"><?= money($totals['pending']) ?></div><div class="small" style="color:var(--ff-muted)">Not counted as revenue until verified</div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Transactions</div><div class="k-value"><?= (int) $totals['count'] ?></div></div></div>
</div>

<div class="panel">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h5 class="mb-0">Payments</h5>
    <form method="get" class="d-flex gap-2">
      <select class="form-select" name="status" style="max-width:190px">
        <option value="">All statuses</option>
        <?php foreach (['pending', 'verified', 'failed', 'refunded'] as $s): ?><option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-ff">Filter</button>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>#</th><th>Gym / Member</th><th>Description</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $p): ?>
      <tr><td><?= (int) $p['id'] ?></td>
        <td><?= e($p['tenant_name'] ?? 'Platform') ?><?= $p['member_name'] ? '<br><small style="color:var(--ff-muted)">' . e($p['member_name']) . '</small>' : '' ?></td>
        <td><?= e($p['description'] ?? '') ?><br><small style="color:var(--ff-muted)"><?= e($p['purpose']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></small></td>
        <td class="fw-bold"><?= money($p['amount']) ?></td><td><?= e($p['method']) ?></td>
        <td><span class="status-pill st-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
        <td><?= e(substr((string) ($p['paid_at'] ?? $p['created_at']), 0, 10)) ?></td>
        <td class="text-end">
          <?php if ($p['status'] === 'pending'): ?>
          <form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
            <button name="action" value="verify" class="btn btn-sm btn-outline-success">Verify</button>
            <button name="action" value="fail" class="btn btn-sm btn-outline-danger">Fail</button></form>
          <?php else: ?><span class="small" style="color:var(--ff-muted)">—</span><?php endif; ?>
        </td></tr>
    <?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="8" style="color:var(--ff-muted)">No payments match.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/../views/super-admin/footer.php'; ?>
