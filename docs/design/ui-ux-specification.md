# Kampusly — Phase 2: UI/UX Screen and Interaction Specification

**Project:** Kampusly: A Web-Based Class Schedule Management and Notification System  
**Document:** `docs/design/ui-ux-specification.md`  
**Version:** 1.0 • 28 October 2026  

---

## 1. Public Landing Page Specification (`/` — `LandingPage.vue`)

### 1.1 Purpose & Guardrails
The public landing page introduces Kampusly’s value proposition to students, instructors, and administrators and directs users to `/login`.
- **Truthfulness Rule:** Contains only accurate product capabilities (schedules, notifications, announcements, offline PDF export). No fabricated testimonials, fake campus statistics, or unapproved self-registration links.
- **Privacy Rule:** The schedule preview uses static, fictional sample data explicitly labeled *"Preview with sample data for illustration only."*

### 1.2 Section Hierarchy & Destinations

| Section | Component | Content & Interactive Behavior | Target Destination |
| :--- | :--- | :--- | :--- |
| **Skip Link** | `LandingPage.vue` | Visually hidden until focused (`Skip to main content`). | `#main-content` |
| **Sticky Header** | `LandingNavbar.vue` | Left: `KampuslyLogo` (`#top`). Center (desktop): `Features`, `How It Works`, `About` with scroll-spy active underline. Right: `ThemeToggle` + `Log In` button. Mobile (`< md`): `ThemeToggle` + `40×40` menu toggle opening an overlay dropdown panel with backdrop and `Escape` dismissal. | `#features`, `#how-it-works`, `#about`, `/login` |
| **Hero Section** | `HeroSection.vue` + `SchedulePreview.vue` | Eyebrow pill (`Smarter Campus Scheduling`), headline (`Your Campus. Your Classes. Your Time.`), supporting copy, primary CTA (`Access My Schedule`), secondary CTA (`Explore Features`). Centered on mobile/tablet (`< lg`), left-aligned 2-column split on desktop (`lg+`). Right column shows the illustrative `My Schedule · Today · Monday` card and floating `"Schedule updated"` toast. | Primary CTA $\rightarrow$ `/login`<br>Secondary CTA $\rightarrow$ `#features` |
| **Features Grid** | `FeatureGrid.vue` | 4-card responsive grid (`1 col` $\rightarrow$ `2 cols` $\rightarrow$ `4 cols`): (1) *Class Schedules*, (2) *Smart Notifications*, (3) *Class Announcements*, (4) *PDF Schedule Export*. | Anchor `#features` |
| **How It Works** | `HowItWorks.vue` | 3-step horizontal/vertical progression with numbered badges: `1. Access Kampusly` $\rightarrow$ `2. Check Your Schedule` $\rightarrow$ `3. Stay Updated`. | Anchor `#how-it-works` |
| **User Roles** | `UserRolesSection.vue` | 3 role cards highlighting tailored workflows for **Students**, **Instructors**, and **Administrators**. | Anchor `#about` |
| **Final CTA Banner** | `FinalCta.vue` | High-contrast navy card (`Make Every Class Count.`) with primary button `Access Kampusly`. | `/login` |
| **Footer** | `LandingFooter.vue` | Brand lockup, tagline, `Explore` anchor links, `Get started -> Log In` link, privacy usage notice, and copyright year. | `#features`, `#how-it-works`, `#about`, `/login` |

---

## 2. Authentication Pages Specification (`AuthLayout.vue`)

> **Provisioning Policy (`ASM-01` / `DEC-04`):** User accounts are provisioned by campus Administrators. Public self-registration (`/register`) is **not enabled** in the MVP; instead, the login card includes an informational note: *"Accounts are provisioned by your campus administrator."* External OAuth providers (e.g., Google Sign-In) are not included.

### 2.1 Shared `AuthLayout.vue` Structure
- **Header Controls:** Top-left `"Back to home"` link (`RouterLink to="/"` with left arrow) and top-right `ThemeToggle`.
- **Centered Auth Card:** `max-w-md w-full rounded-card border border-hairline bg-surface p-6 sm:p-8 shadow-card`.
- **Brand Header:** Stacked `KampuslyLogo` (`size="lg"`), screen title (`h1`), and concise subtitle.

### 2.2 Login Page (`/login` — `LoginPage.vue`)

