# Kampusly — Phase 2: Design-to-Development Traceability Matrix

**Project:** Kampusly: A Web-Based Class Schedule Management and Notification System  
**Document:** `docs/phase-2/phase-2-traceability-matrix.md`  
**Version:** 1.0 • 28 October 2026  

---

## 1. End-to-End Traceability Matrix

Every approved Phase 1 requirement (`FR-*` and `NFR-*`) is mapped below across its user workflow, UI screen/component, `/api/v1` endpoint, MySQL database table(s), and verification acceptance test (`AC-*`).

| Req ID | User Workflow | UI Screen / Component | API Endpoint (`/api/v1`) | Database Entity / Table | Acceptance Test ID |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **FR-AUTH-01**<br>Email/Password Login | `WF-INS-01`, `WF-STU-01` | `/login` (`LoginPage.vue`, `AuthLayout.vue`) | `POST /auth/login` | `users`, `students`, `instructors`, `audit_logs` | `AC-AUTH-01`, `AC-AUTH-03` |
| **FR-AUTH-02**<br>Hardened Session & Role Context | `WF-INS-01`, `WF-STU-01` | `useAuth.ts`, `router/guards.ts`, Role Layouts | `POST /auth/login`, `GET /auth/me` | `users` | `AC-AUTH-01` |
| **FR-AUTH-03**<br>Generic Login Error | `UC-01` Exception Flow | `LoginPage.vue` Alert Banner | `POST /auth/login` | `users` | `AC-AUTH-02` |
| **FR-AUTH-04**<br>Session Check & Logout | All Role Workflows | Topbar / Sidebar `Sign Out` button | `GET /auth/me`, `POST /auth/logout` | `users` (PHP Session store) | `AC-AUTH-01`, `AC-RBAC-01` |
| **FR-AUTH-05**<br>Forgot Password & Hashed Token | `UC-02` | `/forgot-password` (`ForgotPasswordPage.vue`) | `POST /auth/forgot-password` | `users`, `password_reset_tokens` | `AC-AUTH-04` |
| **FR-AUTH-06**<br>Consume Reset Token & Update Password | `UC-02` | `/reset-password` (`ResetPasswordPage.vue`) | `POST /auth/reset-password` | `users`, `password_reset_tokens`, `audit_logs` | `AC-AUTH-04`, `AC-AUTH-05` |
| **FR-AUTH-07**<br>CSRF Token Enforcement | All Write Workflows | `services/api/client.ts` (`X-CSRF-Token`) | All `POST` / `PATCH` / `DELETE` routes | PHP Session store | `AC-RBAC-02` |
| **FR-ACAD-01**<br>Admin User Account Management | `WF-ADM-01` | `/admin/users` (`UsersManagementPage.vue`) | `GET /users`, `POST /users`, `PATCH /users/{id}` | `users`, `audit_logs` | `AC-ACAD-01`, `AC-RBAC-01` |
| **FR-ACAD-02**<br>Admin Student CRUD & Section Linkage | `WF-ADM-01` | `/admin/students` (`StudentsPage.vue`, `StudentFormModal.vue`) | `GET /students`, `POST /students`, `GET /students/{id}`, `PATCH /students/{id}` | `users`, `students`, `sections`, `audit_logs` | `AC-ACAD-01`, `AC-RBAC-01` |
| **FR-ACAD-03**<br>Admin Instructor CRUD | `WF-ADM-01` | `/admin/instructors` (`InstructorsPage.vue`, `InstructorFormModal.vue`) | `GET /instructors`, `POST /instructors`, `GET /instructors/{id}`, `PATCH /instructors/{id}` | `users`, `instructors`, `audit_logs` | `AC-ACAD-01` |
| **FR-ACAD-04**<br>Admin Subject CRUD | `WF-ADM-01` | `/admin/subjects` (`SubjectsPage.vue`, `SubjectFormModal.vue`) | `GET /subjects`, `POST /subjects`, `PATCH /subjects/{id}` | `subjects`, `audit_logs` | `AC-ACAD-01` |
| **FR-ACAD-05**<br>Admin Room CRUD | `WF-ADM-01` | `/admin/rooms` (`RoomsPage.vue`, `RoomFormModal.vue`) | `GET /rooms`, `POST /rooms`, `PATCH /rooms/{id}` | `rooms`, `audit_logs` | `AC-ACAD-01` |
| **FR-ACAD-06**<br>Admin Section CRUD | `WF-ADM-01` | `/admin/sections` (`SectionsPage.vue`, `SectionFormModal.vue`) | `GET /sections`, `POST /sections`, `PATCH /sections/{id}` | `sections`, `audit_logs` | `AC-ACAD-01` |
| **FR-ACAD-07**<br>Safe Deactivation (No Hard Delete) | `WF-ADM-01` | Status Toggle + Confirmation Modal | `PATCH /{resource}/{id}` | `users`, `students`, `instructors`, `subjects`, `rooms`, `sections` | `AC-ACAD-01` |
| **FR-SCHED-01**<br>Admin Schedule Create & Edit | `WF-ADM-02` | `/admin/schedules` (`SchedulesAdminPage.vue`, `ScheduleFormModal.vue`) | `GET /schedules`, `POST /schedules`, `PATCH /schedules/{id}` | `schedules`, `subjects`, `instructors`, `rooms`, `sections`, `audit_logs` | `AC-SCHED-01`, `AC-SCHED-02` |
| **FR-SCHED-02**<br>Time & Conflict Validation | `WF-ADM-02` | `ScheduleFormModal.vue` (`ConflictAlertPanel.vue`) | `POST /schedules`, `PATCH /schedules/{id}`, `GET /schedules/conflicts` | `schedules` | `AC-SCHED-01`, `AC-SCHED-02`, `AC-SCHED-03`, `AC-SCHED-04` |
| **FR-SCHED-03**<br>Structured `409 Conflict` Response | `WF-ADM-02` | `ConflictAlertPanel.vue` | `POST /schedules`, `PATCH /schedules/{id}` | `schedules` | `AC-SCHED-01` |
| **FR-SCHED-04**<br>Schedule Publication Notifications | `WF-ADM-03` | `SchedulesAdminPage.vue`, `NotificationDropdown.vue` | `POST /schedules`, `PATCH /schedules/{id}` | `schedules`, `notifications`, `notification_deliveries` | `AC-SCHED-02`, `AC-NOTIF-03` |
| **FR-SCHED-05**<br>Student Personal Schedule View | `WF-STU-01` | `/student/schedule` (`StudentSchedulePage.vue`, `ScheduleCalendarView.vue`, `ScheduleListView.vue`) | `GET /my/schedule` | `schedules`, `students`, `sections`, `subjects`, `rooms`, `instructors`, `class_notices` | `AC-STU-01`, `AC-RBAC-01` |
| **FR-SCHED-06**<br>Student Dashboard & Next Class | `WF-STU-01` | `/student/dashboard` (`StudentDashboardPage.vue`, `NextClassCard.vue`) | `GET /my/schedule`, `GET /announcements`, `GET /notifications` | `schedules`, `announcements`, `notifications`, `class_notices` | `AC-STU-01` |
| **FR-SCHED-07**<br>Instructor Assigned Schedule & Dashboard | `WF-INS-01` | `/instructor/dashboard`, `/instructor/schedule` (`InstructorDashboardPage.vue`) | `GET /my/schedule`, `GET /class-notices` | `schedules`, `instructors`, `class_notices` | `AC-CN-01`, `AC-CN-03` |
| **FR-SCHED-08**<br>Subject Detail View | `WF-STU-01`, `WF-INS-01` | `SubjectDetailPage.vue` | `GET /subjects/{id}` | `subjects`, `schedules`, `announcements` | `AC-STU-01`, `AC-RBAC-01` |
| **FR-ANN-01**<br>Create, Publish, Archive Announcement | `WF-INS-02`, `WF-ADM-03` | `AnnouncementsPage.vue`, `AnnouncementFormModal.vue` | `POST /announcements`, `PATCH /announcements/{id}`, `POST /announcements/{id}/publish` | `announcements`, `audit_logs` | `AC-ANN-01` |
| **FR-ANN-02**<br>Assigned-Subject Policy Enforcement | `WF-INS-02` | `AnnouncementFormModal.vue` + Server Policy | `POST /announcements`, `PATCH /announcements/{id}` | `announcements`, `schedules` | `AC-ANN-02` |
| **FR-ANN-03**<br>Validated Private Attachment Upload | `WF-INS-02` | `AnnouncementFormModal.vue` File Input | `POST /announcements` | `announcement_attachments` (`backend/storage/uploads/`) | `AC-ANN-03` |
| **FR-ANN-04**<br>Announcement Recipient Notifications | `WF-INS-02` | `NotificationDropdown.vue`, `/notifications` | `POST /announcements`, `POST /announcements/{id}/publish` | `announcements`, `students`, `notifications` | `AC-ANN-01` |
| **FR-ANN-05**<br>Student Announcement Feed | `WF-STU-02` | `/student/announcements` (`StudentAnnouncementsPage.vue`) | `GET /announcements` | `announcements`, `announcement_attachments` | `AC-ANN-01`, `AC-RBAC-01` |
| **FR-CN-01**<br>Submit Class Notice | `WF-INS-03` | `ClassNoticeFormModal.vue` | `POST /schedules/{id}/notices` | `class_notices`, `schedules` | `AC-CN-01`, `AC-CN-02` |
| **FR-CN-02**<br>Notice Submission Confirmation Step | `WF-INS-03` | `ClassNoticeFormModal.vue` Confirmation Dialog | `POST /schedules/{id}/notices` | `class_notices` | `AC-CN-01` |
| **FR-CN-03**<br>Assigned-Schedule Notice Policy | `WF-INS-03` | Server `InstructorPolicy` | `POST /schedules/{id}/notices`, `POST /class-notices/{id}/withdraw` | `class_notices`, `schedules` | `AC-CN-03` |
| **FR-CN-04**<br>Class Notice Review & Status Tracking | `WF-ADM-04`, `WF-INS-03` | `AdminNoticeReviewModal.vue`, Instructor Notice Tracker | `GET /class-notices`, `POST /class-notices/{id}/approve`, `POST /class-notices/{id}/reject`, `POST /class-notices/{id}/withdraw` | `class_notices`, `audit_logs` | `AC-CN-02` |
| **FR-CN-05**<br>Transactional Notice Notifications | `WF-ADM-04`, `WF-INS-03` | `NotificationDropdown.vue` | `POST /schedules/{id}/notices`, `POST /class-notices/{id}/approve` | `class_notices`, `notifications` | `AC-CN-01`, `AC-CN-02` |
| **FR-CN-06**<br>Schedule Notice Status Badges | `WF-STU-01`, `WF-INS-01` | `ScheduleCard.vue`, `ScheduleCalendarView.vue` | `GET /my/schedule`, `GET /schedules` | `schedules`, `class_notices` | `AC-CN-01` |
| **FR-NOTIF-01**<br>Per-Recipient Notification Storage | `WF-STU-02` | `/notifications` (`NotificationsPage.vue`, `NotificationDropdown.vue`) | `GET /notifications` | `notifications` | `AC-NOTIF-03` |
| **FR-NOTIF-02**<br>Mark Notification(s) Read | `WF-STU-02` | `NotificationDropdown.vue`, `NotificationsPage.vue` | `POST /notifications/{id}/read`, `POST /notifications/read-all` | `notifications` | `AC-NOTIF-03` |
| **FR-NOTIF-03**<br>Notification Ownership Isolation | `WF-STU-02` | Server `NotificationPolicy` | `POST /notifications/{id}/read` | `notifications` | `AC-NOTIF-03` |
| **FR-NOTIF-04**<br>30-Minute Class Reminder Worker | `UC-08` | CLI (`php bin/generate-reminders.php`) | Internal CLI / `GenerateClassRemindersUseCase` | `schedules`, `class_notices`, `students`, `notifications` | `AC-NOTIF-01`, `AC-NOTIF-02` |
| **FR-NOTIF-05**<br>Reminder Deduplication Key | `UC-08` | CLI (`php bin/generate-reminders.php`) | Internal CLI / `UNIQUE(deduplication_key)` | `notifications` | `AC-NOTIF-01` |
| **FR-NOTIF-06**<br>Fault-Isolated SMTP Email Delivery | `UC-08`, `WF-ADM-03` | Email Templates (`backend/resources/email/`) | PHPMailer Infrastructure Adapter | `notification_deliveries` | `AC-NOTIF-01` |
| **FR-PDF-01**<br>Student Schedule PDF Stream | `WF-STU-03` | `Download Schedule PDF` button (`StudentDashboardPage.vue`, `StudentSchedulePage.vue`) | `GET /my/schedule.pdf` | `students`, `sections`, `schedules`, `subjects`, `rooms`, `instructors` | `AC-PDF-01` |
| **FR-PDF-02**<br>Formatted Offline PDF Content | `WF-STU-03` | `backend/resources/pdf/schedule/template.php` | `GET /my/schedule.pdf` | `students`, `sections`, `schedules` | `AC-PDF-01` |
| **FR-PDF-03**<br>Student PDF Ownership Enforcement | `WF-STU-03` | Server `StudentPolicy` | `GET /my/schedule.pdf` | `students`, `schedules` | `AC-PDF-01`, `AC-RBAC-01` |
| **FR-ADM-01**<br>Admin Dashboard Operational Summary | `WF-ADM-01`–`04` | `/admin/dashboard` (`AdminDashboardPage.vue`) | `GET /admin/dashboard-summary` | `students`, `instructors`, `subjects`, `rooms`, `schedules`, `announcements`, `class_notices` | `AC-RBAC-01` |
| **FR-ADM-02**<br>Administrative Audit Logging | `WF-ADM-01`–`04` | `AdminDashboardPage.vue` Activity Feed | Internal `AuditLogger` on admin writes | `audit_logs` | `AC-SCHED-01`, `AC-CN-02` |
| **FR-ADM-03**<br>Liveness & Readiness Probes | Operations | Container / CI Health Checks | `GET /health/live`, `GET /health/ready` | MySQL connection check | Smoke Test |

