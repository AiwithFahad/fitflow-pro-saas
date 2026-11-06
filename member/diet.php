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
$dashTitle = 'My Diet Plan';
$activeNav = 'diet';

$plans = DietPlan::forMember($tid, (int) $member['id']);
require __DIR__ . '/../views/member/header.php';
?>
<?php if (!$plans): ?>
<div class="panel" style="color:var(--ff-muted)">No diet plan assigned yet. Your trainer will publish one here after your assessment.</div>
<?php endif; ?>
<?php foreach ($plans as $d): ?>
<div class="panel mb-3">
  <div class="d-flex justify-content-between flex-wrap gap-2">
    <div><h5 class="fw-bold mb-1"><?= e($d['name']) ?></h5>
      <small style="color:var(--ff-muted)">Goal: <?= e($d['goal']) ?><?= $d['trainer_name'] ? ' · by Coach ' . e($d['trainer_name']) : '' ?></small></div>
    <span class="status-pill st-info"><?= e($d['goal']) ?></span>
  </div>
  <div class="row g-3 text-center mt-2">
    <div class="col-3"><div class="kpi"><div class="k-value"><?= $d['calories'] ? number_format((int) $d['calories']) : '—' ?></div><div class="k-label">Calories</div></div></div>
    <div class="col-3"><div class="kpi"><div class="k-value"><?= $d['protein_g'] ?? '—' ?>g</div><div class="k-label">Protein</div></div></div>
    <div class="col-3"><div class="kpi"><div class="k-value"><?= $d['carbs_g'] ?? '—' ?>g</div><div class="k-label">Carbs</div></div></div>
    <div class="col-3"><div class="kpi"><div class="k-value"><?= $d['fats_g'] ?? '—' ?>g</div><div class="k-label">Fats</div></div></div>
  </div>
  <?php $meals = json_decode((string) ($d['meals'] ?? ''), true) ?: []; ?>
  <?php if ($meals): ?>
  <div class="row g-3 mt-1">
    <?php foreach ($meals as $k => $v): ?>
    <div class="col-md-6"><div class="p-3 rounded h-100" style="background:#101012"><strong class="text-capitalize text-orange"><?= e(str_replace('_', ' ', $k)) ?></strong><p class="mb-0 mt-1"><?= e($v) ?></p></div></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($d['notes'])): ?><p class="mt-3 mb-0" style="color:var(--ff-muted)"><i class="bi bi-sticky me-1"></i><?= e($d['notes']) ?></p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/../views/member/footer.php'; ?>
