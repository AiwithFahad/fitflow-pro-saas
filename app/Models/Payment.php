<?php
/**
 * Payments. IMPORTANT business rule: a payment recorded manually starts as
 * "pending". It may only become "verified" after confirmed verification
 * (gateway webhook confirmation or a Super Admin / Accountant review).
 * Nothing in this codebase marks a payment successful on submission alone.
 */
class Payment extends BaseModel
{
    protected static string $table = 'payments';

    public static function recordManual(?int $tenantId, array $data): int
    {
        $data['status'] = 'pending'; // never auto-verified
        $data['method'] = $data['method'] ?? 'manual';
        $id = Database::insert('payments', $data);
        log_activity($tenantId, 'payment.recorded_pending', 'payments', $id, 'Manual payment recorded; awaiting verification.');
        return $id;
    }

    public static function verify(int $id, int $verifiedBy): void
    {
        Database::update('payments', [
            'status'      => 'verified',
            'verified_by' => $verifiedBy,
            'paid_at'     => date('Y-m-d H:i:s'),
        ], 'id = :wid AND status = "pending"', ['wid' => $id]);
    }

    public static function totals(?int $tenantId = null): array
    {
        $where = $tenantId !== null ? 'WHERE tenant_id = ?' : '';
        $params = $tenantId !== null ? [$tenantId] : [];
        return [
            'verified' => (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments {$where} " . ($where ? 'AND' : 'WHERE') . ' status = "verified"', $params),
            'pending'  => (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments {$where} " . ($where ? 'AND' : 'WHERE') . ' status = "pending"', $params),
            'count'    => (int) Database::scalar("SELECT COUNT(*) FROM payments {$where}", $params),
        ];
    }

    public static function recent(?int $tenantId = null, int $limit = 50): array
    {
        $sql = 'SELECT * FROM payments';
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' WHERE tenant_id = ?';
            $params[] = $tenantId;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        return Database::fetchAll($sql, $params);
    }
}
