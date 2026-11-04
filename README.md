# Kampusly

**Your Campus. Your Classes. Your Time.**

A web-based class schedule management and notification system that helps students
access schedules, instructors communicate class updates, and administrators manage
academic scheduling efficiently.

## Stack

| Layer      | Technology                                                   |
| ---------- | ------------------------------------------------------------ |
| Frontend   | Vue 3 + TypeScript + Vite; scoped CSS and shared design tokens |
| Backend    | Native PHP + Composer (PSR-4) — **no PHP framework**         |
| Database   | MySQL + PDO                                                  |
| Mail       | PHPMailer (SMTP); Mailpit locally                            |
| Containers | Docker Compose + Nginx + PHP-FPM                             |
| CI/CD      | GitHub Actions                                               |

Architecture: a **modular monolith** with DDD-inspired bounded contexts
(IdentityAccess, Academic, Scheduling, Announcements, ClassNotices, Notifications,
Shared).

## Repository layout

```
kampusly/
├── frontend/        # Vue 3 + TypeScript + Vite SPA
├── backend/         # Native PHP REST API (PSR-4, bounded contexts)
├── infrastructure/  # Docker, Nginx, (later) monitoring config
├── docs/            # Architecture, API, database, runbooks
├── scripts/         # Repeatable dev/ops scripts
├── .github/         # CI/CD workflows
├── .env.example     # Config names/placeholders — never real secrets
└── docker-compose.yml
```

Directories are created as their modules are implemented; the tree is a reference,
not a mandate to fill every folder up front.

## Local setup

> Prerequisites: Docker + Docker Compose.

```bash
# 1. Create your local environment file (never commit the result)
cp .env.example .env

# 2. Start the local stack
docker compose up -d --build
```

To run only the frontend during development, install its locked dependencies and
start Vite:

```bash
npm --prefix frontend ci
npm --prefix frontend run dev
```

The public landing page links to an interactive, frontend-only prototype. Choose
one of the sample student, instructor, or administrator profiles to explore its
role-specific navigation and dashboard. The prototype includes mock schedule
creation and conflict feedback, publishing, instructor notices, announcements,
student day/week/list schedule views, notification history, and sample
academic-data and user-account actions. Sign-in is simulated in the browser:
any non-empty password is accepted for a listed sample email, and no credentials
or schedule data are sent to a server. These interactions do not implement real
authentication, authorization, server-backed account management, or persistent
application data; role-specific navigation is for UX demonstration only.

Services once running:

| Service      | URL / Port                | Purpose                       |
| ------------ | ------------------------- | ----------------------------- |
| Nginx        | http://localhost:8080     | Frontend + `/api` reverse proxy |
| Frontend dev | http://localhost:5173     | Vite dev server               |
| MySQL        | `localhost:3306`          | Database                      |
| Mailpit      | http://localhost:8025     | Local email inspection        |

## Conventions

- The API is versioned under `/api/v1`. Authorization is enforced **server-side**
  on every protected action — frontend route guards are UX only.
- Domain rules stay independent of HTTP, SQL, email, and PDF details.
- Secrets live in an untracked `.env` (and deployment secret storage), never in Git.
- Uploads, logs, and runtime data stay in `backend/storage/`, outside the web root.

See [`docs/`](./docs) for architecture, API, database, and runbook documentation.