| Field / Control | Type & Attributes | Validation & UX Rules |
| :--- | :--- | :--- |
| **Email Address** | `<input type="email" autocomplete="email" required>` | Label: `Campus Email *`. Validates non-empty and valid email format on blur/submit. |
| **Password** | `<input :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required>` | Label: `Password *` with right-aligned `Forgot password?` link (`/forgot-password`). Includes trailing eye/eye-off visibility toggle button (`aria-label="Show password"`). |
| **Submit Button** | `<BaseButton type="submit" variant="primary" size="lg" class="w-full">` | Label: `Sign In` (changes to spinner + `Signing in…` while request is in flight; inputs and button disabled during submission). |

**States & Error Handling:**
- **Invalid Credentials (`401`):** Displays an accessible top-of-form alert banner (`role="alert"`, red semantic token): *"Invalid email or password."* Password field is cleared and focused; email value is preserved.
- **Inactive Account (`403`):** Displays alert banner: *"Your account is currently inactive. Please contact your campus administrator."*
- **Rate Limited (`429`):** Displays alert banner: *"Too many sign-in attempts. Please wait a moment and try again."*
- **Expired Session Redirect (`?reason=session_expired`):** Displays an informational banner: *"Your session has expired. Please sign in again to continue."*
- **Success (`200`):** Redirects immediately to the role's default dashboard (`/student/dashboard`, `/instructor/dashboard`, or `/admin/dashboard`) or the validated internal `redirect` query path if permitted for that role.

### 2.3 Forgot Password Page (`/forgot-password` — `ForgotPasswordPage.vue`)
- **Fields:** `Campus Email *` (`type="email"`, `autocomplete="email"`, `required`).
- **Primary Action:** `Send Reset Link` (`POST /api/v1/auth/forgot-password`).
- **Secondary Action:** `Back to sign in` (`/login`).
- **Submission Behavior (Anti-Enumeration):** Regardless of whether the email exists in `users`, transitions to a calm confirmation state: *"If an active account matches **{{ email }}**, we have sent a password reset link. Please check your inbox (and spam folder). The link expires in 30 minutes."*

### 2.4 Reset Password Page (`/reset-password?token=...` — `ResetPasswordPage.vue`)
- **Fields:**
  1. `New Password *` (`type="password"`, `autocomplete="new-password"`, `minlength="8"`, required, with visibility toggle and helper text: *"Minimum 8 characters, including at least one letter and one number."*).
  2. `Confirm New Password *` (`type="password"`, `autocomplete="new-password"`, required; must match `New Password`).
- **Primary Action:** `Update Password` (`POST /api/v1/auth/reset-password` with `{ token, password, password_confirmation }`).
- **States:**
  - *Missing / Expired / Used Token (`400`/`422`):* Shows an error state with a button to `Request a new reset link` (`/forgot-password`).
  - *Success (`200`):* Shows confirmation banner *"Your password has been updated."* and a primary button `Sign in with new password` (`/login`).

---

## 3. Authenticated Application Shell & Role-Specific Dashboards

### 3.1 Shared Authenticated Shell (`StudentLayout.vue`, `InstructorLayout.vue`, `AdminLayout.vue`)
- **Desktop (`lg+`):**
  - **Left Sidebar (`w-64 border-r border-hairline bg-surface`):** Top `KampuslyLogo` + role badge pill (`Student`, `Instructor`, `Administrator`), vertical role-scoped navigation links with icons and active indicator, bottom user profile summary + `Sign Out` button.
  - **Top Bar (`h-16 border-b border-hairline bg-surface/85 backdrop-blur-md`):** Current page title/breadcrumb (left), active academic term badge (`1st Semester · AY 2026–2027`), unread notification bell button with counter badge (`NotificationDropdown`), and `ThemeToggle` (right).
- **Mobile & Tablet (`< lg`):**
  - Compact sticky top bar with hamburger menu button (opening an accessible slide-over navigation drawer with backdrop), `KampuslyLogo`, notification bell, and `ThemeToggle`.
  - For students on mobile (`< md`), quick bottom navigation tabs (`Dashboard`, `Schedule`, `Announcements`, `Notifications`) provide one-thumb access to core daily tasks.

---

### 3.2 Student Dashboard (`/student/dashboard`)

**Navigation Items:** `Dashboard`, `My Schedule`, `Announcements`, `Notifications`, `Download PDF` (quick action).

