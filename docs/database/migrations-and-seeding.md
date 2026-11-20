# Kampusly Database & Migration Conventions

This directory holds database migrations, development seeders, and test fixtures for the Kampusly relational database (MySQL 8.4+ and MariaDB 10.4+ compatible).

## Directory Layout

```text
backend/database/
├── migrations/
│   ├── 0001_01_01_000000_create_schema_migrations_table.sql
│   ├── 2026_11_01_000100_create_users_and_auth_tables.sql
│   ├── 2026_11_01_000200_create_academic_reference_tables.sql
│   ├── 2026_11_01_000300_create_schedules_table.sql
│   ├── 2026_11_01_000400_create_announcements_and_notices_tables.sql
│   ├── 2026_11_01_000500_create_notifications_tables.sql
│   ├── 2026_11_09_000600_add_schedule_change_requests_and_term_dates.sql
│   ├── 2026_11_12_000700_create_student_announcement_archives.sql
│   ├── 2026_11_12_000800_add_announcements_archived_at.sql
│   └── rollbacks/
│       ├── 2026_11_01_000100_create_users_and_auth_tables.down.sql
│       ├── 2026_11_01_000200_create_academic_reference_tables.down.sql
│       ├── 2026_11_01_000300_create_schedules_table.down.sql
│       ├── 2026_11_01_000400_create_announcements_and_notices_tables.down.sql
│       ├── 2026_11_01_000500_create_notifications_tables.down.sql
│       ├── 2026_11_09_000600_add_schedule_change_requests_and_term_dates.down.sql
│       ├── 2026_11_12_000700_create_student_announcement_archives.down.sql
│       └── 2026_11_12_000800_add_announcements_archived_at.down.sql
├── seeders/
│   └── (optional development seeders; none are registered by default)
└── factories/
    └── (test fixture factories)
```

## Schema Inventory (16 Application Tables + `schema_migrations`)

1. `users`: Core identity, roles (`admin`, `instructor`, `student`), email uniqueness, bcrypt password hash.
2. `password_reset_tokens`: 64-character SHA-256 tokens with expiry and single-use audit timestamps.
3. `audit_logs`: Administrative actions, entity references, JSON metadata, request correlation ID.
4. `sections`: Program, year level (1-6), `term_start_date`, term uniqueness `uq_sections_term_name (school_year, semester, section_name)`.
5. `subjects`: Subject code, name, description, unit range constraint (1-12 units).
6. `rooms`: Unique room codes/names, building, positive capacity constraint.
7. `students`: References user and section; unique student number.
8. `instructors`: References user; unique employee number, department.
9. `schedules`: Composite conflict-detection indexes, term/day, time order constraint `chk_schedules_time_order (start_time < end_time)`.
10. `schedule_change_requests`: Student-submitted schedule change requests with stored generated column `pending_schedule_id` enforcing at most one pending request per `(student_id, schedule_id)` and `chk_schedule_change_request_time_pair`.
11. `announcements`: Author, instructor, subject, section scoping, status (`draft`, `published`, `archived`, `withdrawn`), `published_at`, and `archived_at` (7-day retention window).
12. `announcement_attachments`: Safe storage keys, MIME type, size limit constraint (`<= 10 MB`).
13. `student_announcement_archives`: Per-student announcement archive table with unique `(student_id, announcement_id)` pair and 7-day retention index on `archived_at`.
14. `class_notices`: Notice types (`unavailable`, `cancelled`, `rescheduled`, `room_change`), approval review workflow.
15. `notifications`: In-app notification delivery, unique deduplication key to prevent duplicate alerts.
16. `notification_deliveries`: Email delivery status tracking, attempt counters, and error logs.
17. `schema_migrations`: Forward-only migration ledger storing `version`, `description`, SHA-256 `checksum`, `executed_at`, and `execution_time_ms`.

## Naming Conventions & Encoding

- **Character Set & Collation:** `utf8mb4` with `utf8mb4_unicode_ci` (cross-compatible across MySQL 8.x and MariaDB 10.4+).
- **Session Timezone:** `+08:00` (`Asia/Manila`).
- **Primary Keys:** `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`.
- **Foreign Keys:** `<singular_entity>_id BIGINT UNSIGNED` with explicit constraint names `fk_<table_name>_<column_name>`.
- **Integrity:** `ON DELETE RESTRICT` on primary academic entities (`subjects`, `instructors`, `rooms`, `sections`); `ON DELETE CASCADE` on child attachments and deliveries.

## Migration & Seeder CLI Usage

```bash
# Check current migration status without applying changes
composer --working-dir=backend migrate -- --status

# Apply pending forward migrations
composer --working-dir=backend migrate

# Roll back the most recently applied migration batch
composer --working-dir=backend migrate -- --rollback

# Roll back in production (requires explicit --force flag)
composer --working-dir=backend migrate -- --rollback --force

# Run optional development seeders (blocked automatically in production)
composer --working-dir=backend seed
```

## Data Separation & Security Policy

- **Development Fixtures (`backend/database/seeders/`)**: Optional and empty by default. Create local test accounts through the application or add an explicit development-only seeder. `backend/bin/seed.php` refuses execution when `APP_ENV=production`.
- **Secrets & Passwords**: Never commit plaintext passwords or production credentials.
