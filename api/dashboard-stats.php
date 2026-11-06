<?php
/**
 * GET /api/dashboard-stats.php?from=YYYY-MM-DD&to=YYYY-MM-DD
 * Real database aggregates for the Gym Admin dashboard KPIs.
 * Default window: start of the current month → today.
 */
require_once __DIR__ . '/_bootstrap.php';

api_handle(function (): void {
    api_require_login();
    api_require_roles(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer', 'Receptionist', 'Accountant']);
    $tid = api_tenant();

    Membership::sweepExpired($tid);

    $from = (string) ($_GET['from'] ?? date('Y-m-01'));
    $to = (string) ($_GET['to'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

    $memberStats = Member::stats($tid);
    $expiring = Member::expiringSoon($tid, 7);
    $expired = Database::fetchAll(
        'SELECT m.id, m.name, m.email, ms.plan_name, ms.end_date FROM memberships ms
         JOIN members m ON m.id = ms.member_id
         WHERE ms.tenant_id = ? AND ms.status = "expired" ORDER BY ms.end_date DESC LIMIT 10',
        [$tid]
    );
    $pendingCount = (int) (Database::scalar('SELECT COUNT(*) FROM payments WHERE tenant_id = ? AND status = "pending"', [$tid]) ?? 0);
    $pendingAmount = (float) (Database::scalar('SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = "pending"', [$tid]) ?? 0);
    $revenue = (float) (Database::scalar(
        'SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = "verified"
         AND DATE(COALESCE(paid_at, created_at)) BETWEEN ? AND ?',
        [$tid, $from, $to]
    ) ?? 0);
    $newRegs = (int) (Database::scalar(
        'SELECT COUNT(*) FROM members WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ?',
        [$tid, $from, $to]
    ) ?? 0);
    $activeClasses = (int) (Database::scalar('SELECT COUNT(*) FROM classes WHERE tenant_id = ? AND status = "active"', [$tid]) ?? 0);
    $alerts = Equipment::maintenanceDue($tid);

    json_out([
        'ok' => true,
        'range' => ['from' => $from, 'to' => $to],
        'kpis' => [
            'total_members'        => $memberStats['total'],
            'active_members'       => $memberStats['active'],
            'new_registrations'    => $newRegs,
            'expired_memberships'  => count($expired),
            'expiring_soon'        => count($expiring),
            'revenue_verified'     => $revenue,
            'revenue_label'        => money($revenue),
            'pending_payments'     => $pendingCount,
            'pending_amount'       => $pendingAmount,
            'pending_label'        => money($pendingAmount),
            'todays_checkins'      => $memberStats['present_today'],
            'total_trainers'       => (int) (Database::scalar('SELECT COUNT(*) FROM trainers WHERE tenant_id = ? AND status = "active"', [$tid]) ?? 0),
            'active_classes'       => $activeClasses,
            'equipment_alerts'     => count($alerts),
        ],
        'expiring_soon' => $expiring,
        'expired'       => $expired,
        'recent_members' => Member::withMembership($tid, 5),
        'recent_payments' => Payment::recent($tid, 5),
        'equipment_alerts' => $alerts,
    ]);
});
