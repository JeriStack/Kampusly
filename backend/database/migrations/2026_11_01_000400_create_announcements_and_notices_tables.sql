-- Migration: 2026_11_01_000400_create_announcements_and_notices_tables
-- Description: Create announcements, announcement_attachments, and class_notices tables (Phase 2 Section 4.8 - 4.10)

CREATE TABLE IF NOT EXISTS `announcements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `author_user_id` BIGINT UNSIGNED NOT NULL,
    `instructor_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `section_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `title` VARCHAR(180) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('draft', 'published', 'archived', 'withdrawn') NOT NULL DEFAULT 'published',
    `published_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_announcements_section_published` (`section_id`, `status`, `published_at` DESC),
    KEY `idx_announcements_subject_published` (`subject_id`, `status`, `published_at` DESC),
    KEY `idx_announcements_author` (`author_user_id`),
    KEY `idx_announcements_instructor` (`instructor_id`),
    CONSTRAINT `fk_announcements_author_user` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_announcements_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_announcements_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_announcements_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcement_attachments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `announcement_id` BIGINT UNSIGNED NOT NULL,
    `storage_key` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `size_bytes` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_announcement_attachments_storage_key` (`storage_key`),
    KEY `idx_announcement_attachments_announcement` (`announcement_id`),
    CONSTRAINT `chk_announcement_attachments_size` CHECK (`size_bytes` <= 10485760),
    CONSTRAINT `fk_announcement_attachments_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `class_notices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `instructor_id` BIGINT UNSIGNED NOT NULL,
    `notice_type` ENUM('unavailable', 'cancelled', 'rescheduled', 'room_change') NOT NULL,
    `affected_date` DATE NOT NULL,
    `title` VARCHAR(180) NOT NULL,
    `message` TEXT NOT NULL,
    `new_room_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `new_date` DATE NULL DEFAULT NULL,
    `new_start_time` TIME NULL DEFAULT NULL,
    `new_end_time` TIME NULL DEFAULT NULL,
    `status` ENUM('pending_approval', 'published', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending_approval',
    `reviewed_by_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `rejection_reason` VARCHAR(500) NULL DEFAULT NULL,
    `reviewed_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_class_notices_schedule_date` (`schedule_id`, `affected_date`, `status`),
    KEY `idx_class_notices_status_created` (`status`, `created_at` DESC),
    KEY `idx_class_notices_instructor` (`instructor_id`, `created_at` DESC),
    KEY `idx_class_notices_new_room` (`new_room_id`),
    KEY `idx_class_notices_reviewer` (`reviewed_by_user_id`),
    CONSTRAINT `fk_class_notices_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_class_notices_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_class_notices_new_room` FOREIGN KEY (`new_room_id`) REFERENCES `rooms` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_class_notices_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

