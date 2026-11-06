<?php
/**
 * /api/payments.php — payments list + record + verify.
 * Business rule (unchanged): recorded payments start PENDING and only a
 * verify action may move them to verified. Nothing here fakes success.
 */
require_once __DIR__ . '/_bootstrap.php';

api_handle(function (): void {
    api_require_login();
    api_require_roles(['Gym Owner', 'Gym Admin', 'Accountant', 'Branch Manager']);
    $tid = api_tenant();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        api_require_perm('payments.view');
        $status = trim((string) ($_GET['status'] ?? ''));
        $purpose = trim((string) ($_GET['purpose'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int) ($_GET['per_page'] ?? 20)));
        $where = 'p.tenant_id = ?';
        $params = [$tid];
        if ($status !== '') {
            $where .= ' AND p.status = ?';
            $params[] = $status;
        }
        if ($purpose !== '') {
            $where .= ' AND p.purpose = ?';
            $params[] = $purpose;
        }
        $total = (int) (Database::scalar("SELECT COUNT(*) FROM payments p WHERE {$where}", $params) ?? 0);
        $rows = Database::fetchAll(
            "SELECT p.*, m.name AS member_name FROM payments p
             LEFT JOIN members m ON m.id = p.member_id
             WHERE {$where} ORDER BY p.id DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );
        json_out([
            'ok' => true, 'data' => $rows, 'total' => $total,
            'page' => $page, 'per_page' => $perPage, 'pages' => (int) max(1, ceil($total / $perPage)),
            'totals' => Payment::totals($tid),
        ]);
    }

    api_require_csrf();
    $in = api_body();
    $action = (string) ($in['action'] ?? '');

    if ($action === 'record') {
        api_require_perm('payments.manage');
        $amount = (float) ($in['amount'] ?? 0);
        if ($amount <= 0) {
            json_out(['ok' => false, 'error' => 'Enter a valid amount.'], 422);
        }
        $description = trim((string) ($in['description'] ?? 'Payment'));
        $pid = Payment::recordManual($tid, [
            'tenant_id' => $tid,
            'user_id' => (int) current_user()['id'],
            'member_id' => (int) ($in['member_id'] ?? 0) ?: null,
            'purpose' => in_array($in['purpose'] ?? 'membership', ['subscription', 'membership', 'other'], true) ? $in['purpose'] : 'membership',
            'description' => $description,
            'amount' => $amount,
            'method' => (string) ($in['method'] ?? 'manual'),
            'reference' => trim((string) ($in['reference'] ?? '')) ?: null,
        ]);
        $invoice = Invoice::generate($tid, $pid, $description, $amount, 'unpaid');
        json_out([
            'ok' => true, 'id' => $pid, 'invoice' => $invoice['number'] ?? null,
            'message' => 'Payment recorded as PENDING. It counts as revenue only after verification.',
            'payment' => Payment::find($pid, $tid),
        ]);
    }

    if ($action === 'verify') {
        api_require_perm('payments.verify');
        $pid = (int) ($in['payment_id'] ?? 0);
        $payment = Payment::find($pid, $tid);
        if ($payment === null) {
            json_out(['ok' => false, 'error' => 'Payment not found.'], 404);
        }
        if ($payment['status'] !== 'pending') {
            json_out(['ok' => false, 'error' => 'Only pending payments can be verified (current: ' . $payment['status'] . ').'], 422);
        }
        Payment::verify($pid, (int) current_user()['id']);
        Database::query('UPDATE invoices SET status = "paid" WHERE payment_id = ? AND tenant_id = ? AND status = "unpaid"', [$pid, $tid]);
        log_activity($tid, 'payment.verified', 'payments', $pid);
        json_out(['ok' => true, 'message' => 'Payment verified; linked invoice marked paid.', 'payment' => Payment::find($pid, $tid)]);
    }

    if ($action === 'fail') {
        api_require_perm('payments.verify');
        $pid = (int) ($in['payment_id'] ?? 0);
        $n = Database::query('UPDATE payments SET status = "failed" WHERE id = ? AND tenant_id = ? AND status = "pending"', [$pid, $tid])->rowCount();
        if ($n === 0) {
            json_out(['ok' => false, 'error' => 'Payment not found or not pending.'], 422);
        }
        log_activity($tid, 'payment.failed', 'payments', $pid);
        json_out(['ok' => true, 'message' => 'Payment marked failed.']);
    }

    json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
});
