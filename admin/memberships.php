<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Receptionist', 'Accountant'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Memberships & Plans';
$activeNav = 'memberships';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'assign' && can('memberships.manage')) {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $plan = MembershipPlan::find((int) ($_POST['plan_id'] ?? 0), $tid);
        $member = Member::find($memberId, $tid);
        if ($plan === null || $member === null) {
            flash('error', 'Choose a valid member and plan.');
        } else {
            $couponId = null;
            $code = trim($_POST['coupon_code'] ?? '');
            if ($code !== '') {
                $coupon = Coupon::validateCode($code);
                if ($coupon === null) {
                    flash('error', 'Coupon code not recognised — membership not assigned.');
                    redirect(url('admin/memberships.php'));
                }
                $couponId = (int) $coupon['id'];
            }
            $msId = Membership::enrol($tid, $memberId, $plan, $couponId);
            $ms = Membership::find($msId, $tid);
            $pid = Payment::recordManual($tid, [
                'tenant_id' => $tid, 'user_id' => (int) current_user()['id'], 'member_id' => $memberId,
                'purpose' => 'membership',
                'description' => $plan['name'] . ' membership — ' . $member['name'],
                'amount' => (float) $ms['price'], 'method' => $_POST['method'] ?? 'cash',
                'reference' => 'MBR-' . str_pad((string) $msId, 4, '0', STR_PAD_LEFT),
            ]);
            Invoice::generate($tid, $pid, $plan['name'] . ' membership — ' . $member['name'], (float) $ms['price'], 'unpaid');
            flash('success', 'Membership assigned (' . $plan['name'] . ', expires ' . $ms['end_date'] . '). Payment recorded as PENDING — verify it under Payments once received.');
        }
    } elseif ($action === 'plan_save' && can('memberships.manage')) {
        $duration = (int) ($_POST['duration_days'] ?? 30);
        $data = [
            'tenant_id' => $tid,
            'name' => trim($_POST['name'] ?? ''),
            'price' => (float) ($_POST['price'] ?? 0),
            'duration_days' => max(1, $duration),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'features' => trim($_POST['features'] ?? '') ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($data['name'] === '') {
            flash('error', 'Plan name is required.');
        } elseif (!empty($_POST['id'])) {
            unset($data['tenant_id']);
            MembershipPlan::updateById((int) $_POST['id'], $data, $tid);
            flash('success', 'Membership plan updated.');
        } else {
            MembershipPlan::create($data);
            flash('success', 'Membership plan created.');
        }
    } elseif ($action === 'plan_delete' && can('memberships.manage') && !empty($_POST['id'])) {
        MembershipPlan::deleteById((int) $_POST['id'], $tid);
        flash('success', 'Membership plan deleted.');
    } elseif ($action === 'reminders' && can('memberships.manage')) {
        $sent = 0;
        foreach (Member::expiringSoon($tid, 30) as $x) {
            $member = Member::find((int) $x['id'], $tid);
            if ($member && !empty($member['user_id'])) {
                notify($tid, (int) $member['user_id'], 'Membership expiring soon',
                    'Your ' . $x['plan_name'] . ' membership ends on ' . $x['end_date'] . '. Renew at the front desk to keep training without a break.', 'warning');
                $sent++;
            }
        }
        notify($tid, null, 'Renewal reminders sent', $sent . ' member(s) with portal accounts were reminded about memberships expiring within 30 days.', 'info');
        log_activity($tid, 'membership.reminders_sent', 'memberships', null, $sent . ' reminders');
        flash('success', 'Renewal reminders sent to ' . $sent . ' member portal account(s). Members without a portal login are listed below for front-desk follow-up.');
    }
    redirect(url('admin/memberships.php'));
}

