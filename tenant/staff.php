<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Staff & Invitations';
$activeNav = 'staff';

$inviteLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'invite' && can('staff.manage')) {
        if (!plan_allows($tid, 'staff')) {
            flash('error', plan_limit_message('staff'));
            redirect(url('tenant/staff.php'));
        }
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid staff email.');
        } elseif (User::findByEmail($email) !== null) {
            flash('error', 'That email already has an account.');
        } else {
            $token = User::invite($tid, (int) $_POST['role_id'], trim($_POST['name'] ?? ''), $email);
            $_SESSION['last_invite_link'] = url('public/accept-invite.php') . '?token=' . $token;
            flash('success', 'Invitation created for ' . $email . '. Share the acceptance link below (in production it is emailed).');
        }
    } elseif ($action === 'status') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        if ($uid !== (int) current_user()['id']) {
            Database::query('UPDATE users SET status = ? WHERE id = ? AND tenant_id = ?', [$_POST['status'] === 'suspended' ? 'suspended' : 'active', $uid, $tid]);
            flash('success', 'Staff status updated.');
        }
    }
    redirect(url('tenant/staff.php'));
}
$inviteLink = $_SESSION['last_invite_link'] ?? null;
unset($_SESSION['last_invite_link']);

$staff = User::withRole($tid, 200);
$roles = Database::fetchAll('SELECT * FROM roles WHERE name != "Super Admin" ORDER BY id');
$sub = active_subscription($tid);
require __DIR__ . '/../views/tenant/header.php';
?>
<?php if ($sub): ?><div class="alert alert-dark" style="border:1px solid var(--ff-border)">Staff seats on <strong><?= e($sub['plan_name']) ?></strong>: <?= (int) $sub['max_staff'] < 0 ? 'Unlimited' : (int) $sub['max_staff'] ?> · currently <?= count(array_filter($staff, fn($s) => $s['role_name'] !== 'Member')) ?> staff accounts.</div><?php endif; ?>
<?php if ($inviteLink): ?><div class="alert alert-info"><strong>Invitation link:</strong><br><a href="<?= e($inviteLink) ?>"><?= e($inviteLink) ?></a></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Person</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($staff as $s): ?>
        <tr>
          <td><span class="fw-bold"><?= e($s['name']) ?></span><br><small style="color:var(--ff-muted)"><?= e($s['email']) ?></small></td>
          <td><?= e($s['role_name']) ?></td>
          <td><span class="status-pill st-<?= e($s['status']) ?>"><?= e($s['status']) ?></span></td>
          <td><?= e($s['last_login_at'] ? substr((string) $s['last_login_at'], 0, 16) : 'Never') ?></td>
          <td class="text-end">
            <?php if ((int) $s['id'] !== (int) current_user()['id'] && $s['status'] !== 'invited'): ?>
            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="user_id" value="<?= (int) $s['id'] ?>">
              <?php if ($s['status'] === 'active'): ?><input type="hidden" name="status" value="suspended"><button class="btn btn-sm btn-outline-danger">Suspend</button>
              <?php else: ?><input type="hidden" name="status" value="active"><button class="btn btn-sm btn-outline-success">Activate</button><?php endif; ?>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div></div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5>Invite staff member</h5>
      <p class="small" style="color:var(--ff-muted)">The invitee sets their own password via the acceptance link. Invitations expire in 7 days.</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="invite">
        <div class="mb-2"><label class="form-label">Full name</label><input class="form-control" name="name" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
        <div class="mb-2"><label class="form-label">Role</label><select class="form-select" name="role_id">
          <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-ff w-100">Send invitation</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
