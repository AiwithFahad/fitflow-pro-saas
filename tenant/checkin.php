<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Receptionist'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'QR Check-in';
$activeNav = 'checkin';

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $token = trim($_POST['qr_token'] ?? '');
    if ($token !== '') {
        $result = Attendance::scanQr($tid, $token);
        if ($result === null) {
            flash('error', 'QR token "' . $token . '" was not recognised for this gym.');
        }
    }
    if ($result === null) {
        redirect(url('tenant/checkin.php'));
    }
}
$today = Attendance::today($tid, 30);
$members = Member::all($tid, 'name ASC', 200);
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="panel text-center">
      <h5><i class="bi bi-qr-code-scan text-orange me-1"></i>Front-desk scanner</h5>
      <p class="small" style="color:var(--ff-muted)">Type or paste the member's QR token (a scanner gun works too — it types + Enter). First scan checks in, the next scan checks out.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input class="form-control form-control-lg text-center" name="qr_token" placeholder="FFP-XXXXXX" autofocus autocomplete="off" style="letter-spacing:.15em;font-weight:700">
        <button class="btn btn-ff btn-lg w-100 mt-3">Scan / Toggle visit</button>
      </form>
      <?php if ($result): ?>
      <div class="alert alert-success mt-3 mb-0">
        <strong><?= e($result['member']['name']) ?></strong>
        <?= $result['action'] === 'check_in' ? 'checked IN' : 'checked OUT' ?> at <?= date('H:i') ?>.
      </div>
      <?php endif; ?>
      <div class="mt-3 small" style="color:var(--ff-muted)">Demo tokens: FFP-DEMO0001 … FFP-DEMO0010</div>
    </div>
    <div class="panel mt-3">
      <h5>Member QR cards</h5>
      <div class="row g-2">
        <?php foreach (array_slice($members, 0, 6) as $m): ?>
        <div class="col-6"><div class="qr-box"><i class="bi bi-qr-code" style="font-size:2.2rem"></i><div class="qr-token" style="font-size:.95rem"><?= e($m['qr_token']) ?></div><small><?= e($m['name']) ?></small></div></div>
        <?php endforeach; ?>
      </div>
      <p class="small mt-2 mb-0" style="color:var(--ff-muted)">Print these tokens on member cards, or members open the same token in their portal.</p>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="panel">
      <h5>Today's visits</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Member</th><th>In</th><th>Out</th><th>State</th></tr></thead>
        <tbody>
        <?php foreach ($today as $r): ?>
          <tr><td class="fw-bold"><?= e($r['member_name']) ?></td>
            <td><?= e(substr((string) $r['check_in_at'], 11, 5)) ?></td>
            <td><?= $r['check_out_at'] ? e(substr((string) $r['check_out_at'], 11, 5)) : '—' ?></td>
            <td><?= $r['check_out_at'] ? '<span class="status-pill st-inactive">Completed</span>' : '<span class="status-pill st-active">In gym</span>' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$today): ?><tr><td colspan="4" style="color:var(--ff-muted)">No visits yet today.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
