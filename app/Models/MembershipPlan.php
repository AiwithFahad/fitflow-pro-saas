<?php
/** Gym-level membership plans sold to members (Monthly, Quarterly, Annual...). */
class MembershipPlan extends BaseModel
{
    protected static string $table = 'membership_plans';

    public static function active(int $tenantId): array
    {
        return Database::fetchAll(
            'SELECT * FROM membership_plans WHERE tenant_id = ? AND is_active = 1 ORDER BY price ASC',
            [$tenantId]
        );
    }
}
