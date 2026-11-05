# Kampusly — Phase 2: UML Diagrams Specification

**Project:** Kampusly: A Web-Based Class Schedule Management and Notification System  
**Document:** `docs/phase-2/phase-2-uml-diagrams.md`  
**Version:** 1.0 • 28 October 2026  

All diagrams below are authored in version-controlled Mermaid syntax and verified against the approved Phase 1 requirements and Phase 2 API/database specifications.

---

## 1. Use Case Diagram

```mermaid
flowchart LR
    subgraph Actors["Actors"]
        Admin["Administrator"]
        Instructor["Instructor"]
        Student["Student"]
        Worker["Scheduled Reminder Worker (CLI)"]
    end

    subgraph Kampusly["Kampusly System Boundary"]
        UC_Auth["UC-01 / UC-02: Sign In, Sign Out & Reset Password"]
        UC_Acad["UC-03: Manage Users, Students, Instructors, Subjects, Rooms & Sections"]
        UC_Sched["UC-04: Create, Edit & Publish Schedules"]
        UC_Conflict["Validate Room, Instructor & Section Conflicts"]
        UC_Dash["View Role-Specific Operational Dashboard"]
        UC_Ann["UC-05: Create, Publish & Archive Subject Announcements"]
        UC_NoticeSub["UC-06: Submit & Withdraw Class Notices"]
        UC_NoticeRev["UC-07: Review, Approve & Reject Class Notices"]
        UC_Remind["UC-08: Generate 30-Minute Class Reminders"]
        UC_ViewSched["UC-09: View Personal / Assigned Timetable"]
        UC_Notif["UC-09: View & Mark Notifications as Read"]
        UC_Pdf["UC-10: Export Personal Schedule as Offline PDF"]
    end

    Admin --> UC_Auth
    Admin --> UC_Acad
    Admin --> UC_Sched
    UC_Sched -. "includes" .-> UC_Conflict
    Admin --> UC_Dash
    Admin --> UC_Ann
    Admin --> UC_NoticeSub
    Admin --> UC_NoticeRev
    Admin --> UC_Notif

    Instructor --> UC_Auth
    Instructor --> UC_Dash
    Instructor --> UC_ViewSched
    Instructor --> UC_Ann
    Instructor --> UC_NoticeSub
    Instructor --> UC_Notif

    Student --> UC_Auth
    Student --> UC_Dash
    Student --> UC_ViewSched
    Student --> UC_Notif
    Student --> UC_Pdf

    Worker --> UC_Remind
```

---

## 2. Activity Diagrams

### 2.1 Authentication and Role-Based Session Activity

```mermaid
flowchart TD
    Start(["User Opens /login"]) --> Input["Enter Campus Email & Password"]
    Input --> Submit["POST /api/v1/auth/login"]
    Submit --> RateCheck{"Under Rate Limit?"}
    RateCheck -- "No" --> Err429["Return 429 Too Many Requests"] --> EndFail(["Display Rate Limit Alert"])
    RateCheck -- "Yes" --> Lookup["Query User by Email & Verify password_verify()"]
    Lookup --> CredValid{"Credentials Valid?"}
    CredValid -- "No" --> Err401["Return 401 Generic Error ('Invalid email or password')"] --> Input
    CredValid -- "Yes" --> StatusCheck{"User status == 'active'?"}
    StatusCheck -- "No" --> Err403["Return 403 Account Inactive"] --> EndFail
    StatusCheck -- "Yes" --> RegenSession["Regenerate Session ID, Set HttpOnly Cookie & CSRF Token"]
    RegenSession --> UpdateLogin["Update users.last_login_at"]
    UpdateLogin --> RoleBranch{"Evaluate users.role"}
    RoleBranch -- "admin" --> AdminDash(["Redirect to /admin/dashboard"])
    RoleBranch -- "instructor" --> InstDash(["Redirect to /instructor/dashboard"])
    RoleBranch -- "student" --> StuDash(["Redirect to /student/dashboard"])
```

---

### 2.2 Schedule Creation, Conflict Detection, and Publication Activity

