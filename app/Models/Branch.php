<?php
/** Gym branches (multi-branch support on higher plans). */
class Branch extends BaseModel
{
    protected static string $table = 'branches';

    public static function withCounts(int $tenantId): array
    {
        return Database::fetchAll(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM members m WHERE m.branch_id = b.id) AS member_count,
                    (SELECT COUNT(*) FROM equipment eq WHERE eq.branch_id = b.id) AS equipment_count
             FROM branches b WHERE b.tenant_id = ? ORDER BY b.name ASC',
            [$tenantId]
        );
    }
}
