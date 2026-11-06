<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Exercise Library';
$activeNav = 'exercises';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        Exercise::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Exercise deleted.');
    } else {
        $data = [
            'tenant_id' => $tid,
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? 'Strength'),
            'muscle_group' => trim($_POST['muscle_group'] ?? '') ?: null,
            'equipment' => trim($_POST['equipment'] ?? '') ?: null,
            'difficulty' => $_POST['difficulty'] ?? 'Beginner',
            'description' => trim($_POST['description'] ?? '') ?: null,
            'video_url' => trim($_POST['video_url'] ?? '') ?: null,
        ];
        if ($data['name'] === '') {
            flash('error', 'Exercise name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            Exercise::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Exercise updated.');
        } else {
            Exercise::create($data);
            flash('success', 'Exercise added to the library.');
        }
    }
    redirect(url('tenant/exercises.php'));
}

$cat = trim($_GET['category'] ?? '');
$exercises = $cat !== ''
    ? Database::fetchAll('SELECT * FROM exercises WHERE tenant_id = ? AND category = ? ORDER BY name', [$tid, $cat])
    : Exercise::all($tid, 'name ASC', 300);
$cats = Exercise::categories($tid);
$edit = !empty($_GET['edit']) ? Exercise::find((int) $_GET['edit'], $tid) : null;
require __DIR__ . '/../views/tenant/header.php';
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-sm <?= $cat === '' ? 'btn-ff' : 'btn-ff-outline' ?>" href="<?= e(url('tenant/exercises.php')) ?>">All</a>
  <?php foreach ($cats as $c): ?><a class="btn btn-sm <?= $cat === $c['category'] ? 'btn-ff' : 'btn-ff-outline' ?>" href="?category=<?= urlencode($c['category']) ?>"><?= e($c['category']) ?> (<?= (int) $c['total'] ?>)</a><?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="row g-3">
      <?php foreach ($exercises as $ex): ?>
      <div class="col-md-6"><div class="ff-card p-3 h-100">
        <div class="d-flex justify-content-between"><h6 class="fw-bold mb-1"><?= e($ex['name']) ?></h6><span class="status-pill st-info"><?= e($ex['difficulty']) ?></span></div>
        <small style="color:var(--ff-muted)"><?= e($ex['category']) ?> · <?= e($ex['muscle_group'] ?? '') ?> · <?= e($ex['equipment'] ?? '') ?></small>
        <p class="small mt-2 mb-2" style="color:var(--ff-muted)"><?= e($ex['description'] ?? '') ?></p>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-light" href="?edit=<?= (int) $ex['id'] ?>">Edit</a>
          <form method="post" onsubmit="return confirm('Delete this exercise?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $ex['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button></form>
        </div>
      </div></div>
      <?php endforeach; ?>
      <?php if (!$exercises): ?><div class="col-12"><div class="panel" style="color:var(--ff-muted)">No exercises in this category yet.</div></div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel">
      <h5><?= $edit ? 'Edit exercise' : 'Add exercise' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Category</label><input class="form-control" name="category" list="catList" value="<?= e($edit['category'] ?? 'Strength') ?>"><datalist id="catList"><option>Strength</option><option>Bodybuilding</option><option>Cardio</option><option>Core</option><option>Flexibility</option></datalist></div>
          <div class="col-6 mb-2"><label class="form-label">Difficulty</label><select class="form-select" name="difficulty"><?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $d): ?><option <?= ($edit['difficulty'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Muscle group</label><input class="form-control" name="muscle_group" value="<?= e($edit['muscle_group'] ?? '') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Equipment</label><input class="form-control" name="equipment" value="<?= e($edit['equipment'] ?? '') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Description / coaching cues</label><textarea class="form-control" name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea></div>
        <div class="mb-2"><label class="form-label">Video URL</label><input class="form-control" name="video_url" value="<?= e($edit['video_url'] ?? '') ?>"></div>
        <button class="btn btn-ff w-100"><?= $edit ? 'Save changes' : 'Add exercise' ?></button>
        <?php if ($edit): ?><a class="btn btn-ff-outline w-100 mt-2" href="<?= e(url('tenant/exercises.php')) ?>">Cancel edit</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
