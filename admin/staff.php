<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Accountant'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Staff & Salaries';
$activeNav = 'staff';
$canStaff = can('staff.manage');
$canFinance = can('finance.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'invite' && $canStaff) {
        if (!plan_allows($tid, 'staff')) {
            flash('error', plan_limit_message('staff'));
            redirect(url('admin/staff.php'));
        }
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $roleId = (int) ($_POST['role_id'] ?? 0);
        if ($name === '' || $email === '' || $roleId <= 0) {
            flash('error', 'Name, email and role are required.');
        } elseif (User::findByEmail(strtolower($email)) !== null) {
            flash('error', 'That email already has an account.');
        } else {
            $token = User::invite($tid, $roleId, $name, $email);
            log_activity($tid, 'staff.invited', 'users', null, $name . ' <' . $email . '>');
            flash('success', 'Invitation created for ' . $email . '. Share this accept link with them: ' . url('public/accept-invite.php') . '?token=' . $token);
        }
    } elseif ($action === 'set_status' && $canStaff && !empty($_POST['user_id'])) {
        $uid = (int) $_POST['user_id'];
        if ($uid !== (int) current_user()['id']) {
            $status = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';
            Database::query('UPDATE users SET status = ? WHERE id = ? AND tenant_id = ?', [$status, $uid, $tid]);
            flash('success', 'Staff account ' . $status . '.');
        }
    } elseif ($action === 'link_trainer' && $canStaff) {
        // Link a Trainer-role user account to a trainer profile (portal access).
        $uid = (int) ($_POST['user_id'] ?? 0);
        $trainerId = (int) ($_POST['trainer_id'] ?? 0);
        if ($uid > 0 && $trainerId > 0) {
            Database::query('UPDATE trainers SET user_id = ? WHERE id = ? AND tenant_id = ?', [$uid, $trainerId, $tid]);
            flash('success', 'Trainer portal account linked.');
        }
    }
    redirect(url('admin/staff.php'));
}

