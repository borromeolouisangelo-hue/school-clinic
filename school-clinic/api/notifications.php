<?php
/**
 * AJAX API for notifications.
 * Supports: GET (fetch), PATCH (mark read), POST (mark all read)
 */
require_once __DIR__ . '/../config/db.php';
require_login();

$uid = (int)current_user()['id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch recent notifications
    $limit = min(20, (int)($_GET['limit'] ?? 10));
    $unreadOnly = ($_GET['unread'] ?? '') === '1';

    $notifications = get_recent_notifications($pdo, $uid, $limit, $unreadOnly);
    $unreadCount = get_unread_count($pdo, $uid);

    header('Content-Type: application/json');
    echo json_encode([
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
    ]);
    exit;
}

if ($method === 'PATCH') {
    // Mark single notification as read
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);

    if ($id > 0) {
        mark_notification_as_read($pdo, $id, $uid);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'unread_count' => get_unread_count($pdo, $uid)]);
    exit;
}

if ($method === 'POST') {
    // Mark all notifications as read
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'mark_all_read') {
        $count = mark_all_notifications_as_read($pdo, $uid);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'marked' => $count, 'unread_count' => 0]);
        exit;
    }

    if ($action === 'mark_read') {
        $id = (int)($input['id'] ?? 0);
        if ($id > 0) {
            mark_notification_as_read($pdo, $id, $uid);
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'unread_count' => get_unread_count($pdo, $uid)]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
