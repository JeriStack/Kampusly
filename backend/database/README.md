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
│   └── rollbacks/
│       ├── 2026_11_01_000100_create_users_and_auth_tables.down.sql
│       ├── 2026_11_01_000200_create_academic_reference_tables.down.sql
│       ├── 2026_11_01_000300_create_schedules_table.down.sql
│       ├── 2026_11_01_000400_create_announcements_and_notices_tables.down.sql
│       └── 2026_11_01_000500_create_notifications_tables.down.sql
├── seeders/
│   └── 01_development_baseline.php
└── README.md
```

## Schema Inventory (14 Approved Tables)

1. `users`: Core identity, roles (`admin`, `instructor`, `student`), email uniqueness, bcrypt password hash.
2. `password_reset_tokens`: 64-character SHA-256 tokens with expiry and single-use audit timestamps.
3. `audit_logs`: Administrative actions, entity references, JSON metadata, request correlation ID.
4. `sections`: Program, year level (1-6), term uniqueness `uq_sections_term_name (school_year, semester, section_name)`.
5. `subjects`: Subject code, name, description, unit range constraint (1-12 units).
6. `rooms`: Unique room codes/names, building, positive capacity constraint.
7. `students`: References user and section; unique student number.
8. `instructors`: References user; unique employee number, department.
9. `schedules`: Composite conflict-detection indexes, term/day, time order constraint `chk_schedules_time_order (start_time < end_time)`.
10. `announcements`: Author, instructor, subject, section scoping, status (`draft`, `published`, `archived`, `withdrawn`).
11. `announcement_attachments`: Safe storage keys, MIME type, size limit constraint ($\le 10\text{ MB}$).
12. `class_notices`: Notice types (`unavailable`, `cancelled`, `rescheduled`, `room_change`), approval review workflow.
13. `notifications`: In-app notification delivery, unique deduplication key to prevent duplicate alerts.
14. `notification_deliveries`: Email delivery status tracking, attempt counters, and error logs.

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

# Run idempotent development seeders (blocked automatically in production)
composer --working-dir=backend seed
```

## Data Separation & Security Policy

- **Development Fixtures (`backend/database/seeders/`)**: Contains synthetic fictional records (e.g., `admin.dev@kampusly.local`, `CS 201`, `RM-301`, `BSCS 2A`). `backend/bin/seed.php` and `DevelopmentBaselineSeeder` refuse execution when `APP_ENV=production`.
- **Secrets & Passwords**: Dynamic password hashing using `password_hash()` or environment-provided `DEV_SEED_PASSWORD`. Never commit plaintext passwords or production credentials.
