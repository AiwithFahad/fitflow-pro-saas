<?php
/** Printable payment receipt (standalone page, print stylesheet friendly). */
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Accountant', 'Branch Manager', 'Receptionist'], Auth::homeFor(current_user()));
$tid = tenant_id();
$paymentId = (int) ($_GET['payment_id'] ?? 0);
$receipt = $paymentId > 0 ? Database::fetch(
    'SELECT p.*, m.name AS member_name, m.member_code, t.name AS tenant_name
     FROM payments p
     LEFT JOIN members m ON m.id = p.member_id
     LEFT JOIN tenants t ON t.id = p.tenant_id
     WHERE p.id = ? AND p.tenant_id = ?',
    [$paymentId, $tid]
) : null;
$invoice = $receipt ? Database::fetch('SELECT * FROM invoices WHERE payment_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1', [$paymentId, $tid]) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receipt <?= $receipt ? e($receipt['reference'] ?? ('PAY-' . $receipt['id'])) : '' ?> · FitFlow Pro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('public/assets/css/style.css')) ?>" rel="stylesheet">
<style>@media print { .no-print { display: none !important; } body { background: #fff; } }</style>
</head>
<body>
<div class="container py-5" style="max-width:640px">
  <?php if (!$receipt): ?>
    <div class="panel text-center"><h5>Receipt not found</h5><p class="small" style="color:var(--ff-muted)">This payment does not exist for your gym.</p>
    <a class="btn btn-ff" href="<?= e(url('admin/payments.php')) ?>">Back to payments</a></div>
  <?php else: ?>
  <div class="panel" id="receiptPanel">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <span class="brand-mark"><span class="bm-icon"><i class="bi bi-lightning-charge-fill"></i></span>FitFlow<span class="text-orange">Pro</span></span>
        <div class="small" style="color:var(--ff-muted)"><?= e($receipt['tenant_name'] ?? '') ?></div>
      </div>
      <button class="btn btn-ff no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print receipt</button>
    </div>
    <h4 class="mb-3">Payment receipt</h4>
    <table class="table table-sm mb-0">
      <tr><th style="width:38%">Receipt #</th><td><?= e($receipt['reference'] ?? ('PAY-' . $receipt['id'])) ?></td></tr>
      <?php if ($invoice): ?><tr><th>Invoice</th><td><?= e($invoice['number']) ?> (<?= e($invoice['status']) ?>)</td></tr><?php endif; ?>
      <tr><th>Member</th><td><?= e($receipt['member_name'] ?? '—') ?><?= $receipt['member_code'] ? ' · ' . e($receipt['member_code']) : '' ?></td></tr>
      <tr><th>Description</th><td><?= e($receipt['description'] ?? '') ?></td></tr>
      <tr><th>Purpose</th><td><?= e($receipt['purpose']) ?></td></tr>
      <tr><th>Amount</th><td class="fw-bold fs-5"><?= money($receipt['amount']) ?></td></tr>
      <tr><th>Method</th><td><?= e($receipt['method']) ?></td></tr>
      <tr><th>Status</th><td><span class="status-pill st-<?= e($receipt['status']) ?>"><?= e($receipt['status']) ?></span>
        <?= $receipt['status'] === 'pending' ? '<small style="color:var(--ff-muted)">(recorded — awaiting verification)</small>' : '' ?></td></tr>
      <tr><th>Date</th><td><?= e($receipt['paid_at'] ?? $receipt['created_at']) ?></td></tr>
    </table>
    <p class="small mt-3 mb-0" style="color:var(--ff-muted)">Thank you for training with <?= e($receipt['tenant_name'] ?? 'us') ?>. Keep this receipt for your records.</p>
  </div>
  <div class="text-center mt-3 no-print"><a href="<?= e(url('admin/payments.php')) ?>">← Back to payments</a></div>
  <?php endif; ?>
</div>
</body>
</html>
