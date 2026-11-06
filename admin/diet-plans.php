<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Diet Plans';
$activeNav = 'diet-plans';
$canManage = can('diet.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save' && $canManage) {
        // Structured meals → JSON object keyed by meal name.
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
            'tenant_id' => $tid,
            'member_id' => (int) ($_POST['member_id'] ?? 0) ?: null,
            'trainer_id' => (int) ($_POST['trainer_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'goal' => $_POST['goal'] ?? 'Muscle Gain',
            'calories' => (int) ($_POST['calories'] ?? 0) ?: null,
            'protein_g' => (int) ($_POST['protein_g'] ?? 0) ?: null,
            'carbs_g' => (int) ($_POST['carbs_g'] ?? 0) ?: null,
            'fats_g' => (int) ($_POST['fats_g'] ?? 0) ?: null,
            'meals' => json_encode($meals),
            'notes' => trim($_POST['notes'] ?? '') ?: null,
        ];
        if ($data['name'] === '') {
            flash('error', 'Diet plan name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            DietPlan::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Diet plan updated.');
        } else {
            DietPlan::create($data);
            flash('success', 'Diet plan created.');
        }
    } elseif ($action === 'delete' && $canManage && !empty($_POST['id'])) {
        DietPlan::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Diet plan deleted.');
    }
    redirect(url('admin/diet-plans.php'));
}

$plans = Database::fetchAll(
    'SELECT d.*, m.name AS member_name, t.name AS trainer_name
     FROM diet_plans d
     LEFT JOIN members m ON m.id = d.member_id
     LEFT JOIN trainers t ON t.id = d.trainer_id
     WHERE d.tenant_id = ? ORDER BY d.id DESC LIMIT 200',
    [$tid]
);
$members = Member::all($tid, 'name ASC', 500);
$trainers = Trainer::all($tid, 'name ASC', 100);
$edit = !empty($_GET['edit']) ? DietPlan::find((int) $_GET['edit'], $tid) : null;
$editMeals = $edit ? (json_decode((string) ($edit['meals'] ?? '{}'), true) ?: []) : [];
require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <?php foreach ($plans as $d): $meals = json_decode((string) ($d['meals'] ?? '{}'), true) ?: []; ?>
    <div class="panel mb-3">
      <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
          <h5 class="mb-0"><?= e($d['name']) ?></h5>
          <div class="small" style="color:var(--ff-muted)">Goal: <?= e($d['goal']) ?>
            <?= $d['calories'] ? ' · ' . (int) $d['calories'] . ' kcal' : '' ?>
            <?= $d['protein_g'] ? ' · P ' . (int) $d['protein_g'] . 'g' : '' ?><?= $d['carbs_g'] ? ' · C ' . (int) $d['carbs_g'] . 'g' : '' ?><?= $d['fats_g'] ? ' · F ' . (int) $d['fats_g'] . 'g' : '' ?>
            <?= $d['member_name'] ? ' · Member: ' . e($d['member_name']) : ' · Template' ?>
            <?= $d['trainer_name'] ? ' · Coach: ' . e($d['trainer_name']) : '' ?></div>
        </div>
        <?php if ($canManage): ?>
        <div class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $d['id'] ?>">Edit</a>
          <form method="post" class="d-inline" data-confirm="Delete this diet plan?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
        </div>
        <?php endif; ?>
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
    <?php if (!$plans): ?><div class="panel">No diet plans yet.</div><?php endif; ?>
  </div>

  <div class="col-xl-4">
    <?php if ($canManage): ?>
    <div class="panel">
      <h5><?= $edit ? 'Edit diet plan' : 'New diet plan' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Plan name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Assign member</label><select class="form-select" name="member_id"><option value="">Template</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>" <?= ($edit['member_id'] ?? null) == $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Trainer</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= ($edit['trainer_id'] ?? null) == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Goal</label><select class="form-select" name="goal"><?php foreach (['Muscle Gain', 'Weight Loss', 'Maintenance', 'Performance'] as $g): ?><option <?= ($edit['goal'] ?? 'Muscle Gain') === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Calories (kcal)</label><input type="number" class="form-control" name="calories" value="<?= e($edit['calories'] ?? '') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Protein (g)</label><input type="number" class="form-control" name="protein_g" value="<?= e($edit['protein_g'] ?? '') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Carbs (g)</label><input type="number" class="form-control" name="carbs_g" value="<?= e($edit['carbs_g'] ?? '') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Fats (g)</label><input type="number" class="form-control" name="fats_g" value="<?= e($edit['fats_g'] ?? '') ?>"></div>
        </div>
        <label class="form-label">Meals</label>
        <div id="mealRows">
          <?php $mealRows = $editMeals ?: ['Breakfast' => ['time' => '07:30', 'items' => '', 'calories' => '', 'protein_g' => '']]; ?>
          <?php foreach ($mealRows as $mealName => $meal): ?>
          <div class="border rounded p-2 mb-2 meal-row" style="border-color:var(--ff-border)!important">
            <div class="row g-1">
              <div class="col-5"><input class="form-control form-control-sm" name="meal_name[]" placeholder="Meal (Breakfast)" value="<?= e((string) $mealName) ?>"></div>
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
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
