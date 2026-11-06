<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Receptionist'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Membership Registration';
$activeNav = 'enroll';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!plan_allows($tid, 'members')) {
        flash('error', plan_limit_message('members'));
        redirect(url('tenant/enroll.php'));
    }
    $plan = MembershipPlan::find((int) ($_POST['plan_id'] ?? 0), $tid);
    if ($plan === null) {
        flash('error', 'Please choose a membership plan.');
        redirect(url('tenant/enroll.php'));
    }
    $pdo = Database::connection();
    try {
        $pdo->beginTransaction();
        $memberId = (int) ($_POST['member_id'] ?? 0);
        if ($memberId <= 0) {
            $height = (float) ($_POST['height_cm'] ?? 0);
            $weight = (float) ($_POST['weight_kg'] ?? 0);
            $b = ($height > 0 && $weight > 0) ? bmi($weight, $height) : ['value' => null];
            $memberId = Member::create([
                'tenant_id' => $tid,
                'branch_id' => (int) ($_POST['branch_id'] ?? 0) ?: null,
                'trainer_id' => (int) ($_POST['trainer_id'] ?? 0) ?: null,
                'name' => trim($_POST['name'] ?? ''),
                'email' => trim($_POST['email'] ?? '') ?: null,
                'phone' => trim($_POST['phone'] ?? '') ?: null,
                'gender' => $_POST['gender'] ?? 'male',
                'date_of_birth' => ($_POST['date_of_birth'] ?? '') ?: null,
                'height_cm' => $height ?: null,
                'weight_kg' => $weight ?: null,
                'bmi' => $b['value'],
                'qr_token' => qr_token(),
                'status' => 'active',
                'joined_at' => date('Y-m-d'),
            ]);
            // Optional member-portal login account.
            if (!empty($_POST['create_login']) && !empty($_POST['email'])) {
                $roleId = (int) Database::scalar('SELECT id FROM roles WHERE name = "Member"');
                $userId = Database::insert('users', [
                    'tenant_id' => $tid, 'role_id' => $roleId,
                    'name' => trim($_POST['name'] ?? ''), 'email' => strtolower(trim($_POST['email'])),
                    'password_hash' => password_hash((string) ($_POST['login_password'] ?? 'password'), PASSWORD_DEFAULT),
                    'status' => 'active', 'email_verified_at' => date('Y-m-d H:i:s'),
                ]);
                Database::update('members', ['user_id' => $userId], 'id = :wid AND tenant_id = :wtid', ['wid' => $memberId, 'wtid' => $tid]);
            }
        }
        $coupon = null;
        $code = trim($_POST['coupon'] ?? '');
        if ($code !== '') {
            $coupon = Coupon::validateCode($code);
            if ($coupon === null) {
                throw new RuntimeException('Coupon code "' . $code . '" is not valid.');
            }
        }
        $membershipId = Membership::enrol($tid, $memberId, $plan, $coupon ? (int) $coupon['id'] : null);
        $membership = Membership::find($membershipId, $tid);
        $paymentId = Payment::recordManual($tid, [
            'tenant_id' => $tid,
            'user_id' => (int) current_user()['id'],
            'member_id' => $memberId,
            'purpose' => 'membership',
            'description' => 'Membership enrolment — ' . $plan['name'],
            'amount' => (float) $membership['price'],
            'method' => $_POST['method'] ?? 'cash',
            'reference' => 'ENR-' . $membershipId,
        ]);
        $inv = Invoice::generate($tid, $paymentId, 'Membership — ' . $plan['name'], (float) $membership['price'], 'unpaid');
        Database::query('UPDATE subscription_usage SET used = used + 1 WHERE tenant_id = ? AND metric = "members"', [$tid]);
        $pdo->commit();
        notify($tid, null, 'New member enrolled', trim($_POST['name'] ?? 'Member') . ' joined on the ' . $plan['name'] . ' plan. Invoice ' . $inv['number'] . ' is awaiting payment verification.', 'success');
        log_activity($tid, 'member.enrolled', 'members', $memberId, $plan['name']);
        flash('success', 'Member registered. Membership active until ' . $membership['end_date'] . '. Payment recorded as PENDING — verify it under Payments once the money is confirmed. Invoice: ' . $inv['number']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'Registration failed: ' . $e->getMessage());
    }
    redirect(url('tenant/enroll.php'));
}

$plans = MembershipPlan::active($tid);
$branches = Branch::all($tid, 'name ASC', 50);
$trainers = Trainer::all($tid, 'name ASC', 100);
$existing = Member::all($tid, 'name ASC', 300);
$sub = active_subscription($tid);
require __DIR__ . '/../views/tenant/header.php';
?>
<?php if ($sub): ?>
<div class="alert alert-dark" style="border:1px solid var(--ff-border)">
  Plan <strong><?= e($sub['plan_name']) ?></strong> member limit:
  <strong><?= (int) $sub['max_members'] < 0 ? 'Unlimited' : (int) $sub['max_members'] ?></strong> ·
  used: <strong><?= Member::count($tid, ' AND status != "cancelled"') ?></strong>
  <?php if (!plan_allows($tid, 'members')): ?> — <span class="text-danger fw-bold">limit reached, upgrade to enrol more members.</span><?php endif; ?>
</div>
<?php endif; ?>
<div class="panel">
  <form method="post" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-12"><h5 class="mb-0">1 · Member details <small style="color:var(--ff-muted)">(or pick an existing member below to add a new membership)</small></h5></div>
    <div class="col-md-4"><label class="form-label">Existing member</label>
      <select class="form-select" name="member_id"><option value="0">— New member —</option>
      <?php foreach ($existing as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['qr_token']) ?>)</option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Full name (new member)</label><input class="form-control" name="name" placeholder="e.g. Liam Walker"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
    <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
    <div class="col-md-2"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
    <div class="col-md-3"><label class="form-label">Date of birth</label><input type="date" class="form-control" name="date_of_birth"></div>
    <div class="col-md-2"><label class="form-label">Height (cm)</label><input type="number" step="0.1" class="form-control" name="height_cm"></div>
    <div class="col-md-2"><label class="form-label">Weight (kg)</label><input type="number" step="0.1" class="form-control" name="weight_kg"></div>
    <div class="col-md-3"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Assign trainer</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?> · <?= e($t['specialty'] ?? '') ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="create_login" id="createLogin" value="1"><label class="form-check-label" for="createLogin">Create member portal login (uses the email above)</label></div>
      <input class="form-control mt-2" name="login_password" placeholder="Portal password (default: password)"></div>

    <div class="col-12"><h5 class="mb-0 mt-2">2 · Plan &amp; payment</h5></div>
    <div class="col-md-4"><label class="form-label">Membership plan</label>
      <select class="form-select" name="plan_id" required><?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> — <?= money($p['price']) ?> / <?= (int) $p['duration_days'] ?> days</option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Coupon code</label><input class="form-control" name="coupon" placeholder="WELCOME10"></div>
    <div class="col-md-3"><label class="form-label">Payment method</label><select class="form-select" name="method"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option><option value="manual">Manual / other</option></select></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-ff w-100">Register member</button></div>
    <div class="col-12"><p class="small mb-0" style="color:var(--ff-muted)">The payment is recorded as <strong>pending</strong> and an invoice is generated. It only counts as revenue after verification under Payments &amp; Invoices — money is never marked received automatically.</p></div>
  </form>
</div>
<?php require __DIR__ . '/../views/tenant/footer.php'; ?>
