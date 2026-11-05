-- Migration: 2026_11_01_000300_create_schedules_table
-- Description: Create schedules table with conflict-detection indexes and time range check (Phase 2 Section 4.7)

CREATE TABLE IF NOT EXISTS `schedules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `instructor_id` BIGINT UNSIGNED NOT NULL,
    `room_id` BIGINT UNSIGNED NOT NULL,
    `section_id` BIGINT UNSIGNED NOT NULL,
    `school_year` VARCHAR(20) NOT NULL,
    `semester` ENUM('1st', '2nd', 'Summer') NOT NULL,
    `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `status` ENUM('draft', 'published', 'cancelled') NOT NULL DEFAULT 'published',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_schedules_room_slot` (`school_year`, `semester`, `day_of_week`, `room_id`, `status`, `start_time`, `end_time`),
    KEY `idx_schedules_instructor_slot` (`school_year`, `semester`, `day_of_week`, `instructor_id`, `status`, `start_time`, `end_time`),
    KEY `idx_schedules_section_slot` (`school_year`, `semester`, `day_of_week`, `section_id`, `status`, `start_time`, `end_time`),
    KEY `idx_schedules_subject` (`subject_id`, `status`),
    KEY `idx_schedules_reminder_window` (`status`, `day_of_week`, `start_time`),
    CONSTRAINT `chk_schedules_time_order` CHECK (`start_time` < `end_time`),
    CONSTRAINT `fk_schedules_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_schedules_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_schedules_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_schedules_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

