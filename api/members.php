<?php
/**
 * /api/members.php — member CRUD + detail for AJAX tables and modals.
 *  GET  ?q=&status=&branch_id=&plan=&page=&per_page=   → paged list
 *  GET  ?id=N                                          → full detail
 *  POST action=create|update|delete                    → CRUD (CSRF)
 *  POST action=progress                                → body measurement
 *  POST action=medical_save                            → clearance record (restricted)
 *  POST action=membership_status {membership_id, op}   → suspend/reactivate/cancel/renew
 *  POST action=change_plan {member_id, plan_id, coupon_code?}
 * Every query is tenant-scoped; medical data is only returned or written
 * when the caller's role holds the 'members.medical' permission.
 */
require_once __DIR__ . '/_bootstrap.php';

api_handle(function (): void {
    api_require_login();
    api_require_roles(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer', 'Receptionist']);
    $tid = api_tenant();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ---------------------------------------------------------------- GET
    if ($method === 'GET' && isset($_GET['id'])) {
        api_require_perm('members.view');
        $id = (int) $_GET['id'];
        $member = Member::find($id, $tid);
        if ($member === null) {
            json_out(['ok' => false, 'error' => 'Member not found.'], 404);
        }
        $membership = Database::fetch(
            'SELECT * FROM memberships WHERE tenant_id = ? AND member_id = ? ORDER BY id DESC LIMIT 1',
            [$tid, $id]
        );
        if ($membership) {
            $membership['computed_status'] = Membership::computedStatus($membership);
            $membership['days_left'] = Membership::daysLeft($membership);
        }
        $out = [
            'ok' => true,
            'member' => $member,
            'membership' => $membership,
            'history' => Database::fetchAll(
                'SELECT * FROM memberships WHERE tenant_id = ? AND member_id = ? ORDER BY id DESC LIMIT 10',
                [$tid, $id]
            ),
            'goals' => FitnessGoal::forMember($tid, $id),
            'progress' => Database::fetchAll(
                'SELECT * FROM progress_records WHERE tenant_id = ? AND member_id = ? ORDER BY recorded_at DESC LIMIT 12',
                [$tid, $id]
            ),
            'workout_logs' => WorkoutLog::forMember($tid, $id, 12),
            'trainer' => !empty($member['trainer_id'])
                ? Database::fetch('SELECT id, name, specialty, qualifications FROM trainers WHERE id = ? AND tenant_id = ?', [(int) $member['trainer_id'], $tid])
                : null,
            'medical_restricted' => !can('members.medical'),
        ];
        if (can('members.medical')) {
            $out['medical'] = MedicalRecord::latestForMember($tid, $id);
        }
        json_out($out);
    }

    if ($method === 'GET') {
        api_require_perm('members.view');
        $q = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $branchId = (int) ($_GET['branch_id'] ?? 0);
        $plan = trim((string) ($_GET['plan'] ?? ''));
        $trainerId = (int) ($_GET['trainer_id'] ?? 0);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int) ($_GET['per_page'] ?? 15)));

        $where = 'm.tenant_id = ?';
        $params = [$tid];
        if ($q !== '') {
            $where .= ' AND (m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ? OR m.member_code LIKE ? OR m.qr_token LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where .= ' AND m.status = ?';
            $params[] = $status;
        }
        if ($branchId > 0) {
            $where .= ' AND m.branch_id = ?';
            $params[] = $branchId;
        }
        if ($trainerId > 0) {
            $where .= ' AND m.trainer_id = ?';
            $params[] = $trainerId;
        }
        if ($plan !== '') {
            $where .= ' AND ms.plan_name = ?';
            $params[] = $plan;
        }
        $join = 'FROM members m
                 LEFT JOIN branches b ON b.id = m.branch_id
                 LEFT JOIN trainers t ON t.id = m.trainer_id
                 LEFT JOIN memberships ms ON ms.id = (SELECT id FROM memberships WHERE member_id = m.id ORDER BY id DESC LIMIT 1)';
        $total = (int) (Database::scalar("SELECT COUNT(*) {$join} WHERE {$where}", $params) ?? 0);
        $rows = Database::fetchAll(
            "SELECT m.*, b.name AS branch_name, t.name AS trainer_name,
                    ms.id AS membership_id, ms.plan_name AS membership_plan, ms.start_date AS membership_start,
                    ms.end_date AS membership_end, ms.status AS membership_status, ms.price AS membership_price
             {$join} WHERE {$where} ORDER BY m.id DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );
        foreach ($rows as &$r) {
            if (!empty($r['membership_id'])) {
                $r['computed_status'] = Membership::computedStatus([
                    'status' => $r['membership_status'], 'end_date' => $r['membership_end'],
                ]);
                $r['days_left'] = Membership::daysLeft(['end_date' => $r['membership_end']]);
            } else {
                $r['computed_status'] = 'none';
                $r['days_left'] = null;
            }
        }
        unset($r);
        json_out([
            'ok' => true, 'data' => $rows, 'total' => $total,
            'page' => $page, 'per_page' => $perPage,
            'pages' => (int) max(1, ceil($total / $perPage)),
        ]);
    }

    // --------------------------------------------------------------- POST
    api_require_csrf();
    $in = api_body();
    $action = (string) ($in['action'] ?? '');

    if ($action === 'create') {
        api_require_perm('members.create');
        if (!plan_allows($tid, 'members')) {
            json_out(['ok' => false, 'error' => plan_limit_message('members')], 422);
        }
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            json_out(['ok' => false, 'error' => 'Member name is required.'], 422);
        }
        $height = (float) ($in['height_cm'] ?? 0) ?: null;
        $weight = (float) ($in['weight_kg'] ?? 0) ?: null;
        $bmiVal = ($height && $weight) ? bmi($weight, $height)['value'] : null;
        // Provisional insert to obtain the id, then stamp the member code.
        $id = Member::create([
            'tenant_id' => $tid,
            'branch_id' => (int) ($in['branch_id'] ?? 0) ?: null,
            'trainer_id' => (int) ($in['trainer_id'] ?? 0) ?: null,
            'name' => $name,
            'email' => trim((string) ($in['email'] ?? '')) ?: null,
            'phone' => trim((string) ($in['phone'] ?? '')) ?: null,
            'photo_url' => trim((string) ($in['photo_url'] ?? '')) ?: null,
            'address' => trim((string) ($in['address'] ?? '')) ?: null,
            'emergency_contact_name' => trim((string) ($in['emergency_contact_name'] ?? '')) ?: null,
            'emergency_contact_phone' => trim((string) ($in['emergency_contact_phone'] ?? '')) ?: null,
            'gender' => in_array($in['gender'] ?? 'male', ['male', 'female', 'other'], true) ? ($in['gender'] ?? 'male') : 'male',
            'date_of_birth' => ($in['date_of_birth'] ?? '') ?: null,
            'height_cm' => $height,
            'weight_kg' => $weight,
            'bmi' => $bmiVal,
            'qr_token' => qr_token(),
            'status' => 'active',
            'joined_at' => date('Y-m-d'),
        ]);
        Member::updateById($id, ['member_code' => 'FFP-M' . str_pad((string) $id, 4, '0', STR_PAD_LEFT)], $tid);
        $membershipId = null;
        $planId = (int) ($in['plan_id'] ?? 0);
        if ($planId > 0) {
            $plan = MembershipPlan::find($planId, $tid);
            if ($plan) {
                $couponId = null;
                $code = trim((string) ($in['coupon_code'] ?? ''));
                if ($code !== '') {
                    $coupon = Coupon::validateCode($code);
                    if ($coupon) {
                        $couponId = (int) $coupon['id'];
                    }
                }
                $membershipId = Membership::enrol($tid, $id, $plan, $couponId);
            }
        }
        log_activity($tid, 'member.created', 'members', $id, $name);
        notify($tid, null, 'New member registered', $name . ' joined the gym.', 'member');
        json_out(['ok' => true, 'message' => 'Member registered.', 'id' => $id, 'membership_id' => $membershipId,
                  'member' => Member::find($id, $tid)]);
    }

    if ($action === 'update') {
        api_require_perm('members.edit');
        $id = (int) ($in['id'] ?? 0);
        if (Member::find($id, $tid) === null) {
            json_out(['ok' => false, 'error' => 'Member not found.'], 404);
        }
        $data = [];
        foreach (['name', 'email', 'phone', 'photo_url', 'address', 'emergency_contact_name', 'emergency_contact_phone', 'gender', 'date_of_birth', 'status'] as $f) {
            if (array_key_exists($f, $in)) {
                $data[$f] = trim((string) $in[$f]) === '' ? null : trim((string) $in[$f]);
            }
        }
        if (isset($data['name']) && $data['name'] === null) {
            json_out(['ok' => false, 'error' => 'Member name cannot be empty.'], 422);
        }
        foreach (['branch_id', 'trainer_id'] as $f) {
            if (array_key_exists($f, $in)) {
                $data[$f] = (int) $in[$f] ?: null;
            }
        }
        foreach (['height_cm', 'weight_kg'] as $f) {
            if (array_key_exists($f, $in)) {
                $data[$f] = (float) $in[$f] ?: null;
            }
        }
        if (isset($data['height_cm'], $data['weight_kg']) && $data['height_cm'] && $data['weight_kg']) {
            $data['bmi'] = bmi((float) $data['weight_kg'], (float) $data['height_cm'])['value'];
        }
        if ($data) {
            Member::updateById($id, $data, $tid);
        }
        json_out(['ok' => true, 'message' => 'Member updated.', 'member' => Member::find($id, $tid)]);
    }

    if ($action === 'delete') {
        api_require_perm('members.delete');
        $id = (int) ($in['id'] ?? 0);
        $n = Member::deleteById($id, $tid);
        if ($n === 0) {
            json_out(['ok' => false, 'error' => 'Member not found.'], 404);
        }
        log_activity($tid, 'member.deleted', 'members', $id);
        json_out(['ok' => true, 'message' => 'Member deleted.']);
    }

    if ($action === 'progress') {
        api_require_perm('members.edit');
        $id = (int) ($in['member_id'] ?? 0);
        $weight = (float) ($in['weight_kg'] ?? 0);
        if ($weight <= 0) {
            json_out(['ok' => false, 'error' => 'Enter a valid weight.'], 422);
        }
        $pid = Member::addProgress($tid, $id, $weight, isset($in['body_fat_percent']) && $in['body_fat_percent'] !== '' ? (float) $in['body_fat_percent'] : null, trim((string) ($in['notes'] ?? '')));
        json_out(['ok' => true, 'message' => 'Progress recorded.', 'id' => $pid, 'member' => Member::find($id, $tid)]);
    }

    if ($action === 'medical_save') {
        api_require_perm('members.medical');
        $memberId = (int) ($in['member_id'] ?? 0);
        if (Member::find($memberId, $tid) === null) {
            json_out(['ok' => false, 'error' => 'Member not found.'], 404);
        }
        $status = (string) ($in['clearance_status'] ?? 'pending');
        if (!in_array($status, ['cleared', 'pending', 'restricted', 'expired'], true)) {
            $status = 'pending';
        }
        $id = MedicalRecord::create([
            'tenant_id' => $tid,
            'member_id' => $memberId,
            'clearance_status' => $status,
            'conditions' => trim((string) ($in['conditions'] ?? '')) ?: null,
            'allergies' => trim((string) ($in['allergies'] ?? '')) ?: null,
            'medications' => trim((string) ($in['medications'] ?? '')) ?: null,
            'doctor_name' => trim((string) ($in['doctor_name'] ?? '')) ?: null,
            'clearance_date' => ($in['clearance_date'] ?? '') ?: null,
            'expiry_date' => ($in['expiry_date'] ?? '') ?: null,
            'notes' => trim((string) ($in['notes'] ?? '')) ?: null,
            'recorded_by' => (int) current_user()['id'],
        ]);
        log_activity($tid, 'member.medical_updated', 'members', $memberId, 'Clearance: ' . $status);
        json_out(['ok' => true, 'message' => 'Medical clearance saved.', 'id' => $id]);
    }

    if ($action === 'membership_status') {
        api_require_perm('memberships.manage');
        $msId = (int) ($in['membership_id'] ?? 0);
        $op = (string) ($in['op'] ?? '');
        $ms = Membership::find($msId, $tid);
        if ($ms === null) {
            json_out(['ok' => false, 'error' => 'Membership not found.'], 404);
        }
        $done = match ($op) {
            'suspend'    => Membership::suspend($tid, $msId),
            'reactivate' => Membership::reactivate($tid, $msId),
            'cancel'     => Membership::cancelMembership($tid, $msId),
            'renew'      => (function () use ($tid, $msId) { Membership::renew($tid, $msId); return 1; })(),
            default      => 0,
        };
        if (!$done) {
            json_out(['ok' => false, 'error' => 'That operation is not valid for this membership state.'], 422);
        }
        log_activity($tid, 'membership.' . $op, 'memberships', $msId);
        $fresh = Membership::find($msId, $tid);
        $fresh['computed_status'] = Membership::computedStatus($fresh);
        $fresh['days_left'] = Membership::daysLeft($fresh);
        json_out(['ok' => true, 'message' => 'Membership ' . $op . ' applied.', 'membership' => $fresh]);
    }

    if ($action === 'change_plan') {
        api_require_perm('memberships.manage');
        $memberId = (int) ($in['member_id'] ?? 0);
        $plan = MembershipPlan::find((int) ($in['plan_id'] ?? 0), $tid);
        if ($plan === null || Member::find($memberId, $tid) === null) {
            json_out(['ok' => false, 'error' => 'Member or plan not found.'], 404);
        }
        $couponId = null;
        $code = trim((string) ($in['coupon_code'] ?? ''));
        if ($code !== '') {
            $coupon = Coupon::validateCode($code);
            if ($coupon) {
                $couponId = (int) $coupon['id'];
            }
        }
        $res = Membership::changePlan($tid, $memberId, $plan, $couponId);
        json_out(['ok' => true, 'message' => 'Plan changed to ' . $plan['name'] . '.', 'membership_id' => $res['id']]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
});
