<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Platform Invoices';
$activeNav = 'invoices';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['invoice_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0 && $action === 'void') {
        Invoice::updateById($id, ['status' => 'void'], null);
        flash('success', 'Invoice voided.');
    } elseif ($id > 0 && $action === 'paid') {
        Invoice::updateById($id, ['status' => 'paid'], null);
        flash('success', 'Invoice marked paid.');
    }
    redirect(url('admin/invoices.php'));
}

$invoices = Database::fetchAll(
    'SELECT i.*, t.name AS tenant_name FROM invoices i LEFT JOIN tenants t ON t.id = i.tenant_id ORDER BY i.id DESC LIMIT 200'
);
require __DIR__ . '/../views/admin/header.php';
?>
<div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Number</th><th>Gym</th><th>Description</th><th>Amount</th><th>Status</th><th>Issued</th><th>Due</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($invoices as $inv): ?>
    <tr><td class="fw-bold"><?= e($inv['number']) ?></td><td><?= e($inv['tenant_name'] ?? 'Platform') ?></td>
      <td><?= e($inv['description'] ?? '') ?></td><td><?= money($inv['amount']) ?></td>
      <td><span class="status-pill st-<?= e($inv['status']) ?>"><?= e($inv['status']) ?></span></td>
      <td><?= e($inv['issued_at'] ? substr((string) $inv['issued_at'], 0, 10) : '') ?></td>
      <td><?= e($inv['due_at'] ? substr((string) $inv['due_at'], 0, 10) : '') ?></td>
      <td class="text-end">
        <?php if ($inv['status'] === 'unpaid'): ?>
        <form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= (int) $inv['id'] ?>">
          <button name="action" value="paid" class="btn btn-sm btn-outline-success">Mark paid</button>
          <button name="action" value="void" class="btn btn-sm btn-outline-danger">Void</button>
        </form>
        <?php endif; ?>
      </td></tr>
  <?php endforeach; ?>
  <?php if (!$invoices): ?><tr><td colspan="8" style="color:var(--ff-muted)">No invoices yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
