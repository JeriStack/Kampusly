-- ---------------------------------------------------------------------------
-- Kampusly — MySQL 8.4 Local Initialization Script
-- Executed automatically by the official MySQL container on first volume init
-- via `/docker-entrypoint-initdb.d/01-init-kampusly.sql`.
--
-- Phase 3 Scope:
--   1. Enforce UTF-8 (utf8mb4 / utf8mb4_0900_ai_ci) and UTC+8 / session defaults.
--   2. Create the `schema_migrations` tracking table used by `backend/bin/migrate.php`.
--   3. Do NOT create business domain tables here (those belong to Phase 4+ migrations)
--      and NEVER place production credentials or real student data in this directory.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
SET time_zone = '+08:00';

CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version` VARCHAR(128) NOT NULL COMMENT 'Migration filename stem (e.g. 0001_01_01_000000_create_schema_migrations_table)',
    `description` VARCHAR(255) NOT NULL COMMENT 'Human-readable migration summary',
    `checksum` CHAR(64) NOT NULL COMMENT 'SHA-256 checksum of the SQL migration file',
    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `execution_time_ms` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_schema_migrations_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`, `checksum`, `execution_time_ms`)
VALUES (
    '0001_01_01_000000_create_schema_migrations_table',
    'Initialize schema_migrations tracking table',
    SHA2('0001_01_01_000000_create_schema_migrations_table', 256),
    0
);

