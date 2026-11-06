<?php
/** Exercise library entries. */
class Exercise extends BaseModel
{
    protected static string $table = 'exercises';

    public static function categories(int $tenantId): array
    {
        return Database::fetchAll(
            'SELECT category, COUNT(*) AS total FROM exercises WHERE tenant_id = ? GROUP BY category ORDER BY category',
            [$tenantId]
        );
    }
}
