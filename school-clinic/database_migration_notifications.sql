-- ============================================================
-- Notification System Migration
-- Run this after the main database.sql import
-- ============================================================

USE school_clinic;

-- ------------------------------------------------------------
-- 1. Alter notifications table: remove restrictive unique key,
--    add reference_id, action_url, severity, icon
-- ------------------------------------------------------------
ALTER TABLE notifications
  DROP FOREIGN KEY fk_notification_loan,
  DROP INDEX uq_notification_reference;

ALTER TABLE notifications
  ADD COLUMN reference_id INT DEFAULT NULL AFTER loan_id,
  ADD COLUMN action_url VARCHAR(255) DEFAULT NULL AFTER message,
  ADD COLUMN severity ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info' AFTER type,
  ADD COLUMN icon VARCHAR(50) DEFAULT NULL AFTER severity,
  ADD INDEX idx_notifications_user_read (user_id, is_read),
  ADD INDEX idx_notifications_created (created_at);

-- Re-add loan_id foreign key (nullable, for backward compat)
ALTER TABLE notifications
  ADD CONSTRAINT fk_notification_loan FOREIGN KEY (loan_id) REFERENCES equipment_loans(id) ON DELETE CASCADE;

-- ------------------------------------------------------------
-- 2. Sick leave requests table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sick_leaves (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  reason       TEXT NOT NULL,
  start_date   DATE NOT NULL,
  end_date     DATE DEFAULT NULL,
  status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  attachment   VARCHAR(255) DEFAULT NULL,
  reviewed_by  INT DEFAULT NULL,
  review_note  TEXT DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at  DATETIME DEFAULT NULL,
  CONSTRAINT fk_sickleave_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sickleave_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. Notification settings (per-user preferences)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_settings (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  notification_type VARCHAR(60) NOT NULL,
  is_enabled      TINYINT(1) NOT NULL DEFAULT 1,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notif_setting (user_id, notification_type),
  CONSTRAINT fk_notifset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. Notification queue (for background/batch processing)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_queue (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  type         VARCHAR(60) NOT NULL,
  title        VARCHAR(150) NOT NULL,
  message      TEXT NOT NULL,
  action_url   VARCHAR(255) DEFAULT NULL,
  severity     ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  icon         VARCHAR(50) DEFAULT NULL,
  reference_id INT DEFAULT NULL,
  status       ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME DEFAULT NULL,
  INDEX idx_queue_status (status),
  CONSTRAINT fk_queue_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
