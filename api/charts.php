<?php
/**
 * GET /api/charts.php
 * Chart.js-ready series computed from real rows (never hardcoded):
 * monthly revenue, membership growth, daily attendance (14d),
 * plan distribution, trainer performance.
 */
require_once __DIR__ . '/_bootstrap.php';

api_handle(function (): void {
    api_require_login();
    api_require_roles(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer', 'Receptionist', 'Accountant']);
    $tid = api_tenant();

    // --- Monthly revenue (verified payments, last 6 months) -------------
    $revRows = Database::fetchAll(
        'SELECT DATE_FORMAT(COALESCE(paid_at, created_at), "%Y-%m") AS ym, COALESCE(SUM(amount),0) AS total
         FROM payments WHERE tenant_id = ? AND status = "verified"
           AND COALESCE(paid_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym',
        [$tid]
    );
    $revMap = array_column($revRows, 'total', 'ym');

    // --- Membership growth (new members per month, last 6 months) -------
    $growRows = Database::fetchAll(
        'SELECT DATE_FORMAT(created_at, "%Y-%m") AS ym, COUNT(*) AS total
         FROM members WHERE tenant_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym',
        [$tid]
    );
    $growMap = array_column($growRows, 'total', 'ym');

    $labels = $revData = $growData = [];
    for ($i = 5; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-{$i} months"));
        $labels[] = date('M Y', strtotime($ym . '-01'));
        $revData[] = (float) ($revMap[$ym] ?? 0);
        $growData[] = (int) ($growMap[$ym] ?? 0);
    }

    // --- Daily attendance (last 14 days, zero-filled) -------------------
    $attMap = [];
    foreach (Attendance::series($tid, 14) as $row) {
        $attMap[$row['day']] = (int) $row['total'];
    }
    $attLabels = $attData = [];
    for ($i = 13; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $attLabels[] = date('D j', strtotime($day));
        $attData[] = $attMap[$day] ?? 0;
    }

    // --- Plan distribution (current memberships) -------------------------
    $planRows = Database::fetchAll(
        'SELECT plan_name, COUNT(*) AS total FROM memberships
         WHERE tenant_id = ? AND status IN ("active","suspended") GROUP BY plan_name ORDER BY total DESC',
        [$tid]
    );

    // --- Trainer performance --------------------------------------------
    $trainerRows = Database::fetchAll(
        'SELECT t.name,
                (SELECT COUNT(*) FROM members m WHERE m.trainer_id = t.id AND m.tenant_id = t.tenant_id) AS members,
                (SELECT COUNT(*) FROM classes c WHERE c.trainer_id = t.id AND c.tenant_id = t.tenant_id AND c.status = "active") AS classes,
                (SELECT COUNT(*) FROM attendance a JOIN members m2 ON m2.id = a.member_id
                  WHERE m2.trainer_id = t.id AND a.tenant_id = t.tenant_id
                    AND a.check_in_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS member_checkins
         FROM trainers t WHERE t.tenant_id = ? AND t.status = "active" ORDER BY members DESC',
        [$tid]
    );

    json_out([
        'ok' => true,
        'revenue_monthly'     => ['labels' => $labels, 'data' => $revData],
        'membership_growth'   => ['labels' => $labels, 'data' => $growData],
        'attendance_daily'    => ['labels' => $attLabels, 'data' => $attData],
        'plan_distribution'   => [
            'labels' => array_column($planRows, 'plan_name'),
            'data'   => array_map('intval', array_column($planRows, 'total')),
        ],
        'trainer_performance' => [
            'labels'  => array_column($trainerRows, 'name'),
            'members' => array_map('intval', array_column($trainerRows, 'members')),
            'classes' => array_map('intval', array_column($trainerRows, 'classes')),
            'member_checkins' => array_map('intval', array_column($trainerRows, 'member_checkins')),
        ],
    ]);
});
