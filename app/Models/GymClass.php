<?php
/** Group fitness classes and their bookings count. */
class GymClass extends BaseModel
{
    protected static string $table = 'classes';

    public static function upcoming(int $tenantId, int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT c.*, t.name AS trainer_name, b.name AS branch_name,
                    (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = c.id AND cb.status = "booked") AS booked_count
             FROM classes c
             LEFT JOIN trainers t ON t.id = c.trainer_id
             LEFT JOIN branches b ON b.id = c.branch_id
             WHERE c.tenant_id = ? AND c.status = "active"
             ORDER BY FIELD(c.day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), c.start_time
             LIMIT ' . $limit,
            [$tenantId]
        );
    }
}
