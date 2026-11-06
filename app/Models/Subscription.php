<?php
/** Tenant subscriptions, billing cycles, MRR/ARR analytics. */
class Subscription extends BaseModel
{
    protected static string $table = 'subscriptions';

    public static function current(int $tenantId): ?array
    {
        return active_subscription($tenantId);
    }

    public static function stats(): array
    {
        return [
            'active'  => (int) Database::scalar('SELECT COUNT(*) FROM subscriptions WHERE status IN ("active","trial","grace")'),
            'expired' => (int) Database::scalar('SELECT COUNT(*) FROM subscriptions WHERE status IN ("expired","cancelled")'),
            'mrr'     => (float) Database::scalar(
                'SELECT COALESCE(SUM(CASE WHEN s.billing_cycle = "yearly" THEN p.price_yearly / 12 ELSE p.price_monthly END), 0)
                 FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id
                 WHERE s.status IN ("active", "grace")'
            ),
            'arr'     => (float) Database::scalar(
                'SELECT COALESCE(SUM(CASE WHEN s.billing_cycle = "yearly" THEN p.price_yearly ELSE p.price_monthly * 12 END), 0)
                 FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id
                 WHERE s.status IN ("active", "grace")'
            ),
        ];
    }

    /** Change plan (upgrade/downgrade) with a fresh billing period. */
    public static function changePlan(int $tenantId, int $planId, string $cycle): int
    {
        Database::query('UPDATE subscriptions SET status = "cancelled", cancelled_at = NOW() WHERE tenant_id = ? AND status IN ("trial","active","grace")', [$tenantId]);
        $months = $cycle === 'yearly' ? 12 : 1;
        return Database::insert('subscriptions', [
            'tenant_id'     => $tenantId,
            'plan_id'       => $planId,
            'status'        => 'active',
            'billing_cycle' => $cycle,
            'starts_at'     => date('Y-m-d H:i:s'),
            'ends_at'       => date('Y-m-d H:i:s', strtotime("+{$months} months")),
        ]);
    }

    public static function cancel(int $tenantId): void
    {
        Database::query('UPDATE subscriptions SET status = "cancelled", cancelled_at = NOW() WHERE tenant_id = ? AND status IN ("trial","active","grace")', [$tenantId]);
    }

    /** Monthly revenue series (verified payments) for charts. */
    public static function revenueSeries(int $months = 6): array
    {
        return Database::fetchAll(
            'SELECT DATE_FORMAT(paid_at, "%Y-%m") AS month, COALESCE(SUM(amount),0) AS total
             FROM payments WHERE status = "verified" AND paid_at IS NOT NULL
               AND paid_at > (NOW() - INTERVAL ? MONTH)
             GROUP BY month ORDER BY month ASC',
            [$months]
        );
    }

    public static function byPlan(): array
    {
        return Database::fetchAll(
            'SELECT p.name AS plan_name, COUNT(s.id) AS total
             FROM subscription_plans p
             LEFT JOIN subscriptions s ON s.plan_id = p.id AND s.status IN ("active","trial","grace")
             GROUP BY p.id ORDER BY total DESC'
        );
    }
}
