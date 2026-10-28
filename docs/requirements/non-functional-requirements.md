# Kampusly — Non-Functional Requirements Specification

**Project:** Kampusly: A Web-Based Class Schedule Management and Notification System  
**Status:** Approved Phase 1 Baseline (Version 1.0 • 28 October 2026)

---

## 1. Security Requirements (`NFR-SEC`)

| ID | Requirement | Implementation Target & Verification |
| :--- | :--- | :--- |
| **NFR-SEC-01** | **SQL Injection Prevention** | All database queries must use PDO prepared statements with bound parameters. Dynamic sort/filter identifiers must be validated against strict server-side allow-lists; arbitrary client strings must never be interpolated into SQL. |
| **NFR-SEC-02** | **Password & Reset Token Cryptography** | User passwords must be hashed with `password_hash($password, PASSWORD_DEFAULT)` and verified with `password_verify()`. Password reset tokens must be generated via `random_bytes(32)`, single-use, short-lived (e.g., 30 minutes), and stored only as `hash('sha256', $token)` in `password_reset_tokens.token_hash`. |
| **NFR-SEC-03** | **Session Hardening** | Browser authentication uses server-side PHP sessions with `HttpOnly = true`, `SameSite = Lax`, and `Secure = true` in HTTPS deployments. Session IDs must be regenerated (`session_regenerate_id(true)`) upon login and privilege changes, and destroyed on logout or password reset. |
| **NFR-SEC-04** | **CSRF Protection** | All state-changing (`POST`, `PUT`, `PATCH`, `DELETE`) cookie-authenticated API endpoints must require and validate a cryptographic CSRF token (`X-CSRF-Token`). |
| **NFR-SEC-05** | **Server-Side RBAC & Ownership Policies** | Every protected API route must verify both the user's role (`admin`, `instructor`, `student`) and resource ownership (`InstructorPolicy`, `StudentPolicy`). Client-side route guards and hidden buttons are UX conveniences only. |
| **NFR-SEC-06** | **Brute-Force & Enumeration Defense** | Login and forgot-password endpoints must enforce rate limiting (`429 Too Many Requests`) and return generic responses so attackers cannot enumerate registered emails. |
| **NFR-SEC-07** | **Safe File Uploads & Private Storage** | Announcement attachments must validate allow-listed MIME types (`finfo_file`) and extensions, enforce a maximum file size ($\le 5\text{ MB}$), randomize storage filenames, and reside in `backend/storage/uploads/` outside `backend/public/`. |
| **NFR-SEC-08** | **Secret & Error Hygiene** | Real credentials must never be committed to Git (`.env` is gitignored; `.env.example` holds placeholders only). When `APP_DEBUG=false`, the error handler must return structured JSON error envelopes (`code`, `message`, `errors`, `request_id`) and never leak stack traces or SQL details. |

---

## 2. Reliability & Data Integrity (`NFR-REL`)

| ID | Requirement | Implementation Target & Verification |
| :--- | :--- | :--- |
| **NFR-REL-01** | **Transactional Write Consistency** | Multi-table writes (schedule creation/update + conflict check + recipient notifications; announcement/class notice publication + recipient notifications; password reset + token consumption) must execute inside explicit PDO database transactions. |
| **NFR-REL-02** | **Idempotent Reminder Generation** | The CLI reminder job (`bin/generate-reminders.php`) must use a deterministic `deduplication_key` (`reminder:{schedule_id}:{YYYY-MM-DD}:{user_id}`) backed by a database `UNIQUE` constraint so repeated executions produce zero duplicate notifications. |
| **NFR-REL-03** | **Fault-Isolated Email Delivery** | In-app notifications are persisted as the source of truth before SMTP dispatch. Transient SMTP failures must be logged and recorded in `notification_deliveries` without rolling back the saved schedule, announcement, class notice, or in-app notification. |
| **NFR-REL-04** | **Referential Integrity & Safe Deactivation** | Foreign keys and `UNIQUE` constraints (`users.email`, `students.student_number`, `instructors.employee_number`, `subjects.subject_code`, `rooms.room_code`) must be enforced at the MySQL schema level. Referenced academic records use status deactivation (`inactive`/`archived`) rather than destructive hard deletes. |

