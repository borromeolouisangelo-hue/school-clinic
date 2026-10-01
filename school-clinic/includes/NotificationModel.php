<?php
/**
 * Notification Model — legacy compatibility.
 * Handles notification-related database operations.
 */

if (!class_exists('NotificationModel')) {
    class NotificationModel {
        private $pdo;

        public function __construct($pdo) {
            $this->pdo = $pdo;
        }

        public function createNotification($user_id, $title, $message, $type = 'info', $related_id = null, $related_type = null) {
            try {
                // The live `notifications` table has `reference_id` (generic) and
                // `loan_id` (loan-specific); there are no related_id/related_type
                // columns, so the generic reference is stored in reference_id.
                $stmt = $this->pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, reference_id, is_read, created_at)
                    VALUES (?, ?, ?, ?, ?, 0, NOW())
                ");
                $stmt->execute([$user_id, $title, $message, $type, $related_id]);
                return $this->pdo->lastInsertId();
            } catch (PDOException $e) {
                return false;
            }
        }

        /**
         * Drain pending rows from notification_queue into notifications.
         * Each row is moved atomically: the notification insert and the queue
         * status update commit together, so a crash cannot duplicate or drop a
         * notification. Returns how many rows were delivered.
         */
        public function processQueue($limit = 100) {
            $limit = max(1, (int)$limit);
            $validSeverities = ['info', 'success', 'warning', 'danger'];

            try {
                $stmt = $this->pdo->prepare(
                    "SELECT id, user_id, type, title, message, action_url, severity, icon, reference_id
                     FROM notification_queue
                     WHERE status = 'pending'
                     ORDER BY id
                     LIMIT " . $limit
                );
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Queue table missing or unreadable — nothing to process.
                return 0;
            }

            $insert = $this->pdo->prepare(
                "INSERT INTO notifications (user_id, type, severity, icon, title, message, action_url, reference_id, is_read, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())"
            );
            $markProcessed = $this->pdo->prepare(
                "UPDATE notification_queue SET status = 'processed', processed_at = NOW() WHERE id = ?"
            );
            $markFailed = $this->pdo->prepare(
                "UPDATE notification_queue SET status = 'failed', processed_at = NOW() WHERE id = ?"
            );

            $delivered = 0;
            foreach ($rows as $row) {
                try {
                    $this->pdo->beginTransaction();
                    $insert->execute([
                        $row['user_id'],
                        $row['type'],
                        in_array($row['severity'], $validSeverities, true) ? $row['severity'] : 'info',
                        $row['icon'],
                        $row['title'],
                        $row['message'],
                        $row['action_url'],
                        $row['reference_id'],
                    ]);
                    $markProcessed->execute([$row['id']]);
                    $this->pdo->commit();
                    $delivered++;
                } catch (PDOException $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    try {
                        $markFailed->execute([$row['id']]);
                    } catch (PDOException $ignored) {
                        // Leave the row pending so the next run retries it.
                    }
                }
            }
            return $delivered;
        }

        public function getNotifications($user_id, $limit = 50, $unread_only = false) {
            $where = $unread_only ? 'WHERE user_id = ? AND is_read = 0' : 'WHERE user_id = ?';
            $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * SSE streaming support — required by api/notifications_sse.php
         * (original 9/26 implementation, restored: the 9/29 model rewrite
         * dropped these three methods while the SSE endpoint still calls them).
         */
        public function getUnreadCount($user_id) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([(int)$user_id]);
            return (int)$stmt->fetchColumn();
        }

        public function getSince($user_id, $since, $limit = 20) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM notifications WHERE user_id = ? AND created_at > ? ORDER BY created_at ASC LIMIT ' . (int)$limit
            );
            $stmt->execute([(int)$user_id, $since]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function getLatestTimestamp($user_id) {
            $stmt = $this->pdo->prepare('SELECT MAX(created_at) FROM notifications WHERE user_id = ?');
            $stmt->execute([(int)$user_id]);
            $val = $stmt->fetchColumn();
            return $val ?: null;
        }

        public function markAsRead($pdo, $notification_id) {
            try {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
                $stmt->execute([$notification_id]);
                return true;
            } catch (PDOException $e) {
                return false;
            }
        }

        /**
         * Per-user notification preferences.
         * Returns [notification_type => bool]. Missing rows mean "enabled",
         * matching the default of notification_settings.is_enabled.
         */
        public function getPreferences($user_id) {
            $prefs = [];
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT notification_type, is_enabled FROM notification_settings WHERE user_id = ?"
                );
                $stmt->execute([(int)$user_id]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $prefs[$row['notification_type']] = (bool)$row['is_enabled'];
                }
            } catch (PDOException $e) {
                // Table missing or unreadable — fall back to "all enabled".
            }
            return $prefs;
        }

        public function setPreference($user_id, $type, $enabled) {
            try {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO notification_settings (user_id, notification_type, is_enabled)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)"
                );
                $stmt->execute([(int)$user_id, (string)$type, $enabled ? 1 : 0]);
                return true;
            } catch (PDOException $e) {
                return false;
            }
        }

        public static function register_notification_listeners($pdo) {
            // Register default notification listeners
            // Can be extended by subclasses or application code
        }
    }
}

// Global function for backward compatibility — called from config/db.php
if (!function_exists('register_notification_listeners')) {
    function register_notification_listeners($pdo) {
        // Default implementation — creates a NotificationModel instance
        // Listeners can be registered here for specific events
    }
}