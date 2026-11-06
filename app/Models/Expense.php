<?php
/** Expense — operating expenses per gym (rent, utilities, marketing, ...). */
class Expense extends BaseModel
{
    protected static string $table = 'expenses';

    public static function categories(): array
    {
        return ['Rent', 'Utilities', 'Marketing', 'Maintenance', 'Supplies', 'Software', 'Insurance', 'General'];
    }

    /** Total expenses in a date range (tenant scoped). */
    public static function totalInRange(int $tenantId, string $from, string $to): float
    {
        return (float) (Database::scalar(
            'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?',
            [$tenantId, $from, $to]
        ) ?? 0);
    }

    /** Expenses grouped by category in a date range. */
    public static function byCategory(int $tenantId, string $from, string $to): array
    {
        return Database::fetchAll(
            'SELECT category, COUNT(*) AS entries, COALESCE(SUM(amount),0) AS total
             FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?
             GROUP BY category ORDER BY total DESC',
            [$tenantId, $from, $to]
        );
    }
}