---

## 3. Performance & Scalability (`NFR-PERF`)

| ID | Requirement | Implementation Target & Verification |
| :--- | :--- | :--- |
| **NFR-PERF-01** | **Server-Side Pagination** | All administrative collection endpoints (`/users`, `/students`, `/instructors`, `/subjects`, `/rooms`, `/sections`, `/schedules`) must enforce server-side pagination (`page`, `per_page` default `20`, max `100`). |
| **NFR-PERF-02** | **Indexed Schedule & Notification Queries** | Composite indexes must cover schedule conflict lookups (`school_year, semester, day_of_week, start_time`, plus `room_id`, `instructor_id`, and `section_id`), announcement feeds (`subject_id, status, published_at`), class notices (`schedule_id, affected_date`), and recipient notifications (`user_id, read_at, created_at`). |
| **NFR-PERF-03** | **Elimination of $N+1$ Queries** | Schedule lists, dashboard summaries, and bulk recipient notification inserts must use batched queries or `JOIN`s rather than per-row query loops. |

---

## 4. Accessibility & Responsive UX (`NFR-A11Y`, `NFR-COMP`)

| ID | Requirement | Implementation Target & Verification |
| :--- | :--- | :--- |
| **NFR-A11Y-01** | **Keyboard Operability & Focus Visibility** | All interactive controls, modals, menus, and forms must be operable via keyboard (`Tab`, `Shift+Tab`, `Enter`, `Space`, `Escape`) with visible `:focus-visible` rings. |
| **NFR-A11Y-02** | **Color Contrast (WCAG AA)** | Normal text must maintain at least $4.5:1$ contrast and large text, icons, and UI boundaries at least $3:1$ contrast in both Light Mode (default) and Dark Mode. |
| **NFR-A11Y-03** | **Non-Color Status Communication** | Schedule statuses (`Draft`, `Published`, `Cancelled`) and class notices (`Room Change`, `Unavailable`, `Rescheduled`) must pair color tokens with explicit text labels and icons. |
| **NFR-A11Y-04** | **Reduced Motion Support** | CSS transitions and animations must respect `@media (prefers-reduced-motion: reduce)`. |
| **NFR-COMP-01** | **Mobile-First Responsive Layouts** | Views must adapt cleanly across mobile (`360px–440px`), tablet (`768px`), and desktop (`1280px+`) viewports, offering an accessible list/card view alongside visual calendar and table views. |

---

## 5. Maintainability, Observability & Portability (`NFR-MAINT`, `NFR-OBS`, `NFR-PORT`)

| ID | Requirement | Implementation Target & Verification |
| :--- | :--- | :--- |
| **NFR-MAINT-01** | **Native PHP Modular Monolith (No Framework)** | Backend code must use Composer PSR-4 autoloading (`Kampusly\`) organized into bounded contexts (`Shared`, `IdentityAccess`, `Academic`, `Scheduling`, `Announcements`, `ClassNotices`, `Notifications`) and strict layers (`Domain`, `Application`, `Infrastructure`, `Presentation\Http`). |
| **NFR-MAINT-02** | **Contract-First / Validated OpenAPI** | REST endpoints under `/api/v1` must be documented in `docs/api/openapi.yaml` and kept in sync with backend responses and frontend TypeScript types. |
| **NFR-OBS-01** | **Structured Logging & Health Checks** | Application errors, reminder worker runs, and security events must log structured JSON/context via Monolog with request/correlation IDs, and expose `/api/v1/health/live` and `/api/v1/health/ready`. |
| **NFR-PORT-01** | **Reproducible Docker Environment & Timezone** | Local development (`nginx`, `php`, `mysql`, `frontend`, `mailpit`) must run via `docker compose up -d --build` with `APP_TIMEZONE=Asia/Manila` applied consistently across schedule and reminder calculations. |

