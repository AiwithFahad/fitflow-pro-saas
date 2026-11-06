<?php
/** WorkoutLog — per-exercise completion tracking (sets/reps/weight/duration). */
class WorkoutLog extends BaseModel
{
    protected static string $table = 'workout_logs';

    /** Recent completion history for one member. */
    public static function forMember(int $tenantId, int $memberId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT l.*, w.name AS workout_name
             FROM workout_logs l LEFT JOIN workouts w ON w.id = l.workout_id
             WHERE l.tenant_id = ? AND l.member_id = ?
             ORDER BY l.completed_at DESC LIMIT ' . max(1, $limit),
            [$tenantId, $memberId]
        );
    }

    /** Best (max) weight logged per exercise for a member — progress signal. */
    public static function personalBests(int $tenantId, int $memberId): array
    {
        return Database::fetchAll(
            'SELECT exercise_name, MAX(weight_kg) AS best_kg, COUNT(*) AS sessions,
                    MAX(completed_at) AS last_done
             FROM workout_logs
             WHERE tenant_id = ? AND member_id = ? AND weight_kg IS NOT NULL
             GROUP BY exercise_name ORDER BY best_kg DESC',
            [$tenantId, $memberId]
        );
    }

    /** Days a member trained (logged anything) in the current month. */
    public static function sessionsThisMonth(int $tenantId, int $memberId): int
    {
        return (int) (Database::scalar(
            'SELECT COUNT(DISTINCT DATE(completed_at)) FROM workout_logs
             WHERE tenant_id = ? AND member_id = ? AND completed_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")',
            [$tenantId, $memberId]
        ) ?? 0);
    }
}
