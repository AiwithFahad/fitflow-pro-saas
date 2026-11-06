<?php
/** TrainerAttendance — staff check-in / check-out for trainers. */
class TrainerAttendance extends BaseModel
{
    protected static string $table = 'trainer_attendance';

    /** Open (not yet checked out) session for a trainer today, or null. */
    public static function openSession(int $tenantId, int $trainerId): ?array
    {
        return Database::fetch(
            'SELECT * FROM trainer_attendance
             WHERE tenant_id = ? AND trainer_id = ? AND DATE(check_in_at) = CURDATE() AND check_out_at IS NULL
             ORDER BY check_in_at DESC LIMIT 1',
            [$tenantId, $trainerId]
        );
    }

    /**
     * Toggle a trainer in/out for today. A duplicate check-in can never be
     * created: an open session always becomes a check-out instead.
     */
    public static function toggle(int $tenantId, int $trainerId, ?int $branchId = null): array
    {
        $trainer = Database::fetch(
            'SELECT * FROM trainers WHERE id = ? AND tenant_id = ?',
            [$trainerId, $tenantId]
        );
        if ($trainer === null) {
            return ['ok' => false, 'error' => 'Trainer not found.'];
        }
        if (($trainer['status'] ?? 'active') !== 'active') {
            return ['ok' => false, 'error' => $trainer['name'] . ' is not an active trainer.'];
        }
        $open = self::openSession($tenantId, $trainerId);
        if ($open !== null) {
            Database::update('trainer_attendance', ['check_out_at' => date('Y-m-d H:i:s')],
                'id = :wid AND tenant_id = :wtid', ['wid' => $open['id'], 'wtid' => $tenantId]);
            log_activity($tenantId, 'trainer_attendance.check_out', 'trainer_attendance', $open['id'], $trainer['name']);
            return ['ok' => true, 'action' => 'checked_out', 'trainer' => $trainer['name'], 'at' => date('H:i')];
        }
        $id = Database::insert('trainer_attendance', [
            'tenant_id'   => $tenantId,
            'trainer_id'  => $trainerId,
            'branch_id'   => $branchId ?? ($trainer['branch_id'] ?? null),
            'check_in_at' => date('Y-m-d H:i:s'),
            'method'      => 'manual',
        ]);
        log_activity($tenantId, 'trainer_attendance.check_in', 'trainer_attendance', $id, $trainer['name']);
        return ['ok' => true, 'action' => 'checked_in', 'trainer' => $trainer['name'], 'at' => date('H:i'), 'id' => $id];
    }

    /** Today's trainer attendance with trainer names. */
    public static function today(int $tenantId): array
    {
        return Database::fetchAll(
            'SELECT ta.*, t.name AS trainer_name
             FROM trainer_attendance ta JOIN trainers t ON t.id = ta.trainer_id
             WHERE ta.tenant_id = ? AND DATE(ta.check_in_at) = CURDATE()
             ORDER BY ta.check_in_at DESC',
            [$tenantId]
        );
    }

    /** Attendance rows between two dates (for reports). */
    public static function between(int $tenantId, string $from, string $to): array
    {
        return Database::fetchAll(
            'SELECT ta.*, t.name AS trainer_name
             FROM trainer_attendance ta JOIN trainers t ON t.id = ta.trainer_id
             WHERE ta.tenant_id = ? AND DATE(ta.check_in_at) BETWEEN ? AND ?
             ORDER BY ta.check_in_at DESC',
            [$tenantId, $from, $to]
        );
    }
}
