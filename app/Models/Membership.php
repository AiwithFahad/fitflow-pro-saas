<?php
/** Member memberships (gym plans sold to members): enrol, renew, expire. */
class Membership extends BaseModel
{
    protected static string $table = 'memberships';

    /** Enrol a member into a gym membership plan; returns membership id. */
    public static function enrol(int $tenantId, int $memberId, array $plan, ?int $couponId = null): int
    {
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime($start . ' +' . (int) $plan['duration_days'] . ' days'));
        $price = (float) $plan['price'];
        if ($couponId !== null) {
            $coupon = Coupon::find($couponId);
            if ($coupon) {
                $price = Coupon::applyDiscount($price, $coupon);
                Coupon::redeem($couponId);
            }
        }
        $id = Database::insert('memberships', [
            'tenant_id'  => $tenantId,
            'member_id'  => $memberId,
            'plan_name'  => $plan['name'],
            'price'      => $price,
            'start_date' => $start,
            'end_date'   => $end,
            'status'     => 'active',
            'coupon_id'  => $couponId,
        ]);
        Database::update('members', ['status' => 'active'], 'id = :wid AND tenant_id = :wtid', ['wid' => $memberId, 'wtid' => $tenantId]);
        log_activity($tenantId, 'membership.enrolled', 'memberships', $id, $plan['name']);
        return $id;
    }

    /** Renew: extend the latest membership (or start a new period today). */
    public static function renew(int $tenantId, int $membershipId): void
    {
        $ms = self::find($membershipId, $tenantId);
        if ($ms === null) {
            return;
        }
        $plan = Database::fetch('SELECT duration_days FROM membership_plans WHERE tenant_id = ? AND name = ? LIMIT 1', [$tenantId, $ms['plan_name']]);
        $days = (int) ($plan['duration_days'] ?? 30);
        $base = strtotime($ms['end_date']) >= strtotime(date('Y-m-d')) ? $ms['end_date'] : date('Y-m-d');
        $newEnd = date('Y-m-d', strtotime($base . ' +' . $days . ' days'));
        Database::update('memberships', ['end_date' => $newEnd, 'status' => 'active'], 'id = :wid AND tenant_id = :wtid', ['wid' => $membershipId, 'wtid' => $tenantId]);
        log_activity($tenantId, 'membership.renewed', 'memberships', $membershipId, 'Extended to ' . $newEnd);
    }

    /** Mark overdue memberships expired (called from dashboards/cron). */
    public static function sweepExpired(int $tenantId): int
    {
        return Database::query(
            'UPDATE memberships SET status = "expired" WHERE tenant_id = ? AND status = "active" AND end_date < CURDATE()',
            [$tenantId]
        )->rowCount();
    }

    /**
     * Automatic status calculation: manual states (suspended/cancelled)
     * always win; an "active" row past its end date is expired, one ending
     * within 7 days is expiring.
     */
    public static function computedStatus(array $membership): string
    {
        $status = (string) ($membership['status'] ?? 'active');
        if ($status === 'suspended' || $status === 'cancelled') {
            return $status;
        }
        $end = strtotime((string) ($membership['end_date'] ?? ''));
        if ($end !== false && $end < strtotime('today')) {
            return 'expired';
        }
        if ($status === 'expired') {
            return 'expired';
        }
        if ($end !== false && $end <= strtotime('+7 days')) {
            return 'expiring';
        }
        return 'active';
    }

    /** Days left on a membership (negative when already expired). */
    public static function daysLeft(array $membership): int
    {
        $end = strtotime((string) ($membership['end_date'] ?? ''));
        if ($end === false) {
            return 0;
        }
        return (int) floor(($end - strtotime('today')) / 86400);
    }

    /** Pause a membership (dates kept; member access is paused). */
    public static function suspend(int $tenantId, int $id): int
    {
        return Database::update('memberships', ['status' => 'suspended'],
            'id = :wid AND tenant_id = :wtid AND status IN ("active","expired")', ['wid' => $id, 'wtid' => $tenantId]);
    }

    /** Resume a suspended membership; if its end date passed it becomes expired. */
    public static function reactivate(int $tenantId, int $id): int
    {
        $ms = self::find($id, $tenantId);
        if ($ms === null) {
            return 0;
        }
        $status = strtotime((string) $ms['end_date']) < strtotime('today') ? 'expired' : 'active';
        return Database::update('memberships', ['status' => $status],
            'id = :wid AND tenant_id = :wtid', ['wid' => $id, 'wtid' => $tenantId]);
    }

    /** Cancel a membership immediately (history kept; access ends). */
    public static function cancelMembership(int $tenantId, int $id): int
    {
        return Database::update('memberships', ['status' => 'cancelled'],
            'id = :wid AND tenant_id = :wtid AND status <> "cancelled"', ['wid' => $id, 'wtid' => $tenantId]);
    }

    /**
     * Upgrade/downgrade: cancel the member's current active membership and
     * enrol them in the new plan starting today (coupon optional).
     */
    public static function changePlan(int $tenantId, int $memberId, array $plan, ?int $couponId = null): array
    {
        Database::update('memberships', ['status' => 'cancelled'],
            'tenant_id = :wtid AND member_id = :mid AND status = "active"',
            ['wtid' => $tenantId, 'mid' => $memberId]);
        $id = self::enrol($tenantId, $memberId, $plan, $couponId);
        return ['ok' => true, 'id' => $id, 'plan_name' => $plan['name']];
    }
}
