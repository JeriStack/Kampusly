-- Migration: 0001_01_01_000000_create_schema_migrations_table
-- Description: Initialize the schema_migrations tracking table for Kampusly

CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version` VARCHAR(128) NOT NULL COMMENT 'Migration filename stem',
    `description` VARCHAR(255) NOT NULL COMMENT 'Human-readable migration summary',
    `checksum` CHAR(64) NOT NULL COMMENT 'SHA-256 checksum of the SQL migration file',
    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `execution_time_ms` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_schema_migrations_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
