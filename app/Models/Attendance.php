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

    /** Open (not checked out) session for a member today, or null. */
    public static function openSession(int $tenantId, int $memberId): ?array
    {
        return Database::fetch(
            'SELECT * FROM attendance WHERE tenant_id = ? AND member_id = ?
             AND DATE(check_in_at) = CURDATE() AND check_out_at IS NULL
             ORDER BY check_in_at DESC LIMIT 1',
            [$tenantId, $memberId]
        );
    }

    /**
     * Manual check-in. A duplicate ACTIVE check-in is refused: the member
     * must check out of the open session first (never silently double-books).
     */
    public static function manualCheckIn(int $tenantId, int $memberId, ?int $branchId = null): array
    {
        $member = Database::fetch('SELECT * FROM members WHERE id = ? AND tenant_id = ?', [$memberId, $tenantId]);
        if ($member === null) {
            return ['ok' => false, 'error' => 'Member not found.'];
        }
        if (($member['status'] ?? 'active') !== 'active') {
            return ['ok' => false, 'error' => $member['name'] . ' is ' . $member['status'] . ' and cannot check in.'];
        }
        if (self::openSession($tenantId, $memberId) !== null) {
            return ['ok' => false, 'error' => $member['name'] . ' already has an active check-in today — check them out first (duplicate prevented).'];
        }
        $id = Database::insert('attendance', [
            'tenant_id'   => $tenantId,
            'member_id'   => $memberId,
            'branch_id'   => $branchId ?? ($member['branch_id'] ?? null),
            'check_in_at' => date('Y-m-d H:i:s'),
            'method'      => 'manual',
        ]);
        log_activity($tenantId, 'attendance.manual_check_in', 'attendance', $id, $member['name']);
        return ['ok' => true, 'action' => 'checked_in', 'member' => $member['name'], 'id' => $id, 'at' => date('H:i')];
    }

    /** Check a specific open session out. */
    public static function checkOut(int $tenantId, int $attendanceId): array
    {
        $row = Database::fetch(
            'SELECT a.*, m.name AS member_name FROM attendance a JOIN members m ON m.id = a.member_id
             WHERE a.id = ? AND a.tenant_id = ?', [$attendanceId, $tenantId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'Attendance record not found.'];
        }
        if (!empty($row['check_out_at'])) {
            return ['ok' => false, 'error' => 'This session is already checked out.'];
        }
        Database::update('attendance', ['check_out_at' => date('Y-m-d H:i:s')], 'id = :wid AND tenant_id = :wtid', ['wid' => $attendanceId, 'wtid' => $tenantId]);
        log_activity($tenantId, 'attendance.check_out', 'attendance', $attendanceId, $row['member_name']);
        return ['ok' => true, 'action' => 'checked_out', 'member' => $row['member_name']];
    }

    /**
     * Correct check-in / check-out times with a full audit trail: every
     * changed field is written to attendance_corrections (old vs new value,
     * reason, who corrected it) before the record itself is updated.
     */
    public static function correct(int $tenantId, int $attendanceId, array $fields, string $reason, ?int $userId): array
    {
        $row = Database::fetch('SELECT * FROM attendance WHERE id = ? AND tenant_id = ?', [$attendanceId, $tenantId]);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Attendance record not found.'];
        }
        $updates = [];
        foreach (['check_in_at', 'check_out_at'] as $field) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }
            $new = ($fields[$field] === '' || $fields[$field] === null) ? null : date('Y-m-d H:i:s', strtotime((string) $fields[$field]));
            $old = !empty($row[$field]) ? date('Y-m-d H:i:s', strtotime((string) $row[$field])) : null;
            if ($new === $old) {
                continue;
            }
            Database::insert('attendance_corrections', [
                'tenant_id'     => $tenantId,
                'attendance_id' => $attendanceId,
                'subject_type'  => 'member',
                'subject_id'    => (int) $row['member_id'],
                'corrected_by'  => $userId,
                'field'         => $field,
                'old_value'     => $old,
                'new_value'     => $new,
                'reason'        => $reason !== '' ? $reason : null,
            ]);
            $updates[$field] = $new;
        }
        if (!$updates) {
            return ['ok' => false, 'error' => 'Nothing changed — new times match the stored ones.'];
        }
        Database::update('attendance', $updates, 'id = :wid AND tenant_id = :wtid', ['wid' => $attendanceId, 'wtid' => $tenantId]);
        log_activity($tenantId, 'attendance.corrected', 'attendance', $attendanceId, implode(', ', array_keys($updates)) . ' — ' . $reason);
        return ['ok' => true, 'changed' => array_keys($updates)];
    }

    /** Aggregated report rows for daily / weekly / monthly views. */
    public static function report(int $tenantId, string $period): array
    {
        $group = match ($period) {
            'weekly'  => 'YEARWEEK(check_in_at, 1)',
            'monthly' => 'DATE_FORMAT(check_in_at, "%Y-%m")',
            default   => 'DATE(check_in_at)',
        };
        $label = match ($period) {
            'weekly'  => 'CONCAT("W/C ", DATE_FORMAT(MIN(check_in_at), "%e %b %Y"))',
            'monthly' => 'DATE_FORMAT(check_in_at, "%b %Y")',
            default   => 'DATE_FORMAT(check_in_at, "%a %e %b")',
        };
        $limit = $period === 'daily' ? 14 : ($period === 'weekly' ? 8 : 6);
        $rows = Database::fetchAll(
            "SELECT {$label} AS label, COUNT(*) AS checkins, COUNT(DISTINCT member_id) AS members
             FROM attendance WHERE tenant_id = ?
             GROUP BY {$group} ORDER BY MIN(check_in_at) DESC LIMIT {$limit}",
            [$tenantId]
        );
        return array_reverse($rows);
    }

    /** Recent corrections (audit view). */
    public static function corrections(int $tenantId, int $limit = 20): array
    {
        return Database::fetchAll(
            'SELECT c.*, u.name AS corrected_by_name, m.name AS member_name
             FROM attendance_corrections c
             LEFT JOIN users u ON u.id = c.corrected_by
             LEFT JOIN members m ON m.id = c.subject_id AND c.subject_type = "member"
             WHERE c.tenant_id = ? ORDER BY c.id DESC LIMIT ' . max(1, $limit),
            [$tenantId]
        );
    }
}
