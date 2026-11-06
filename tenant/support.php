<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Support Tickets';
$activeNav = 'support';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($subject !== '' && $message !== '') {
        SupportTicket::create([
            'tenant_id' => $tid,
            'user_id' => (int) current_user()['id'],
            'subject' => $subject,
            'message' => $message,
            'priority' => $_POST['priority'] ?? 'medium',
            'status' => 'open',
        ]);
        flash('success', 'Ticket raised. The FitFlow Pro team will reply here.');
    } else {
        flash('error', 'Subject and message are required.');
    }
    redirect(url('tenant/support.php'));
}

$tickets = SupportTicket::all($tid, 'id DESC', 100);
$announcements = Database::fetchAll('SELECT * FROM announcements WHERE is_active = 1 ORDER BY id DESC LIMIT 5');
require __DIR__ . '/../views/tenant/header.php';
?>
<?php foreach ($announcements as $a): ?>
<div class="alert alert-dark" style="border:1px solid var(--ff-border)"><i class="bi bi-megaphone text-orange me-2"></i><strong><?= e($a['title']) ?></strong> — <?= e($a['body']) ?></div>
<?php endforeach; ?>
<div class="row g-3">
  <div class="col-lg-7">
    <?php foreach ($tickets as $tk): ?>
    <div class="panel mb-3">
      <div class="d-flex justify-content-between"><h6 class="fw-bold mb-1"><?= e($tk['subject']) ?></h6><span class="status-pill st-<?= e($tk['status']) ?>"><?= e(str_replace('_', ' ', $tk['status'])) ?></span></div>
      <small style="color:var(--ff-muted)"><?= e(substr((string) $tk['created_at'], 0, 16)) ?> · priority <?= e($tk['priority']) ?></small>
      <p class="mt-2 mb-2"><?= nl2br(e($tk['message'])) ?></p>
      <?php if (!empty($tk['reply'])): ?><div class="p-2 rounded" style="background:#101012"><small class="text-orange fw-bold">FitFlow Pro reply</small><p class="mb-0 small"><?= nl2br(e($tk['reply'])) ?></p></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$tickets): ?><div class="panel" style="color:var(--ff-muted)">No tickets yet. Raise one whenever you need platform help.</div><?php endif; ?>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <h5>Raise a ticket</h5>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-2"><label class="form-label">Subject</label><input class="form-control" name="subject" required></div>
        <div class="mb-2"><label class="form-label">Priority</label><select class="form-select" name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
        <div class="mb-2"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="4" required></textarea></div>
        <button class="btn btn-ff w-100">Submit ticket</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