**Widgets & Page Hierarchy:**
1. **Welcome & Term Banner:** Greeting (`Good morning, {{ firstName }}`), Section badge (`BSCS 2A · 1st Semester AY 2026–2027`), and primary action button `Download Schedule PDF` (`GET /api/v1/my/schedule.pdf`).
2. **Hero Widget — Next Upcoming Class Card:**
   - Displays the next scheduled class for today (or next school day): Subject code & name, human-readable time range (`10:00 AM – 12:00 PM`), `Room 212`, `Dr. Santos`, and a relative time cue (`Starts in 28 mins` — accompanied by the exact clock time so meaning never depends on the countdown alone).
   - If an active `ClassNotice` exists for that session (`Cancelled`, `Instructor Unavailable`, `Room Change`, `Rescheduled`), renders a prominent semantic alert banner inside the card linking to the notice details.
3. **Summary Metric Strip (3 Cards):**
   - *Today's Classes:* Count of scheduled classes today (with count of any cancelled/rescheduled sessions).
   - *Unread Notifications:* Count of unread items (links to `/student/notifications`).
   - *New Announcements:* Count of published subject announcements this week (links to `/student/announcements`).
4. **Two-Column Main Grid (`lg:grid-cols-12`):**
   - **Left (`lg:col-span-7`) — Today's Timetable Preview:** Ordered list of today's classes with time, subject, room, instructor, status/notice badges, and a `View Full Schedule →` link (`/student/schedule`).
   - **Right (`lg:col-span-5`) — Recent Announcements & Class Notices:** Latest 5 published announcements and class notices for the student's section.
5. **Empty & Error States:**
   - *No classes today:* Calm illustration/icon with *"You have no classes scheduled for today."* and a button to `View Weekly Schedule`.
   - *API Failure:* Inline error card with a `Retry` button.

---

### 3.3 Instructor Dashboard (`/instructor/dashboard`)

**Navigation Items:** `Dashboard`, `Assigned Schedule`, `Announcements`, `Class Notices`, `Notifications`.

**Widgets & Page Hierarchy:**
1. **Header & Quick Actions:** Greeting (`Prof. {{ lastName }}`), department badge, and two primary quick-action buttons:
   - `Post Announcement` (opens Announcement creation modal/page pre-filtered to assigned subjects).
   - `Submit Class Notice` (opens Class Notice modal/page pre-filtered to assigned schedules).
2. **Summary Metric Strip (4 Cards):**
   - *Today's Assigned Classes*
   - *Assigned Subjects & Sections*
   - *Active / Pending Class Notices*
   - *Published Announcements*
3. **Two-Column Main Grid (`lg:grid-cols-12`):**
   - **Left (`lg:col-span-7`) — Today’s & Upcoming Teaching Schedule:**
     - Lists each assigned class with time, subject code/name, section (`BSCS 2A`), room (`Room 304`), current notice badge (if any), and an inline row action button `Report Notice / Change` plus `View Roster / Subject`.
   - **Right (`lg:col-span-5`) — Recent Class Notices & Status Tracker:**
     - Shows the instructor's recently submitted class notices with their live status badge (`Published`, `Pending Approval`, `Rejected` with expandable admin rejection feedback and `Revise & Resubmit` button, or `Withdrawn`).

---

### 3.4 Administrator Dashboard (`/admin/dashboard`)

**Navigation Items:** `Dashboard`, `Schedules`, `Class Notices` (with pending badge count), `Announcements`, `Students`, `Instructors`, `Subjects`, `Rooms`, `Sections`, `Users`.

**Widgets & Page Hierarchy:**
1. **Header & Quick Actions:** Operational overview header with quick actions: `+ Create Schedule`, `+ Add User`, and `Review Pending Notices`.
2. **8-Card Operational Metric Grid (`GET /api/v1/admin/dashboard-summary`):**
   - `Total Active Students`
   - `Total Active Instructors`
   - `Active Subjects`
   - `Active Rooms`
   - `Today's Classes`
   - `Upcoming Classes`
   - `Active Announcements`
   - `Cancelled / Pending Notice Classes`
3. **Two-Column Operational Workspace (`lg:grid-cols-12`):**
   - **Left (`lg:col-span-7`) — Pending Class-Notice Approvals & Today's Schedule Exceptions:**
     - Actionable queue of instructor-submitted notices (`room_change`, `rescheduled`, and recent `cancelled`/`unavailable` notices) with one-click `Inspect / Approve / Reject` actions.
   - **Right (`lg:col-span-5`) — Recent Administrative Activity (`audit_logs`):**
     - Chronological feed of recent schedule creations/publications, notice decisions, and account status changes.

---

## 4. Timetable and Academic Management Interfaces