Membership::sweepExpired($tid);
$plans = MembershipPlan::all($tid, 'price ASC', 100);
$members = Member::all($tid, 'name ASC', 500);
$memberships = Database::fetchAll(
    'SELECT ms.*, m.name AS member_name, m.member_code,
            (SELECT p.id FROM payments p WHERE p.tenant_id = ms.tenant_id AND p.member_id = ms.member_id AND p.purpose = "membership" ORDER BY p.id DESC LIMIT 1) AS payment_id,
            c.code AS coupon_code
     FROM memberships ms
     JOIN members m ON m.id = ms.member_id
     LEFT JOIN coupons c ON c.id = ms.coupon_id
     WHERE ms.tenant_id = ? ORDER BY ms.id DESC LIMIT 200',
    [$tid]
);
$expiring30 = Member::expiringSoon($tid, 30);
$editPlan = !empty($_GET['edit_plan']) ? MembershipPlan::find((int) $_GET['edit_plan'], $tid) : null;
$durationPresets = [30 => 'Monthly (30 days)', 90 => 'Quarterly (90 days)', 180 => 'Half-yearly (180 days)', 365 => 'Yearly (365 days)'];

$extraScripts = '<script>
document.addEventListener("DOMContentLoaded", function(){
  document.querySelectorAll("[data-msop]").forEach(function(btn){
    btn.addEventListener("click", function(){
      var op = btn.getAttribute("data-msop"), id = btn.getAttribute("data-ms");
      FF.confirm("Apply \"" + op + "\" to membership #" + id + "?", "Membership").then(function(ok){
        if (!ok) return;
        FF.api("members.php", {method:"POST", data:{action:"membership_status", membership_id:id, op:op}})
          .then(function(r){ FF.toast(r.message); setTimeout(function(){ window.location.reload(); }, 600); })
          .catch(function(e){ FF.toast(e.message, "error"); });
      });
    });
  });
});
</script>';

