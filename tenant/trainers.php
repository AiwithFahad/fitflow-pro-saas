<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Trainers';
$activeNav = 'trainers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        Trainer::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Trainer removed.');
    } else {
        $data = [
            'tenant_id' => $tid,
            'branch_id' => (int) ($_POST['branch_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'photo_url' => trim($_POST['photo_url'] ?? '') ?: null,
            'specialty' => trim($_POST['specialty'] ?? '') ?: null,
            'experience_years' => (int) ($_POST['experience_years'] ?? 0),
            'bio' => trim($_POST['bio'] ?? '') ?: null,
            'status' => $_POST['status'] ?? 'active',
        ];
        if ($data['name'] === '') {
            flash('error', 'Trainer name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            Trainer::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Trainer updated.');
        } else {
            if (!plan_allows($tid, 'trainers')) {
                flash('error', plan_limit_message('trainers'));
                redirect(url('tenant/trainers.php'));
            }
            Trainer::create($data);
            flash('success', 'Trainer added.');
        }
    }
    redirect(url('tenant/trainers.php'));
}

$trainers = Trainer::withSchedule($tid);
$branches = Branch::all($tid, 'name ASC', 50);
$edit = !empty($_GET['edit']) ? Trainer::find((int) $_GET['edit'], $tid) : null;
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="row g-3">
      <?php foreach ($trainers as $t): ?>
      <div class="col-md-6">
        <div class="ff-card h-100">
          <div class="d-flex gap-3 p-3 pb-0">
            <?php if (!empty($t['photo_url'])): ?><img src="<?= e($t['photo_url']) ?>" alt="<?= e($t['name']) ?>" style="width:84px;height:84px;border-radius:.8rem;object-fit:cover">
            <?php else: ?><span class="avatar-initials" style="width:84px;height:84px;font-size:1.8rem;border-radius:.8rem"><?= e(strtoupper(substr($t['name'], 0, 1))) ?></span><?php endif; ?>
            <div>
              <h5 class="fw-bold mb-0"><?= e($t['name']) ?></h5>
              <div class="text-orange fw-bold small"><?= e($t['specialty'] ?? '') ?></div>
              <small style="color:var(--ff-muted)"><?= (int) $t['experience_years'] ?> yrs · <?= (int) $t['member_count'] ?> members assigned</small><br>
              <span class="status-pill st-<?= e($t['status']) ?>"><?= e($t['status']) ?></span>
            </div>
          </div>
          <div class="p-3">
            <p class="small mb-2" style="color:var(--ff-muted)"><?= e($t['bio'] ?? '') ?></p>
            <?php if (!empty($t['schedule'])): ?>
            <div class="small"><strong>Schedule:</strong>
              <?php foreach ($t['schedule'] as $s): ?><span class="badge bg-dark border me-1"><?= e(substr($s['day_of_week'], 0, 3)) ?> <?= e(substr($s['start_time'], 0, 5)) ?>–<?= e(substr($s['end_time'], 0, 5)) ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="d-flex gap-2 mt-3">
              <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $t['id'] ?>">Edit</a>
              <a class="btn btn-sm btn-outline-light" href="<?= e(url('tenant/schedules.php')) ?>?trainer=<?= (int) $t['id'] ?>">Schedule</a>
              <form method="post" onsubmit="return confirm('Remove this trainer?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger">Remove</button></form>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$trainers): ?><div class="col-12"><div class="panel" style="color:var(--ff-muted)">No trainers yet.</div></div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit trainer' : 'Add trainer' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label">Specialty</label><input class="form-control" name="specialty" value="<?= e($edit['specialty'] ?? '') ?>" placeholder="Strength, Bodybuilding…"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Experience (yrs)</label><input type="number" class="form-control" name="experience_years" value="<?= e($edit['experience_years'] ?? '0') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) ($edit['branch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Photo URL</label><input class="form-control" name="photo_url" value="<?= e($edit['photo_url'] ?? '') ?>" placeholder="https://…"></div>
        <div class="mb-2"><label class="form-label">Bio</label><textarea class="form-control" name="bio" rows="3"><?= e($edit['bio'] ?? '') ?></textarea></div>
        <div class="mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save changes' : 'Add trainer' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="<?= e(url('tenant/trainers.php')) ?>">Cancel edit</a><?php endif; ?>
      </form>
      <p class="small mt-3 mb-0" style="color:var(--ff-muted)">To give a trainer a login, invite them from Staff &amp; Invitations with the Trainer role.</p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
