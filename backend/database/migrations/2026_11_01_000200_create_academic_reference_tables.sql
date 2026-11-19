-- Migration: 2026_11_01_000200_create_academic_reference_tables
-- Description: Create sections, subjects, rooms, students, and instructors tables (Phase 2 Section 4.2 - 4.6)

CREATE TABLE IF NOT EXISTS `sections` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_name` VARCHAR(60) NOT NULL,
    `program` VARCHAR(120) NOT NULL,
    `year_level` TINYINT UNSIGNED NOT NULL,
    `school_year` VARCHAR(20) NOT NULL,
    `semester` ENUM('1st', '2nd', 'Summer') NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sections_term_name` (`school_year`, `semester`, `section_name`),
    KEY `idx_sections_program_year` (`program`, `year_level`, `status`),
    CONSTRAINT `chk_sections_year_level` CHECK (`year_level` BETWEEN 1 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subjects` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_code` VARCHAR(30) NOT NULL,
    `subject_name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `units` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subjects_code` (`subject_code`),
    KEY `idx_subjects_status_name` (`status`, `subject_name`),
    CONSTRAINT `chk_subjects_units` CHECK (`units` BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room_code` VARCHAR(30) NOT NULL,
    `room_name` VARCHAR(100) NOT NULL,
    `building` VARCHAR(100) NOT NULL,
    `capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 40,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rooms_code` (`room_code`),
    UNIQUE KEY `uq_rooms_name` (`room_name`),
    KEY `idx_rooms_status_building` (`status`, `building`),
    CONSTRAINT `chk_rooms_capacity` CHECK (`capacity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `students` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `section_id` BIGINT UNSIGNED NOT NULL,
    `student_number` VARCHAR(32) NOT NULL,
    `first_name` VARCHAR(80) NOT NULL,
    `last_name` VARCHAR(80) NOT NULL,
    `program` VARCHAR(120) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_students_user_id` (`user_id`),
    UNIQUE KEY `uq_students_student_number` (`student_number`),
    KEY `idx_students_section_id` (`section_id`),
    CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_students_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `instructors` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `employee_number` VARCHAR(32) NOT NULL,
    `first_name` VARCHAR(80) NOT NULL,
    `last_name` VARCHAR(80) NOT NULL,
    `department` VARCHAR(120) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_instructors_user_id` (`user_id`),
    UNIQUE KEY `uq_instructors_employee_number` (`employee_number`),
    KEY `idx_instructors_department` (`department`),
    CONSTRAINT `fk_instructors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

