<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Member'], Auth::homeFor(current_user()));
$tid = tenant_id();
$user = current_user();
$member = Database::fetch('SELECT * FROM members WHERE user_id = ? AND tenant_id = ?', [(int) $user['id'], $tid]);
if ($member === null) {
    redirect(url('public/logout.php'));
}
$dashTitle = 'My Workouts';
$activeNav = 'workouts';

$workouts = Workout::forMember($tid, (int) $member['id']);
$bookings = ClassBooking::forMember($tid, (int) $member['id']);
$classes = GymClass::upcoming($tid, 50);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $res = ClassBooking::book($tid, (int) ($_POST['class_id'] ?? 0), (int) $member['id']);
    flash($res['ok'] ? 'success' : 'error', $res['message']);
    redirect(url('member/workouts.php'));
}
require __DIR__ . '/../views/member/header.php';
?>
<div class="row g-3">
  <div class="col-lg-7">
    <h5 class="fw-bold mb-3">Assigned by my trainer</h5>
    <?php foreach ($workouts as $w): ?>
    <div class="panel mb-3">
      <div class="d-flex justify-content-between"><h6 class="fw-bold mb-1"><?= e($w['name']) ?></h6><span class="status-pill st-<?= e($w['status']) ?>"><?= e($w['status']) ?></span></div>
      <small style="color:var(--ff-muted)"><?= e($w['category']) ?> · <?= e($w['level']) ?> · <?= (int) $w['duration_weeks'] ?> weeks<?= $w['trainer_name'] ? ' · Coach ' . e($w['trainer_name']) : '' ?></small>
      <p class="mt-2 mb-2" style="color:var(--ff-muted)"><?= e($w['description'] ?? '') ?></p>
      <?php $exs = json_decode((string) ($w['exercises'] ?? '[]'), true) ?: []; ?>
      <ol class="mb-0">
        <?php foreach ($exs as $ex): ?><li class="mb-1"><?= e($ex) ?></li><?php endforeach; ?>
      </ol>
    </div>
    <?php endforeach; ?>
    <?php if (!$workouts): ?><div class="panel" style="color:var(--ff-muted)">No workout assigned yet — your trainer will publish one here.</div><?php endif; ?>
  </div>
  <div class="col-lg-5">
    <div class="panel mb-3">
      <h5>Book a group class</h5>
      <form method="post">
        <?= csrf_field() ?>
        <select class="form-select mb-2" name="class_id" required>
          <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> · <?= e($c['day_of_week']) ?> <?= e(substr($c['start_time'], 0, 5)) ?> (<?= (int) $c['booked_count'] ?>/<?= (int) $c['capacity'] ?>)</option><?php endforeach; ?>
        </select>
        <button class="btn btn-ff w-100">Book my spot</button>
      </form>
    </div>
    <div class="panel">
      <h5>My class bookings</h5>
      <?php foreach ($bookings as $b): ?>
      <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--ff-border)">
        <span><strong><?= e($b['class_name']) ?></strong><br><small style="color:var(--ff-muted)"><?= e($b['day_of_week']) ?> <?= e(substr($b['start_time'], 0, 5)) ?></small></span>
        <span class="status-pill st-<?= e($b['status']) ?>"><?= e(str_replace('_', ' ', $b['status'])) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (!$bookings): ?><p class="mb-0" style="color:var(--ff-muted)">No bookings yet.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/member/footer.php'; ?>
