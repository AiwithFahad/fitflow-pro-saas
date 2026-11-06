<?php
/** Discount coupons for subscriptions and memberships. */
class Coupon extends BaseModel
{
    protected static string $table = 'coupons';
    protected static bool $tenantScoped = false;

    /** Validate a coupon code; returns the coupon row or null. */
    public static function validateCode(string $code): ?array
    {
        $coupon = Database::fetch(
            'SELECT * FROM coupons WHERE code = ? AND is_active = 1
               AND (expires_at IS NULL OR expires_at > NOW())
               AND (max_uses IS NULL OR used_count < max_uses) LIMIT 1',
            [strtoupper(trim($code))]
        );
        return $coupon ?: null;
    }

    public static function applyDiscount(float $amount, array $coupon): float
    {
        $discount = $coupon['type'] === 'percent'
            ? $amount * ((float) $coupon['value'] / 100)
            : min((float) $coupon['value'], $amount);
        return round(max(0, $amount - $discount), 2);
    }

    public static function redeem(int $id): void
    {
        Database::query('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$id]);
    }
}
