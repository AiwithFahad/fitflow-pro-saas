<?php
/**
 * GET /api/members-export.php?q=&status=&branch_id=&plan=
 * Streams the filtered member list as CSV (same filters as api/members.php).
 * Medical data is NEVER included in exports.
 */
require_once __DIR__ . '/../app/bootstrap.php';

$user = current_user();
if ($user === null) {
    http_response_code(401);
    exit('Unauthenticated');
}
if (!has_role('Gym Owner', 'Gym Admin', 'Branch Manager', 'Receptionist') || !can('members.view')) {
    http_response_code(403);
    exit('Forbidden');
}
$tid = tenant_id();
if ($tid === null) {
    http_response_code(403);
    exit('No gym associated with this account');
}

try {
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $branchId = (int) ($_GET['branch_id'] ?? 0);
    $plan = trim((string) ($_GET['plan'] ?? ''));
    $where = 'm.tenant_id = ?';
    $params = [$tid];
    if ($q !== '') {
        $where .= ' AND (m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ? OR m.member_code LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($status !== '') {
        $where .= ' AND m.status = ?';
        $params[] = $status;
    }
    if ($branchId > 0) {
        $where .= ' AND m.branch_id = ?';
        $params[] = $branchId;
    }
    if ($plan !== '') {
        $where .= ' AND ms.plan_name = ?';
        $params[] = $plan;
    }
    $rows = Database::fetchAll(
        "SELECT m.member_code, m.name, m.email, m.phone, m.status, m.joined_at,
                b.name AS branch_name, t.name AS trainer_name,
                ms.plan_name, ms.start_date, ms.end_date, ms.status AS membership_status, m.bmi
         FROM members m
         LEFT JOIN branches b ON b.id = m.branch_id
         LEFT JOIN trainers t ON t.id = m.trainer_id
         LEFT JOIN memberships ms ON ms.id = (SELECT id FROM memberships WHERE member_id = m.id ORDER BY id DESC LIMIT 1)
         WHERE {$where} ORDER BY m.id DESC LIMIT 5000",
        $params
    );
} catch (Throwable $ex) {
    http_response_code(500);
    exit('Export failed');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="fitflow-members-' . date('Ymd-His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Member ID', 'Name', 'Email', 'Phone', 'Status', 'Joined', 'Branch', 'Trainer', 'Plan', 'Membership Start', 'Membership End', 'Membership Status', 'BMI']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['member_code'], $r['name'], $r['email'], $r['phone'], $r['status'], $r['joined_at'],
        $r['branch_name'], $r['trainer_name'], $r['plan_name'], $r['start_date'], $r['end_date'],
        $r['membership_status'], $r['bmi'],
    ]);
}
fclose($out);
