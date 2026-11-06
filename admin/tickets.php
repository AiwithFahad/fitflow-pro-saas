<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Support Tickets';
$activeNav = 'tickets';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['ticket_id'] ?? 0);
    if ($id > 0) {
        $data = ['status' => $_POST['status'] ?? 'in_progress'];
        if (!empty($_POST['reply'])) {
            $data['reply'] = trim($_POST['reply']);
        }
        if (!in_array($data['status'], ['open', 'in_progress', 'resolved', 'closed'], true)) {
            $data['status'] = 'in_progress';
        }
        SupportTicket::updateById($id, $data, null);
        flash('success', 'Ticket updated.');
    }
    redirect(url('admin/tickets.php'));
}

$tickets = Database::fetchAll(
    'SELECT st.*, t.name AS tenant_name, u.name AS user_name FROM support_tickets st
     LEFT JOIN tenants t ON t.id = st.tenant_id LEFT JOIN users u ON u.id = st.user_id
     ORDER BY FIELD(st.status,"open","in_progress","resolved","closed"), st.id DESC LIMIT 100'
);
require __DIR__ . '/../views/admin/header.php';
?>
<div class="row g-3">
<?php foreach ($tickets as $tk): ?>
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="d-flex justify-content-between align-items-start">
        <h6 class="fw-bold mb-1"><?= e($tk['subject']) ?></h6>
        <span class="status-pill st-<?= e($tk['status']) ?>"><?= e(str_replace('_', ' ', $tk['status'])) ?></span>
      </div>
      <small style="color:var(--ff-muted)"><?= e($tk['tenant_name'] ?? 'Website / platform') ?><?= $tk['user_name'] ? ' · ' . e($tk['user_name']) : '' ?> · <?= e(substr((string) $tk['created_at'], 0, 16)) ?> · priority: <?= e($tk['priority']) ?></small>
      <p class="mt-2 mb-2"><?= nl2br(e($tk['message'])) ?></p>
      <?php if (!empty($tk['reply'])): ?><div class="p-2 rounded" style="background:#101012"><small class="text-orange fw-bold">Platform reply</small><p class="mb-0 small"><?= nl2br(e($tk['reply'])) ?></p></div><?php endif; ?>
      <form method="post" class="mt-3">
        <?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int) $tk['id'] ?>">
        <div class="mb-2"><textarea class="form-control" name="reply" rows="2" placeholder="Write a reply…"></textarea></div>
        <div class="d-flex gap-2">
          <select class="form-select" name="status">
            <?php foreach (['open', 'in_progress', 'resolved', 'closed'] as $s): ?><option value="<?= $s ?>" <?= $tk['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-ff">Update</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$tickets): ?><div class="col-12"><div class="panel" style="color:var(--ff-muted)">No support tickets yet.</div></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
