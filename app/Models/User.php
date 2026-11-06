<?php
/** Platform / tenant user model (staff + member accounts). */
class User extends BaseModel
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [strtolower(trim($email))]);
    }

    public static function withRole(?int $tenantId = null, int $limit = 200): array
    {
        $sql = 'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id';
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' WHERE u.tenant_id = ?';
            $params[] = $tenantId;
        }
        $sql .= ' ORDER BY u.id DESC LIMIT ' . $limit;
        return Database::fetchAll($sql, $params);
    }

    /** Invite a staff member: creates an inactive account + invitation token. */
    public static function invite(int $tenantId, int $roleId, string $name, string $email): string
    {
        $token = bin2hex(random_bytes(24));
        $userId = Database::insert('users', [
            'tenant_id'     => $tenantId,
            'role_id'       => $roleId,
            'name'          => $name,
            'email'         => strtolower(trim($email)),
            'password_hash' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
            'status'        => 'invited',
        ]);
        Database::insert('invitations', [
            'tenant_id'  => $tenantId,
            'email'      => strtolower(trim($email)),
            'role_id'    => $roleId,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);
        log_activity($tenantId, 'staff.invited', 'users', $userId, $email);
        return $token;
    }

    /** Accept an invitation: set password + activate. */
    public static function acceptInvitation(string $token, string $password): bool
    {
        $inv = Database::fetch('SELECT * FROM invitations WHERE token = ? AND accepted_at IS NULL AND expires_at > NOW()', [$token]);
        if ($inv === null) {
            return false;
        }
        Database::update('users', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status'        => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ], 'email = :wemail AND tenant_id = :wtid', ['wemail' => $inv['email'], 'wtid' => $inv['tenant_id']]);
        Database::update('invitations', ['accepted_at' => date('Y-m-d H:i:s')], 'id = :wid', ['wid' => $inv['id']]);
        return true;
    }
}