### 4.1 Timetable Interface (`ScheduleCalendarView.vue` & `ScheduleListView.vue`)
- **View Modes:**
  1. **Accessible List / Day-Card View (Default on Mobile `< md`, Toggleable on Desktop):**
     - Day filter pills: `Today`, `All`, `Mon`, `Tue`, `Wed`, `Thu`, `Fri`, `Sat`.
     - Groups classes by day of week, sorted by `start_time`.
     - Each Schedule Card displays:
       - Time block (`08:00 AM – 10:00 AM`, `tabular-nums`)
       - Subject badge & title (`CS 201 — Data Structures`, `3 units`)
       - Metadata chips: `Room 304`, `Prof. Reyes`, `Section BSCS 2A`
       - Explicit schedule state badge (`Published`, `Draft`, or `Cancelled`)
       - If an active `ClassNotice` exists for the current/selected week: an inline notice banner (`Room Changed to Room 118 on Oct 30` or `Cancelled on Oct 30 — Prof. Reyes: Attending conference`) with a link to the full notice.
  2. **Visual Calendar View (`FullCalendar` `timeGridWeek` / `timeGridDay`, Default on Desktop `md+`):**
     - Time grid spanning campus hours (`07:00` to `21:00`) across `Monday`–`Saturday`.
     - Color-coded schedule blocks with high-contrast text, room label, and status icon so cancelled or room-changed classes are visually unmistakable without relying on color alone.
- **Role-Specific Filters:**
  - *Student:* Day filter (`Today`, `Mon`–`Sat`, `All`) + View mode toggle (`List` / `Calendar`) + `Download PDF` button.
  - *Instructor:* Day filter + Section/Subject filter (scoped to their assigned classes) + View mode toggle.
  - *Administrator (`/admin/schedules`):* Filter by `School Year`, `Semester`, `Section`, `Instructor`, `Room`, `Subject`, `Day of Week`, and `Status` (`all`, `published`, `draft`, `cancelled`).
- **Print & PDF Export Behavior (`GET /api/v1/my/schedule.pdf`):**
  - Generates a clean A4 landscape/portrait document with the Kampusly header, student full name, student number, program, section, academic term (`1st Semester, AY 2026–2027`), generation timestamp (`Asia/Manila`), and a bordered timetable table with clear page-break rules.

---

### 4.2 Academic Management CRUD Screens (`/admin/{users|students|instructors|subjects|rooms|sections|schedules}`)
Each administrative resource screen follows a consistent layout pattern:

1. **Toolbar:**
   - Debounced search input (`Search by code, name, or number…`), status filter dropdown (`All`, `Active`, `Inactive`), role/department/program filter where applicable, and primary `+ New {Entity}` button.
2. **Responsive Data Table (`TanStack Table` / Accessible Table + Mobile Card Fallback):**
   - Columns include key identifiers (e.g., `Subject Code`, `Subject Name`, `Units`, `Status`, `Actions`).
   - Server-side pagination footer (`Showing 1–20 of 142`, `Per page: 20 / 50 / 100`, `Previous / Next` buttons).
   - Row actions: `Edit`, `Activate / Deactivate` (opens confirmation dialog if deactivating an active record).
3. **Schedule Create / Edit Form & Conflict Resolution UI (`ScheduleFormModal.vue`):**
   - **Fields:** `School Year *`, `Semester *`, `Subject *`, `Section *`, `Instructor *`, `Room *`, `Day of Week *` (`Monday`–`Saturday`), `Start Time *` (`HH:MM`), `End Time *` (`HH:MM`), and `Status *` (`Draft` or `Published`).
   - **Interactive Conflict Detection & Resolution:**
     - When the Administrator clicks `Save as Draft` or `Save & Publish` (or triggers a live conflict preview check via `GET /api/v1/schedules/conflicts`), if the server returns `409 Conflict`:
     - An accessible **Conflict Resolution Alert Panel** (`role="alert"`) appears at the top of the form listing every overlap:
       - *"Room Conflict: **Room 304** is already assigned to **CS 201 (BSCS 2A)** on **Monday, 08:00 AM – 10:00 AM**."*
       - *"Instructor Conflict: **Prof. Reyes** is already assigned to **CS 201 (BSCS 2A)** on **Monday, 08:00 AM – 10:00 AM**."*
     - The conflicting form fields (`Room`, `Instructor`, or `Section`/`Time`) are highlighted in red (`aria-invalid="true"`), and all entered values remain intact so the Administrator can immediately pick another room, instructor, or time slot.

---

## 5. Notifications, Announcements, and Class-Notice Forms

