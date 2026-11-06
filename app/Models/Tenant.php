<?php
/** Tenant (gym organization) model. */
class Tenant extends BaseModel
{
    protected static string $table = 'tenants';
    protected static bool $tenantScoped = false;

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM tenants WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function stats(): array
    {
        return [
            'total'     => (int) Database::scalar('SELECT COUNT(*) FROM tenants'),
            'active'    => (int) Database::scalar('SELECT COUNT(*) FROM tenants WHERE status = "active"'),
            'suspended' => (int) Database::scalar('SELECT COUNT(*) FROM tenants WHERE status = "suspended"'),
            'trial'     => (int) Database::scalar('SELECT COUNT(*) FROM tenants WHERE status = "trial"'),
            'users'     => (int) Database::scalar('SELECT COUNT(*) FROM users'),
        ];
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::update('tenants', ['status' => $status], 'id = :wid', ['wid' => $id]);
    }

    /** Register a brand-new gym tenant with owner account + trial subscription. */
    public static function register(array $gym, array $owner, int $trialPlanId, int $trialDays = 14): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $tenantId = Database::insert('tenants', [
                'name'     => $gym['name'],
                'slug'     => $gym['slug'],
                'email'    => $gym['email'],
                'phone'    => $gym['phone'] ?? null,
                'city'     => $gym['city'] ?? null,
                'country'  => $gym['country'] ?? null,
                'status'   => 'trial',
                'trial_ends_at' => date('Y-m-d H:i:s', strtotime("+{$trialDays} days")),
            ]);
            $roleId = (int) Database::scalar('SELECT id FROM roles WHERE name = "Gym Owner"');
            Database::insert('users', [
                'tenant_id'     => $tenantId,
                'role_id'       => $roleId,
                'name'          => $owner['name'],
                'email'         => strtolower($owner['email']),
                'password_hash' => password_hash($owner['password'], PASSWORD_DEFAULT),
                'status'        => 'active',
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);
            Database::insert('tenant_settings', [
                'tenant_id' => $tenantId,
                'brand_color' => '#f97316',
                'currency' => 'USD',
                'settings' => json_encode(['onboarding_step' => 1]),
            ]);
            Database::insert('subscriptions', [
                'tenant_id' => $tenantId,
                'plan_id'   => $trialPlanId,
                'status'    => 'trial',
                'billing_cycle' => 'monthly',
                'starts_at' => date('Y-m-d H:i:s'),
                'ends_at'   => date('Y-m-d H:i:s', strtotime("+{$trialDays} days")),
                'trial_ends_at' => date('Y-m-d H:i:s', strtotime("+{$trialDays} days")),
            ]);
            $pdo->commit();
            return $tenantId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
