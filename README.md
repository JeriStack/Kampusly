# Kampusly

**“Your Campus. Your Classes. Your Time.”**

Kampusly is a web-based class schedule management and notification system designed for higher-education academic departments. It centralizes class scheduling, room/instructor/section conflict detection, targeted academic announcements, instructor class-unavailability workflows, in-app/email notifications, and student PDF timetable exports.

---

## 1. Main Features & User Roles

| Role | Core Capabilities |
| :--- | :--- |
| **Administrator** | Manages users, students, instructors, subjects, rooms, sections, and academic terms; creates and validates class schedules with automated room/instructor/section conflict detection; publishes schedules and announcements; reviews and resolves instructor class notices. |
| **Instructor** | Views personal assigned teaching schedules (weekly calendar and agenda views); reads targeted announcements; submits class-unavailability notices or class-change requests (`pending -> approved / rejected /resolved`) and tracks decisions. |
| **Student** | Views personal published section timetable; receives real-time in-app and optional email notifications for schedule updates, announcements, and approved class notices; downloads an official A4 landscape schedule PDF. |

---

## 2. Approved Technology Stack

| Layer | Technology |
| :--- | :--- |
| **Frontend** | Vue 3 (Composition API) + TypeScript + Vite, Tailwind CSS + `shadcn-vue` design tokens, FullCalendar, Vitest, ESLint, Prettier |
| **Backend** | Native PHP 8.2+ REST API (**no Laravel or other framework**), Composer PSR-4 autoloading (`Kampusly\`), PDO MySQL with prepared statements, lightweight HTTP router (`/api/v1`), OpenAPI 3.1 contract |
| **Database** | MySQL 8.4 (`utf8mb4_0900_ai_ci`), versioned forward-only SQL migrations (`schema_migrations`) |
| **Infrastructure** | Docker & Docker Compose, Nginx `1.27-alpine`, PHP-FPM `8.3-alpine`, Mailpit (`v1.21`), GitHub Actions CI |

Architecture: **Modular Monolith** with DDD-inspired bounded contexts (`IdentityAccess`, `Academic`, `Scheduling`, `Announcements`, `ClassNotices`, `Notifications`, `Shared`).

---

## 3. Prerequisites

- **Git** `2.40+`
- **Docker Engine** `24+` and **Docker Compose** `v2` (recommended for full containerized stack)
- **Node.js** `20.19+` or `22+` and **npm** `10+` (for local frontend development & CI checks)
- **PHP** `8.2+` (with `pdo_mysql`, `mbstring`, `json`, `openssl`) and **Composer** `2.x` (for local backend development & CI checks)

---

## 4. Repository Structure

```text
kampusly/
├── frontend/                  # Vue 3 + TypeScript + Vite SPA
│   ├── public/                # Static favicon & public assets
│   ├── scripts/               # Deterministic lint, format-check, and test runners
│   ├── src/
│   │   ├── app/               # App root, Vue Router, layouts
│   │   ├── assets/styles/     # CSS custom-property tokens & Tailwind entry
│   │   ├── components/        # Reusable UI primitives (BaseButton, ThemeToggle, etc.)
│   │   ├── composables/       # Shared Vue composables (useTheme, useScrollSpy)
│   │   ├── features/          # Feature modules (landing, auth, schedules, etc.)
│   │   ├── services/api/      # Typed REST API client, error normalizer, /api/v1 endpoints
│   │   └── tests/             # Frontend unit tests
│   ├── .env.example           # Browser-safe VITE_* environment template
│   ├── eslint.config.js       # ESLint flat configuration
│   ├── .prettierrc.json       # Prettier formatting configuration
│   ├── vitest.config.ts       # Vitest configuration
│   ├── tailwind.config.ts     # Tailwind CSS theme mapping
│   ├── vite.config.ts         # Vite bundler & /api proxy configuration
│   └── package.json
├── backend/                   # Native PHP REST API (PSR-4: Kampusly\ -> src/)
│   ├── bin/                   # CLI runners (migrate, seed, health-check, test, stan, cs)
│   ├── config/                # Centralized config (app, database, logging, mail, security)
│   ├── database/
│   │   ├── migrations/        # Versioned SQL migrations (0001_..._schema_migrations.sql)
│   │   ├── seeders/           # Development-only data seeders
│   │   ├── factories/         # Test fixture factories
│   │   └── README.md          # Database naming & migration conventions
│   ├── public/
│   │   └── index.php          # Single HTTP entry point (Nginx document root)
│   ├── routes/
│   │   ├── api.php            # /api/v1 route registration
│   │   └── health.php         # /api/v1/health/live and /api/v1/health/ready
│   ├── src/
│   │   ├── Bootstrap/         # EnvLoader, Config, Container, Router, ErrorHandler, ApplicationFactory
│   │   ├── Shared/            # PDO factory, HttpRequest, JsonResponse, RequestValidator, Logger
│   │   ├── IdentityAccess/    # Auth, Users, RBAC bounded context (Phase 4+)
│   │   ├── Academic/          # Terms, Subjects, Rooms, Sections (Phase 4+)
│   │   ├── Scheduling/        # Timetables, Conflict Engine, PDF Export (Phase 4+)
│   │   ├── Announcements/     # Targeted announcements (Phase 4+)
│   │   ├── ClassNotices/      # Instructor unavailability notices (Phase 4+)
│   │   └── Notifications/     # In-app & SMTP notifications (Phase 4+)
│   ├── storage/               # Outside web root: logs/, cache/, tmp/, uploads/
│   ├── tests/                 # Unit and Feature test suites
│   ├── .env.example           # Backend server environment template
│   ├── composer.json          # Composer PSR-4 autoload & quality scripts
│   ├── phpcs.xml              # PSR-12 coding standard ruleset
│   ├── phpstan.neon           # Static analysis configuration
│   └── phpunit.xml            # PHPUnit test suite configuration
├── infrastructure/
│   ├── docker/
│   │   ├── frontend/Dockerfile
│   │   ├── mysql/init/01-init-kampusly.sql
│   │   └── php/Dockerfile
│   └── nginx/
│       ├── nginx.conf
│       ├── conf.d/default.conf
│       └── snippets/security-headers.conf
├── docs/
│   ├── requirements/          # Phase 1 functional, non-functional & acceptance specs
│   ├── phase-2/               # Phase 2 UI/UX, UML, DB schema, OpenAPI 3.1, Architecture
│   └── runbooks/              # Local setup & operational runbooks
├── .github/
│   ├── workflows/             # Frontend CI, Backend CI, and Repo Hygiene workflows
│   └── PULL_REQUEST_TEMPLATE.md
├── CONTRIBUTING.md            # Branching strategy, commit rules & PR workflow
├── docker-compose.yml         # Local multi-container stack
├── .env.example               # Root environment template
└── README.md
```

---

## 5. Installation & Environment Configuration

1. **Clone the repository:**
   ```bash
   git clone https://github.com/<your-org>/kampusly.git
   cd kampusly
   ```

2. **Create local environment files from templates (never commit `.env` files):**
   ```bash
   cp .env.example .env
   cp backend/.env.example backend/.env
   cp frontend/.env.example frontend/.env
   ```

3. **Install dependencies (when running tools on the host):**
   ```bash
   # Frontend dependencies
   npm --prefix frontend ci

   # Backend PSR-4 autoloader
   composer --working-dir=backend dump-autoload --optimize
   ```

---

## 6. Docker Compose Commands (Start, Stop, Inspect)

```bash
# Build and start all services in detached mode
docker compose up -d --build

# Check service status and health checks
docker compose ps

# View real-time logs
docker compose logs -f

# Stop services (preserves MySQL data in the `mysql-data` named volume)
docker compose down

# Stop services and remove named volumes (resets database)
docker compose down -v
```

---

## 7. Database Initialization, Migrations & Seeders

When the `mysql` container starts for the first time, `/docker-entrypoint-initdb.d/01-init-kampusly.sql` automatically configures `utf8mb4_unicode_ci` and initializes the `schema_migrations` tracking table.

To inspect, apply, roll back migrations, or run development seeders:

```bash
# Inside Docker
docker compose exec php composer migrate -- --status
docker compose exec php composer migrate
docker compose exec php composer migrate -- --rollback
docker compose exec php composer seed

# Or directly on the host CLI
composer --working-dir=backend migrate -- --status
composer --working-dir=backend migrate
composer --working-dir=backend migrate -- --rollback
composer --working-dir=backend seed
```

See [`backend/database/README.md`](./backend/database/README.md) for full table schemas, foreign key rules, PDO configuration, and seeding policies.

---

## 8. Local URLs & Ports

All development ports bind to `127.0.0.1` by default:

| Service | Local URL / Address | Description |
| :--- | :--- | :--- |
| **Frontend (Vite Dev)** | `http://localhost:5173` | Vue 3 SPA (landing page, theme toggle, `/login` route) |
| **API Gateway (Nginx)** | `http://localhost:8080/api/v1` | Reverse proxy to PHP-FPM (`backend/public/index.php`) |
| **Interactive API Docs** | `http://localhost:8080/api/v1/docs` | Swagger UI referencing versioned OpenAPI 3.1 specification |
| **OpenAPI 3.1 Contract** | `http://localhost:8080/api/v1/openapi.yaml` | Machine-readable OpenAPI 3.1.0 contract |
| **API Liveness Probe** | `http://localhost:8080/api/v1/health/live` | Returns `200 OK` `{ "status": "ok", "service": "kampusly-api" }` |
| **API Readiness Probe** | `http://localhost:8080/api/v1/health/ready` | Verifies PDO MySQL `SELECT 1` connectivity (`200` or `503`) |
| **Conflict Validation** | `http://localhost:8080/api/v1/schedules/validate-conflicts` | Detects room, instructor, and section schedule conflicts |
| **MySQL 8.4** | `127.0.0.1:3306` | Database `kampusly` |
| **Mailpit Web UI** | `http://localhost:8025` | Local SMTP capture UI (SMTP port `127.0.0.1:1025`) |

---

## 9. Testing & Code-Quality Commands

### Frontend Checks
```bash
npm --prefix frontend run lint          # Lint Vue SFCs and TypeScript files
npm --prefix frontend run format:check  # Check code formatting (Prettier)
npm --prefix frontend run type-check    # Run vue-tsc strict TypeScript check
npm --prefix frontend run test          # Run unit tests
npm --prefix frontend run build         # Type-check and build production bundle
```

### Backend Checks
```bash
composer --working-dir=backend validate --strict  # Validate composer.json
composer --working-dir=backend lint               # PHP syntax lint (php -l)
composer --working-dir=backend cs                 # PSR-12 coding standard check
composer --working-dir=backend stan               # Static analysis & PSR-4 verification
composer --working-dir=backend test               # Run Unit and Feature tests
composer --working-dir=backend check              # Run all backend checks (@lint, @cs, @stan, @test)
composer --working-dir=backend health             # Run CLI liveness health probe
```

---

## 10. Branching & Contribution Workflow

- **Branches:** `main` (protected release branch), `feature/<scope>-<slug>`, `fix/<scope>-<slug>`, `hotfix/<scope>-<slug>`, `docs/<slug>`, `chore/<slug>`.
- **Commits:** Conventional Commits (`feat(schedules): ...`, `fix(auth): ...`, `docs(phase-2): ...`).
- **Pull Requests:** All changes merge into `main` via Pull Requests requiring passing GitHub Actions status checks (`Frontend CI`, `Backend CI`, `Repository & Infrastructure Hygiene`) and at least 1 code review approval.

See [`CONTRIBUTING.md`](./CONTRIBUTING.md) for full branching, review, conflict resolution, and CI guidelines.

---

## 11. Troubleshooting

- **Missing `.env` configuration error:** Run `cp .env.example .env && cp backend/.env.example backend/.env` and verify required keys (`APP_ENV`, `APP_TIMEZONE`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`).
- **Database readiness check returns `503`:** Ensure MySQL is running and reachable at `DB_HOST:DB_PORT` (`mysql:3306` inside Docker Compose, or `127.0.0.1:3306` on the host).
- **Detailed setup guide:** See [`docs/runbooks/local-setup.md`](./docs/runbooks/local-setup.md).

---

## 12. Project Documentation Index

- **Phase 1 — Foundation:** [`docs/requirements/`](./docs/requirements) (`functional-requirements.md`, `non-functional-requirements.md`, `acceptance-criteria.md`)
- **Phase 2 — UI/UX & System Design:** [`docs/phase-2/`](./docs/phase-2) (`phase-2-design-system.md`, `phase-2-ui-ux-specification.md`, `phase-2-uml-diagrams.md`, `phase-2-database-design.md`, `phase-2-api-contract.yaml`, `phase-2-system-architecture.md`, `phase-2-traceability-matrix.md`)
- **Phase 3 — Runbooks & Contribution:** [`CONTRIBUTING.md`](./CONTRIBUTING.md), [`docs/runbooks/local-setup.md`](./docs/runbooks/local-setup.md), [`backend/database/README.md`](./backend/database/README.md)
- **Phase 4 — Database & Backend Foundation:** [`backend/database/README.md`](./backend/database/README.md), [`docs/api/openapi.yaml`](./docs/api/openapi.yaml) (OpenAPI 3.1 contract and Swagger UI at `/api/v1/docs`)
