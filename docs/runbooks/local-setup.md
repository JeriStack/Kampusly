# Local Development & Environment Setup Runbook

This runbook provides step-by-step instructions for setting up, running, inspecting, and troubleshooting the Kampusly development environment using Docker Compose or local host tooling (PHP 8.2+, Composer 2, Node.js 22+, MySQL 8.x).

---

## 1. Prerequisites

| Tool | Minimum Version | Check Command |
| :--- | :--- | :--- |
| Git | `2.40+` | `git --version` |
| Docker Engine & Docker Compose | Docker `24+` / Compose `v2` | `docker --version && docker compose version` |
| Node.js & npm *(for host CLI checks)* | Node `20.19+` or `22+` | `node -v && npm -v` |
| PHP & Composer *(for host CLI checks)* | PHP `8.2+` (`pdo_mysql`, `mbstring`, `json`) & Composer `2.x` | `php -v && composer --version` |

---

## 2. Environment Configuration

Kampusly separates environment configuration into three template files containing placeholder values only:
- Root `.env.example` — Used by `docker-compose.yml` and container services.
- `backend/.env.example` — Used by the native PHP backend when running CLI scripts or tests directly on the host.
- `frontend/.env.example` — Browser-safe variables (`VITE_*` prefix only) for the Vue 3 Vite frontend.

### Create Local `.env` Files

```bash
# Root environment file (used by Docker Compose)
cp .env.example .env

# Backend environment file (used when running PHP CLI on the host)
cp backend/.env.example backend/.env

# Frontend environment file (used when running Vite on the host)
cp frontend/.env.example frontend/.env
```

> **Security Rule:** `.env`, `backend/.env`, and `frontend/.env` are ignored by `.gitignore`. Never commit real credentials or secrets, and never place server secrets (`DB_PASSWORD`, `SMTP_PASSWORD`, session keys) in `VITE_*` variables.

---

## 3. Running with Docker Compose (Recommended)

All development ports in `docker-compose.yml` are bound strictly to `127.0.0.1` so services are reachable on your machine without exposing MySQL, phpMyAdmin, or Mailpit to external networks.

```bash
# Build images and start all services in the background
docker compose up -d --build

# Inspect container status and health checks
docker compose ps

# Follow logs across all services (or specify nginx, php, mysql, frontend, mailpit)
docker compose logs -f
docker compose logs -f php nginx

# Install Composer dependencies / regenerate PSR-4 autoloader inside the PHP container
docker compose exec php composer install

# Run backend quality checks inside the PHP container
docker compose exec php composer check

# Run database migrations inside the PHP container
docker compose exec php composer migrate

# Stop containers while preserving the MySQL volume
docker compose down

# Stop containers AND wipe the MySQL volume (clean slate reset)
docker compose down -v
```

### Local Service Endpoints

| Service | Local URL / Host | Internal Docker Host | Purpose |
| :--- | :--- | :--- | :--- |
| Nginx (API Gateway) | `http://localhost:8080` | `nginx:80` | Reverse proxy for `/api/v1/*` -> PHP-FPM |
| Liveness Health Probe | `http://localhost:8080/api/v1/health/live` | `http://nginx/api/v1/health/live` | Confirms PHP-FPM & API routing are healthy |
| Readiness Health Probe | `http://localhost:8080/api/v1/health/ready` | `http://nginx/api/v1/health/ready` | Confirms PDO MySQL `SELECT 1` connectivity |
| Vue 3 Vite Dev Server | `http://localhost:5173` | `frontend:5173` | SPA frontend with `/api` proxy to Nginx |
| MySQL 8.4 | `127.0.0.1:3306` | `mysql:3306` | Relational database (`kampusly`) |
| phpMyAdmin | `http://localhost:8081` | `phpmyadmin:80` | Local database administration UI; log in with `DB_USERNAME` and `DB_PASSWORD` from root `.env` |
| Mailpit Web UI | `http://localhost:8025` | `mailpit:8025` (SMTP `1025`) | Captures outbound development emails |

---

## 4. Running Without Docker (Host CLI / XAMPP Workflow)

If Docker Desktop is not running or you are developing directly on Windows/macOS/Linux with local PHP 8.2+, MySQL/MariaDB, and Node.js:

### Backend (Native PHP REST API)
```bash
# 1. Validate composer.json and generate the PSR-4 autoloader
composer --working-dir=backend validate --strict
composer --working-dir=backend dump-autoload --optimize

# 2. Run syntax lint, PSR-12 style check, static analysis, and unit/feature tests
composer --working-dir=backend check

# 3. Check database migration status and apply pending SQL migrations
composer --working-dir=backend migrate -- --status
composer --working-dir=backend migrate

# 4. Serve the backend API locally on port 8080 using PHP's built-in server
php -S 127.0.0.1:8080 -t backend/public backend/public/index.php
```

### Frontend (Vue 3 + TypeScript + Vite)
```bash
# 1. Install dependencies from lockfile
npm --prefix frontend ci

# 2. Run lint, formatting check, type-check, unit tests, and production build
npm --prefix frontend run lint
npm --prefix frontend run format:check
npm --prefix frontend run type-check
npm --prefix frontend run test
npm --prefix frontend run build

# 3. Start the Vite dev server on http://localhost:5173
npm --prefix frontend run dev
```

---

## 5. Troubleshooting

1. **`Missing required environment configuration key(s)` on API startup:**
   - Ensure `.env` (or `backend/.env`) exists and defines `APP_ENV`, `APP_TIMEZONE`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, and `DB_USERNAME`.
2. **`503 Service Unavailable` on `GET /api/v1/health/ready`:**
   - The PHP runtime is healthy, but MySQL is unreachable or credentials do not match. Verify that MySQL is running (`docker compose ps mysql` or local MySQL service on port `3306`) and that `DB_HOST` is `mysql` inside Docker or `127.0.0.1` on the host.
3. **Port `3306` or `8080` already in use:**
   - Override `NGINX_PORT`, `FRONTEND_PORT`, `DB_FORWARD_PORT`, or `PHPMYADMIN_PORT` in your root `.env` file before running `docker compose up -d`.