### 5.1 Notification Center & Unread Indicators (`NotificationDropdown.vue` & `/notifications`)
- **Top Bar Bell Indicator:** Shows an unread count pill (e.g., `3`, or `9+`) when `unread_count > 0`.
- **Notification List Item Anatomy:**
  - Category icon badge:
    - `bell` (30-Minute Class Reminder)
    - `megaphone` (Subject Announcement)
    - `calendar` / alert (Class Notice: Cancellation, Unavailability, Room Change, Rescheduled)
  - Title, concise message summary, relative + exact timestamp (`10 mins ago · Oct 28, 9:30 AM`), unread dot indicator, and action link (`View Class` / `View Announcement`).
- **Interactions:**
  - Clicking an unread item calls `POST /api/v1/notifications/{id}/read`, decrements the unread badge immediately, and navigates to the referenced resource.
  - `Mark all as read` button calls `POST /api/v1/notifications/read-all`.

---

### 5.2 Subject Announcement Form (`AnnouncementFormModal.vue`)

| Field | Control Type | Validation Rules & Dependencies |
| :--- | :--- | :--- |
| **`subject_id` \*** | `<select>` | Required. For Instructors, populated strictly with subjects they are assigned to teach; for Admins, all active subjects. |
| **`section_id`** | `<select>` | Optional filter (`All assigned sections for this subject` by default, or a specific assigned section). |
| **`title` \*** | `<input type="text">` | Required; `5` to `150` characters. |
| **`message` \*** | `<textarea rows="5">` | Required; `10` to `5000` characters. Plain text / safe formatted lines (escaped on output). |
| **`attachment`** | `<input type="file">` | Optional; max `5 MB`; allow-listed MIME types: `application/pdf`, `image/png`, `image/jpeg`. |
| **`status` \*** | Action Buttons | `Save as Draft` (`status = 'draft'`) or `Publish Announcement` (`status = 'published'`). |

---

### 5.3 Class-Notice Submission Form (`ClassNoticeFormModal.vue`)

| Field | Control Type | Validation Rules & Field Dependencies |
| :--- | :--- | :--- |
| **`schedule_id` \*** | `<select>` | Required. Lists only the authenticated instructor's `published` schedules (showing `Subject Code · Section · Day · Time · Room`). |
| **`notice_type` \*** | Radio Cards / `<select>` | Required. Options: `unavailable` (*Instructor Unavailable*), `cancelled` (*Class Cancelled*), `room_change` (*Room Change*), `rescheduled` (*Rescheduled*). |
| **`affected_date` \*** | `<input type="date">` | Required. Must be today or a future date (`>= today` in `Asia/Manila`) whose weekday matches the selected schedule's `day_of_week`. |
| **`new_room_id` \*** | `<select>` | **Conditionally Required** when `notice_type === 'room_change'`. Hidden otherwise. Lists active rooms (excluding the schedule's current room). |
| **`new_date` / `new_start_time` / `new_end_time` \*** | Date + Time inputs | **Conditionally Required** when `notice_type === 'rescheduled'`. Validates `new_start_time < new_end_time`. |
| **`message` \*** | `<textarea rows="4">` | Required (`10` to `1000` chars). Explains the reason or instructions for students. |

**Submission & Confirmation Behavior:**
1. Clicking `Continue` validates all fields and opens a **Confirmation Step** summarizing:
   - Affected Class (`CS 201 · BSCS 2A`), Date (`Monday, Nov 2, 2026`), Notice Type, and Impact:
   - For `unavailable` / `cancelled`: *"This notice will be published immediately and notify all active students in BSCS 2A."*
   - For `room_change` / `rescheduled`: *"This request will be submitted to Administration for conflict verification and approval before students are notified."*
2. Instructor clicks `Confirm & Submit`.

---

### 5.4 Administrative Class-Notice Review Modal (`AdminNoticeReviewModal.vue`)
- Displays full notice context: Instructor name, original schedule (`Subject`, `Section`, `Day/Time`, `Room`), `notice_type`, `affected_date`, proposed `new_room_id` or `new_date`/`new_start_time`/`new_end_time`, and automated conflict check status for the proposed room/time.
- **Actions for `pending_approval` notices:**
  - **`Approve & Publish` (`primary`):** Sets `status = 'published'`, logs in `audit_logs`, and notifies both the instructor and all active students in the section.
  - **`Reject Notice` (`danger`):** Requires filling a mandatory `Rejection Reason *` textarea (`5` to `500` chars); sets `status = 'rejected'` and notifies the authoring instructor with the feedback so they can revise and resubmit.

