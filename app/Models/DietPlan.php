<?php
/** Diet / nutrition plans assigned by trainers. */
class DietPlan extends BaseModel
{
    protected static string $table = 'diet_plans';

    public static function forMember(int $tenantId, int $memberId): array
    {
        return Database::fetchAll(
            'SELECT d.*, t.name AS trainer_name FROM diet_plans d
             LEFT JOIN trainers t ON t.id = d.trainer_id
             WHERE d.tenant_id = ? AND d.member_id = ? ORDER BY d.id DESC',
            [$tenantId, $memberId]
        );
    }
}
