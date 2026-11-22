-- Rollback admin record archival columns and indexes.

ALTER TABLE `schedules`
    DROP KEY `idx_schedules_archived_at`,
    DROP COLUMN `archived_at`;

ALTER TABLE `sections`
    DROP KEY `idx_sections_archived_at`,
    DROP COLUMN `archived_at`;

ALTER TABLE `rooms`
    DROP KEY `idx_rooms_archived_at`,
    DROP COLUMN `archived_at`;

ALTER TABLE `subjects`
    DROP KEY `idx_subjects_archived_at`,
    DROP COLUMN `archived_at`;

ALTER TABLE `users`
    DROP KEY `idx_users_archived_at`,
    DROP COLUMN `archived_at`;
