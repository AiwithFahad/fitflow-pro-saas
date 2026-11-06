<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Receptionist', 'Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Class Bookings';
$activeNav = 'bookings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'book') {
        $res = ClassBooking::book($tid, (int) ($_POST['class_id'] ?? 0), (int) ($_POST['member_id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['message']);
    } elseif ($action === 'status') {
        $status = $_POST['status'] ?? 'booked';
        if (in_array($status, ['booked', 'attended', 'cancelled', 'no_show'], true)) {
            ClassBooking::updateById((int) $_POST['id'], ['status' => $status], $tid);
            flash('success', 'Booking updated.');
        }
    }
    redirect(url('tenant/bookings.php') . (!empty($_POST['filter_class']) ? '?class=' . (int) $_POST['filter_class'] : ''));
}

$filterClass = (int) ($_GET['class'] ?? 0);
$sql = 'SELECT cb.*, c.name AS class_name, c.day_of_week, c.start_time, m.name AS member_name
        FROM class_bookings cb JOIN classes c ON c.id = cb.class_id JOIN members m ON m.id = cb.member_id
        WHERE cb.tenant_id = ?';
$params = [$tid];
if ($filterClass > 0) {
    $sql .= ' AND cb.class_id = ?';
    $params[] = $filterClass;
}
$sql .= ' ORDER BY cb.id DESC LIMIT 200';
$bookings = Database::fetchAll($sql, $params);
$classes = GymClass::upcoming($tid, 200);
$members = Member::all($tid, 'name ASC', 300);
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="panel mb-3">
  <form method="post" class="row g-2 align-items-end">
    <?= csrf_field() ?><input type="hidden" name="action" value="book">
    <div class="col-md-5"><label class="form-label">Class</label><select class="form-select" name="class_id" required><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $filterClass === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> · <?= e($c['day_of_week']) ?> <?= e(substr($c['start_time'], 0, 5)) ?> (<?= (int) $c['booked_count'] ?>/<?= (int) $c['capacity'] ?> booked)</option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Member</label><select class="form-select" name="member_id" required><?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-ff w-100">Book member in</button></div>
  </form>
</div>
<div class="panel">
  <form method="get" class="d-flex gap-2 mb-3">
    <select class="form-select" name="class" style="max-width:320px"><option value="0">All classes</option>
      <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $filterClass === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-ff">Filter</button>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Member</th><th>Class</th><th>When</th><th>Booked at</th><th>Status</th><th class="text-end">Set status</th></tr></thead>
    <tbody>
    <?php foreach ($bookings as $b): ?>
      <tr><td class="fw-bold"><?= e($b['member_name']) ?></td><td><?= e($b['class_name']) ?></td>
        <td><?= e($b['day_of_week']) ?> <?= e(substr($b['start_time'], 0, 5)) ?></td>
        <td><?= e($b['booked_at'] ? substr((string) $b['booked_at'], 0, 16) : '') ?></td>
        <td><span class="status-pill st-<?= e($b['status']) ?>"><?= e(str_replace('_', ' ', $b['status'])) ?></span></td>
        <td class="text-end">
          <form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="filter_class" value="<?= $filterClass ?>">
            <select class="form-select form-select-sm" name="status" style="width:auto"><?php foreach (['booked', 'attended', 'cancelled', 'no_show'] as $s): ?><option value="<?= $s ?>" <?= $b['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select>
            <button class="btn btn-sm btn-ff">Save</button></form>
        </td></tr>
    <?php endforeach; ?>
    <?php if (!$bookings): ?><tr><td colspan="6" style="color:var(--ff-muted)">No bookings found.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
