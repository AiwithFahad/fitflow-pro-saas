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
$dashTitle = 'Diet Plans';
$activeNav = 'diet-plans';
$assigned = Database::fetchAll('SELECT id, name FROM members WHERE tenant_id = ? AND trainer_id = ? ORDER BY name', [$tid, $trainerId]);
$assignedIds = array_map('intval', array_column($assigned, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $memberId = (int) ($_POST['member_id'] ?? 0) ?: null;
        if ($memberId !== null && !in_array($memberId, $assignedIds, true)) {
            flash('error', 'You can only assign diet plans to your own members.');
            redirect(url('trainer/diet-plans.php'));
        }
        $meals = [];
        foreach ((array) ($_POST['meal_name'] ?? []) as $i => $mealName) {
            $mealName = trim((string) $mealName);
            if ($mealName === '') {
                continue;
            }
            $meals[$mealName] = [
                'time' => trim((string) ($_POST['meal_time'][$i] ?? '')),
                'items' => trim((string) ($_POST['meal_items'][$i] ?? '')),
                'calories' => (int) ($_POST['meal_calories'][$i] ?? 0) ?: null,
                'protein_g' => (int) ($_POST['meal_protein'][$i] ?? 0) ?: null,
            ];
        }
        $data = [
            'tenant_id' => $tid, 'trainer_id' => $trainerId, 'member_id' => $memberId,
            'name' => trim($_POST['name'] ?? ''),
            'goal' => $_POST['goal'] ?? 'Muscle Gain',
            'calories' => (int) ($_POST['calories'] ?? 0) ?: null,
            'protein_g' => (int) ($_POST['protein_g'] ?? 0) ?: null,
            'carbs_g' => (int) ($_POST['carbs_g'] ?? 0) ?: null,
            'fats_g' => (int) ($_POST['fats_g'] ?? 0) ?: null,
            'meals' => json_encode($meals),
            'notes' => trim($_POST['notes'] ?? '') ?: null,
        ];
        $editId = (int) ($_POST['id'] ?? 0);
        if ($data['name'] === '') {
            flash('error', 'Diet plan name is required.');
        } elseif ($editId > 0) {
            $existing = DietPlan::find($editId, $tid);
            if ($existing && (int) ($existing['trainer_id'] ?? 0) === $trainerId) {
                unset($data['tenant_id']);
                DietPlan::updateById($editId, $data, $tid);
                flash('success', 'Diet plan updated.');
            } else {
                flash('error', 'You can only edit your own diet plans.');
            }
        } else {
            DietPlan::create($data);
            flash('success', 'Diet plan created.');
        }
    } elseif ($action === 'delete' && !empty($_POST['id'])) {
        $existing = DietPlan::find((int) $_POST['id'], $tid);
        if ($existing && (int) ($existing['trainer_id'] ?? 0) === $trainerId) {
            DietPlan::deleteById((int) $_POST['id'], $tid);
            flash('success', 'Diet plan deleted.');
        }
    }
    redirect(url('trainer/diet-plans.php'));
}

