<?php
/**
 * GET /api/super-stats.php — platform aggregates for the Super Admin
 * dashboard (all tenants). Real counts and verified revenue only.
 */
require_once __DIR__ . '/_bootstrap.php';

api_handle(function (): void {
    api_require_login();
    api_require_roles(['Super Admin']);

    $tenantStats = Tenant::stats();
    $payTotals = Payment::totals(null);
    $pendingGyms = Database::fetchAll(
        'SELECT t.*, s.status AS sub_status, p.name AS plan_name
         FROM tenants t
         LEFT JOIN subscriptions s ON s.id = (SELECT id FROM subscriptions WHERE tenant_id = t.id ORDER BY id DESC LIMIT 1)
         LEFT JOIN subscription_plans p ON p.id = s.plan_id
         WHERE t.status IN ("trial", "pending") ORDER BY t.created_at DESC LIMIT 10'
    );
    $recentTenants = Database::fetchAll(
        'SELECT t.*, s.status AS sub_status, p.name AS plan_name,
                (SELECT COUNT(*) FROM members m WHERE m.tenant_id = t.id) AS member_count
         FROM tenants t
         LEFT JOIN subscriptions s ON s.id = (SELECT id FROM subscriptions WHERE tenant_id = t.id ORDER BY id DESC LIMIT 1)
         LEFT JOIN subscription_plans p ON p.id = s.plan_id
         ORDER BY t.id DESC LIMIT 8'
    );
    $subStats = Subscription::stats();
    $mrr = (float) ($subStats['mrr'] ?? 0);
    $arr = (float) ($subStats['arr'] ?? 0);

    json_out([
        'ok' => true,
        'kpis' => [
            'tenants_total' => $tenantStats['total'] ?? 0,
            'tenants_active' => $tenantStats['active'] ?? 0,
            'tenants_trial' => $tenantStats['trial'] ?? 0,
            'tenants_suspended' => $tenantStats['suspended'] ?? 0,
            'users_total' => (int) (Database::scalar('SELECT COUNT(*) FROM users') ?? 0),
            'members_total' => (int) (Database::scalar('SELECT COUNT(*) FROM members') ?? 0),
            'mrr' => round($mrr, 2),
            'mrr_label' => money($mrr),
            'arr_label' => money($arr),
            'subscriptions_active' => $subStats['active'] ?? 0,
            'verified_revenue' => $payTotals['verified'],
            'verified_label' => money($payTotals['verified']),
            'pending_amount' => $payTotals['pending'],
            'pending_label' => money($payTotals['pending']),
            'pending_count' => (int) (Database::scalar('SELECT COUNT(*) FROM payments WHERE status = "pending"') ?? 0),
            'open_tickets' => SupportTicket::openCount(),
        ],
        'revenue_series' => Subscription::revenueSeries(6),
        'subs_by_plan' => Subscription::byPlan(),
        'pending_gyms' => $pendingGyms,
        'recent_tenants' => $recentTenants,
        'recent_payments' => Payment::recent(null, 6),
    ]);
});
