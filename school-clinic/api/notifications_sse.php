<?php
/**
 * Server-Sent Events endpoint for real-time notifications.
 * Replaces the 30-second polling with a persistent connection.
 * 
 * The client (EventSource) automatically reconnects with Last-Event-ID,
 * so we only send new notifications since the last check.
 */
require_once __DIR__ . '/../config/db.php';
require_login();

$uid = (int)current_user()['id'];
$nm = new NotificationModel($pdo);

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no'); // Disable Nginx buffering
header('Connection: keep-alive');

// Time limit for this request (PHP max_execution_time safety)
$maxRuntime = 55; // seconds
$startTime = time();

// Get the last event ID from the client
$lastEventId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? null;

if ($lastEventId) {
    // Client reconnecting — send only notifications since last check
    $notifications = $nm->getSince($uid, date('Y-m-d H:i:s', (int)$lastEventId));
    foreach ($notifications as $n) {
        echo "id: " . strtotime($n['created_at']) . "\n";
        echo "data: " . json_encode([
            'id' => (int)$n['id'],
            'type' => $n['type'],
            'title' => $n['title'],
            'message' => $n['message'],
            'severity' => $n['severity'],
            'icon' => $n['icon'],
            'is_read' => (bool)$n['is_read'],
            'created_at' => $n['created_at'],
            'action_url' => $n['action_url'],
        ]) . "\n\n";
    }
    ob_flush();
    flush();
}

// Send initial unread count
$unreadCount = $nm->getUnreadCount($uid);
echo "id: " . time() . "\n";
echo "data: " . json_encode(['type' => 'init', 'unread_count' => $unreadCount]) . "\n\n";
ob_flush();
flush();

// Keep the connection alive, checking for new notifications
while (true) {
    // Check if we've exceeded max runtime
    if (time() - $startTime >= $maxRuntime) {
        echo "event: ping\n";
        echo "data: " . json_encode(['type' => 'ping', 'time' => time()]) . "\n\n";
        ob_flush();
        flush();
        break; // Client will reconnect automatically
    }

    // Check for new notifications
    $latestTs = $nm->getLatestTimestamp($uid);
    $since = $lastEventId ? date('Y-m-d H:i:s', (int)$lastEventId) : date('Y-m-d H:i:s', time() - 5);
    $newNotifs = $nm->getSince($uid, $since);

    if ($newNotifs) {
        foreach ($newNotifs as $n) {
            echo "id: " . strtotime($n['created_at']) . "\n";
            echo "data: " . json_encode([
                'id' => (int)$n['id'],
                'type' => $n['type'],
                'title' => $n['title'],
                'message' => $n['message'],
                'severity' => $n['severity'],
                'icon' => $n['icon'],
                'is_read' => (bool)$n['is_read'],
                'created_at' => $n['created_at'],
                'action_url' => $n['action_url'],
            ]) . "\n\n";
        }
        ob_flush();
        flush();
    }

    // Send a keep-alive comment every 15 seconds
    echo ": keep-alive\n\n";
    ob_flush();
    flush();

    sleep(3); // Check every 3 seconds
}
