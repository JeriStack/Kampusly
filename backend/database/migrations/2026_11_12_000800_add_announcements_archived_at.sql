-- Instructor-side archive: an archived announcement is hidden from students and deleted after 7 days.

ALTER TABLE `announcements`
    ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `published_at`,
    ADD KEY `idx_announcements_status_archived_at` (`status`, `archived_at`);
