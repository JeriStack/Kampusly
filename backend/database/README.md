# Kampusly Database & Migration Conventions

This directory holds database migrations, development seeders, and test factories for the Kampusly MySQL 8.4 relational database.

## Directory Layout

```text
backend/database/
├── migrations/   # Ordered, forward-only SQL migration files
├── seeders/      # Local development seed data (NEVER production or real student data)
├── factories/    # Test fixture factories for integration/feature tests
└── README.md     # Database naming, PDO, and migration conventions
```

## Naming Conventions (Approved in Phase 2)

1. **Character Set & Collation**
   - Default character set: `utf8mb4`
   - Default collation: `utf8mb4_0900_ai_ci`
   - Session timezone: `+08:00` (`Asia/Manila`)
2. **Tables & Columns**
   - Table names: plural `snake_case` nouns (e.g., `users`, `academic_terms`, `schedules`, `class_notices`, `schema_migrations`).
   - Primary keys: `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`.
   - Foreign keys: `<singular_entity>_id BIGINT UNSIGNED` with explicit constraint names `fk_<table_name>_<column_name>`.
   - Unique constraints: `uq_<table_name>_<columns>`.
   - Secondary indexes: `idx_<table_name>_<columns>`.
   - Audit timestamps: `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
3. **Migration File Naming**
   - Format: `YYYY_MM_DD_HHMMSS_<verb>_<target>.sql` (e.g., `2026_11_01_000100_create_users_table.sql`).
   - Every applied migration is recorded in `schema_migrations` with its SHA-256 checksum so modified historical migrations are detected immediately.

## PDO Security & Connection Rules

All database access goes through `Kampusly\Shared\Infrastructure\Database\PdoConnectionFactory` configured via `backend/config/database.php`:
- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` (all SQL errors throw `PDOException`).
- `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC` (consistent associative arrays).
- `PDO::ATTR_EMULATE_PREPARES => false` (native MySQL prepared statements enforced).
- `PDO::ATTR_STRINGIFY_FETCHES => false` (preserves native numeric types).

## Running Migrations & Seeders

```bash
# Check migration status without applying changes
composer --working-dir=backend migrate -- --status

# Apply pending migrations in backend/database/migrations/
composer --working-dir=backend migrate

# Run local development seeders (blocked automatically when APP_ENV=production)
composer --working-dir=backend seed
```

## Data Separation Policy

- **Development Data (`backend/database/seeders/`)**: Contains synthetic test accounts, rooms, subjects, and sample schedules for local verification only. `backend/bin/seed.php` refuses to run when `APP_ENV=production`.
- **Production Data**: Managed strictly through authenticated administrative workflows and backed up outside the Git repository. Never commit production credentials, real student/instructor PII, or `.sql` dumps to version control.