---

## 2. Gap, Contradiction, and Readiness Verification

1. **Zero Unmapped Functional Requirements:** All 39 functional requirements (`FR-AUTH-01` through `FR-ADM-03`) map to at least one workflow, UI screen/component, `/api/v1` endpoint, database table, and acceptance test.
2. **Resolved Contradictions Across Reference Artifacts:**
   - *Class-Notice Endpoint Naming:* Sequence Diagram 3 used `POST /api/v1/class-notices` while the System Development Plan API Catalogue used `POST /api/v1/schedules/{id}/notices`. `phase-2-api-contract.yaml` defines `POST /api/v1/schedules/{id}/notices` for creation (and `GET /api/v1/class-notices` + `/api/v1/class-notices/{id}/{approve|reject|withdraw}` for lifecycle actions), and the backend router can alias `POST /api/v1/class-notices` cleanly.
   - *ERD vs. System Plan Columns (`DEC-06`):* Reconciled in `phase-2-database-design.md` so both `users.full_name` and profile `first_name`/`last_name` exist, and `announcements` links `author_user_id`, `instructor_id`, `subject_id`, and `section_id`.
   - *Class-Notice Approval Policy (`DEC-01`):* Reconciled via the approved hybrid rule (`unavailable`/`cancelled` publish immediately upon instructor confirmation; `room_change`/`rescheduled` enter `pending_approval` for admin review).