```mermaid
flowchart TD
    Start(["Admin Opens Schedule Form"]) --> EnterData["Select Subject, Instructor, Room, Section, Term, Day, Start/End Time & Status"]
    EnterData --> Submit["Submit POST /api/v1/schedules (or PATCH)"]
    Submit --> TimeValid{"start_time < end_time & Entities Active?"}
    TimeValid -- "No" --> Err422["Return 422 Validation Error"] --> ShowFormErr["Highlight Invalid Fields & Preserve Input"] --> EnterData
    TimeValid -- "Yes" --> BeginTx["Begin MySQL Transaction"]
    BeginTx --> CheckOverlap["Query Active Schedules in Same School Year, Semester & Day Where startA < endB AND startB < endA"]
    CheckOverlap --> HasConflict{"Same Room, Instructor, or Section Found?"}
    HasConflict -- "Yes" --> Rollback["Rollback Transaction"]
    Rollback --> Err409["Return 409 Conflict with Conflicting Resource Details"]
    Err409 --> ShowFormErr
    HasConflict -- "No" --> SaveSched["Insert / Update Schedule Record & Write Audit Log"]
    SaveSched --> IsPub{"Schedule status == 'published'?"}
    IsPub -- "No (Draft)" --> CommitDraft["Commit Transaction"] --> EndDraft(["Return Saved Draft Schedule"])
    IsPub -- "Yes (Published)" --> Notify["Insert In-App Notifications for Section Students & Assigned Instructor"]
    Notify --> CommitPub["Commit Transaction"]
    CommitPub --> EndPub(["Return Published Schedule & Trigger Optional SMTP Dispatch"])
```

---

### 2.3 Class-Notice Submission and Hybrid Approval Activity

```mermaid
flowchart TD
    Start(["Instructor Selects Assigned Class"]) --> FillNotice["Select Notice Type, Affected Date, Conditional Room/Time & Message"]
    FillNotice --> ConfirmDialog["Confirm Impact in Modal Dialog"]
    ConfirmDialog --> Submit["POST /api/v1/schedules/{id}/notices"]
    Submit --> PolicyCheck{"Instructor Assigned to schedule_id?"}
    PolicyCheck -- "No" --> Err403(["Return 403 Forbidden"])
    PolicyCheck -- "Yes" --> ValidateNotice{"Date Valid, No Duplicate Active Notice & New Room/Time Valid?"}
    ValidateNotice -- "No" --> Err422["Return 409 / 422 Validation Error"] --> FillNotice
    ValidateNotice -- "Yes" --> ModeCheck{"Notice Type?"}

    ModeCheck -- "unavailable or cancelled (Immediate)" --> PubNow["Save Notice with status = 'published' & Audit Log (In Transaction)"]
    PubNow --> NotifyStudents["Create In-App Notifications for Enrolled Section Students"]
    NotifyStudents --> EndPub(["Notice Live on Student & Instructor Schedules"])

    ModeCheck -- "room_change or rescheduled (Approval-Gated)" --> SavePending["Save Notice with status = 'pending_approval' & Notify Admins"]
    SavePending --> AdminReview{"Admin Reviews Notice"}
    AdminReview -- "Approve" --> ApproveTx["Update status = 'published', Write Audit Log & Notify Students + Instructor"]
    ApproveTx --> EndPub
    AdminReview -- "Reject (Reason Required)" --> RejectTx["Update status = 'rejected', Save rejection_reason & Notify Instructor"]
    RejectTx --> CanRevise(["Instructor Views Feedback & May Revise/Resubmit"])
```

---

## 3. Sequence Diagrams

### 3.1 Schedule Creation and Conflict Validation Sequence

```mermaid
sequenceDiagram
    actor Admin as Administrator
    participant UI as Vue 3 SPA
    participant API as PHP REST Controller
    participant Policy as AdminPolicy / AuthMiddleware
    participant UseCase as CreateScheduleUseCase
    participant Checker as ScheduleConflictChecker
    participant DB as MySQL (PDO)
    participant Notif as NotificationService

    Admin->>UI: Fill schedule form & click Save & Publish
    UI->>API: POST /api/v1/schedules (with X-CSRF-Token & session cookie)
    API->>Policy: verifyAuthenticatedAdmin(session, csrf)
    alt Unauthorized or Invalid CSRF
        Policy-->>API: Deny (401 / 403)
        API-->>UI: 403 Forbidden JSON
        UI-->>Admin: Show permission error
    else Authorized Admin
        Policy-->>API: Authorized (adminUserId)
        API->>UseCase: execute(CreateScheduleDTO)
        UseCase->>DB: beginTransaction()
        UseCase->>Checker: findConflicts(candidateSchedule)
        Checker->>DB: SELECT overlapping active schedules (same term + day)
        DB-->>Checker: Overlapping rows (room / instructor / section)
        alt Conflict Detected
            Checker-->>UseCase: ConflictList (non-empty)
            UseCase->>DB: rollBack()
            UseCase-->>API: Throw ScheduleConflictException(conflicts)
            API-->>UI: 409 Conflict { code: "SCHEDULE_CONFLICT", conflicts: [...] }
            UI-->>Admin: Highlight conflicting fields & show conflict details
        else No Conflicts
            Checker-->>UseCase: Empty ConflictList
            UseCase->>DB: INSERT INTO schedules (...)
            UseCase->>DB: INSERT INTO audit_logs (...)
            opt status == 'published'
                UseCase->>Notif: notifySchedulePublished(scheduleId, sectionId, instructorId)
                Notif->>DB: INSERT INTO notifications (per student + instructor)
            end
            UseCase->>DB: commit()
            UseCase-->>API: ScheduleDTO
            API-->>UI: 201 Created { data: ScheduleDTO }
            UI-->>Admin: Show success toast & updated schedule list
        end
    end
```

