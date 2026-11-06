<?php
/**
 * /api/notifications.php — in-app notifications for the current user/tenant.
 *  GET                        → latest notifications + unread count
 *  POST action=read {id}      → mark one read
 *  POST action=read_all       → mark all read
 *  POST action=send {title, body, type?} → staff broadcast to the gym (staff roles)
 */
require_once __DIR__ . '/_bootstrap.php';

api_handle(function (): void {
    $user = api_require_login();
    $tid = tenant_id();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $scope = '(tenant_id = ? OR user_id = ?)';
    $params = [$tid, (int) $user['id']];

    if ($method === 'GET') {
        $rows = Database::fetchAll(
            "SELECT * FROM notifications WHERE {$scope} ORDER BY id DESC LIMIT 30",
            $params
        );
        $unread = (int) (Database::scalar(
            "SELECT COUNT(*) FROM notifications WHERE {$scope} AND is_read = 0",
            $params
        ) ?? 0);
        json_out(['ok' => true, 'data' => $rows, 'unread' => $unread]);
    }

    api_require_csrf();
    $in = api_body();
    $action = (string) ($in['action'] ?? '');

    if ($action === 'read') {
        Database::query(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND {$scope}",
            array_merge([(int) ($in['id'] ?? 0)], $params)
        );
        json_out(['ok' => true, 'message' => 'Marked read.']);
    }

    if ($action === 'read_all') {
        Database::query("UPDATE notifications SET is_read = 1 WHERE {$scope}", $params);
        json_out(['ok' => true, 'message' => 'All notifications marked read.']);
    }

    if ($action === 'send') {
        api_require_roles(['Gym Owner', 'Gym Admin', 'Branch Manager']);
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '' || $tid === null) {
            json_out(['ok' => false, 'error' => 'A title is required.'], 422);
        }
        notify($tid, null, $title, trim((string) ($in['body'] ?? '')), (string) ($in['type'] ?? 'info'));
        json_out(['ok' => true, 'message' => 'Notification sent to the gym.']);
    }

    json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
});
