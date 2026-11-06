<?php
/**
 * FitFlow Pro — Authentication: registration, login with rate limiting,
 * logout, password recovery tokens. Passwords use password_hash().
 */
class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    /** Attempt a login. Returns the user row on success, null on failure. */
    public static function attempt(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));
        if (self::tooManyAttempts($email)) {
            flash('error', 'Too many failed login attempts. Please wait 15 minutes and try again.');
            return null;
        }
        $user = Database::fetch(
            'SELECT u.*, r.name AS role_name, t.name AS tenant_name, t.status AS tenant_status
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = ? LIMIT 1',
            [$email]
        );
        $ok = $user !== null
            && $user['status'] === 'active'
            && password_verify($password, $user['password_hash']);

        Database::insert('login_attempts', [
            'email'      => $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'successful' => $ok ? 1 : 0,
        ]);

        if (!$ok) {
            flash('error', 'Invalid email or password, or the account is inactive.');
            return null;
        }
        if (!empty($user['tenant_id']) && ($user['tenant_status'] ?? 'active') === 'suspended') {
            flash('error', 'This gym account is suspended. Please contact FitFlow Pro support.');
            return null;
        }
        // Secure session handling: new ID on privilege change.
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :wid', ['wid' => $user['id']]);
        log_activity($user['tenant_id'] ? (int) $user['tenant_id'] : null, 'auth.login', 'users', $user['id']);
        return $user;
    }

    private static function tooManyAttempts(string $email): bool
    {
        $count = (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND successful = 0
               AND created_at > (NOW() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)',
            [$email]
        );
        return $count >= self::MAX_ATTEMPTS;
    }

    public static function logout(): void
    {
        $u = current_user();
        if ($u) {
            log_activity($u['tenant_id'] ? (int) $u['tenant_id'] : null, 'auth.logout', 'users', $u['id']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Dashboard home path per role. */
    public static function homeFor(array $user): string
    {
        return match ($user['role_name'] ?? '') {
            'Super Admin' => '../super-admin/dashboard.php',
            'Trainer'     => '../trainer/dashboard.php',
            'Member'      => '../member/dashboard.php',
            default       => '../admin/dashboard.php',
        };
    }

    /** Create a password-reset token; returns the raw token to email. */
    public static function createResetToken(string $email): ?string
    {
        $user = Database::fetch('SELECT id FROM users WHERE email = ? AND status = "active"', [strtolower(trim($email))]);
        if ($user === null) {
            return null; // Do not leak whether the account exists.
        }
        $token = bin2hex(random_bytes(32));
        Database::insert('password_resets', [
            'user_id'    => $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);
        return $token;
    }

    public static function resetPassword(string $token, string $newPassword): bool
    {
        $row = Database::fetch(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [hash('sha256', $token)]
        );
        if ($row === null) {
            return false;
        }
        Database::update('users', ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)], 'id = :wid', ['wid' => $row['user_id']]);
        Database::update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = :wid', ['wid' => $row['id']]);
        return true;
    }
}
