ALTER TABLE `announcements`
    DROP KEY `idx_announcements_status_archived_at`,
    DROP COLUMN `archived_at`;
