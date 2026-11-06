<?php
/** Attendance with QR-token check-in / check-out flow. */
class Attendance extends BaseModel
{
    protected static string $table = 'attendance';

    /**
     * Scan a member QR token: if the member has an open session today,
     * close it (check-out); otherwise open a new one (check-in).
     * Returns ['action' => 'check_in'|'check_out', 'member' => row] or null.
     */
    public static function scanQr(int $tenantId, string $qrToken): ?array
    {
        $member = Database::fetch('SELECT * FROM members WHERE tenant_id = ? AND qr_token = ?', [$tenantId, trim($qrToken)]);
        if ($member === null) {
            return null;
        }
        $open = Database::fetch(
            'SELECT * FROM attendance WHERE tenant_id = ? AND member_id = ? AND check_out_at IS NULL AND DATE(check_in_at) = CURDATE() ORDER BY id DESC LIMIT 1',
            [$tenantId, $member['id']]
        );
        if ($open !== null) {
            Database::update('attendance', ['check_out_at' => date('Y-m-d H:i:s')], 'id = :wid AND tenant_id = :wtid', ['wid' => $open['id'], 'wtid' => $tenantId]);
            log_activity($tenantId, 'attendance.check_out', 'attendance', $open['id'], $member['name']);
            return ['action' => 'check_out', 'member' => $member];
        }
        $id = Database::insert('attendance', [
            'tenant_id'   => $tenantId,
            'branch_id'   => $member['branch_id'],
            'member_id'   => $member['id'],
            'check_in_at' => date('Y-m-d H:i:s'),
            'method'      => 'qr',
        ]);
        log_activity($tenantId, 'attendance.check_in', 'attendance', $id, $member['name']);
        return ['action' => 'check_in', 'member' => $member];
    }

    public static function today(int $tenantId, int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT a.*, m.name AS member_name FROM attendance a
             JOIN members m ON m.id = a.member_id
             WHERE a.tenant_id = ? AND DATE(a.check_in_at) = CURDATE()
             ORDER BY a.check_in_at DESC LIMIT ' . $limit,
            [$tenantId]
        );
    }

    /** Daily attendance counts for the last $days days (chart data). */
    public static function series(int $tenantId, int $days = 14): array
    {
        return Database::fetchAll(
            'SELECT DATE(check_in_at) AS day, COUNT(*) AS total
             FROM attendance WHERE tenant_id = ? AND check_in_at > (NOW() - INTERVAL ? DAY)
             GROUP BY day ORDER BY day ASC',
            [$tenantId, $days]
        );
    }

    public static function forMember(int $tenantId, int $memberId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM attendance WHERE tenant_id = ? AND member_id = ? ORDER BY check_in_at DESC LIMIT ' . $limit,
            [$tenantId, $memberId]
        );
    }
}
