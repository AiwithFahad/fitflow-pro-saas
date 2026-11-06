<?php
/** Member fitness goals with progress percentage. */
class FitnessGoal extends BaseModel
{
    protected static string $table = 'fitness_goals';

    public static function forMember(int $tenantId, int $memberId): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM fitness_goals WHERE tenant_id = ? AND member_id = ? ORDER BY id DESC',
            [$tenantId, $memberId]
        );
        foreach ($rows as &$g) {
            $start = (float) ($g['start_value'] ?? 0);
            $target = (float) ($g['target_value'] ?? 0);
            $current = (float) ($g['current_value'] ?? $start);
            $span = $target - $start;
            $g['progress_percent'] = $span == 0.0 ? 100 : (int) max(0, min(100, round((($current - $start) / $span) * 100)));
        }
        return $rows;
    }
}
