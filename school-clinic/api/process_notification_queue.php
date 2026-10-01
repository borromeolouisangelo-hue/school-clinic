<?php
/**
 * Process the notification queue.
 * Run via cron every minute, for example:
 *   php /path/to/school-clinic/api/process_notification_queue.php
 * Or call from a shutdown handler for near-real-time processing.
 */
require_once __DIR__ . '/../config/db.php';

// No login required — this is a CLI/cron endpoint
// Block web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Forbidden');
}

$nm = new NotificationModel($pdo);
$processed = $nm->processQueue(100);

echo date('Y-m-d H:i:s') . " - Processed $processed notification(s)\n";
