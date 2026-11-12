-- Per-student announcement archive. A row hides the announcement from that student's inbox.
-- Rows older than the retention window (7 days) are treated as deleted by the application.

CREATE TABLE `student_announcement_archives` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `announcement_id` BIGINT UNSIGNED NOT NULL,
    `archived_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_student_announcement_archives_pair` (`student_id`, `announcement_id`),
    KEY `idx_student_announcement_archives_archived_at` (`archived_at`),
    KEY `idx_student_announcement_archives_announcement` (`announcement_id`),
    CONSTRAINT `fk_student_announcement_archives_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_student_announcement_archives_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
