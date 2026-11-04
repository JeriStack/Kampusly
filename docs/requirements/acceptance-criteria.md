# Kampusly — Acceptance Criteria and Verification Matrix

**Project:** Kampusly: A Web-Based Class Schedule Management and Notification System  
**Status:** Approved Phase 1 Baseline (Version 1.0 • 28 October 2026)

---

## 1. Measurable Acceptance Criteria (`Given–When–Then`)

### 1.1 Identity, Authentication, and Role-Based Access (`AC-AUTH`, `AC-RBAC`)

| ID | Scenario | Given | When | Then |
| :--- | :--- | :--- | :--- | :--- |
| **AC-AUTH-01** | Valid Sign-In & Session Creation | An `active` user exists with `role = 'student'` (or `'instructor'` / `'admin'`). | The user submits valid email and password credentials to `POST /api/v1/auth/login`. | The server regenerates the session ID, sets an `HttpOnly`, `SameSite=Lax` cookie, updates `last_login_at`, and returns `200 OK` with the user profile, role context, and CSRF token. |
| **AC-AUTH-02** | Generic Error on Invalid Login | Any login screen request. | A user submits a non-existent email or an incorrect password to `POST /api/v1/auth/login`. | The server returns `401 Unauthorized` with the generic message `"Invalid email or password."` and creates no session. |
| **AC-AUTH-03** | Inactive Account Blocked | A user account has `status = 'inactive'`. | The user submits valid email and password credentials. | The server denies authentication with `403 Forbidden` and explains that the account is inactive. |
| **AC-AUTH-04** | Single-Use Hashed Reset Token | An active user requests a password reset via `POST /api/v1/auth/forgot-password`. | The token is generated and subsequently submitted once to `POST /api/v1/auth/reset-password`. | Only the SHA-256 hash of the token is stored in `password_reset_tokens`; the first valid submission updates `users.password_hash` and sets `used_at = NOW()`; any second attempt with the same token is rejected (`400`/`422`). |
| **AC-AUTH-05** | Expired Reset Token Rejected | A password reset token has `expires_at < NOW()`. | A user submits the expired token to `POST /api/v1/auth/reset-password`. | The server rejects the request (`400`/`422`) and leaves the user's password unchanged. |
| **AC-RBAC-01** | Cross-Student & Admin Route Isolation | Student A (`section_id = 1`) is authenticated. | Student A requests Student B's data, another student's schedule ID, or `/api/v1/admin/*` endpoints. | The server rejects the request with `403 Forbidden`. |
| **AC-RBAC-02** | Missing CSRF Token on Write | An authenticated session cookie is present. | A client sends a `POST`, `PATCH`, or `DELETE` request without a valid `X-CSRF-Token` header. | The server rejects the state-changing request (`403 Forbidden`). |

---

### 1.2 Academic Data & Schedule Conflict Rules (`AC-ACAD`, `AC-SCHED`)

| ID | Scenario | Given | When | Then |
| :--- | :--- | :--- | :--- | :--- |
| **AC-ACAD-01** | Duplicate Unique Code Rejected | A subject with `subject_code = 'CS 201'` (or room `room_code`, student `student_number`, instructor `employee_number`, user `email`) already exists. | An Administrator submits a create or update request duplicating that unique identifier. | The server rejects the request with `422 Unprocessable Entity` and returns a field-specific validation error. |
| **AC-SCHED-01** | Room, Instructor, or Section Overlap Rejected | An active schedule (`draft` or `published`) exists on `Monday 08:00–10:00` in `2026-2027 1st Semester` for `Room 304`, `Prof. Reyes`, and `BSCS 2A`. | An Administrator submits a schedule on `Monday 09:00–11:00` in the same term that shares `Room 304`, `Prof. Reyes`, **or** `BSCS 2A`. | The server rolls back the transaction and returns `409 Conflict` listing each conflicting dimension (`room`, `instructor`, `section`), subject code, section, and time window. |
| **AC-SCHED-02** | Adjacent Back-to-Back Classes Allowed | An active schedule exists on `Monday 08:00–10:00` for `Room 304`, `Prof. Reyes`, and `BSCS 2A`. | An Administrator submits a schedule on `Monday 10:00–12:00` (`endA == startB`) for the same room, instructor, and section. | The server accepts and persists the schedule (`201 Created` / `200 OK`). |
| **AC-SCHED-03** | Invalid Time Interval Rejected | An Administrator is creating or editing a schedule. | The submitted `start_time` is greater than or equal to `end_time` (e.g., `14:00` to `13:00` or `10:00` to `10:00`). | The server rejects the request with `422 Unprocessable Entity` before running conflict queries. |
| **AC-SCHED-04** | Cancelled Schedule Frees Slot | A schedule on `Monday 08:00–10:00` in `Room 304` has `status = 'cancelled'`. | An Administrator creates a new schedule in `Room 304` on `Monday 08:00–10:00`. | The server permits and saves the new schedule without reporting a conflict against the cancelled record. |

