<?php
/** SaaS subscription plans (Free Trial, Starter, Professional, Business, Enterprise). */
class SubscriptionPlan extends BaseModel
{
    protected static string $table = 'subscription_plans';
    protected static bool $tenantScoped = false;

    public static function active(): array
    {
        return Database::fetchAll('SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC');
    }

    public static function trial(): ?array
    {
        return Database::fetch('SELECT * FROM subscription_plans WHERE slug = "free-trial" LIMIT 1');
    }
}