---

### 3.2 Class-Notice Submission, Approval, and Notification Sequence

```mermaid
sequenceDiagram
    actor Instructor as Instructor
    actor Admin as Administrator
    participant UI as Vue 3 SPA
    participant API as PHP REST Controller
    participant Policy as InstructorPolicy
    participant NoticeUC as SubmitClassNoticeUseCase
    participant ReviewUC as ReviewClassNoticeUseCase
    participant Notif as NotificationService
    participant DB as MySQL (PDO)

    Instructor->>UI: Select assigned schedule, choose notice_type & confirm
    UI->>API: POST /api/v1/schedules/{id}/notices
    API->>Policy: canManageSchedule(instructorId, scheduleId)
    Policy->>DB: SELECT instructor_id FROM schedules WHERE id = :id
    alt Instructor Not Assigned
        Policy-->>API: false
        API-->>UI: 403 Forbidden
        UI-->>Instructor: Display permission error
    else Instructor Assigned
        Policy-->>API: true
        API->>NoticeUC: execute(SubmitClassNoticeDTO)
        NoticeUC->>DB: beginTransaction()
        alt Immediate Notice (unavailable / cancelled)
            NoticeUC->>DB: INSERT INTO class_notices (status = 'published')
            NoticeUC->>Notif: notifySectionStudents(sectionId, notice)
            Notif->>DB: INSERT INTO notifications (recipient rows)
            NoticeUC->>DB: commit()
            NoticeUC-->>API: ClassNoticeDTO (status = 'published')
            API-->>UI: 201 Created
            UI-->>Instructor: Show published status
        else Approval-Gated Notice (room_change / rescheduled)
            NoticeUC->>DB: INSERT INTO class_notices (status = 'pending_approval')
            NoticeUC->>Notif: notifyAdmins(notice)
            Notif->>DB: INSERT INTO notifications (admin rows)
            NoticeUC->>DB: commit()
            NoticeUC-->>API: ClassNoticeDTO (status = 'pending_approval')
            API-->>UI: 201 Created
            UI-->>Instructor: Show Pending Approval status
            Admin->>UI: Open Pending Notice & click Approve (or Reject)
            UI->>API: POST /api/v1/class-notices/{noticeId}/approve
            API->>ReviewUC: approve(noticeId, adminUserId)
            ReviewUC->>DB: beginTransaction()
            ReviewUC->>DB: UPDATE class_notices SET status = 'published'
            ReviewUC->>DB: INSERT INTO audit_logs (...)
            ReviewUC->>Notif: notifySectionStudentsAndInstructor(sectionId, instructorUserId, notice)
            Notif->>DB: INSERT INTO notifications (...)
            ReviewUC->>DB: commit()
            ReviewUC-->>API: ClassNoticeDTO (status = 'published')
            API-->>UI: 200 OK
        end
    end
```

---

## 4. Component Diagram (Modular Monolith & SPA)

