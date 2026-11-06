<?php
/** Trainers and their weekly schedules. */
class Trainer extends BaseModel
{
    protected static string $table = 'trainers';

    public static function withSchedule(int $tenantId): array
    {
        $trainers = self::all($tenantId, 'name ASC', 200);
        foreach ($trainers as &$t) {
            $t['schedule'] = Database::fetchAll(
                'SELECT * FROM trainer_schedules WHERE trainer_id = ? AND tenant_id = ? ORDER BY FIELD(day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday")',
                [$t['id'], $tenantId]
            );
            $t['member_count'] = (int) Database::scalar('SELECT COUNT(*) FROM members WHERE trainer_id = ? AND tenant_id = ?', [$t['id'], $tenantId]);
        }
        return $trainers;
    }
}
