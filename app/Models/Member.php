<?php
/** Gym members: profiles, memberships, expiry alerts, progress. */
class Member extends BaseModel
{
    protected static string $table = 'members';

    /** Next human-friendly membership ID, e.g. FFP-M0042 (unique per gym). */
    public static function nextMemberCode(int $tenantId): string
    {
        $max = (int) (Database::scalar(
            'SELECT COALESCE(MAX(id),0) FROM members WHERE tenant_id = ?', [$tenantId]
        ) ?? 0);
        return 'FFP-M' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function withMembership(int $tenantId, int $limit = 200, string $search = ''): array
    {
        $sql = 'SELECT m.*, b.name AS branch_name,
                       ms.plan_name AS membership_plan, ms.end_date AS membership_end, ms.status AS membership_status,
                       t.name AS trainer_name
                FROM members m
                LEFT JOIN branches b ON b.id = m.branch_id
                LEFT JOIN memberships ms ON ms.id = (
                    SELECT id FROM memberships WHERE member_id = m.id ORDER BY id DESC LIMIT 1
                )
                LEFT JOIN trainers t ON t.id = m.trainer_id
                WHERE m.tenant_id = ?';
        $params = [$tenantId];
        if ($search !== '') {
            $sql .= ' AND (m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }
        $sql .= ' ORDER BY m.id DESC LIMIT ' . $limit;
        return Database::fetchAll($sql, $params);
    }

    /** Members whose current membership ends within $days days (expiry alerts). */
    public static function expiringSoon(int $tenantId, int $days = 7): array
    {
        return Database::fetchAll(
            'SELECT m.id, m.name, m.email, ms.plan_name, ms.end_date
             FROM memberships ms
             JOIN members m ON m.id = ms.member_id
             WHERE ms.tenant_id = ? AND ms.status = "active"
               AND ms.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY ms.end_date ASC',
            [$tenantId, $days]
        );
    }

    public static function expired(int $tenantId): array
    {
        return Database::fetchAll(
            'SELECT m.id, m.name, m.email, ms.plan_name, ms.end_date
             FROM memberships ms JOIN members m ON m.id = ms.member_id
             WHERE ms.tenant_id = ? AND ms.status = "active" AND ms.end_date < CURDATE()
             ORDER BY ms.end_date DESC LIMIT 100',
            [$tenantId]
        );
    }

    public static function stats(int $tenantId): array
    {
        return [
            'total'   => (int) Database::scalar('SELECT COUNT(*) FROM members WHERE tenant_id = ?', [$tenantId]),
            'active'  => (int) Database::scalar('SELECT COUNT(*) FROM members WHERE tenant_id = ? AND status = "active"', [$tenantId]),
            'new_month' => (int) Database::scalar('SELECT COUNT(*) FROM members WHERE tenant_id = ? AND created_at > (NOW() - INTERVAL 30 DAY)', [$tenantId]),
            'present_today' => (int) Database::scalar('SELECT COUNT(DISTINCT member_id) FROM attendance WHERE tenant_id = ? AND DATE(check_in_at) = CURDATE()', [$tenantId]),
        ];
    }

    /** Record a body-measurement progress entry and refresh member BMI fields. */
    public static function addProgress(int $tenantId, int $memberId, float $weightKg, ?float $bodyFat = null, string $notes = ''): int
    {
        $member = self::find($memberId, $tenantId);
        $bmiVal = null;
        if ($member && !empty($member['height_cm'])) {
            $b = bmi($weightKg, (float) $member['height_cm']);
            $bmiVal = $b['value'];
            Database::update('members', ['weight_kg' => $weightKg, 'bmi' => $bmiVal], 'id = :wid AND tenant_id = :wtid', ['wid' => $memberId, 'wtid' => $tenantId]);
        }
        return Database::insert('progress_records', [
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'weight_kg' => $weightKg,
            'body_fat_percent' => $bodyFat,
            'bmi' => $bmiVal,
            'notes' => $notes,
            'recorded_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
