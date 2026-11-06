<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Accountant', 'Receptionist'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Payments & Invoices';
$activeNav = 'payments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'record' && can('payments.manage')) {
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount > 0) {
            $pid = Payment::recordManual($tid, [
                'tenant_id' => $tid,
                'user_id' => (int) current_user()['id'],
                'member_id' => (int) ($_POST['member_id'] ?? 0) ?: null,
                'purpose' => $_POST['purpose'] ?? 'membership',
                'description' => trim($_POST['description'] ?? ''),
                'amount' => $amount,
                'method' => $_POST['method'] ?? 'manual',
                'reference' => trim($_POST['reference'] ?? '') ?: null,
            ]);
            Invoice::generate($tid, $pid, trim($_POST['description'] ?? 'Payment'), $amount, 'unpaid');
            flash('success', 'Payment recorded as PENDING. Verify it once the money is confirmed — only verified payments count as revenue.');
        } else {
            flash('error', 'Enter a valid amount.');
        }
    } elseif ($action === 'verify' && can('payments.verify')) {
        $pid = (int) ($_POST['payment_id'] ?? 0);
        Payment::verify($pid, (int) current_user()['id']);
        Database::query('UPDATE invoices SET status = "paid" WHERE payment_id = ? AND tenant_id = ? AND status = "unpaid"', [$pid, $tid]);
        log_activity($tid, 'payment.verified', 'payments', $pid);
        flash('success', 'Payment verified; linked invoice marked paid.');
    } elseif ($action === 'fail' && can('payments.verify')) {
        Database::query('UPDATE payments SET status = "failed" WHERE id = ? AND tenant_id = ? AND status = "pending"', [(int) $_POST['payment_id'], $tid]);
        flash('success', 'Payment marked failed.');
    }
    redirect(url('tenant/payments.php'));
}

$payments = Payment::recent($tid, 100);
$invoices = Invoice::all($tid, 'id DESC', 100);
$totals = Payment::totals($tid);
$members = Member::all($tid, 'name ASC', 300);
$receipt = null;
if (!empty($_GET['receipt'])) {
    $receipt = Database::fetch(
        'SELECT p.*, m.name AS member_name FROM payments p LEFT JOIN members m ON m.id = p.member_id WHERE p.id = ? AND p.tenant_id = ?',
        [(int) $_GET['receipt'], $tid]
    );
}
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3 mb-1">
  <div class="col-md-4"><div class="kpi"><div class="k-label">Verified revenue</div><div class="k-value"><?= money($totals['verified']) ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Pending verification</div><div class="k-value"><?= money($totals['pending']) ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="k-label">Transactions</div><div class="k-value"><?= $totals['count'] ?></div></div></div>
</div>

<?php if ($receipt): ?>
<div class="panel mb-3" id="receiptPanel">
  <div class="d-flex justify-content-between"><h5><i class="bi bi-receipt text-orange me-1"></i>Payment receipt</h5>
    <button class="btn btn-sm btn-ff-outline" onclick="window.print()">Print receipt</button></div>
  <table class="table table-sm mb-0" style="max-width:520px">
    <tr><th>Receipt #</th><td><?= e($receipt['reference'] ?? ('PAY-' . $receipt['id'])) ?></td></tr>
    <tr><th>Member</th><td><?= e($receipt['member_name'] ?? '—') ?></td></tr>
    <tr><th>Description</th><td><?= e($receipt['description'] ?? '') ?></td></tr>
    <tr><th>Amount</th><td class="fw-bold"><?= money($receipt['amount']) ?></td></tr>
    <tr><th>Method</th><td><?= e($receipt['method']) ?></td></tr>
    <tr><th>Status</th><td><span class="status-pill st-<?= e($receipt['status']) ?>"><?= e($receipt['status']) ?></span> <?= $receipt['status'] === 'pending' ? '<small style="color:var(--ff-muted)">(unverified — not yet confirmed received)</small>' : '' ?></td></tr>
    <tr><th>Date</th><td><?= e($receipt['paid_at'] ?? $receipt['created_at']) ?></td></tr>
  </table>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel">
      <h5>Payments</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Description</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr><td><?= (int) $p['id'] ?></td><td><?= e($p['description'] ?? '') ?><br><small style="color:var(--ff-muted)"><?= e($p['purpose']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></small></td>
            <td class="fw-bold"><?= money($p['amount']) ?></td><td><?= e($p['method']) ?></td>
            <td><span class="status-pill st-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
            <td><?= e(substr((string) ($p['paid_at'] ?? $p['created_at']), 0, 10)) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-light" href="?receipt=<?= (int) $p['id'] ?>">Receipt</a>
              <?php if ($p['status'] === 'pending' && can('payments.verify')): ?>
              <form method="post" class="d-inline-flex gap-1 ms-1"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                <button name="action" value="verify" class="btn btn-sm btn-outline-success">Verify</button>
                <button name="action" value="fail" class="btn btn-sm btn-outline-danger">Fail</button></form>
              <?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="7" style="color:var(--ff-muted)">No payments yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
    <div class="panel mt-3">
      <h5>Invoices</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Number</th><th>Description</th><th>Amount</th><th>Status</th><th>Due</th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $inv): ?>
          <tr><td class="fw-bold"><?= e($inv['number']) ?></td><td><?= e($inv['description'] ?? '') ?></td><td><?= money($inv['amount']) ?></td>
            <td><span class="status-pill st-<?= e($inv['status']) ?>"><?= e($inv['status']) ?></span></td><td><?= e($inv['due_at'] ? substr((string) $inv['due_at'], 0, 10) : '') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$invoices): ?><tr><td colspan="5" style="color:var(--ff-muted)">No invoices yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5>Record a payment</h5>
      <p class="small" style="color:var(--ff-muted)">Recorded payments start as <strong>pending</strong>. They become revenue only after verification.</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="record">
        <div class="mb-2"><label class="form-label">Member (optional)</label><select class="form-select" name="member_id"><option value="">—</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" required placeholder="e.g. PT package — 10 sessions"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Amount ($)</label><input type="number" step="0.01" class="form-control" name="amount" required></div>
          <div class="col-6 mb-2"><label class="form-label">Purpose</label><select class="form-select" name="purpose"><option value="membership">Membership</option><option value="other">Other</option></select></div>
          <div class="col-6 mb-2"><label class="form-label">Method</label><select class="form-select" name="method"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option><option value="manual">Manual</option></select></div>
          <div class="col-6 mb-2"><label class="form-label">Reference</label><input class="form-control" name="reference" placeholder="Optional"></div>
        </div>
        <button class="btn btn-ff w-100">Record (pending)</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
