<?php
/**
 * /api/attendance.php — check-in/out, trainer attendance, reports.
 *  GET  ?view=today                        → today's member sessions
 *  GET  ?view=trainers                     → today's trainer sessions
 *  GET  ?view=report&period=daily|weekly|monthly
 *  GET  ?view=corrections                  → audit trail
 *  POST action=scan       {qr_token}       → QR toggle in/out
 *  POST action=manual_in  {member_id}      → manual check-in (duplicates refused)
 *  POST action=out        {attendance_id}  → check out an open session
 *  POST action=trainer_toggle {trainer_id}
 *  POST action=correct    {attendance_id, check_in_at, check_out_at, reason}
 */
require_once __DIR__ . '/_bootstrap.php';

api_handle(function (): void {
    api_require_login();
    api_require_roles(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer', 'Receptionist']);
    $tid = api_tenant();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        api_require_perm('attendance.view');
        $view = (string) ($_GET['view'] ?? 'today');
        if ($view === 'trainers') {
            json_out(['ok' => true, 'data' => TrainerAttendance::today($tid)]);
        }
        if ($view === 'report') {
            $period = (string) ($_GET['period'] ?? 'daily');
            if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
                $period = 'daily';
            }
            json_out(['ok' => true, 'period' => $period, 'data' => Attendance::report($tid, $period)]);
        }
        if ($view === 'corrections') {
            json_out(['ok' => true, 'data' => Attendance::corrections($tid, 30)]);
        }
        $rows = Attendance::today($tid, 200);
        foreach ($rows as &$r) {
            $r['state'] = empty($r['check_out_at']) ? 'in_gym' : 'checked_out';
        }
        unset($r);
        json_out(['ok' => true, 'data' => $rows, 'in_gym' => count(array_filter($rows, fn ($r) => $r['state'] === 'in_gym'))]);
    }

    api_require_csrf();
    $in = api_body();
    $action = (string) ($in['action'] ?? '');

    if ($action === 'scan') {
        api_require_perm('attendance.manage');
        $token = trim((string) ($in['qr_token'] ?? ''));
        if ($token === '') {
            json_out(['ok' => false, 'error' => 'Enter or scan a QR token.'], 422);
        }
        $res = Attendance::scanQr($tid, $token);
        if ($res === null) {
            json_out(['ok' => false, 'error' => 'QR token not recognised for this gym.'], 404);
        }
        json_out([
            'ok' => true,
            'action' => $res['action'],
            'member' => $res['member']['name'],
            'message' => $res['member']['name'] . ($res['action'] === 'check_in' ? ' checked IN.' : ' checked OUT.'),
        ]);
    }

    if ($action === 'manual_in') {
        api_require_perm('attendance.manage');
        $res = Attendance::manualCheckIn($tid, (int) ($in['member_id'] ?? 0), isset($in['branch_id']) ? (int) $in['branch_id'] : null);
        if (!$res['ok']) {
            json_out(['ok' => false, 'error' => $res['error']], 422);
        }
        json_out(['ok' => true, 'message' => $res['member'] . ' checked IN manually.', 'data' => $res]);
    }

    if ($action === 'out') {
        api_require_perm('attendance.manage');
        $res = Attendance::checkOut($tid, (int) ($in['attendance_id'] ?? 0));
        if (!$res['ok']) {
            json_out(['ok' => false, 'error' => $res['error']], 422);
        }
        json_out(['ok' => true, 'message' => $res['member'] . ' checked OUT.', 'data' => $res]);
    }

    if ($action === 'trainer_toggle') {
        api_require_perm('trainers.attendance');
        $res = TrainerAttendance::toggle($tid, (int) ($in['trainer_id'] ?? 0));
        if (!$res['ok']) {
            json_out(['ok' => false, 'error' => $res['error']], 422);
        }
        json_out(['ok' => true, 'message' => $res['trainer'] . ($res['action'] === 'checked_in' ? ' checked IN.' : ' checked OUT.'), 'data' => $res]);
    }

    if ($action === 'correct') {
        api_require_perm('attendance.correct');
        $reason = trim((string) ($in['reason'] ?? ''));
        if ($reason === '') {
            json_out(['ok' => false, 'error' => 'A reason is required for every correction (audit trail).'], 422);
        }
        $res = Attendance::correct($tid, (int) ($in['attendance_id'] ?? 0), [
            'check_in_at' => $in['check_in_at'] ?? null,
            'check_out_at' => $in['check_out_at'] ?? null,
        ], $reason, (int) current_user()['id']);
        if (!$res['ok']) {
            json_out(['ok' => false, 'error' => $res['error']], 422);
        }
        json_out(['ok' => true, 'message' => 'Attendance corrected and logged: ' . implode(', ', $res['changed']) . '.']);
    }

    json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
});
