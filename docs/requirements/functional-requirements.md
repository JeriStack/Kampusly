# Kampusly — Functional Requirements, Scope, Roles, Workflows, and Use Cases

**Project:** Kampusly: A Web-Based Class Schedule Management and Notification System  
**Tagline:** *“Your Campus. Your Classes. Your Time.”*  
**Status:** Approved Phase 1 Baseline (Version 1.0 • 28 October 2026)

---

## 1. Problem Statement, Vision, and Objectives

### 1.1 Problem Statement
Academic institutions frequently rely on disconnected spreadsheets, static bulletin boards, or ad-hoc messaging channels to distribute class schedules, room assignments, and instructor availability updates. This causes:
1. **Schedule Ambiguity for Students:** No single, authoritative, mobile-friendly view of daily classes, assigned rooms, and instructors—especially when offline.
2. **Double-Booked Campus Resources:** Manual scheduling without interval overlap detection leads to room, instructor, and section conflicts.
3. **Uncoordinated Class-Change Communication:** Cancellation, unavailability, rescheduling, and room-change notices reach affected students late or inconsistently.
4. **Lack of Access Control & Auditability:** Informal tools fail to enforce strict role and resource-ownership boundaries or audit administrative changes.

### 1.2 Vision & Design Principle
**Useful first, sophisticated second.** Kampusly centralizes class schedules, subject announcements, class-change notices, and 30-minute class reminders into a calm, responsive, and trustworthy campus web application.

### 1.3 Primary Objectives (`OBJ-*`)
- **`OBJ-01`:** Centralize academic reference data (`Users`, `Students`, `Instructors`, `Subjects`, `Rooms`, `Sections`) and timetable management under administrative control.
- **`OBJ-02`:** Eliminate room, instructor, and section double-booking through deterministic server-side conflict rules inside database transactions.
- **`OBJ-03`:** Enforce strict role-based access control (RBAC) and resource-ownership policies across every API endpoint.
- **`OBJ-04`:** Deliver targeted class communications and idempotent 30-minute class reminders without duplicate alerts.
- **`OBJ-05`:** Provide a responsive, accessible UI across desktop, tablet, and mobile viewports with offline schedule PDF export.
- **`OBJ-06`:** Maintain a clean, testable native-PHP PSR-4 modular monolith and Vue 3 SPA with repeatable Docker setup and CI gates.

---

## 2. Scope and Feature Boundaries

### 2.1 Approved MVP Scope (Phases 1–8)
- **Public Landing Page & Theme:** Responsive landing page (`LandingPage.vue`), light mode by default with persisted dark mode toggle (`useTheme`).
- **Identity & Access (`IdentityAccess`):** Email/password sign-in and sign-out, server-side PHP sessions (`HttpOnly`, `SameSite=Lax`, `Secure` in prod), CSRF token verification, account status checks (`active`/`inactive`), role resolution (`admin`, `instructor`, `student`), and single-use hashed password reset flow via PHPMailer.
- **Academic Reference Data (`Academic`):** Administrator CRUD, search, filter, pagination, and status management (`active`/`inactive`) for users, students, instructors, subjects, rooms, and sections.
- **Scheduling (`Scheduling`):** Administrator schedule creation/editing, transactional conflict checking, status transitions (`draft`, `published`, `cancelled`), Student and Instructor schedule views (`Today`, `Monday`–`Saturday`, `All`, Calendar & List views), and Subject Detail view.
- **Announcements (`Announcements`):** Instructor (for assigned subjects) and Administrator creation, editing, publishing, archiving, and withdrawal of subject announcements with optional validated private file attachments.
- **Class Notices (`ClassNotices`):** Instructor submission of class notices (`unavailable`, `cancelled`, `rescheduled`, `room_change`) for assigned schedules with confirmation dialog, status tracking, and Administrator approval/oversight.
- **Notifications (`Notifications`):** Per-recipient in-app notifications (`read_at` per user), unread badge count, mark-as-read / mark-all-read, transactional notification generation on schedule/announcement/notice events, CLI 30-minute class reminder worker (`bin/generate-reminders.php`) with `deduplication_key` uniqueness, and optional SMTP email delivery.
- **Schedule PDF Export:** Authenticated student endpoint (`GET /api/v1/my/schedule.pdf`) streaming a formatted, offline-readable PDF schedule via Dompdf.

