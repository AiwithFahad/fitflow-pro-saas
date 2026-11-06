<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$user = current_user();
$trainer = Database::fetch('SELECT * FROM trainers WHERE user_id = ? AND tenant_id = ?', [(int) $user['id'], $tid]);
if ($trainer === null) {
    flash('error', 'No trainer profile is linked to this account yet.');
    redirect(url('public/logout.php'));
}
$trainerId = (int) $trainer['id'];
$dashTitle = 'My Members';
$activeNav = 'members';
$q = trim((string) ($_GET['q'] ?? ''));
$where = 'm.tenant_id = ? AND m.trainer_id = ?';
$params = [$tid, $trainerId];
if ($q !== '') {
    $where .= ' AND (m.name LIKE ? OR m.email LIKE ? OR m.member_code LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$members = Database::fetchAll(
    "SELECT m.*, ms.plan_name AS membership_plan, ms.end_date AS membership_end, ms.status AS membership_status,
            (SELECT COUNT(*) FROM attendance a WHERE a.member_id = m.id AND a.tenant_id = m.tenant_id AND a.check_in_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS visits_30d
     FROM members m
     LEFT JOIN memberships ms ON ms.id = (SELECT id FROM memberships WHERE member_id = m.id ORDER BY id DESC LIMIT 1)
     WHERE {$where} ORDER BY m.name ASC LIMIT 300",
    $params
);
require __DIR__ . '/../views/trainer/header.php';
?>
<div class="panel">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h5 class="mb-0">Members assigned to you (<?= count($members) ?>)</h5>
    <form method="get" class="d-flex gap-2">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search my members…">
      <button class="btn btn-ff">Search</button>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Member</th><th>Contact</th><th>Membership</th><th>Body</th><th>Visits (30d)</th><th>Status</th><th class="text-end">Progress</th></tr></thead>
    <tbody>
    <?php foreach ($members as $m): ?>
      <tr>
        <td><div class="d-flex align-items-center"><?php if (!empty($m['photo_url'])): ?><img src="<?= e($m['photo_url']) ?>" alt="" class="rounded-circle me-2" width="34" height="34" style="object-fit:cover"><?php endif; ?>
          <div><span class="fw-bold"><?= e($m['name']) ?></span><br><small style="color:var(--ff-muted)"><?= e($m['member_code'] ?? '') ?></small></div></div></td>
        <td><?= e($m['email'] ?? '') ?><br><small style="color:var(--ff-muted)"><?= e($m['phone'] ?? '') ?></small></td>
        <td><?= e($m['membership_plan'] ?? '—') ?><?php if ($m['membership_end']): ?><br><small style="color:var(--ff-muted)">→ <?= e($m['membership_end']) ?></small><?php endif; ?></td>
        <td><?= $m['height_cm'] ? e($m['height_cm']) . ' cm' : '—' ?> · <?= $m['weight_kg'] ? e($m['weight_kg']) . ' kg' : '—' ?><br><small style="color:var(--ff-muted)">BMI <?= e($m['bmi'] ?? '—') ?></small></td>
        <td><?= (int) $m['visits_30d'] ?></td>
        <td><span class="status-pill st-<?= e($m['status']) ?>"><?= e($m['status']) ?></span></td>
        <td class="text-end"><a class="btn btn-sm btn-ff-outline" href="<?= e(url('trainer/progress.php')) ?>?member=<?= (int) $m['id'] ?>">View progress</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$members): ?><tr><td colspan="7" style="color:var(--ff-muted)">No members assigned to you yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  <p class="small mt-2 mb-0" style="color:var(--ff-muted)">You only see members assigned to you. Medical records are restricted to roles with the medical permission and are not shown here.</p>
</div>
<?php require __DIR__ . '/../views/trainer/footer.php'; ?>