$plans = Database::fetchAll(
    'SELECT d.*, m.name AS member_name FROM diet_plans d LEFT JOIN members m ON m.id = d.member_id
     WHERE d.tenant_id = ? AND d.trainer_id = ? ORDER BY d.id DESC LIMIT 100',
    [$tid, $trainerId]
);
$edit = null;
if (!empty($_GET['edit'])) {
    $cand = DietPlan::find((int) $_GET['edit'], $tid);
    if ($cand && (int) ($cand['trainer_id'] ?? 0) === $trainerId) {
        $edit = $cand;
    }
}
$editMeals = $edit ? (json_decode((string) ($edit['meals'] ?? '{}'), true) ?: []) : [];
require __DIR__ . '/../views/trainer/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <?php foreach ($plans as $d): $meals = json_decode((string) ($d['meals'] ?? '{}'), true) ?: []; ?>
    <div class="panel mb-3">
      <div class="d-flex flex-wrap justify-content-between gap-2">
        <div><h5 class="mb-0"><?= e($d['name']) ?></h5>
          <div class="small" style="color:var(--ff-muted)">Goal: <?= e($d['goal']) ?><?= $d['calories'] ? ' · ' . (int) $d['calories'] . ' kcal' : '' ?><?= $d['member_name'] ? ' · ' . e($d['member_name']) : ' · Template' ?></div></div>
        <div class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $d['id'] ?>">Edit</a>
          <form method="post" class="d-inline" data-confirm="Delete this diet plan?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
        </div>
      </div>
      <?php if ($meals): ?>
      <div class="table-responsive mt-2"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Meal</th><th>Time</th><th>Items</th><th>Calories</th><th>Protein</th></tr></thead>
        <tbody>
        <?php foreach ($meals as $mealName => $meal): ?>
          <tr><td class="fw-bold"><?= e((string) $mealName) ?></td><td><?= e($meal['time'] ?? '') ?></td><td><?= e($meal['items'] ?? (is_string($meal) ? $meal : '')) ?></td><td><?= e($meal['calories'] ?? '—') ?></td><td><?= isset($meal['protein_g']) && $meal['protein_g'] !== null ? e($meal['protein_g']) . ' g' : '—' ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <?php if (!empty($d['notes'])): ?><p class="small mt-2 mb-0" style="color:var(--ff-muted)"><?= e($d['notes']) ?></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$plans): ?><div class="panel">You have not created any diet plans yet.</div><?php endif; ?>
  </div>

  <div class="col-xl-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit diet plan' : 'New diet plan' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Plan name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Member</label><select class="form-select" name="member_id"><option value="">Template</option><?php foreach ($assigned as $m): ?><option value="<?= (int) $m['id'] ?>" <?= ($edit['member_id'] ?? null) == $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Goal</label><select class="form-select" name="goal"><?php foreach (['Muscle Gain', 'Weight Loss', 'Maintenance', 'Performance'] as $g): ?><option <?= ($edit['goal'] ?? 'Muscle Gain') === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Calories</label><input type="number" class="form-control" name="calories" value="<?= e($edit['calories'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Protein (g)</label><input type="number" class="form-control" name="protein_g" value="<?= e($edit['protein_g'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Carbs (g)</label><input type="number" class="form-control" name="carbs_g" value="<?= e($edit['carbs_g'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Fats (g)</label><input type="number" class="form-control" name="fats_g" value="<?= e($edit['fats_g'] ?? '') ?>"></div>
        </div>
        <label class="form-label">Meals</label>
        <div id="mealRows">
          <?php $mealRows = $editMeals ?: ['Breakfast' => ['time' => '07:30', 'items' => '', 'calories' => '', 'protein_g' => '']]; ?>
          <?php foreach ($mealRows as $mealName => $meal): ?>
          <div class="border rounded p-2 mb-2 meal-row" style="border-color:var(--ff-border)!important">
            <div class="row g-1">
              <div class="col-5"><input class="form-control form-control-sm" name="meal_name[]" placeholder="Meal" value="<?= e((string) $mealName) ?>"></div>
              <div class="col-3"><input class="form-control form-control-sm" name="meal_time[]" placeholder="07:30" value="<?= e($meal['time'] ?? '') ?>"></div>
              <div class="col-2"><input class="form-control form-control-sm" name="meal_calories[]" type="number" placeholder="kcal" value="<?= e($meal['calories'] ?? '') ?>"></div>
              <div class="col-2"><input class="form-control form-control-sm" name="meal_protein[]" type="number" placeholder="P g" value="<?= e($meal['protein_g'] ?? '') ?>"></div>
              <div class="col-11"><input class="form-control form-control-sm" name="meal_items[]" placeholder="Foods / items" value="<?= e($meal['items'] ?? '') ?>"></div>
              <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.meal-row').remove()">×</button></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-ff-outline mb-3" id="addMealRow"><i class="bi bi-plus-lg me-1"></i>Add meal</button>
        <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($edit['notes'] ?? '') ?></textarea></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save diet plan' : 'Create diet plan' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="diet-plans.php">Cancel edit</a><?php endif; ?>
      </form>
      <script>
      document.getElementById('addMealRow').addEventListener('click', function(){
        var row = document.querySelector('.meal-row').cloneNode(true);
        row.querySelectorAll('input').forEach(function(i){ i.value = ''; });
        document.getElementById('mealRows').appendChild(row);
      });
      </script>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/trainer/footer.php'; ?>