### 2.2 Deferred Post-MVP Enhancements (Phases 9–10)
- Server-Sent Events (SSE), WebSockets live updates, and Web Push browser notifications.
- User-configurable 15 / 30 / 60-minute reminder lead times.
- Redis queues/caching, Prometheus/Grafana dashboards, OpenTelemetry/Loki, and Terraform cloud provisioning.
- Dedicated audit-log search UI and advanced room-availability analytics.

### 2.3 Excluded Features (Out of Scope)
- LMS features (quizzes, homework submissions, grading, rubrics).
- Student self-enrollment or course registration shopping carts.
- Automated AI / genetic constraint-solver timetable generation.
- Tuition, billing, or financial management.
- Biometric or QR daily attendance roll-call.
- Direct peer-to-peer chat between students and instructors.

---

## 3. Stakeholders, User Roles, and Permission Matrix

### 3.1 Roles
1. **Administrator (`admin`):** Manages users, academic reference entities, schedules, announcements, class notices, and operational dashboard metrics.
2. **Instructor (`instructor`):** Linked $1:1$ to an `instructors` profile; views assigned schedules/subjects and permitted section rosters, publishes announcements for assigned subjects, and submits class notices for assigned schedules.
3. **Student (`student`):** Linked $1:1$ to a `students` profile with an assigned `section_id`; views personal section schedule, subject details, announcements, and class notices, manages own notification read state, and downloads own schedule PDF.
4. **Scheduled Reminder Worker (`system` CLI):** Executes `bin/generate-reminders.php` to create idempotent 30-minute class reminders.

### 3.2 Role-Permission Matrix

| Module / Resource | Operation | Public | Student | Instructor | Administrator |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **Landing Page** | View public landing page & theme toggle | ALLOW | ALLOW | ALLOW | ALLOW |
| **Auth** | Login (`POST /api/v1/auth/login`), Forgot/Reset Password | ALLOW | ALLOW | ALLOW | ALLOW |
| **Auth** | Logout (`POST /api/v1/auth/logout`), Session (`GET /api/v1/auth/me`) | NONE | OWN | OWN | OWN |
| **Admin Summary** | View dashboard counts (`GET /api/v1/admin/dashboard-summary`) | NONE | NONE | NONE | FULL |
| **Users** | Create, list, view, update role/status, deactivate | NONE | NONE | NONE | FULL |
| **Students** | CRUD & section linkage | NONE | OWN (Read) | ASSIGNED ROSTER (Read) | FULL |
| **Instructors** | CRUD & user linkage | NONE | SECTION (Read) | OWN (Read) | FULL |
| **Subjects / Rooms / Sections** | CRUD, search, paginate, activate/deactivate | NONE | SECTION (Read) | ASSIGNED (Read) | FULL |
| **Schedules** | Create, update, conflict-check, publish, cancel | NONE | NONE | NONE | FULL |
| **Schedules** | View personal/assigned schedule (`GET /api/v1/my/schedule`) | NONE | SECTION (Published) | OWN (Assigned) | FULL (via `/schedules`) |
| **Announcements** | View published announcements | NONE | SECTION | OWN (Assigned subjects) | FULL |
| **Announcements** | Create, edit, publish, archive, withdraw | NONE | NONE | OWN (Assigned subjects) | FULL |
| **Class Notices** | Submit/withdraw notice (`unavailable`, `cancelled`, `rescheduled`, `room_change`) | NONE | NONE | OWN (Assigned schedules) | FULL |
| **Class Notices** | Approve, reject, or override class notice | NONE | NONE | NONE | FULL |
| **Notifications** | List own notifications & mark read / read-all | NONE | OWN | OWN | OWN |
| **Schedule PDF** | Download personal schedule PDF (`GET /api/v1/my/schedule.pdf`) | NONE | OWN | NONE | NONE |

---

## 4. Functional Requirements Catalogue (`FR-*`)

