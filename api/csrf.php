<?php
/** GET /api/csrf.php — returns the session CSRF token for AJAX calls. */
require_once __DIR__ . '/_bootstrap.php';

api_handle(function (): void {
    api_require_login();
    json_out(['ok' => true, 'csrf_token' => csrf_token()]);
});
