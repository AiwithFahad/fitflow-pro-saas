<?php
/**
 * Shared bootstrap for the JSON API (api/*.php).
 * - Always answers JSON, never an HTML redirect or error page.
 * - Session auth + role/permission guards mirror the page guards.
 * - State-changing calls require the CSRF token (form field or header).
 */
require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function api_require_login(): array
{
    $user = current_user();
    if ($user === null) {
        json_out(['ok' => false, 'error' => 'Unauthenticated. Please log in again.'], 401);
    }
    return $user;
}

function api_require_roles(array $roles): void
{
    if (!has_role(...$roles)) {
        json_out(['ok' => false, 'error' => 'You do not have access to this endpoint.'], 403);
    }
}

function api_require_perm(string $slug): void
{
    if (!can($slug)) {
        json_out(['ok' => false, 'error' => 'Missing permission: ' . $slug], 403);
    }
}

function api_tenant(): int
{
    $tid = tenant_id();
    if ($tid === null) {
        json_out(['ok' => false, 'error' => 'No gym is associated with this account.'], 403);
    }
    return $tid;
}

/** Verify CSRF for POST calls: session token via field or X-CSRF-Token header. */
function api_require_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $known = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals($known, $sent)) {
        json_out(['ok' => false, 'error' => 'Invalid or missing CSRF token. Reload the page and try again.'], 419);
    }
}

/** Merged input: JSON body wins over form fields, GET stays separate. */
function api_body(): array
{
    static $body = null;
    if ($body === null) {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        $body = array_merge($_POST, is_array($json) ? $json : []);
    }
    return $body;
}

function api_param(string $key, $default = null)
{
    $body = api_body();
    return $body[$key] ?? ($_GET[$key] ?? $default);
}

/** Run a handler and turn any DB failure into a clean JSON 500. */
function api_handle(callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $ex) {
        error_log('FitFlow API error: ' . $ex->getMessage());
        json_out(['ok' => false, 'error' => 'The database is unavailable or the query failed. Please try again.'], 500);
    }
}
