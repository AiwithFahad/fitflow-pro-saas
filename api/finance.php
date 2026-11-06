<?php
/**
 * /api/finance.php — expenses + staff salaries + finance summary.
 *  GET  ?view=summary&from&to          → revenue vs expenses vs salaries, net
 *  GET  ?view=expenses&from&to         → expense rows + by-category totals
 *  GET  ?view=salaries&period=YYYY-MM  → salary rows for the month
 *  POST action=expense_create | expense_delete
 *  POST action=salary_create | salary_pay | salary_delete
 */
require_once __DIR__ . '/_bootstrap.php';

api_handle(function (): void {
    api_require_login();
    api_require_roles(['Gym Owner', 'Gym Admin', 'Accountant']);
    $tid = api_tenant();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        api_require_perm('finance.view');
        $view = (string) ($_GET['view'] ?? 'summary');
        $from = (string) ($_GET['from'] ?? date('Y-m-01'));
        $to = (string) ($_GET['to'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

        if ($view === 'expenses') {
            $rows = Database::fetchAll(
                'SELECT e.*, b.name AS branch_name FROM expenses e
                 LEFT JOIN branches b ON b.id = e.branch_id
                 WHERE e.tenant_id = ? AND e.expense_date BETWEEN ? AND ?
                 ORDER BY e.expense_date DESC, e.id DESC LIMIT 300',
                [$tid, $from, $to]
            );
            json_out(['ok' => true, 'data' => $rows,
                      'by_category' => Expense::byCategory($tid, $from, $to),
                      'total' => count($rows),
                      'total_amount' => Expense::totalInRange($tid, $from, $to)]);
        }
        if ($view === 'salaries') {
            $period = (string) ($_GET['period'] ?? date('Y-m'));
            if (!preg_match('/^\d{4}-\d{2}$/', $period)) $period = date('Y-m');
            json_out(['ok' => true, 'period' => $period,
                      'data' => StaffSalary::forPeriod($tid, $period),
                      'total' => StaffSalary::totalForPeriod($tid, $period),
                      'paid_total' => StaffSalary::totalForPeriod($tid, $period, 'paid'),
                      'pending_total' => StaffSalary::totalForPeriod($tid, $period, 'pending')]);
        }
        // summary
        $revenue = (float) (Database::scalar(
            'SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = "verified"
             AND DATE(COALESCE(paid_at, created_at)) BETWEEN ? AND ?',
            [$tid, $from, $to]
        ) ?? 0);
        $expenses = Expense::totalInRange($tid, $from, $to);
        $period = substr($from, 0, 7);
        $salaries = StaffSalary::totalForPeriod($tid, $period);
        json_out([
            'ok' => true, 'range' => ['from' => $from, 'to' => $to],
            'revenue_verified' => $revenue,
            'expenses' => $expenses,
            'salaries_period' => $salaries,
            'net' => $revenue - $expenses - $salaries,
            'expenses_by_category' => Expense::byCategory($tid, $from, $to),
        ]);
    }

    api_require_csrf();
    $in = api_body();
    $action = (string) ($in['action'] ?? '');

    if ($action === 'expense_create') {
        api_require_perm('finance.manage');
        $amount = (float) ($in['amount'] ?? 0);
        $title = trim((string) ($in['title'] ?? ''));
        if ($amount <= 0 || $title === '') {
            json_out(['ok' => false, 'error' => 'Title and a valid amount are required.'], 422);
        }
        $category = (string) ($in['category'] ?? 'General');
        if (!in_array($category, Expense::categories(), true)) {
            $category = 'General';
        }
        $id = Expense::create([
            'tenant_id' => $tid,
            'branch_id' => (int) ($in['branch_id'] ?? 0) ?: null,
            'category' => $category,
            'title' => $title,
            'amount' => $amount,
            'expense_date' => ($in['expense_date'] ?? '') ?: date('Y-m-d'),
            'method' => (string) ($in['method'] ?? 'bank'),
            'reference' => trim((string) ($in['reference'] ?? '')) ?: null,
            'recorded_by' => (int) current_user()['id'],
            'notes' => trim((string) ($in['notes'] ?? '')) ?: null,
        ]);
        log_activity($tid, 'expense.created', 'expenses', $id, $title . ' — ' . money($amount));
        json_out(['ok' => true, 'message' => 'Expense recorded.', 'id' => $id]);
    }

    if ($action === 'expense_delete') {
        api_require_perm('finance.manage');
        $n = Expense::deleteById((int) ($in['id'] ?? 0), $tid);
        if ($n === 0) {
            json_out(['ok' => false, 'error' => 'Expense not found.'], 404);
        }
        json_out(['ok' => true, 'message' => 'Expense deleted.']);
    }

    if ($action === 'salary_create') {
        api_require_perm('finance.manage');
        $name = trim((string) ($in['person_name'] ?? ''));
        $period = (string) ($in['period_month'] ?? date('Y-m'));
        if ($name === '' || !preg_match('/^\d{4}-\d{2}$/', $period)) {
            json_out(['ok' => false, 'error' => 'Person name and a valid month (YYYY-MM) are required.'], 422);
        }
        $id = StaffSalary::create([
            'tenant_id' => $tid,
            'user_id' => (int) ($in['user_id'] ?? 0) ?: null,
            'trainer_id' => (int) ($in['trainer_id'] ?? 0) ?: null,
            'person_name' => $name,
            'role_label' => trim((string) ($in['role_label'] ?? '')) ?: null,
            'period_month' => $period,
            'base_salary' => (float) ($in['base_salary'] ?? 0),
            'allowances' => (float) ($in['allowances'] ?? 0),
            'deductions' => (float) ($in['deductions'] ?? 0),
            'method' => (string) ($in['method'] ?? 'bank'),
            'status' => 'pending',
            'notes' => trim((string) ($in['notes'] ?? '')) ?: null,
        ]);
        log_activity($tid, 'salary.created', 'staff_salaries', $id, $name . ' — ' . $period);
        json_out(['ok' => true, 'message' => 'Salary record created (pending).', 'id' => $id]);
    }

    if ($action === 'salary_pay') {
        api_require_perm('finance.manage');
        $n = StaffSalary::markPaid($tid, (int) ($in['id'] ?? 0));
        if ($n === 0) {
            json_out(['ok' => false, 'error' => 'Salary record not found.'], 404);
        }
        json_out(['ok' => true, 'message' => 'Salary marked as paid.']);
    }

    if ($action === 'salary_delete') {
        api_require_perm('finance.manage');
        $n = StaffSalary::deleteById((int) ($in['id'] ?? 0), $tid);
        if ($n === 0) {
            json_out(['ok' => false, 'error' => 'Salary record not found.'], 404);
        }
        json_out(['ok' => true, 'message' => 'Salary record deleted.']);
    }

    json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
});