require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">All memberships</h5>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="reminders">
          <button class="btn btn-sm btn-ff-outline"><i class="bi bi-bell me-1"></i>Send renewal reminders (<?= count($expiring30) ?> expiring ≤30d)</button></form>
      </div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Member</th><th>Plan</th><th>Period</th><th>Price</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($memberships as $ms): $cs = Membership::computedStatus($ms); $dl = Membership::daysLeft($ms); ?>
          <tr>
            <td class="fw-bold"><?= e($ms['member_name']) ?><br><small style="color:var(--ff-muted)"><?= e($ms['member_code'] ?? '') ?></small></td>
            <td><?= e($ms['plan_name']) ?><?= $ms['coupon_code'] ? '<br><small class="text-success">Coupon ' . e($ms['coupon_code']) . '</small>' : '' ?></td>
            <td class="small"><?= e($ms['start_date']) ?> → <?= e($ms['end_date']) ?><br><span style="color:var(--ff-muted)"><?= $dl >= 0 ? $dl . ' days left' : abs($dl) . ' days overdue' ?></span></td>
            <td class="fw-bold"><?= money($ms['price']) ?></td>
            <td><span class="status-pill st-<?= e($cs) ?>"><?= e($cs) ?></span></td>
            <td class="text-end text-nowrap">
              <?php if ($ms['payment_id']): ?><a class="btn btn-sm btn-outline-light" href="<?= e(url('admin/receipt.php')) ?>?payment_id=<?= (int) $ms['payment_id'] ?>">Receipt</a><?php endif; ?>
              <?php if (can('memberships.manage')): ?>
                <button type="button" class="btn btn-sm btn-outline-info" data-msop="renew" data-ms="<?= (int) $ms['id'] ?>">Renew</button>
                <?php if (in_array($ms['status'], ['active', 'expired'], true)): ?><button type="button" class="btn btn-sm btn-outline-warning" data-msop="suspend" data-ms="<?= (int) $ms['id'] ?>">Suspend</button><?php endif; ?>
                <?php if ($ms['status'] === 'suspended'): ?><button type="button" class="btn btn-sm btn-outline-success" data-msop="reactivate" data-ms="<?= (int) $ms['id'] ?>">Reactivate</button><?php endif; ?>
                <?php if ($ms['status'] !== 'cancelled'): ?><button type="button" class="btn btn-sm btn-outline-danger" data-msop="cancel" data-ms="<?= (int) $ms['id'] ?>">Cancel</button><?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$memberships): ?><tr><td colspan="6" style="color:var(--ff-muted)">No memberships yet — assign one below.</td></tr><?php endif; ?>
        </tbody></table></div>
      <p class="small mt-2 mb-0" style="color:var(--ff-muted)">Status is calculated automatically from the end date (active → expiring ≤7d → expired). Renew extends from the later of today / current end date. Upgrades: assign the new plan from the form — the previous active membership is replaced when you use “Change plan” in the member profile (Members page).</p>
    </div>

    <div class="panel">
      <h5>Membership plans</h5>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Plan</th><th>Duration</th><th>Price</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($plans as $p): ?>
          <tr><td class="fw-bold"><?= e($p['name']) ?><br><small style="color:var(--ff-muted)"><?= e($p['description'] ?? '') ?></small></td>
            <td><?= $durationPresets[(int) $p['duration_days']] ?? ((int) $p['duration_days'] . ' days') ?></td>
            <td class="fw-bold"><?= money($p['price']) ?></td>
            <td><span class="status-pill st-<?= $p['is_active'] ? 'active' : 'inactive' ?>"><?= $p['is_active'] ? 'active' : 'inactive' ?></span></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-light" href="?edit_plan=<?= (int) $p['id'] ?>">Edit</a>
              <form method="post" class="d-inline" data-confirm="Delete this membership plan? Existing memberships keep their plan name and price.">
                <?= csrf_field() ?><input type="hidden" name="action" value="plan_delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button></form>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$plans): ?><tr><td colspan="5" style="color:var(--ff-muted)">No plans yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>

  <div class="col-xl-4">
    <?php if (can('memberships.manage')): ?>
    <div class="panel mb-3">
      <h5>Assign membership</h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="assign">
        <div class="mb-2"><label class="form-label">Member</label><select class="form-select" name="member_id" required>
          <option value="">Choose member…</option>
          <?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['member_code'] ?? '') ?>)</option><?php endforeach; ?>
        </select></div>
        <div class="mb-2"><label class="form-label">Plan</label><select class="form-select" name="plan_id" required>
          <option value="">Choose plan…</option>
          <?php foreach ($plans as $p): ?><?php if ($p['is_active']): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> — <?= money($p['price']) ?> / <?= (int) $p['duration_days'] ?>d</option><?php endif; ?><?php endforeach; ?>
        </select></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Coupon (optional)</label><input class="form-control" name="coupon_code" placeholder="WELCOME10"></div>
          <div class="col-6 mb-2"><label class="form-label">Paid via</label><select class="form-select" name="method"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option><option value="manual">Manual</option></select></div>
        </div>
        <button class="btn btn-ff w-100">Assign (payment starts pending)</button>
        <p class="small mt-2 mb-0" style="color:var(--ff-muted)">Expiry date and status are calculated automatically from the plan duration. The payment is recorded as pending and must be verified under Payments.</p>
      </form>
    </div>
    <div class="panel">
      <h5><?= $editPlan ? 'Edit plan' : 'New membership plan' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="plan_save">
        <?php if ($editPlan): ?><input type="hidden" name="id" value="<?= (int) $editPlan['id'] ?>"><?php endif; ?>
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($editPlan['name'] ?? '') ?>" placeholder="e.g. Quarterly"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Duration</label><select class="form-select" name="duration_days">
            <?php foreach ($durationPresets as $days => $label): ?><option value="<?= $days ?>" <?= ($editPlan && (int) $editPlan['duration_days'] === $days) ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
          </select></div>
          <div class="col-6 mb-2"><label class="form-label">Price ($)</label><input type="number" step="0.01" class="form-control" name="price" required value="<?= e($editPlan['price'] ?? '') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($editPlan['description'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label">Features (comma separated)</label><textarea class="form-control" name="features" rows="2"><?= e($editPlan['features'] ?? '') ?></textarea></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="mpActive" <?= ($editPlan['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="mpActive">Active</label></div>
        <button class="btn btn-ff w-100"><?= $editPlan ? 'Save plan' : 'Create plan' ?></button>
        <?php if ($editPlan): ?><a class="btn btn-ff-outline w-100 mt-2" href="memberships.php">Cancel edit</a><?php endif; ?>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
