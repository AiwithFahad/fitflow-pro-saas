<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer', 'Receptionist', 'Accountant'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Members';
$activeNav = 'members';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['member_id'] ?? 0);
    if ($action === 'delete' && can('members.delete')) {
        Member::deleteById($id, $tid);
        log_activity($tid, 'member.deleted', 'members', $id);
        flash('success', 'Member deleted.');
    } elseif ($action === 'save' && can('members.edit')) {
        $height = (float) ($_POST['height_cm'] ?? 0);
        $weight = (float) ($_POST['weight_kg'] ?? 0);
        $b = ($height > 0 && $weight > 0) ? bmi($weight, $height) : ['value' => null];
        Member::updateById($id, [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'branch_id' => (int) ($_POST['branch_id'] ?? 0) ?: null,
            'trainer_id' => (int) ($_POST['trainer_id'] ?? 0) ?: null,
            'height_cm' => $height ?: null,
            'weight_kg' => $weight ?: null,
            'bmi' => $b['value'],
            'status' => $_POST['status'] ?? 'active',
        ], $tid);
        log_activity($tid, 'member.updated', 'members', $id);
        flash('success', 'Member updated.');
    } elseif ($action === 'weighin' && can('members.edit')) {
        Member::addProgress($tid, $id, (float) ($_POST['weight_kg'] ?? 0), ($_POST['body_fat'] ?? '') === '' ? null : (float) $_POST['body_fat'], trim($_POST['notes'] ?? ''));
        flash('success', 'Weigh-in recorded and BMI updated.');
    }
    redirect(url('tenant/members.php') . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
}

$search = trim($_GET['q'] ?? '');
$members = Member::withMembership($tid, 200, $search);
$branches = Branch::all($tid, 'name ASC', 50);
$trainers = Trainer::all($tid, 'name ASC', 100);
$edit = !empty($_GET['edit']) ? Member::find((int) $_GET['edit'], $tid) : null;
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <form class="d-flex gap-2" method="get">
    <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone…" style="min-width:260px">
    <button class="btn btn-ff">Search</button>
  </form>
  <a class="btn btn-ff" href="<?= e(url('tenant/enroll.php')) ?>"><i class="bi bi-person-plus me-1"></i>Register member</a>
</div>

<?php if ($edit): ?>
<div class="panel mb-3">
  <h5>Edit member — <?= e($edit['name']) ?></h5>
  <form method="post" class="row g-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="member_id" value="<?= (int) $edit['id'] ?>">
    <div class="col-md-3"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name']) ?>"></div>
    <div class="col-md-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
    <div class="col-md-2"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
    <div class="col-md-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) ($edit['branch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">Trainer</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($edit['trainer_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">Height (cm)</label><input type="number" step="0.1" class="form-control" name="height_cm" value="<?= e($edit['height_cm'] ?? '') ?>"></div>
    <div class="col-md-2"><label class="form-label">Weight (kg)</label><input type="number" step="0.1" class="form-control" name="weight_kg" value="<?= e($edit['weight_kg'] ?? '') ?>"></div>
    <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['active', 'inactive', 'cancelled'] as $s): ?><option <?= $edit['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-ff">Save member</button><a class="btn btn-ff-outline" href="<?= e(url('tenant/members.php')) ?>">Cancel</a></div>
  </form>
</div>
<?php endif; ?>

<div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Member</th><th>Branch</th><th>Trainer</th><th>BMI</th><th>Plan</th><th>Expires</th><th>Status</th><th>QR token</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($members as $m): ?>
    <tr>
      <td><span class="fw-bold"><?= e($m['name']) ?></span><br><small style="color:var(--ff-muted)"><?= e($m['email'] ?? '') ?> · <?= e($m['phone'] ?? '') ?></small></td>
      <td><?= e($m['branch_name'] ?? '—') ?></td>
      <td><?= e($m['trainer_name'] ?? '—') ?></td>
      <td><?= $m['bmi'] !== null ? e($m['bmi']) : '—' ?></td>
      <td><?= e($m['membership_plan'] ?? '—') ?></td>
      <td><?= e($m['membership_end'] ?? '—') ?></td>
      <td><span class="status-pill st-<?= e($m['membership_status'] ?? $m['status']) ?>"><?= e($m['membership_status'] ?? $m['status']) ?></span></td>
      <td><code><?= e($m['qr_token']) ?></code></td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $m['id'] ?>">Edit</a>
        <form method="post" class="d-inline-flex gap-1 ms-1 align-items-center" onsubmit="return this.weight_kg.value !== ''">
          <?= csrf_field() ?><input type="hidden" name="action" value="weighin"><input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
          <input type="number" step="0.1" name="weight_kg" class="form-control form-control-sm" style="width:90px" placeholder="kg" title="Weigh-in (kg)">
          <button class="btn btn-sm btn-outline-warning" title="Record weigh-in">Weigh-in</button>
        </form>
        <?php if (can('members.delete')): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this member and their records?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$members): ?><tr><td colspan="9" style="color:var(--ff-muted)">No members found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
