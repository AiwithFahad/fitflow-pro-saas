<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Platform Payments';
$activeNav = 'payments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'verify' && $id > 0) {
        Payment::verify($id, (int) current_user()['id']);
        // Mark a linked invoice paid on verification.
        Database::query('UPDATE invoices SET status = "paid" WHERE payment_id = ? AND status = "unpaid"', [$id]);
        log_activity(null, 'payment.verified', 'payments', $id);
        flash('success', 'Payment verified and marked as paid.');
    } elseif ($action === 'fail' && $id > 0) {
        Database::update('payments', ['status' => 'failed'], 'id = :wid AND status = "pending"', ['wid' => $id]);
        flash('success', 'Payment marked as failed.');
    }
    redirect(url('admin/payments.php'));
}

$payments = Database::fetchAll(
    'SELECT p.*, t.name AS tenant_name FROM payments p LEFT JOIN tenants t ON t.id = p.tenant_id ORDER BY p.id DESC LIMIT 200'
);
$totals = Payment::totals(null);
require __DIR__ . '/../views/admin/header.php';
?>
<div class="row g-3 mb-1">
  <div class="col-md-4"><div class="kpi"><div class="k-label">Verified revenue</div><div class="k-value"><?= money($totals['verified']) ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Pending verification</div><div class="k-value"><?= money($totals['pending']) ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Transactions</div><div class="k-value"><?= $totals['count'] ?></div></div></div>
</div>
<div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>#</th><th>Gym</th><th>Purpose</th><th>Description</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($payments as $p): ?>
    <tr><td><?= (int) $p['id'] ?></td><td><?= e($p['tenant_name'] ?? 'Platform') ?></td><td><?= e($p['purpose']) ?></td>
      <td><?= e($p['description'] ?? '') ?></td><td class="fw-bold"><?= money($p['amount']) ?></td><td><?= e($p['method']) ?></td>
      <td><span class="status-pill st-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
      <td><?= e(substr((string) $p['created_at'], 0, 10)) ?></td>
      <td class="text-end">
        <?php if ($p['status'] === 'pending'): ?>
        <form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
          <button name="action" value="verify" class="btn btn-sm btn-outline-success">Verify</button>
          <button name="action" value="fail" class="btn btn-sm btn-outline-danger">Fail</button>
        </form>
        <?php endif; ?>
      </td></tr>
  <?php endforeach; ?>
  <?php if (!$payments): ?><tr><td colspan="9" style="color:var(--ff-muted)">No payments recorded yet.</td></tr><?php endif; ?>
  </tbody></table></div>
  <p class="small mt-3 mb-0" style="color:var(--ff-muted)">Manual payments are never auto-verified — a payment only becomes "verified" here after confirmation.</p>
</div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
