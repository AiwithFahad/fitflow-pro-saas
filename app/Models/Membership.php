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
}
