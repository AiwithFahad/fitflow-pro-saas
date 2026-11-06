<?php
/** Gym equipment inventory + maintenance records. */
class Equipment extends BaseModel
{
    protected static string $table = 'equipment';

    public static function withBranch(int $tenantId): array
    {
        return Database::fetchAll(
            'SELECT eq.*, b.name AS branch_name FROM equipment eq
             LEFT JOIN branches b ON b.id = eq.branch_id
             WHERE eq.tenant_id = ? ORDER BY eq.name ASC',
            [$tenantId]
        );
    }

    public static function maintenanceDue(int $tenantId): array
    {
        return Database::fetchAll(
            'SELECT eq.*, b.name AS branch_name FROM equipment eq
             LEFT JOIN branches b ON b.id = eq.branch_id
             WHERE eq.tenant_id = ? AND eq.next_maintenance_at IS NOT NULL
               AND eq.next_maintenance_at <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             ORDER BY eq.next_maintenance_at ASC',
            [$tenantId]
        );
    }
}
