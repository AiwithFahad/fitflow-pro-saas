<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Super Admin'], Auth::homeFor(current_user()));
$dashTitle = 'Activity & Audit Logs';
$activeNav = 'logs';

$tab = ($_GET['tab'] ?? 'activity') === 'audit' ? 'audit' : 'activity';
if ($tab === 'audit') {
    $rows = Database::fetchAll(
        'SELECT a.*, u.name AS user_name, t.name AS tenant_name FROM audit_logs a
         LEFT JOIN users u ON u.id = a.user_id LEFT JOIN tenants t ON t.id = a.tenant_id
         ORDER BY a.id DESC LIMIT 200'
    );
} else {
    $rows = Database::fetchAll(
        'SELECT a.*, u.name AS user_name, t.name AS tenant_name FROM activity_logs a
         LEFT JOIN users u ON u.id = a.user_id LEFT JOIN tenants t ON t.id = a.tenant_id
         ORDER BY a.id DESC LIMIT 200'
    );
}
require __DIR__ . '/../views/admin/header.php';
?>
<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab === 'activity' ? 'active bg-warning text-dark' : 'text-white' ?>" href="?tab=activity">Activity logs</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab === 'audit' ? 'active bg-warning text-dark' : 'text-white' ?>" href="?tab=audit">Audit logs</a></li>
</ul>
<div class="panel"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>When</th><th><?= $tab === 'audit' ? 'Event' : 'Action' ?></th><th>User</th><th>Gym</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="small"><?= e(substr((string) $r['created_at'], 0, 16)) ?></td>
      <td class="fw-bold small"><?= e($r['event'] ?? $r['action']) ?></td>
      <td class="small"><?= e($r['user_name'] ?? 'System') ?></td>
      <td class="small"><?= e($r['tenant_name'] ?? 'Platform') ?></td>
      <td class="small"><?= e(($r['entity'] ?? '') . (!empty($r['entity_id']) ? ' #' . (int) $r['entity_id'] : '')) ?></td>
      <td class="small" style="color:var(--ff-muted)"><?= e($tab === 'audit' ? (string) ($r['new_values'] ?? '') : (string) ($r['details'] ?? '')) ?></td>
      <td class="small"><?= e($r['ip_address'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" style="color:var(--ff-muted)">No log entries yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php require __DIR__ . '/../views/admin/footer.php'; ?>