### 4.1 Identity and Access (`FR-AUTH`)
- **`FR-AUTH-01` (Must-Have):** Authenticate users via email and password (`POST /api/v1/auth/login`) using `password_verify()` against `users.password_hash` and reject `inactive` accounts.
- **`FR-AUTH-02` (Must-Have):** Regenerate the PHP session ID on login/privilege change, issue an `HttpOnly`, `SameSite=Lax` (`Secure` in prod) cookie, update `last_login_at`, and return user/role context.
- **`FR-AUTH-03` (Must-Have):** Return a uniform generic error (`"Invalid email or password."`) on failed login attempts.
- **`FR-AUTH-04` (Must-Have):** Provide `GET /api/v1/auth/me` and `POST /api/v1/auth/logout` (destroying server session data and clearing the cookie).
- **`FR-AUTH-05` (Must-Have):** Support `POST /api/v1/auth/forgot-password` with a generic response, generating a cryptographically random single-use token, storing only its SHA-256 hash (`password_reset_tokens.token_hash`) with expiry, and emailing the reset link via PHPMailer.
- **`FR-AUTH-06` (Must-Have):** Validate `POST /api/v1/auth/reset-password` by verifying token hash, `used_at IS NULL`, and `expires_at > NOW()`, updating `password_hash`, setting `used_at = NOW()`, and invalidating active sessions.
- **`FR-AUTH-07` (Must-Have):** Enforce CSRF token verification on all state-changing cookie-authenticated requests.

### 4.2 Academic Reference Data (`FR-ACAD`)
- **`FR-ACAD-01` (Must-Have):** Admin CRUD, filtering, and status/role management for `users` (`admin`, `instructor`, `student`; `active`, `inactive`).
- **`FR-ACAD-02` (Must-Have):** Admin CRUD, search, and pagination for `students` (`student_number` UK, `first_name`, `last_name`, `program`, `user_id` FK, `section_id` FK).
- **`FR-ACAD-03` (Must-Have):** Admin CRUD, search, and pagination for `instructors` (`employee_number` UK, `first_name`, `last_name`, `department`, `user_id` FK).
- **`FR-ACAD-04` (Must-Have):** Admin CRUD, search, pagination, and status toggle for `subjects` (`subject_code` UK, `subject_name`, `description`, `units`, `status`).
- **`FR-ACAD-05` (Must-Have):** Admin CRUD, search, pagination, and status toggle for `rooms` (`room_code` UK, `room_name` UK, `building`, `capacity`, `status`).
- **`FR-ACAD-06` (Must-Have):** Admin CRUD, search, pagination, and status toggle for `sections` (`section_name`, `program`, `year_level`, `academic_year`/`school_year`, `semester`, `status`).
- **`FR-ACAD-07` (Must-Have):** Prevent hard deletion of referenced academic records; use status deactivation/archiving instead.

### 4.3 Schedule Management and Views (`FR-SCHED`)
- **`FR-SCHED-01` (Must-Have):** Admin creation and updating of `schedules` (`subject_id`, `instructor_id`, `room_id`, `section_id`, `school_year`, `semester`, `day_of_week`, `start_time`, `end_time`, `status`).
- **`FR-SCHED-02` (Must-Have):** Validate `start_time < end_time`, active status of all referenced entities, and transactional conflict detection across room, instructor, and section before saving or publishing.
- **`FR-SCHED-03` (Must-Have):** Reject conflicting schedules (`409 Conflict`) with structured conflict details (`dimension`, `resource_label`, `subject_code`, `section_name`, `day_of_week`, `start_time`, `end_time`).
- **`FR-SCHED-04` (Must-Have):** Create in-app notifications for affected students and the assigned instructor when a schedule is published or a published schedule is modified.
- **`FR-SCHED-05` (Must-Have):** Student Schedule View (`GET /api/v1/my/schedule`) with `Today`, `Monday`–`Saturday`, and `All` filters in both Calendar (`FullCalendar`) and accessible List views.
- **`FR-SCHED-06` (Must-Have):** Student Dashboard displaying next upcoming class (time, subject, room, instructor, optional countdown), today's class count, new announcements, and unread notifications.
- **`FR-SCHED-07` (Must-Have):** Instructor Schedule View and Dashboard displaying today's/upcoming assigned classes, rooms, sections, permitted rosters, and quick actions.
- **`FR-SCHED-08` (Must-Have):** Subject Detail View (`GET /api/v1/subjects/{id}`) displaying subject metadata, schedule meetings, instructor, room, section, and published announcements.

