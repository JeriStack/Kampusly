-- Add explicit academic term dates and student schedule-change requests.

ALTER TABLE `sections`
    ADD COLUMN `term_start_date` DATE NULL DEFAULT NULL AFTER `semester`;

CREATE TABLE `schedule_change_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `current_day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    `current_start_time` TIME NOT NULL,
    `current_end_time` TIME NOT NULL,
    `current_room_code` VARCHAR(30) NOT NULL,
    `preferred_day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NULL DEFAULT NULL,
    `preferred_start_time` TIME NULL DEFAULT NULL,
    `preferred_end_time` TIME NULL DEFAULT NULL,
    `preferred_room_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `reason` VARCHAR(1000) NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `pending_schedule_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN `status` = 'pending' THEN `schedule_id` ELSE NULL END
    ) STORED,
    `reviewed_by_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `review_note` VARCHAR(1000) NULL DEFAULT NULL,
    `reviewed_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_schedule_change_requests_student_status` (`student_id`, `status`, `created_at` DESC),
    KEY `idx_schedule_change_requests_schedule_status` (`schedule_id`, `status`),
    UNIQUE KEY `uq_schedule_change_requests_one_pending` (`student_id`, `pending_schedule_id`),
    CONSTRAINT `chk_schedule_change_request_time_pair` CHECK (
        (`preferred_start_time` IS NULL AND `preferred_end_time` IS NULL)
        OR (`preferred_start_time` IS NOT NULL AND `preferred_end_time` IS NOT NULL AND `preferred_start_time` < `preferred_end_time`)
    ),
    CONSTRAINT `fk_schedule_change_requests_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_schedule_change_requests_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_schedule_change_requests_room` FOREIGN KEY (`preferred_room_id`) REFERENCES `rooms` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_schedule_change_requests_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
