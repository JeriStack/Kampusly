# Kampusly

**“Your Campus. Your Classes. Your Time.”**

<div align="justify">

Kampusly is a web-based class schedule management and notification system for higher-education academic departments. It centralizes class scheduling with automated room, instructor, and section conflict detection, targeted academic announcements, instructor class-unavailability notices, student schedule-change requests, in-app/email notifications, and timetable exports.

</div>

---

## Core Roles & Capabilities

<table>
  <thead>
    <tr>
      <th align="center">Role</th>
      <th align="center">Capabilities</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td align="center"><strong>Administrator</strong></td>
      <td align="justify"><div align="justify">Manages users, students, instructors, subjects, rooms, sections, and terms; builds and publishes conflict-free schedules; reviews schedule-change requests and instructor class notices; manages archived records.</div></td>
    </tr>
    <tr>
      <td align="center"><strong>Instructor</strong></td>
      <td align="justify"><div align="justify">Views assigned teaching schedules; publishes targeted class announcements (with archive retention); submits class-unavailability or change notices and tracks review decisions.</div></td>
    </tr>
    <tr>
      <td align="center"><strong>Student</strong></td>
      <td align="justify"><div align="justify">Views published section timetables; receives in-app and optional email alerts for schedule updates, announcements, and class notices; submits schedule-change requests; exports schedules.</div></td>
    </tr>
  </tbody>
</table>

---

## Technology Stack

| Layer | Technology |
| :--- | :--- |
| **Frontend** | Vue 3 (Composition API), TypeScript, Vite, Tailwind CSS, ESLint, Prettier |
| **Backend** | Native PHP 8.2+ REST API (no framework), Composer PSR-4 (`Kampusly\`), PDO MySQL with prepared statements, OpenAPI 3.1 contract |
| **Database** | MySQL 8.4 (`InnoDB`, `utf8mb4_unicode_ci`), versioned forward-only SQL migrations (`schema_migrations`) |
| **Infrastructure** | Docker & Docker Compose, Nginx `1.27-alpine`, PHP-FPM `8.3-alpine`, Mailpit `v1.21`, GitHub Actions CI |

<div align="justify">

Architecture: **Modular Monolith** with DDD-inspired bounded contexts (`IdentityAccess`, `Academic`, `Scheduling`, `Announcements`, `ClassNotices`, `Notifications`, `Shared`).

</div>

---

## Quick Start

### Prerequisites
- **Git** `2.40+`
- **Docker Engine** `24+` & **Docker Compose** `v2` *(recommended)*
- **Node.js** `20.19+` or `22+` & **PHP** `8.2+` + **Composer** `2.x` *(optional, for host CLI runs)*

### 1. Clone & Configure Environment
```bash
git clone https://github.com/JeriStack/Kampusly.git
cd Kampusly

cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

### 2. Start the Stack & Apply Migrations
```bash
# Build images and start services (all host ports bind to 127.0.0.1)
docker compose up -d --build

# Check service health
docker compose ps

# Apply database migrations
docker compose exec php composer migrate
```

### Local Service URLs

| Service | URL / Address | Purpose |
| :--- | :--- | :--- |
| **Frontend (Vite)** | `http://localhost:5173` | Vue 3 SPA (proxies `/api/*` to Nginx) |
| **API Gateway (Nginx)** | `http://localhost:8080/api/v1` | REST API entry point |
| **Swagger / OpenAPI** | `http://localhost:8080/api/v1/docs` | Interactive API docs (`openapi.yaml` at `/api/v1/openapi.yaml`) |
| **Health Probes** | `http://localhost:8080/api/v1/health/live` | Liveness & readiness (`/health/ready`) endpoints |
| **MySQL 8.4** | `127.0.0.1:3306` | `kampusly` relational database |
| **phpMyAdmin** | `http://localhost:8081` | Local database admin UI |
| **Mailpit** | `http://localhost:8025` | Local SMTP capture UI (SMTP `127.0.0.1:1025`) |

> For host-only workflows (without Docker), migration rollbacks, and troubleshooting, see [`docs/runbooks/local-setup.md`](./docs/runbooks/local-setup.md) and [`docs/database/migrations-and-seeding.md`](./docs/database/migrations-and-seeding.md).

---

## Repository Layout

```text
Kampusly/
├── frontend/                  # Vue 3 + TypeScript + Vite SPA
├── backend/                   # Native PHP REST API (PSR-4: Kampusly\ -> src/)
│   ├── bin/                   # CLI scripts (migrate, seed, health-check, check)
│   ├── config/                # App, database, logging, mail, security config
│   ├── database/              # SQL migrations, rollbacks, seeders, factories
│   ├── public/                # Nginx document root (index.php, Swagger UI)
│   ├── routes/                # /api/v1 route definitions
│   ├── src/                   # Bounded contexts (IdentityAccess, Academic, Scheduling, ...)
│   └── tests/                 # Unit and Feature test suites
├── infrastructure/            # Dockerfiles (php, frontend), MySQL init, Nginx config
├── docs/                      # Categorized project documentation (see docs/README.md)
├── .github/                   # CI workflows & PR template
└── docker-compose.yml         # Local development stack
```

---

## Verification & Quality Checks

```bash
# Frontend
npm --prefix frontend run lint
npm --prefix frontend run format:check
npm --prefix frontend run type-check
npm --prefix frontend run test
npm --prefix frontend run build

# Backend (host or via `docker compose exec php composer check`)
composer --working-dir=backend validate --strict
composer --working-dir=backend check
```

---

## Documentation

<div align="justify">

All project specifications, architecture diagrams, database designs, and runbooks are indexed in [`docs/README.md`](./docs/README.md):

</div>

- **Requirements & Traceability:** [`docs/requirements/`](./docs/requirements)
- **Architecture & UML:** [`docs/architecture/`](./docs/architecture)
- **Design System & UI/UX:** [`docs/design/`](./docs/design)
- **Database Design & Migrations:** [`docs/database/`](./docs/database)
- **OpenAPI 3.1 Contract:** [`docs/api/openapi.yaml`](./docs/api/openapi.yaml)
- **Local Setup Runbook:** [`docs/runbooks/local-setup.md`](./docs/runbooks/local-setup.md)

---

## Maintainers

- **Lead Maintainer:** Jeril Mark Estinsu
- **Core Maintainer:** Kevin Quijano

---

## Contributing, Conduct & Security

- **Contributing:** Read [`CONTRIBUTING.md`](./CONTRIBUTING.md) for branching rules, Conventional Commits, and pull request requirements.
- **Code of Conduct:** Participation in this project is governed by [`CODE_OF_CONDUCT.md`](./CODE_OF_CONDUCT.md).
- **Security:** To report a vulnerability privately, follow [`SECURITY.md`](./SECURITY.md) or email [jerilpalin@gmail.com](mailto:jerilpalin@gmail.com).
- **License:** Released under the [MIT License](./LICENSE).