### 4.4 Announcements (`FR-ANN`)
- **`FR-ANN-01` (Must-Have):** Instructor (for assigned subjects) and Admin creation, editing, publishing, archiving, and withdrawal of `announcements` (`title`, `message`/`content`, `status`, `published_at`).
- **`FR-ANN-02` (Must-Have):** Reject (`403 Forbidden`) any instructor attempt to manage announcements for an unassigned subject/section.
- **`FR-ANN-03` (Should-Have):** Support optional validated attachments (`announcement_attachments`) stored outside `backend/public/` in `backend/storage/uploads/`.
- **`FR-ANN-04` (Must-Have):** Transactionally generate per-recipient `notifications` for enrolled students when an announcement is published.
- **`FR-ANN-05` (Must-Have):** Display published announcements relevant to the student's section/subjects in reverse chronological order.

### 4.5 Class Notices (`FR-CN`)
- **`FR-CN-01` (Must-Have):** Instructor (for assigned `schedule_id`) and Admin submission of `class_notices` (`unavailable`, `cancelled`, `rescheduled`, `room_change`) with `affected_date`, `message`, and conditional `new_room_id` or new time fields.
- **`FR-CN-02` (Must-Have):** Require explicit confirmation in the UI before submitting a cancellation or unavailability notice.
- **`FR-CN-03` (Must-Have):** Reject (`403 Forbidden`) any instructor attempt to submit or modify a notice for an unassigned `schedule_id`.
- **`FR-CN-04` (Must-Have):** Track notice statuses (`pending_approval`, `published`, `rejected`, `withdrawn`) and surface status history to the authoring instructor and administrators.
- **`FR-CN-05` (Must-Have):** Transactionally notify affected students in `schedule.section_id` when a notice reaches `published` status.
- **`FR-CN-06` (Must-Have):** Display clear text + icon badges (`Cancelled`, `Instructor Unavailable`, `Room Change`, `Rescheduled`) beside affected classes on schedules and dashboards.

### 4.6 Notifications and Scheduled Reminders (`FR-NOTIF`)
- **`FR-NOTIF-01` (Must-Have):** Persist one `notifications` record per recipient (`user_id`, `read_at`, `deduplication_key`).
- **`FR-NOTIF-02` (Must-Have):** Provide `GET /api/v1/notifications`, `POST /api/v1/notifications/{id}/read`, and `POST /api/v1/notifications/read-all`.
- **`FR-NOTIF-03` (Must-Have):** Enforce recipient ownership (`403`/`404`) when marking notifications read.
- **`FR-NOTIF-04` (Must-Have):** CLI worker (`bin/generate-reminders.php`) generates 30-minute pre-class reminders in `Asia/Manila` for `published` schedules, excluding sessions with an active `cancelled`/`unavailable` notice for that date.
- **`FR-NOTIF-05` (Must-Have):** Enforce idempotency via a `UNIQUE(deduplication_key)` constraint (`reminder:{schedule_id}:{YYYY-MM-DD}:{user_id}`).
- **`FR-NOTIF-06` (Should-Have):** Dispatch optional SMTP emails via PHPMailer and track status in `notification_deliveries` without rolling back in-app notifications on SMTP error.

### 4.7 Schedule PDF Export and Operations (`FR-PDF`, `FR-ADM`)
- **`FR-PDF-01` (Must-Have):** Authenticated student endpoint (`GET /api/v1/my/schedule.pdf`) generating a downloadable PDF via Dompdf.
- **`FR-PDF-02` (Must-Have):** Include student name, student number, program, section, school year, semester, generated timestamp, and day/time-ordered class table in the PDF.
- **`FR-PDF-03` (Must-Have):** Derive PDF content strictly from the authenticated student's session context.
- **`FR-ADM-01` (Must-Have):** Admin Dashboard summary endpoint (`GET /api/v1/admin/dashboard-summary`) returning counts of students, instructors, subjects, rooms, today's classes, upcoming classes, active announcements, and cancelled classes.
- **`FR-ADM-02` (Should-Have):** Record administrative and security-sensitive writes in `audit_logs`.
- **`FR-ADM-03` (Must-Have):** Expose `/api/v1/health/live` and `/api/v1/health/ready`.

