<?php
/** StaffSalary — monthly salary records for trainers and staff users. */
class StaffSalary extends BaseModel
{
    protected static string $table = 'staff_salaries';

    /** Salary rows for one payroll month (YYYY-MM), with computed net pay. */
    public static function forPeriod(int $tenantId, string $period): array
    {
        return Database::fetchAll(
            'SELECT s.*, (s.base_salary + s.allowances - s.deductions) AS net_pay
             FROM staff_salaries s WHERE s.tenant_id = ? AND s.period_month = ?
             ORDER BY s.person_name',
            [$tenantId, $period]
        );
    }

    public static function totalForPeriod(int $tenantId, string $period, ?string $status = null): float
    {
        $sql = 'SELECT COALESCE(SUM(base_salary + allowances - deductions),0) FROM staff_salaries
                WHERE tenant_id = ? AND period_month = ?';
        $params = [$tenantId, $period];
        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        return (float) (Database::scalar($sql, $params) ?? 0);
    }

    public static function markPaid(int $tenantId, int $id): int
    {
        return Database::update('staff_salaries', [
            'status'  => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
        ], 'id = :wid AND tenant_id = :wtid', ['wid' => $id, 'wtid' => $tenantId]);
    }
}
