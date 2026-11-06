<?php
/** Workout programs assigned to members, plus completion tracking. */
class Workout extends BaseModel
{
    protected static string $table = 'workouts';

    public static function forMember(int $tenantId, int $memberId): array
    {
        return Database::fetchAll(
            'SELECT w.*, t.name AS trainer_name FROM workouts w
             LEFT JOIN trainers t ON t.id = w.trainer_id
             WHERE w.tenant_id = ? AND w.member_id = ? ORDER BY w.id DESC',
            [$tenantId, $memberId]
        );
    }

    public static function library(int $tenantId): array
    {
        return Database::fetchAll(
            'SELECT * FROM workouts WHERE tenant_id = ? AND member_id IS NULL ORDER BY category, name',
            [$tenantId]
        );
    }
}