---

## 5. Schedule Conflict Rules (`SCR-*`)

Two schedule intervals $A = [\text{start}_A, \text{end}_A)$ and $B = [\text{start}_B, \text{end}_B)$ in the same `school_year`, `semester`, and `day_of_week` overlap **if and only if**:

$$\text{start}_A < \text{end}_B \quad \land \quad \text{start}_B < \text{end}_A$$

1. **`SCR-01` (Room Conflict):** Rejects overlapping active schedules sharing the same `room_id`.
2. **`SCR-02` (Instructor Conflict):** Rejects overlapping active schedules sharing the same `instructor_id`.
3. **`SCR-03` (Section Conflict):** Rejects overlapping active schedules sharing the same `section_id`.
4. **`SCR-04` (Adjacent Classes Allowed):** Back-to-back classes where $\text{end}_A = \text{start}_B$ (e.g., `08:00–10:00` and `10:00–12:00`) do not overlap and are permitted.
5. **`SCR-05` (Different Day/Term):** Schedules on different `day_of_week`, `semester`, or `school_year` values never conflict.
6. **`SCR-06` (Invalid Time Values):** Missing times or $\text{start\_time} \ge \text{end\_time}$ are rejected with `422 Unprocessable Entity`.
7. **`SCR-07` (Self-Exclusion on Edit):** Updating a schedule excludes its own `id` from conflict comparison.
8. **`SCR-08` (Cancelled Schedules Excluded):** Schedules with master `status IN ('cancelled', 'inactive')` do not block new schedule assignments.
9. **`SCR-09` (Draft & Published Blocking):** Conflicts block schedule creation, editing, and publication across both `draft` and `published` schedules inside a database transaction (`409 Conflict`).

---

## 6. Class-Notice Approval Policy (`CNP-*`)

1. **`CNP-01` (Hybrid Approval Policy — Confirmed Default):**
   - Urgent attendance notices (`unavailable`, `cancelled`) require explicit instructor confirmation in the UI and publish immediately (`status = 'published'`), notifying affected students in the same transaction.
   - Structural resource/time notices (`room_change`, `rescheduled`) enter `status = 'pending_approval'` (unless submitted by an Administrator), validate room/time availability, and notify students once approved by an Administrator.
2. **`CNP-02` (Single-Occurrence Scope):** A `ClassNotice` applies to the specified `affected_date` occurrence; permanent semester-wide changes are made by an Administrator updating `schedules`.
3. **`CNP-03` (Rejection & Resubmission):** Rejecting a `pending_approval` notice requires a non-empty `rejection_reason` sent to the instructor. Instructors may revise and resubmit rejected notices or withdraw active notices (`status = 'withdrawn'`).
4. **`CNP-04` (One Active Notice per Class Date):** Only one active (`pending_approval` or `published`) notice may exist for a given `schedule_id` + `affected_date`.

---

## 7. Approved Stakeholder Decisions (`DEC-01` to `DEC-06`)

- **`DEC-01` (Approved):** Hybrid class-notice policy (`unavailable`/`cancelled` publish immediately upon confirmation; `room_change`/`rescheduled` require Admin approval).
- **`DEC-02` (Approved):** Both `draft` and `published` schedules participate in conflict checking so drafts cannot accumulate hidden conflicts.
- **`DEC-03` (Approved):** `ClassNotice` applies to a single `affected_date` occurrence; permanent schedule edits happen on `schedules`.
- **`DEC-04` (Approved):** Block-section student enrollment model (`students.section_id`) for the MVP.
- **`DEC-05` (Approved):** Instructors may view basic student roster records (`student_number`, `full_name`) strictly for sections assigned to their active schedules.
- **`DEC-06` (Approved):** Unified database schema harmonizing Diagram 6 (ERD) and System Plan Section 10 (`users.full_name` alongside profile `first_name`/`last_name`, and `announcements` supporting `author_user_id`, `instructor_id`, and `subject_id`).