```mermaid
flowchart TB
    subgraph Frontend["Vue 3 + TypeScript + Vite SPA (frontend/)"]
        Router["Vue Router + Route Guards"]
        Features["Feature Modules (landing, auth, student, instructor, admin, schedules, announcements, class-notices, notifications)"]
        UIComp["Shared UI Components (Tailwind + shadcn-vue + FullCalendar)"]
        ApiClient["Typed HTTP Client (services/api/client.ts)"]
        Router --> Features
        Features --> UIComp
        Features --> ApiClient
    end

    subgraph Backend["Native PHP 8.x Modular Monolith (backend/)"]
        Entry["public/index.php + FastRoute + Middleware (Session, CSRF, RateLimit, ErrorHandler)"]
        subgraph Contexts["Bounded Contexts (src/)"]
            IAM["IdentityAccess (Auth, PasswordReset, RBAC Policies)"]
            Acad["Academic (Users, Students, Instructors, Subjects, Rooms, Sections)"]
            Sched["Scheduling (Schedule, ScheduleConflictChecker)"]
            Ann["Announcements (Announcement, AttachmentValidator)"]
            CN["ClassNotices (ClassNotice, NoticeApprovalService)"]
            NotifCtx["Notifications (NotificationService, ReminderGenerator)"]
            SharedCtx["Shared (Clock, Pagination, Exceptions, AuditLogger)"]
        end
        subgraph Infra["Infrastructure Adapters"]
            PDOAdapter["PDO MySQL Repositories"]
            MailAdapter["PHPMailer SMTP Adapter"]
            PdfAdapter["Dompdf Schedule PDF Generator"]
            StorageAdapter["Private Upload Storage (storage/uploads/)"]
        end
        CLI["CLI Reminder Worker (bin/generate-reminders.php)"]

        Entry --> IAM
        Entry --> Acad
        Entry --> Sched
        Entry --> Ann
        Entry --> CN
        Entry --> NotifCtx
        CLI --> NotifCtx
        IAM & Acad & Sched & Ann & CN & NotifCtx --> SharedCtx
        IAM & Acad & Sched & Ann & CN & NotifCtx --> PDOAdapter
        IAM & NotifCtx --> MailAdapter
        Sched --> PdfAdapter
        Ann --> StorageAdapter
    end

    ApiClient -- "HTTPS / JSON (/api/v1/*)" --> Entry
    PDOAdapter --> MySQL[("MySQL 8.4 Database")]
    MailAdapter --> SMTP["SMTP / Mailpit"]
```

---

## 5. Deployment Diagram

```mermaid
flowchart LR
    subgraph ClientDevice["User Device (Desktop / Tablet / Mobile)"]
        Browser["Modern Evergreen Browser (Vue 3 SPA + Offline PDF Viewer)"]
    end

    subgraph DockerHost["Docker Compose Environment (kampusly network)"]
        Nginx["nginx:1.27-alpine (Port 8080 -> 80)\nServes static assets & proxies /api/v1"]
        PhpFpm["php-fpm 8.x Container\nNative PHP REST API (/var/www/backend)"]
        Worker["PHP CLI Cron / Scheduler\n(php bin/generate-reminders.php)"]
        MySQL[("mysql:8.4 Container\nPersistent Volume: mysql-data")]
        Mailpit["axllent/mailpit\nSMTP :1025 / Web UI :8025 (Local Dev)"]
        PrivateVol[("backend/storage/uploads & logs\n(Outside public web root)")]
    end

    Browser -- "HTTP/HTTPS :8080" --> Nginx
    Nginx -- "FastCGI :9000" --> PhpFpm
    PhpFpm -- "PDO TCP :3306" --> MySQL
    PhpFpm -- "SMTP :1025 / :587" --> Mailpit
    PhpFpm -- "Read/Write" --> PrivateVol
    Worker -- "PDO TCP :3306" --> MySQL
    Worker -- "SMTP :1025 / :587" --> Mailpit
```

---

## 6. State Diagrams

### 6.1 Schedule Lifecycle State Diagram

```mermaid
stateDiagram-v2
    [*] --> draft : Admin saves draft (Conflict-checked)
    [*] --> published : Admin creates & publishes (Conflict-checked)
    draft --> published : Admin publishes (Notifies section & instructor)
    published --> published : Admin updates time/room (Conflict-checked + Notifies)
    draft --> cancelled : Admin cancels draft
    published --> cancelled : Admin cancels schedule (Frees slot + Notifies)
    cancelled --> [*]
```

### 6.2 Announcement Lifecycle State Diagram

```mermaid
stateDiagram-v2
    [*] --> draft : Instructor/Admin saves draft
    [*] --> published : Instructor/Admin publishes immediately
    draft --> published : Publish (Creates student notifications)
    published --> archived : Archive at end of topic/term
    published --> withdrawn : Withdraw erroneous post
    archived --> [*]
    withdrawn --> [*]
```

### 6.3 Class-Notice Lifecycle State Diagram

```mermaid
stateDiagram-v2
    [*] --> published : Instructor submits unavailable/cancelled (or Admin submits)
    [*] --> pending_approval : Instructor submits room_change/rescheduled
    pending_approval --> published : Admin approves (Notifies students + instructor)
    pending_approval --> rejected : Admin rejects with reason (Notifies instructor)
    pending_approval --> withdrawn : Instructor withdraws before review
    rejected --> pending_approval : Instructor revises & resubmits
    published --> withdrawn : Instructor/Admin withdraws (Notifies students class resumes)
    withdrawn --> [*]
```

