-- Admin record archival support across users, subjects, rooms, sections, and schedules.
-- Archived records remain indefinitely until restored or permanently deleted (when unreferenced).

ALTER TABLE `users`
    ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `status`,
    ADD KEY `idx_users_archived_at` (`archived_at`);

ALTER TABLE `subjects`
    ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `status`,
    ADD KEY `idx_subjects_archived_at` (`archived_at`);

ALTER TABLE `rooms`
    ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `status`,
    ADD KEY `idx_rooms_archived_at` (`archived_at`);

ALTER TABLE `sections`
    ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `status`,
    ADD KEY `idx_sections_archived_at` (`archived_at`);

ALTER TABLE `schedules`
    ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `status`,
    ADD KEY `idx_schedules_archived_at` (`archived_at`);
