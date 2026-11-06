<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Gyms (Tenants)';
$activeNav = 'gyms';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $tenantId = (int) ($_POST['tenant_id'] ?? 0);
    $allowed = ['active', 'suspended', 'cancelled', 'trial'];
    if ($action === 'set_status' && $tenantId > 0 && in_array($_POST['status'] ?? '', $allowed, true)) {
        Tenant::setStatus($tenantId, (string) $_POST['status']);
        // Keep the subscription row consistent with the tenant state.
        if ($_POST['status'] === 'suspended') {
            Database::query('UPDATE subscriptions SET status = "past_due" WHERE tenant_id = ? AND status IN ("active","trial","grace")', [$tenantId]);
        } elseif ($_POST['status'] === 'cancelled') {
            Subscription::cancel($tenantId);
        } elseif ($_POST['status'] === 'active') {
            Database::query('UPDATE subscriptions SET status = "active" WHERE tenant_id = ? AND status = "past_due"', [$tenantId]);
        }
        log_activity(null, 'tenant.status_' . $_POST['status'], 'tenants', $tenantId);
        flash('success', 'Gym status updated to ' . e($_POST['status']) . '.');
    } elseif ($action === 'extend_trial' && $tenantId > 0) {
        $days = max(1, min(90, (int) ($_POST['days'] ?? 14)));
        Database::query('UPDATE tenants SET trial_ends_at = DATE_ADD(COALESCE(trial_ends_at, NOW()), INTERVAL ? DAY) WHERE id = ?', [$days, $tenantId]);
        Database::query('UPDATE subscriptions SET trial_ends_at = DATE_ADD(COALESCE(trial_ends_at, NOW()), INTERVAL ? DAY), ends_at = DATE_ADD(COALESCE(ends_at, NOW()), INTERVAL ? DAY) WHERE tenant_id = ? AND status = "trial"', [$days, $days, $tenantId]);
        log_activity(null, 'tenant.trial_extended', 'tenants', $tenantId, '+' . $days . ' days');
        flash('success', 'Trial extended by ' . $days . ' days.');
    }
    redirect(url('super-admin/gyms.php'));
}

$q = trim((string) ($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE t.name LIKE ? OR t.email LIKE ? OR t.city LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$gyms = Database::fetchAll(
    "SELECT t.*, p.name AS plan_name, p.max_members, s.status AS sub_status, s.billing_cycle, s.ends_at,
            (SELECT COUNT(*) FROM members m WHERE m.tenant_id = t.id) AS member_count,
            (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS user_count,
            (SELECT COUNT(*) FROM trainers tr WHERE tr.tenant_id = t.id AND tr.status = 'active') AS trainer_count
     FROM tenants t
     LEFT JOIN subscriptions s ON s.id = (SELECT id FROM subscriptions WHERE tenant_id = t.id ORDER BY id DESC LIMIT 1)
     LEFT JOIN subscription_plans p ON p.id = s.plan_id
     {$where} ORDER BY t.id DESC LIMIT 200",
    $params
);
require __DIR__ . '/../views/super-admin/header.php';
?>
<div class="panel">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h5 class="mb-0">All gyms</h5>
    <form method="get" class="d-flex gap-2">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search gym, email, city…">
      <button class="btn btn-ff">Search</button>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Gym</th><th>Plan / Usage</th><th>Status</th><th>Subscription</th><th>Trial / Period end</th><th class="text-end">Manage</th></tr></thead>
    <tbody>
    <?php foreach ($gyms as $t): ?>
      <tr>
        <td class="fw-bold"><?= e($t['name']) ?><br><small style="color:var(--ff-muted)"><?= e($t['email'] ?? '') ?> · <?= e($t['city'] ?? '') ?><?= $t['country'] ? ', ' . e($t['country']) : '' ?></small></td>
        <td><?= e($t['plan_name'] ?? '—') ?><br><small style="color:var(--ff-muted)"><?= (int) $t['member_count'] ?> members<?= ($t['max_members'] ?? 0) > 0 ? ' / ' . (int) $t['max_members'] . ' limit' : '' ?> · <?= (int) $t['trainer_count'] ?> trainers · <?= (int) $t['user_count'] ?> accounts</small></td>
        <td><span class="status-pill st-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
        <td><?= $t['sub_status'] ? '<span class="status-pill st-' . e($t['sub_status']) . '">' . e($t['sub_status']) . '</span><br><small style="color:var(--ff-muted)">' . e($t['billing_cycle'] ?? '') . '</small>' : '—' ?></td>
        <td><?= e($t['trial_ends_at'] ? substr((string) $t['trial_ends_at'], 0, 10) : ($t['ends_at'] ? substr((string) $t['ends_at'], 0, 10) : '—')) ?></td>
        <td class="text-end">
          <form method="post" class="d-inline-flex gap-1 flex-wrap justify-content-end">
            <?= csrf_field() ?><input type="hidden" name="tenant_id" value="<?= (int) $t['id'] ?>">
            <?php if ($t['status'] !== 'active'): ?><button name="action" value="set_status" class="btn btn-sm btn-outline-success" onclick="this.form.status.value='active'">Activate</button><?php endif; ?>
            <?php if ($t['status'] === 'trial'): ?><button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#trialModal" data-tenant="<?= (int) $t['id'] ?>" data-name="<?= e($t['name']) ?>">Extend trial</button><?php endif; ?>
            <?php if ($t['status'] !== 'suspended'): ?><button name="action" value="set_status" class="btn btn-sm btn-outline-warning" onclick="this.form.status.value='suspended'">Suspend</button><?php endif; ?>
            <?php if ($t['status'] !== 'cancelled'): ?><button name="action" value="set_status" class="btn btn-sm btn-outline-danger" onclick="this.form.status.value='cancelled'">Cancel</button><?php endif; ?>
            <input type="hidden" name="status" value="active">
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$gyms): ?><tr><td colspan="6" style="color:var(--ff-muted)">No gyms found.</td></tr><?php endif; ?>
    </tbody></table></div>
  <p class="small mt-3 mb-0" style="color:var(--ff-muted)">New gym registrations and owner accounts are created from the public pricing page (self-serve signup starts a trial). Suspending a gym blocks its logins immediately; cancelling also cancels its subscription.</p>
</div>

<div class="modal fade" id="trialModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post"><div class="modal-header"><h5 class="modal-title">Extend trial — <span id="trialGymName"></span></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><?= csrf_field() ?><input type="hidden" name="action" value="extend_trial"><input type="hidden" name="tenant_id" id="trialTenantId">
      <label class="form-label">Extra days</label><input type="number" class="form-control" name="days" value="14" min="1" max="90" required>
    </div>
    <div class="modal-footer"><button class="btn btn-ff">Extend trial</button></div>
  </form></div></div></div>

<script>
document.getElementById('trialModal').addEventListener('show.bs.modal', function (ev) {
  var b = ev.relatedTarget;
  document.getElementById('trialTenantId').value = b.getAttribute('data-tenant');
  document.getElementById('trialGymName').textContent = b.getAttribute('data-name');
});
</script>
<?php require __DIR__ . '/../views/super-admin/footer.php'; ?>