$staff = array_values(array_filter(
    User::withRole($tid, 300),
    fn ($u) => ($u['role_name'] ?? '') !== 'Member'
));
$roles = Database::fetchAll('SELECT id, name FROM roles WHERE name NOT IN ("Super Admin","Member") ORDER BY id');
$trainers = Trainer::all($tid, 'name ASC', 100);
$period = (string) ($_GET['period'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
    $period = date('Y-m');
}
$salaries = StaffSalary::forPeriod($tid, $period);
$salTotal = StaffSalary::totalForPeriod($tid, $period);
$salPaid = StaffSalary::totalForPeriod($tid, $period, 'paid');
$invites = Database::fetchAll('SELECT * FROM invitations WHERE tenant_id = ? ORDER BY id DESC LIMIT 10', [$tid]);

$extraScripts = '<script>
document.addEventListener("DOMContentLoaded", function(){
  document.addEventListener("ff:saved", function(){ setTimeout(function(){ window.location.reload(); }, 500); });
  document.querySelectorAll("[data-salpay]").forEach(function(btn){
    btn.addEventListener("click", function(){
      FF.confirm("Mark this salary as PAID?", "Salary").then(function(ok){
        if (!ok) return;
        FF.api("finance.php", {method:"POST", data:{action:"salary_pay", id: btn.getAttribute("data-salpay")}})
          .then(function(r){ FF.toast(r.message); setTimeout(function(){ window.location.reload(); }, 500); })
          .catch(function(e){ FF.toast(e.message, "error"); });
      });
    });
  });
  document.querySelectorAll("[data-saldel]").forEach(function(btn){
    btn.addEventListener("click", function(){
      FF.confirm("Delete this salary record?", "Salary").then(function(ok){
        if (!ok) return;
        FF.api("finance.php", {method:"POST", data:{action:"salary_delete", id: btn.getAttribute("data-saldel")}})
          .then(function(r){ FF.toast(r.message); setTimeout(function(){ window.location.reload(); }, 500); })
          .catch(function(e){ FF.toast(e.message, "error"); });
      });
    });
  });
});
</script>';

require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel mb-3">
      <h5>Staff accounts</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Person</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($staff as $u): ?>
          <tr><td class="fw-bold"><?= e($u['name']) ?><br><small style="color:var(--ff-muted)"><?= e($u['email']) ?></small></td>
            <td><?= e($u['role_name']) ?></td>
            <td><span class="status-pill st-<?= e($u['status']) ?>"><?= e($u['status']) ?></span></td>
            <td><?= e($u['last_login_at'] ? substr((string) $u['last_login_at'], 0, 16) : 'Never') ?></td>
            <td class="text-end">
              <?php if ($canStaff && (int) $u['id'] !== (int) current_user()['id']): ?>
              <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>">
                <button class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>"><?= $u['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button></form>
              <?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$staff): ?><tr><td colspan="5" style="color:var(--ff-muted)">No staff accounts yet.</td></tr><?php endif; ?>
        </tbody></table></div>
      <?php if ($invites): ?>
      <h6 class="mt-3">Pending invitations</h6>
      <?php foreach ($invites as $inv): ?>
        <div class="small py-1 border-bottom" style="border-color:var(--ff-border)"><?= e($inv['email']) ?> — invited <?= e(substr((string) $inv['created_at'], 0, 10)) ?>, expires <?= e($inv['expires_at'] ? substr((string) $inv['expires_at'], 0, 10) : '—') ?><?= !empty($inv['accepted_at']) ? ' · accepted' : '' ?></div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h5 class="mb-0">Salary records</h5>
        <form method="get" class="d-flex gap-2 align-items-center">
          <label class="form-label mb-0 small">Month</label>
          <input type="month" class="form-control" name="period" value="<?= e($period) ?>" style="max-width:180px">
          <button class="btn btn-sm btn-ff">Load</button>
        </form>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-md-4"><div class="kpi"><div class="k-label">Total payroll (<?= e($period) ?>)</div><div class="k-value fs-4"><?= money($salTotal) ?></div></div></div>
        <div class="col-md-4"><div class="kpi"><div class="k-label">Paid</div><div class="k-value fs-4"><?= money($salPaid) ?></div></div></div>
        <div class="col-md-4"><div class="kpi"><div class="k-label">Pending</div><div class="k-value fs-4"><?= money($salTotal - $salPaid) ?></div></div></div>
      </div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Person</th><th>Role</th><th>Base</th><th>Allow.</th><th>Deduct.</th><th>Net</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($salaries as $s): ?>
          <tr><td class="fw-bold"><?= e($s['person_name']) ?></td><td><?= e($s['role_label'] ?? '') ?></td>
            <td><?= money($s['base_salary']) ?></td><td><?= money($s['allowances']) ?></td><td><?= money($s['deductions']) ?></td>
            <td class="fw-bold"><?= money($s['net_pay']) ?></td>
            <td><span class="status-pill st-<?= $s['status'] === 'paid' ? 'verified' : 'pending' ?>"><?= e($s['status']) ?></span></td>
            <td class="text-end text-nowrap">
              <?php if ($s['status'] === 'pending' && $canFinance): ?><button type="button" class="btn btn-sm btn-outline-success" data-salpay="<?= (int) $s['id'] ?>">Mark paid</button><?php endif; ?>
              <?php if ($canFinance): ?><button type="button" class="btn btn-sm btn-outline-danger" data-saldel="<?= (int) $s['id'] ?>">Delete</button><?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$salaries): ?><tr><td colspan="8" style="color:var(--ff-muted)">No salary records for <?= e($period) ?> yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>

  <div class="col-xl-4">
    <?php if ($canStaff): ?>
    <div class="panel mb-3">
      <h5>Invite staff member</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="invite">
        <div class="mb-2"><label class="form-label">Full name</label><input class="form-control" name="name" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
        <div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role_id">
          <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
        </select></div>
        <button class="btn btn-ff w-100">Create invitation</button>
        <p class="small mt-2 mb-0" style="color:var(--ff-muted)">An accept link is generated after inviting — share it with the staff member so they can set their password.</p>
      </form>
    </div>
    <div class="panel mb-3">
      <h5>Link trainer portal</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="link_trainer">
        <div class="mb-2"><label class="form-label">Trainer-role account</label><select class="form-select" name="user_id">
          <?php foreach ($staff as $u): ?><?php if ($u['role_name'] === 'Trainer'): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option><?php endif; ?><?php endforeach; ?>
        </select></div>
        <div class="mb-3"><label class="form-label">Trainer profile</label><select class="form-select" name="trainer_id">
          <?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?><?= $t['user_id'] ? ' (already linked)' : '' ?></option><?php endforeach; ?>
        </select></div>
        <button class="btn btn-ff-outline w-100">Link accounts</button>
        <p class="small mt-2 mb-0" style="color:var(--ff-muted)">Linked trainers land in the Trainer portal and only see their own assigned members.</p>
      </form>
    </div>
    <?php endif; ?>
    <?php if ($canFinance): ?>
    <div class="panel">
      <h5>New salary record</h5>
      <form data-ajax data-endpoint="finance.php" data-reset>
        <input type="hidden" name="action" value="salary_create">
        <div class="mb-2"><label class="form-label">Person name</label><input class="form-control" name="person_name" required list="staffNames">
          <datalist id="staffNames"><?php foreach ($trainers as $t): ?><option value="<?= e($t['name']) ?>"><?php endforeach; ?><?php foreach ($staff as $u): ?><option value="<?= e($u['name']) ?>"><?php endforeach; ?></datalist></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Trainer profile (optional)</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Role label</label><input class="form-control" name="role_label" placeholder="Head Trainer"></div>
          <div class="col-6 mb-2"><label class="form-label">Month</label><input type="month" class="form-control" name="period_month" value="<?= e(date('Y-m')) ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Method</label><select class="form-select" name="method"><option value="bank">Bank</option><option value="cash">Cash</option><option value="card">Card</option></select></div>
          <div class="col-4 mb-2"><label class="form-label">Base ($)</label><input type="number" step="0.01" class="form-control" name="base_salary" required></div>
          <div class="col-4 mb-2"><label class="form-label">Allow. ($)</label><input type="number" step="0.01" class="form-control" name="allowances" value="0"></div>
          <div class="col-4 mb-2"><label class="form-label">Deduct. ($)</label><input type="number" step="0.01" class="form-control" name="deductions" value="0"></div>
        </div>
        <div class="mb-2"><label class="form-label">Notes</label><input class="form-control" name="notes"></div>
        <button class="btn btn-ff w-100">Create (starts pending)</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