---

### 1.3 Announcements, Class Notices, Notifications & PDF (`AC-ANN`, `AC-CN`, `AC-NOTIF`, `AC-PDF`)

| ID | Scenario | Given | When | Then |
| :--- | :--- | :--- | :--- | :--- |
| **AC-ANN-01** | Assigned Instructor Publishes Announcement | Instructor X teaches `CS 201` for section `BSCS 2A` (30 active students). | Instructor X publishes an announcement for `CS 201`. | The announcement is saved with `status = 'published'` and 30 recipient `notifications` records are created inside the same database transaction. |
| **AC-ANN-02** | Unassigned Instructor Blocked from Announcing | Instructor Y has no assigned schedule for `MATH 115`. | Instructor Y attempts to create or publish an announcement for `MATH 115`. | The server returns `403 Forbidden` and persists no announcement or notifications. |
| **AC-ANN-03** | Unsafe Attachment Blocked | An instructor or admin attaches a disallowed file type (`.php`, `.exe`, `.html`, or file $> 5\text{ MB}$). | The announcement request is submitted. | The server rejects the upload with `422 Unprocessable Entity` and writes nothing to `backend/storage/uploads/`. |
| **AC-CN-01** | Assigned Instructor Submits Urgent Class Notice | Instructor X is assigned to `schedule_id = 10` (`BSCS 2A`). | Instructor X confirms and submits a `cancelled` or `unavailable` notice for an upcoming class date. | The notice is saved with `status = 'published'`, all active students in `BSCS 2A` receive an in-app notification transactionally, and the class shows a `Cancelled` / `Unavailable` badge in the schedule UI. |
| **AC-CN-02** | Room-Change / Reschedule Approval Flow | Instructor X submits a `room_change` or `rescheduled` notice for `schedule_id = 10`. | The notice is submitted and then reviewed by an Administrator. | The notice starts as `pending_approval`; upon Admin approval it transitions to `published` and notifies affected students; upon Admin rejection (with required reason) it transitions to `rejected` and notifies Instructor X. |
| **AC-CN-03** | Unassigned Instructor Class Notice Blocked | Instructor Y is not assigned to `schedule_id = 10`. | Instructor Y attempts to submit or withdraw a class notice for `schedule_id = 10`. | The server returns `403 Forbidden`. |
| **AC-NOTIF-01** | Idempotent 30-Minute Class Reminder | A published class starts in 25 minutes in `Asia/Manila` and has no active `cancelled`/`unavailable` notice for today. | `php bin/generate-reminders.php` is executed twice consecutively. | Each enrolled student receives **exactly one** reminder notification (`UNIQUE(deduplication_key)` prevents duplicates on the second run). |
| **AC-NOTIF-02** | Cancelled Class Excluded from Reminders | A published class starting in 25 minutes has an active `cancelled` or `unavailable` notice for today's date. | `php bin/generate-reminders.php` executes. | Zero class reminder notifications are generated for that cancelled session. |
| **AC-NOTIF-03** | Per-Recipient Read State Isolation | Student A and Student B each have a notification row for the same announcement. | Student A calls `POST /api/v1/notifications/{idA}/read` and attempts `POST /api/v1/notifications/{idB}/read`. | `{idA}` updates `read_at = NOW()`, `{idB}` remains unread (`read_at IS NULL`), and Student A's attempt to mark `{idB}` returns `403`/`404`. |
| **AC-PDF-01** | Authenticated Student Schedule PDF | Student A (`BSCS 2A`) is authenticated. | Student A requests `GET /api/v1/my/schedule.pdf`. | The server responds with `200 OK` (`Content-Type: application/pdf`) containing Student A's name, student number, section `BSCS 2A`, term, and ordered published classes, readable offline. |

---

## 2. Phase 1 Completion & Readiness Sign-Off

- [x] **Scope & Feature Boundaries Approved**
- [x] **Role-Permission Matrix Approved**
- [x] **Administrator, Instructor, and Student Workflows Approved**
- [x] **Mathematical Schedule Conflict Rules (`SCR-01`–`SCR-09`) Approved**
- [x] **Class-Notice Approval Policy (`CNP-01`–`CNP-04`) Approved**
- [x] **Use Cases (`UC-01`–`UC-10`) & Acceptance Criteria (`AC-*`) Approved**
- [x] **Stakeholder Decisions (`DEC-01`–`DEC-06`) Confirmed**
- [x] **Ready to Proceed to Phase 2**

