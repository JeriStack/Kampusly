-- Migration: 2026_11_01_000500_create_notifications_tables
-- Description: Create notifications and notification_deliveries tables (Phase 2 Section 4.11 - 4.12)

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(180) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('class_reminder', 'announcement', 'class_notice', 'schedule_update') NOT NULL,
    `reference_type` VARCHAR(40) NOT NULL,
    `reference_id` BIGINT UNSIGNED NOT NULL,
    `deduplication_key` VARCHAR(120) NULL DEFAULT NULL,
    `read_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_notifications_dedup_key` (`deduplication_key`),
    KEY `idx_notifications_user_unread_created` (`user_id`, `read_at`, `created_at` DESC),
    KEY `idx_notifications_user_type` (`user_id`, `type`, `created_at` DESC),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_deliveries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_id` BIGINT UNSIGNED NOT NULL,
    `channel` ENUM('email') NOT NULL DEFAULT 'email',
    `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `attempt_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_error` VARCHAR(500) NULL DEFAULT NULL,
    `delivered_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_notification_deliveries_channel` (`notification_id`, `channel`),
    KEY `idx_notification_deliveries_status_attempts` (`status`, `attempt_count`, `created_at`),
    CONSTRAINT `fk_notification_deliveries_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

