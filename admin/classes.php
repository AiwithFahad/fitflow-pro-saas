<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Receptionist', 'Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Classes & Bookings';
$activeNav = 'classes';
$canManage = can('classes.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save' && $canManage) {
        $data = [
            'tenant_id' => $tid,
            'branch_id' => (int) ($_POST['branch_id'] ?? 0) ?: null,
            'trainer_id' => (int) ($_POST['trainer_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? 'General') ?: 'General',
            'day_of_week' => $_POST['day_of_week'] ?? 'Monday',
            'start_time' => $_POST['start_time'] ?? '06:00',
            'end_time' => $_POST['end_time'] ?? '07:00',
            'capacity' => max(1, (int) ($_POST['capacity'] ?? 20)),
            'status' => $_POST['status'] ?? 'active',
        ];
        if ($data['name'] === '') {
            flash('error', 'Class name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            GymClass::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Class updated.');
        } else {
            if (!plan_allows($tid, 'classes')) {
                flash('error', plan_limit_message('classes'));
                redirect(url('admin/classes.php'));
            }
            GymClass::create($data);
            flash('success', 'Class created.');
        }
    } elseif ($action === 'delete' && $canManage && !empty($_POST['id'])) {
        GymClass::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Class deleted.');
    } elseif ($action === 'book' && can('attendance.view')) {
        $res = ClassBooking::book($tid, (int) ($_POST['class_id'] ?? 0), (int) ($_POST['member_id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['message']);
    } elseif ($action === 'cancel_booking' && !empty($_POST['booking_id'])) {
        Database::query('UPDATE class_bookings SET status = "cancelled" WHERE id = ? AND tenant_id = ?', [(int) $_POST['booking_id'], $tid]);
        flash('success', 'Booking cancelled; the seat is available again.');
    }
    redirect(url('admin/classes.php'));
}

$classes = GymClass::upcoming($tid, 200);
$members = Member::all($tid, 'name ASC', 500);
$trainers = Trainer::all($tid, 'name ASC', 100);
$branches = Branch::all($tid, 'name ASC', 100);
$bookings = Database::fetchAll(
    'SELECT cb.*, c.name AS class_name, c.day_of_week, c.start_time, m.name AS member_name
     FROM class_bookings cb
     JOIN classes c ON c.id = cb.class_id
     JOIN members m ON m.id = cb.member_id
     WHERE cb.tenant_id = ? AND cb.status = "booked"
     ORDER BY cb.id DESC LIMIT 100',
    [$tid]
);
$edit = !empty($_GET['edit']) ? GymClass::find((int) $_GET['edit'], $tid) : null;
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel mb-3">
      <h5>Weekly class schedule</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Class</th><th>When</th><th>Trainer</th><th>Capacity</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($classes as $c): $full = (int) $c['booked_count'] >= (int) $c['capacity']; ?>
          <tr>
            <td class="fw-bold"><?= e($c['name']) ?><br><small style="color:var(--ff-muted)"><?= e($c['category']) ?> · <?= e($c['branch_name'] ?? '') ?></small></td>
            <td><?= e($c['day_of_week']) ?><br><small style="color:var(--ff-muted)"><?= e(substr((string) $c['start_time'], 0, 5)) ?> – <?= e(substr((string) $c['end_time'], 0, 5)) ?></small></td>
            <td><?= e($c['trainer_name'] ?? '—') ?></td>
            <td><div class="progress mb-1" style="height:6px;background:#26262b;min-width:90px"><div class="progress-bar" style="width:<?= (int) min(100, $c['capacity'] > 0 ? $c['booked_count'] / $c['capacity'] * 100 : 0) ?>%;background:<?= $full ? '#ef4444' : 'var(--ff-orange)' ?>"></div></div>
              <small><?= (int) $c['booked_count'] ?> / <?= (int) $c['capacity'] ?><?= $full ? ' · FULL' : '' ?></small></td>
            <td><span class="status-pill st-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
            <td class="text-end text-nowrap">
              <?php if (!$full): ?>
              <form method="post" class="d-inline-flex gap-1">
                <?= csrf_field() ?><input type="hidden" name="action" value="book"><input type="hidden" name="class_id" value="<?= (int) $c['id'] ?>">
                <select class="form-select form-select-sm" name="member_id" required style="max-width:170px"><option value="">Book member…</option>
                  <?php foreach ($members as $m): ?><?php if ($m['status'] === 'active'): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endif; ?><?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-ff">Book</button>
              </form>
              <?php endif; ?>
              <?php if ($canManage): ?>
              <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $c['id'] ?>">Edit</a>
              <form method="post" class="d-inline" data-confirm="Delete this class? Existing bookings are removed too.">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$classes): ?><tr><td colspan="6" style="color:var(--ff-muted)">No active classes yet.</td></tr><?php endif; ?>
        </tbody></table></div>
      <p class="small mt-2 mb-0" style="color:var(--ff-muted)">Bookings are capacity-checked in real time: a full class cannot be overbooked and a member cannot be booked twice into the same class.</p>
    </div>

    <div class="panel">
      <h5>Current bookings</h5>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Member</th><th>Class</th><th>When</th><th>Booked at</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr><td class="fw-bold"><?= e($b['member_name']) ?></td><td><?= e($b['class_name']) ?></td>
            <td><?= e($b['day_of_week']) ?> <?= e(substr((string) $b['start_time'], 0, 5)) ?></td><td class="small"><?= e(substr((string) $b['booked_at'], 0, 16)) ?></td>
            <td class="text-end"><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_booking"><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>"><button class="btn btn-sm btn-outline-danger">Cancel booking</button></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$bookings): ?><tr><td colspan="5" style="color:var(--ff-muted)">No bookings yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>

  <div class="col-xl-4">
    <?php if ($canManage): ?>
    <div class="panel">
      <h5><?= $edit ? 'Edit class' : 'New class' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Category</label><input class="form-control" name="category" value="<?= e($edit['category'] ?? 'General') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Day</label><select class="form-select" name="day_of_week"><?php foreach ($days as $d): ?><option <?= ($edit['day_of_week'] ?? 'Monday') === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Start</label><input type="time" class="form-control" name="start_time" value="<?= e($edit ? substr((string) $edit['start_time'], 0, 5) : '06:00') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">End</label><input type="time" class="form-control" name="end_time" value="<?= e($edit ? substr((string) $edit['end_time'], 0, 5) : '07:00') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Trainer</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= ($edit['trainer_id'] ?? null) == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= ($edit['branch_id'] ?? null) == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Capacity</label><input type="number" class="form-control" name="capacity" value="<?= e($edit['capacity'] ?? '20') ?>" min="1"></div>
          <div class="col-6 mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
        </div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save class' : 'Create class' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="classes.php">Cancel edit</a><?php endif; ?>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