---

## 3. Phase 2 Quality and Completion Checklist

- [x] **1. Visual Identity & Design Tokens (`phase-2-design-system.md`):** Logo anatomy and lockups, HEX/RGB tokens, WCAG AA contrast ratios, typography, spacing, and Tailwind + `shadcn-vue` component specifications documented.
- [x] **2. Screen & Interaction Specifications (`phase-2-ui-ux-specification.md`):** Public landing page, authentication screens (`/login`, `/forgot-password`, `/reset-password`), role-specific dashboards (`Student`, `Instructor`, `Administrator`), timetable (`FullCalendar` + accessible List view), CRUD tables, and forms specified.
- [x] **3. Version-Controlled UML Diagrams (`phase-2-uml-diagrams.md`):** Syntactically validated Mermaid diagrams for Use Cases, Activities, Sequences, Components, Deployment, and State Machines completed.
- [x] **4. Normalized Database Design & ERD (`phase-2-database-design.md`):** Conceptual vs. physical 14-table MySQL 8.4 schema, PK/FK/UK constraints, composite conflict/notification indexes, and non-destructive deactivation rules documented.
- [x] **5. OpenAPI 3.1 Contract (`phase-2-api-contract.yaml`):** All `/api/v1` endpoints, request/response DTOs, status codes (`200`, `201`, `204`, `401`, `403`, `409`, `422`, `429`), pagination, and error envelopes defined.
- [x] **6. System Architecture & Security/A11y/Performance Controls (`phase-2-system-architecture.md`):** Native PHP PSR-4 modular monolith, 4-layer dependency rules, request lifecycle, transaction boundaries, and defensive controls documented.
- [x] **7. End-to-End Traceability (`phase-2-traceability-matrix.md`):** Complete mapping from Phase 1 requirements through UI, API, and database to acceptance tests verified.

