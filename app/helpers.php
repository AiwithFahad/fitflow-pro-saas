<?php
/**
 * FitFlow Pro — global helpers: escaping, CSRF, flash messages, redirects,
 * activity/audit logging, notifications, plan-limit enforcement, BMI.
 */

/** Escape output for HTML (XSS prevention). */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Build an app URL relative to the public base path guess. */
function url(string $path = ''): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Walk up from /public, /admin, /tenant, /member folders to the app root.
    $root = preg_replace('#/(public|admin|tenant|member)/.*$#', '', $script);
    return rtrim($root, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** CSRF token (one per session, rotated on login). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}

function require_csrf(): void
{
    if (!csrf_verify()) {
        flash('error', 'Security token mismatch. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? url('public/index.php'));
    }
}

/** Flash messages stored in the session. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_messages(): array
{
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}

function render_flash(): string
{
    $html = '';
    foreach (flash_messages() as $m) {
        $class = $m['type'] === 'success' ? 'success' : ($m['type'] === 'error' ? 'danger' : 'info');
        $html .= '<div class="alert alert-' . $class . ' alert-dismissible fade show" role="alert">'
            . e($m['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    return $html;
}

/** Currently authenticated user row (from session), or null. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function tenant_id(): ?int
{
    $u = current_user();
    return isset($u['tenant_id']) && $u['tenant_id'] !== null ? (int) $u['tenant_id'] : null;
}

function has_role(string ...$roles): bool
{
    $u = current_user();
    return $u !== null && in_array($u['role_name'] ?? '', $roles, true);
}

/** Check a granular permission slug for the current user's role. */
function can(string $permission): bool
{
    $u = current_user();
    if ($u === null) {
        return false;
    }
    if (($u['role_name'] ?? '') === 'Super Admin') {
        return true;
    }
    static $perms = null;
    if ($perms === null) {
        $perms = Database::fetchAll(
            'SELECT p.slug FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?',
            [(int) $u['role_id']]
        );
        $perms = array_column($perms, 'slug');
    }
    return in_array($permission, $perms, true);
}

function require_login(string $loginPath = '../public/login.php'): void
{
    if (current_user() === null) {
        redirect($loginPath);
    }
}

function require_role(array $roles, string $fallback): void
{
    if (!has_role(...$roles)) {
        flash('error', 'You do not have access to that area.');
        redirect($fallback);
    }
}

/** Log a tenant activity + audit entry (best-effort, never fatal). */
function log_activity(?int $tenantId, string $action, string $entity = '', $entityId = null, string $details = ''): void
{
    try {
        $u = current_user();
        Database::insert('activity_logs', [
            'tenant_id' => $tenantId,
            'user_id'   => $u['id'] ?? null,
            'action'    => $action,
            'entity'    => $entity,
            'entity_id' => $entityId,
            'details'   => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        Database::insert('audit_logs', [
            'tenant_id' => $tenantId,
            'user_id'   => $u['id'] ?? null,
            'event'     => $action,
            'entity'    => $entity,
            'entity_id' => $entityId,
            'old_values' => null,
            'new_values' => $details !== '' ? json_encode(['details' => $details]) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
        ]);
    } catch (Throwable $ignored) {
        // Logging must never break the request.
    }
}

/** Create an in-app notification. */
function notify(?int $tenantId, ?int $userId, string $title, string $body = '', string $type = 'info'): void
{
    try {
        Database::insert('notifications', [
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
            'title'     => $title,
            'body'      => $body,
            'type'      => $type,
            'is_read'   => 0,
        ]);
    } catch (Throwable $ignored) {
    }
}

/** Active subscription for a tenant (with plan), or null. */
function active_subscription(int $tenantId): ?array
{
    return Database::fetch(
        'SELECT s.*, p.name AS plan_name, p.max_members, p.max_trainers, p.max_branches,
                p.max_classes, p.max_staff, p.max_storage_mb, p.price_monthly, p.price_yearly
         FROM subscriptions s
         JOIN subscription_plans p ON p.id = s.plan_id
         WHERE s.tenant_id = ? AND s.status IN ("trial", "active", "grace")
         ORDER BY s.id DESC LIMIT 1',
        [$tenantId]
    );
}

/**
 * Enforce a plan limit. $resource is one of members|trainers|branches|classes|staff.
 * Returns true when the tenant may add one more record.
 */
function plan_allows(int $tenantId, string $resource): bool
{
    $sub = active_subscription($tenantId);
    if ($sub === null) {
        return false;
    }
    $map = [
        'members'  => ['max_members', 'SELECT COUNT(*) FROM members WHERE tenant_id = ? AND status != "cancelled"'],
        'trainers' => ['max_trainers', 'SELECT COUNT(*) FROM trainers WHERE tenant_id = ? AND status = "active"'],
        'branches' => ['max_branches', 'SELECT COUNT(*) FROM branches WHERE tenant_id = ? AND status = "active"'],
        'classes'  => ['max_classes', 'SELECT COUNT(*) FROM classes WHERE tenant_id = ? AND status = "active"'],
        'staff'    => ['max_staff', 'SELECT COUNT(*) FROM users WHERE tenant_id = ? AND role_id != (SELECT id FROM roles WHERE name = "Member")'],
    ];
    if (!isset($map[$resource])) {
        return true;
    }
    [$col, $sql] = $map[$resource];
    $limit = (int) ($sub[$col] ?? 0);
    if ($limit < 0) {
        return true; // -1 = unlimited
    }
    $count = (int) Database::scalar($sql, [$tenantId]);
    return $count < $limit;
}

function plan_limit_message(string $resource): string
{
    return 'Your current plan limit for ' . $resource . ' has been reached. Please upgrade your subscription.';
}

/** Money formatting. */
function money($amount): string
{
    return '$' . number_format((float) $amount, 2);
}

/** BMI calculation + category. */
function bmi(float $weightKg, float $heightCm): array
{
    if ($heightCm <= 0) {
        return ['value' => 0, 'category' => 'Unknown'];
    }
    $m = $heightCm / 100;
    $v = $weightKg / ($m * $m);
    $cat = $v < 18.5 ? 'Underweight' : ($v < 25 ? 'Healthy' : ($v < 30 ? 'Overweight' : 'Obese'));
    return ['value' => round($v, 1), 'category' => $cat];
}

/** Generate a unique member QR token. */
function qr_token(): string
{
    return 'FFP-' . strtoupper(bin2hex(random_bytes(5)));
}

/** Simple pagination helper: returns [limit, offset] from ?page=. */
function paginate(int $perPage = 15): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    return [$perPage, ($page - 1) * $perPage, $page];
}

function pager(int $total, int $perPage, int $page, string $baseUrl): string
{
    $pages = (int) ceil($total / max(1, $perPage));
    if ($pages <= 1) {
        return '';
    }
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $html = '<nav><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $pages; $i++) {
        $html .= '<li class="page-item ' . ($i === $page ? 'active' : '') . '"><a class="page-link" href="'
            . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}
