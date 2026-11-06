# FitFlow Pro — Security Notes

## Authentication & sessions
- Passwords are hashed with `password_hash()` (bcrypt) and verified with
  `password_verify()`. Plaintext passwords are never stored.
- Sessions use a hardened cookie: `HttpOnly`, `SameSite=Lax`, `Secure` when
  HTTPS is detected, 2-hour lifetime. The session id is regenerated on login.
- Login is rate limited: 5 failed attempts per email+IP lock sign-in for
  15 minutes (`login_attempts` table). Failed/successful attempts and
  administrative actions are written to `activity_logs` / `audit_logs`.
- Password reset tokens are stored as SHA-256 hashes, expire after 1 hour,
  and are single-use.

## CSRF
- Every state-changing form includes a per-session CSRF token
  (`csrf_field()` / `verify_csrf()`), compared with `hash_equals()`.
  A failed check aborts the request with HTTP 419.

## Authorization & tenant isolation
- Role-based access control: roles carry granular permissions
  (`permissions`, `role_permissions`); `can()` / `require_can()` gate every
  protected action server-side (hiding a button is never the control).
- Every business table carries `tenant_id` (and `branch_id` where relevant).
  All model queries are scoped to the authenticated tenant; Super Admin
  access is explicit (`isSuperAdmin()`), not a missing filter.
- Plans enforce hard limits (members, trainers, branches, classes, staff)
  before creation, using live subscription + usage data.

## Payments integrity
- Manually recorded payments are created as `pending`. The **only** path to
  `verified` is the explicit verify action (permission-gated:
  `payments.verify` / platform admin). Verification stamps `verified_by`,
  `verified_at`, marks the linked invoice paid and is audit-logged.
  There is no code path that auto-marks a manual payment successful.

## Input & output
- All SQL goes through PDO prepared statements (emulation disabled).
- All rendered user data is escaped with `e()` (`htmlspecialchars`,
  ENT_QUOTES, UTF-8).
- Logo uploads are validated by extension + MIME, size-capped (2MB) and
  renamed randomly before storage in `storage/uploads/`.

## Secrets & configuration
- Configuration comes from environment variables / `.env` (git-ignored).
  `.env.example` contains placeholders only. Never commit real credentials.
- Production checklist: set `APP_DEBUG=false`, serve over HTTPS, point the
  web server document root appropriately, delete `database/install.php`
  after setup, keep `storage/` and `database/` outside public web access
  where the host allows it.

## Known demo limitations
- The static Vercel demo is mock data only and contains no real auth.
- The PHP app intentionally ships without a payment-gateway SDK; gateway
  integration should verify webhooks server-side before calling
  `Payment::verify()`.
