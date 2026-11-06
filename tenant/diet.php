<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Diet Plans';
$activeNav = 'diet';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        DietPlan::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Diet plan deleted.');
    } else {
        $meals = [];
        foreach (['breakfast', 'lunch', 'pre_workout', 'dinner', 'snack'] as $k) {
            $v = trim($_POST['meal_' . $k] ?? '');
            if ($v !== '') $meals[$k] = $v;
        }
        $data = [
            'tenant_id' => $tid,
            'member_id' => (int) ($_POST['member_id'] ?? 0) ?: null,
            'trainer_id' => (int) ($_POST['trainer_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'goal' => $_POST['goal'] ?? 'Muscle Gain',
            'calories' => (int) ($_POST['calories'] ?? 0) ?: null,
            'protein_g' => (int) ($_POST['protein_g'] ?? 0) ?: null,
            'carbs_g' => (int) ($_POST['carbs_g'] ?? 0) ?: null,
            'fats_g' => (int) ($_POST['fats_g'] ?? 0) ?: null,
            'meals' => $meals ? json_encode($meals) : null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
        ];
        if ($data['name'] === '') {
            flash('error', 'Plan name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            DietPlan::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Diet plan updated.');
        } else {
            $did = DietPlan::create($data);
            if ($data['member_id']) {
                $member = Member::find((int) $data['member_id'], $tid);
                if ($member) notify($tid, $member['user_id'] ?? null, 'New diet plan', 'Your trainer published "' . $data['name'] . '" — check your portal.', 'info');
            }
            log_activity($tid, 'diet.created', 'diet_plans', $did, $data['name']);
            flash('success', 'Diet plan saved.');
        }
    }
    redirect(url('tenant/diet.php'));
}

$plans = Database::fetchAll(
    'SELECT d.*, m.name AS member_name, t.name AS trainer_name FROM diet_plans d
     LEFT JOIN members m ON m.id = d.member_id LEFT JOIN trainers t ON t.id = d.trainer_id
     WHERE d.tenant_id = ? ORDER BY d.id DESC',
    [$tid]
);
$members = Member::all($tid, 'name ASC', 300);
$trainers = Trainer::all($tid, 'name ASC', 100);
$edit = !empty($_GET['edit']) ? DietPlan::find((int) $_GET['edit'], $tid) : null;
$editMeals = $edit ? (json_decode((string) ($edit['meals'] ?? ''), true) ?: []) : [];
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="row g-3">
      <?php foreach ($plans as $d): ?>
      <div class="col-md-6"><div class="ff-card p-3 h-100">
        <div class="d-flex justify-content-between"><h6 class="fw-bold mb-1"><?= e($d['name']) ?></h6><span class="status-pill st-info"><?= e($d['goal']) ?></span></div>
        <small style="color:var(--ff-muted)"><?= $d['member_name'] ? 'For ' . e($d['member_name']) : 'Reusable template' ?><?= $d['trainer_name'] ? ' · by ' . e($d['trainer_name']) : '' ?></small>
        <div class="d-flex gap-3 mt-2 text-center">
          <div><div class="fw-black" style="font-weight:900"><?= $d['calories'] ? number_format((int) $d['calories']) : '—' ?></div><small style="color:var(--ff-muted)">kcal</small></div>
          <div><div class="fw-black" style="font-weight:900"><?= $d['protein_g'] ?? '—' ?>g</div><small style="color:var(--ff-muted)">protein</small></div>
          <div><div class="fw-black" style="font-weight:900"><?= $d['carbs_g'] ?? '—' ?>g</div><small style="color:var(--ff-muted)">carbs</small></div>
          <div><div class="fw-black" style="font-weight:900"><?= $d['fats_g'] ?? '—' ?>g</div><small style="color:var(--ff-muted)">fats</small></div>
        </div>
        <?php $meals = json_decode((string) ($d['meals'] ?? ''), true) ?: []; ?>
        <?php if ($meals): ?><ul class="list-unstyled small mt-2 mb-2"><?php foreach ($meals as $k => $v): ?><li><strong class="text-capitalize"><?= e(str_replace('_', ' ', $k)) ?>:</strong> <span style="color:var(--ff-muted)"><?= e($v) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
        <?php if (!empty($d['notes'])): ?><p class="small mb-2" style="color:var(--ff-muted)"><i class="bi bi-sticky me-1"></i><?= e($d['notes']) ?></p><?php endif; ?>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $d['id'] ?>">Edit</a>
          <form method="post" onsubmit="return confirm('Delete this diet plan?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button></form>
        </div>
      </div></div>
      <?php endforeach; ?>
      <?php if (!$plans): ?><div class="col-12"><div class="panel" style="color:var(--ff-muted)">No diet plans yet.</div></div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit diet plan' : 'Create diet plan' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Plan name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Goal</label><select class="form-select" name="goal"><?php foreach (['Muscle Gain', 'Weight Loss', 'Maintenance', 'Performance'] as $g): ?><option <?= ($edit['goal'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Calories</label><input type="number" class="form-control" name="calories" value="<?= e($edit['calories'] ?? '') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Protein g</label><input type="number" class="form-control" name="protein_g" value="<?= e($edit['protein_g'] ?? '') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Carbs g</label><input type="number" class="form-control" name="carbs_g" value="<?= e($edit['carbs_g'] ?? '') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Fats g</label><input type="number" class="form-control" name="fats_g" value="<?= e($edit['fats_g'] ?? '') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Assign to member</label><select class="form-select" name="member_id"><option value="">— Template —</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) ($edit['member_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Trainer</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($edit['trainer_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
        <?php foreach (['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'pre_workout' => 'Pre-workout', 'dinner' => 'Dinner', 'snack' => 'Snack'] as $k => $label): ?>
        <div class="mb-2"><label class="form-label"><?= $label ?></label><input class="form-control" name="meal_<?= $k ?>" value="<?= e($editMeals[$k] ?? '') ?>"></div>
        <?php endforeach; ?>
        <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($edit['notes'] ?? '') ?></textarea></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save changes' : 'Create diet plan' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="<?= e(url('tenant/diet.php')) ?>">Cancel edit</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
