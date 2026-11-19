# Kampusly — Phase 2: Visual Identity and Design System Specification

**Project:** Kampusly: A Web-Based Class Schedule Management and Notification System  
**Tagline:** *“Your Campus. Your Classes. Your Time.”*  
**Document:** `docs/phase-2/phase-2-design-system.md`  
**Version:** 1.0 • 28 October 2026  

---

## 1. Brand Essence and Aesthetic Direction

Kampusly uses a **Modern Academic SaaS** aesthetic: calm, trustworthy, structured, and approachable. Because the primary user tasks involve scanning dense timetables, checking room assignments under time pressure, and reading urgent class-change notices, the visual system prioritizes:

1. **Clarity Over Decoration:** Crisp typographic hierarchy, high-contrast status badges, and generous whitespace rather than heavy gradients or ornamental animations.
2. **Role-Aware Calm:** Neutral slate backgrounds (`#F8FAFC` light / `#0F172A` dark) let schedule blocks, next-class highlights, and cancellation alerts stand out immediately.
3. **Non-Color Redundancy:** Every semantic color state (`Published`, `Draft`, `Cancelled`, `Room Change`, `Unavailable`, `Conflict`) pairs color tokens with explicit text labels and distinct iconography.
4. **Light Mode by Default, Persisted Dark Mode:** The application defaults to Light Mode on first visit (`<meta name="color-scheme" content="light">`) and persists explicit user theme toggles in `localStorage` (`kampusly-theme`).

---

## 2. Official Logo Direction and Usage Guidelines

### 2.1 Logo Anatomy
The official Kampusly brand identity consists of three visual elements that synthesize **Education**, **Scheduling**, and **Time**:

1. **The Kampusly Emblem (Icon Mark):**
   - **Graduation Mortarboard Cap:** Positioned atop the calendar in a Royal-to-Navy gradient (`#2563EB` $\rightarrow$ `#123B78`) with a hanging right-side tassel, representing higher education and campus life.
   - **Rounded Calendar Body & Binding Rings:** A blue gradient calendar frame housing a crisp white inner card with two upper binding rings.
   - **Geometric Monogram "K":** Anchors the left side of the calendar face in deep royal-to-navy blue.
   - **$3 \times 2$ Timetable Grid:** Six rounded schedule cells beside the "K" (`#BBD3F2` soft blue) with the middle-right cell highlighted in vibrant teal-green (`#10B981` / `#0D9488`) to signify an active scheduled class.
   - **Teal Clock Badge:** Overlapping the bottom-right corner in a teal gradient (`#14B8A6` $\rightarrow$ `#0D9488`) with white clock hands set to ~4:00, representing timely notifications and reminders.
2. **The Wordmark (`Kampusly`):**
   - Set in bold geometric sans-serif (`Inter` / `Geist Sans`, weight `700`/`800`, tight tracking `-0.025em`), colored in Primary Navy (`#123B78`) in Light Mode and pure White (`#FFFFFF`) in Dark Mode.
3. **The Tagline (`Your Campus. Your Classes. Your Time.`):**
   - Used in stacked hero/auth lockups below the wordmark in medium slate-navy (`#334155` / `#94A3B8`).

### 2.2 Approved Lockup Variants & Sizing

| Variant | Composition | Primary Placement | Minimum Size |
| :--- | :--- | :--- | :--- |
| **Horizontal Lockup** (`KampuslyLogo.vue`) | Emblem mark (left) + `Kampusly` wordmark (right), `gap-2.5` (`10px`) | Public navbar (`size="md"`, `36×36px` mark), Authenticated sidebar header, Footer | Mark `28×28px` (`size="sm"`) |
| **Stacked Full Lockup** | Emblem mark (top) + `Kampusly` wordmark (middle) + Tagline (bottom) | Authentication screens (`/login`, `/forgot-password`, `/reset-password`), PDF cover header | Mark `48×48px` (`size="lg"`) |
| **Icon-Only Mark** (`iconOnly` prop) | Emblem mark without text | Collapsed mobile/tablet navigation rail, browser favicon (`/favicon.svg`) | `24×24px` (`16×16px` simplified favicon) |

### 2.3 Clear Space and Misuse Rules
- **Clear Space:** Maintain a minimum padding around the logo equal to $0.25 \times$ the height of the emblem mark on all sides.
- **Do Not:**
  - Alter the proportions between the mortarboard cap, calendar body, and clock badge.
  - Recolor the emblem into single-color neon or low-contrast grays (except in high-contrast print stylesheets where a monochrome navy/black vector is permitted).
  - Place the Primary Navy wordmark (`#123B78`) directly on dark backgrounds (`#0F172A` / `#1E293B`) without switching the wordmark fill to `#FFFFFF`.

---

## 3. Color System and Accessibility Contrast

### 3.1 Core Brand & Theme-Aware Surface Tokens (`tokens.css` & `tailwind.config.ts`)

| Token Name | Tailwind Utility | Light Mode Value (Default) | Dark Mode Value (`.dark`) | Contrast Ratio & Usage |
| :--- | :--- | :--- | :--- | :--- |
| **Primary Navy** | `text-navy`, `bg-navy` | `#123B78` (`rgb(18 59 120)`) | `#123B78` (Text flips to `#FFFFFF`) | `10.8:1` on white; brand headings, primary navigation accents, CTA banner surface. |
| **Navy Scale** | `navy-50` / `100` / `600` / `800` / `900` | `#EEF3FB` / `#D7E3F4` / `#1B4B8F` / `#0F3263` / `#0C274D` | Same palette (paired with `/10`–`/40` alpha on dark surfaces) | Subtle hover tints, active nav backgrounds, hero gradients. |
| **Action Blue** | `bg-action`, `text-action`, `ring-action` | `#2563EB` (`rgb(37 99 235)`) | `#2563EB` (Icons/text on dark use `#60A5FA` `blue-400`) | `5.17:1` with `#FFFFFF` text; primary CTA buttons, focus rings, active indicators. |
| **Action Hover / Active** | `hover:bg-action-hover`, `active:bg-action-active` | Hover: `#1D4ED8` • Active: `#1E40AF` | Hover: `#1D4ED8` • Active: `#1E40AF` | Interactive button states. |
| **Teal Accent** | `text-accent`, `bg-accent` | `#0D9488` (`rgb(13 148 136)`) | `#0D9488` (Text on dark uses `#5EEAD4` `teal-300`) | Positive highlights, role badges, active schedule indicators. |
| **Teal Accent Soft** | `bg-accent-soft` | `#CCFBF1` (`teal-100`) | `rgb(13 148 136 / 0.15)` (`dark:bg-accent/15`) | Pill badges and secondary highlight backgrounds. |
| **Page Canvas** | `bg-canvas` (`--canvas`) | `#F8FAFC` (`248 250 252`, `slate-50`) | `#0F172A` (`15 23 42`, `slate-900`) | Root viewport and dashboard background surface. |
| **Card Surface** | `bg-surface` (`--surface`) | `#FFFFFF` (`255 255 255`, `white`) | `#1E293B` (`30 41 59`, `slate-800`) | Cards, tables, modals, navbar, form containers. |
| **Primary Ink** | `text-ink` (`--ink`) | `#0F172A` (`15 23 42`, `slate-900`) | `#F1F5F9` (`241 245 249`, `slate-100`) | `17.4:1` (light) / `13.6:1` (dark) on surface; body copy, table data, titles. |
| **Muted Text** | `text-muted` (`--muted`) | `#475569` (`71 85 105`, `slate-600`) | `#CBD5E1` (`203 213 225`, `slate-300`) | `7.58:1` (light) / `9.32:1` (dark) on surface; descriptions, secondary labels. |
| **Subtle Text** | `text-subtle` (`--subtle`) | `#64748B` (`100 116 139`, `slate-500`) | `#94A3B8` (`148 163 184`, `slate-400`) | `4.76:1` (light) / `5.62:1` (dark) on surface (passes WCAG AA 4.5:1); captions, timestamps. |
| **Hairline Border** | `border-hairline` (`--border`) | `#E2E8F0` (`226 232 240`, `slate-200`) | `#334155` (`51 65 85`, `slate-700`) | Card outlines, input borders, section dividers. |
| **Soft Border** | `border-hairline-soft` (`--border-soft`) | `#F1F5F9` (`241 245 249`, `slate-100`) | `#334155` (`51 65 85`, `slate-700`) | Internal table row and card header dividers. |

### 3.2 Semantic Status Tokens (Schedules, Notices, Validation & Alerts)
All semantic badges and alerts **must** include an icon + text label in addition to color:

| Semantic State | Use Cases | Light Mode (Bg / Border / Text) | Dark Mode (Bg / Border / Text) | Required Icon / Prefix |
| :--- | :--- | :--- | :--- | :--- |
| **Success / Published / Active** | Schedule `Published`, Account `Active`, Notice `Approved` | Bg: `#ECFDF5` (`emerald-50`)<br>Border: `#A7F3D0` (`emerald-200`)<br>Text: `#065F46` (`emerald-800`, `7.8:1`) | Bg: `rgba(16, 185, 129, 0.15)`<br>Border: `rgba(52, 211, 153, 0.3)`<br>Text: `#6EE7B7` (`emerald-300`, `8.9:1`) | Checkmark (`check`) or status dot + `"Published"` / `"Active"` |
| **Info / Rescheduled / Room Change** | Class Notice `Room Change` or `Rescheduled`, Next Class badge | Bg: `#EFF6FF` (`blue-50`)<br>Border: `#BFDBFE` (`blue-200`)<br>Text: `#1E40AF` (`blue-800`, `8.6:1`) | Bg: `rgba(37, 99, 235, 0.18)`<br>Border: `rgba(96, 165, 250, 0.35)`<br>Text: `#93C5FD` (`blue-300`, `8.1:1`) | Calendar/Bell icon + `"Room Change"` / `"Rescheduled"` |
| **Warning / Pending / Draft** | Schedule `Draft`, Class Notice `Pending Approval`, Unsaved changes | Bg: `#FFFBEB` (`amber-50`)<br>Border: `#FDE68A` (`amber-200`)<br>Text: `#92400E` (`amber-800`, `7.1:1`) | Bg: `rgba(245, 158, 11, 0.15)`<br>Border: `rgba(251, 191, 36, 0.35)`<br>Text: `#FCD34D` (`amber-300`, `10.2:1`) | Clock/Warning icon + `"Draft"` / `"Pending Approval"` |
| **Danger / Conflict / Cancelled** | Schedule `Conflict` (`409`), Class `Cancelled`, Instructor `Unavailable`, Form Error (`422`) | Bg: `#FEF2F2` (`red-50`)<br>Border: `#FECACA` (`red-200`)<br>Text: `#991B1B` (`red-800`, `8.2:1`) | Bg: `rgba(239, 68, 68, 0.16)`<br>Border: `rgba(248, 113, 113, 0.35)`<br>Text: `#FCA5A5` (`red-300`, `7.9:1`) | Alert/Close icon + `"Conflict"` / `"Cancelled"` / `"Unavailable"` |
| **Neutral / Archived / Withdrawn** | Schedule `Inactive`, Announcement `Archived`, Notice `Withdrawn` | Bg: `#F1F5F9` (`slate-100`)<br>Border: `#CBD5E1` (`slate-300`)<br>Text: `#334155` (`slate-700`, `9.8:1`) | Bg: `rgba(148, 163, 184, 0.12)`<br>Border: `rgba(148, 163, 184, 0.25)`<br>Text: `#CBD5E1` (`slate-300`, `9.3:1`) | Archive/Minus icon + `"Archived"` / `"Withdrawn"` |

---

## 4. Typography System

### 4.1 Font Families
- **Primary UI & Body (`font-sans`):** `'Inter', 'Geist Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif`
- **Tabular Numbers (`tabular-nums`):** Applied to all schedule times (`08:00–10:00`), countdown timers, student/employee numbers, room capacities, and dashboard metric counters so digits align vertically in tables and cards.

### 4.2 Type Scale

| Role / Element | Tailwind Classes | Size (`rem` / `px`) | Line Height | Weight | Tracking |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Hero Display (`h1` Landing)** | `text-4xl sm:text-5xl font-extrabold tracking-tight` | `2.25rem` (`36px`) / `3rem` (`48px`) | `1.15` | `800` | `-0.025em` |
| **Section Heading (`h2` Landing)** | `text-3xl sm:text-4xl font-bold tracking-tight` | `1.875rem` (`30px`) / `2.25rem` (`36px`) | `1.2` | `700` | `-0.02em` |
| **App Page Title (`h1` Dashboard)** | `text-2xl sm:text-3xl font-bold tracking-tight text-ink` | `1.5rem` (`24px`) / `1.875rem` (`30px`) | `1.25` | `700` | `-0.015em` |
| **Card / Modal Title (`h2` / `h3`)** | `text-lg font-semibold text-ink` | `1.125rem` (`18px`) | `1.4` | `600` | `-0.01em` |
| **Subheading / Table Header** | `text-sm font-semibold text-ink` | `0.875rem` (`14px`) | `1.4` | `600` | `0` |
| **Body Default** | `text-base font-normal text-ink leading-relaxed` | `1rem` (`16px`) | `1.625` | `400` | `0` |
| **Body Compact (Tables / Forms)** | `text-sm font-normal text-ink` | `0.875rem` (`14px`) | `1.5` | `400` | `0` |
| **Caption / Metadata** | `text-xs font-medium text-muted` | `0.75rem` (`12px`) | `1.4` | `500` | `0` |
| **Overline / Eyebrow Badge** | `text-xs font-bold uppercase tracking-wide` | `0.75rem` (`12px`) | `1.2` | `700` | `0.05em` |

---

## 5. Layout, Spacing, Grid, Radii, Shadows, and Iconography

### 5.1 Responsive Breakpoints and Grid
- **Breakpoints (Tailwind defaults):**
  - **Mobile base (`< 640px`):** Single-column stacked layout, collapsible top dropdown or bottom nav bar, horizontal scroll or card-stack fallback for tables, list-first schedule view. Minimum supported width: `360px` (tested at `360px`, `390px`, `440px`).
  - **`sm` (`640px+`):** 2-column metric cards, inline form button groups.
  - **`md` (`768px+`):** Desktop horizontal navigation bar on landing page; 2–3 column grids; collapsible sidebar in authenticated shell.
  - **`lg` (`1024px+`):** Persistent `256px` (`w-64`) left sidebar in authenticated shell + fluid main content area; 2-column hero split.
  - **`xl` (`1280px+`):** Max content container `max-w-content` (`1200px`) on public pages and `max-w-7xl` (`1280px`) on data-dense admin views.

### 5.2 Spacing, Border Radii, and Elevation
- **Spacing Scale:** 4px base unit (`0.25rem`). Standard section padding: `py-16 sm:py-24` (public), `p-4 sm:p-6 lg:p-8` (authenticated main canvas). Card padding: `p-5` (`20px`) or `p-6` (`24px`).
- **Border Radii:**
  - Cards & Modals (`rounded-card` / `--radius-card`): `1rem` (`16px`).
  - Schedule Rows & Inner Containers (`rounded-xl`): `0.75rem` (`12px`).
  - Buttons, Inputs, Selects (`rounded-lg`): `0.5rem` (`8px`).
  - Status Pills & Badges (`rounded-full`): `9999px`.
- **Elevation / Box Shadows (`tokens.css`):**
  - `shadow-card` (`--shadow-card`):
    - Light: `0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.04)`
    - Dark: `0 1px 3px rgba(0, 0, 0, 0.4), 0 1px 2px rgba(0, 0, 0, 0.3)`
  - `shadow-card-hover` (`--shadow-card-hover`):
    - Light: `0 10px 25px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.08)`
    - Dark: `0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.4)`

### 5.3 Iconography (`AppIcon.vue`)
- 24×24 stroke-based SVG icons (`stroke-width="1.8"`, `stroke-linecap="round"`, `stroke-linejoin="round"`).
- Decorative icons set `aria-hidden="true"`; standalone icon buttons (such as `ThemeToggle` or action buttons) provide an explicit `aria-label`.
- Standard sizes: `h-4 w-4` (`16px` inline button/badge icon), `h-5 w-5` (`20px` nav/control icon), `h-6 w-6` (`24px` card feature icon).

---

## 6. Reusable Component Specifications (Tailwind CSS + `shadcn-vue`)

### 6.1 Buttons (`BaseButton.vue` / `components/ui/button`)
- **Variants:**
  - `primary`: `bg-action text-white shadow-sm hover:bg-action-hover active:bg-action-active`
  - `secondary`: `border border-hairline bg-surface text-navy hover:bg-navy-50 active:bg-navy-100 dark:text-white dark:hover:bg-white/10`
  - `ghost`: `text-navy hover:bg-navy-50 dark:text-white dark:hover:bg-white/10`
  - `danger` *(for destructive confirmations such as Cancel Schedule / Reject Notice)*: `bg-red-600 text-white hover:bg-red-700 active:bg-red-800`
- **Sizes:** `sm` (`px-3.5 py-2 text-xs`), `md` (`px-5 py-2.5 text-sm`), `lg` (`px-6 py-3 text-base`).
- **States:**
  - *Focus:* `focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-canvas`
  - *Disabled / Loading:* `disabled:cursor-not-allowed disabled:opacity-60`, inline spinner icon when submitting.

### 6.2 Form Inputs, Selects, Textareas, and Checkboxes (`components/forms/`)
- **Anatomy:**
  1. Visible `<label :for="id" class="block text-sm font-medium text-ink">` with `<span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>` on required fields.
  2. Control surface: `w-full rounded-lg border border-hairline bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-subtle focus:border-action focus:outline-none focus:ring-2 focus:ring-action/20 disabled:bg-canvas disabled:opacity-60`.
  3. Validation error state: `border-red-500 focus:border-red-500 focus:ring-red-500/20`, `aria-invalid="true"`, `:aria-describedby="errorId"`.
  4. Helper / Error text: `<p :id="errorId" role="alert" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">` with an inline warning icon.
- **Password Input:** Includes a trailing toggle button (`type="button"`, `aria-label="Show password"` / `"Hide password"`) with `eye` / `eye-off` icon.

### 6.3 Cards, Badges, Modals, Dropdowns, and Tooltips
- **Cards (`components/ui/card`):** `rounded-card border border-hairline bg-surface p-6 shadow-card`.
- **Status Badges (`components/ui/badge`):** `inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold` using the semantic status token palette (Section 3.2).
- **Modals / Confirmation Dialogs (`components/ui/dialog`):**
  - Rendered using native `<dialog>` or accessible `shadcn-vue` Dialog primitive with focus trapping, `Escape` dismissal, backdrop (`bg-slate-900/50 backdrop-blur-xs`), `aria-labelledby`, and `aria-describedby`.
  - Required for all high-impact actions: cancelling a schedule, submitting an instructor unavailability/cancellation notice, rejecting a notice, or deactivating a user account.
- **Dropdowns & Tooltips:** Keyboard-navigable (`ArrowUp`/`ArrowDown`/`Enter`/`Escape`), anchored with collision awareness, `rounded-xl border border-hairline bg-surface p-1.5 shadow-card-hover`.

---

## 7. Branding Consistency Across Public vs. Authenticated Surfaces

| Dimension | Public Pages (`/`, `/login`, `/forgot-password`, `/reset-password`) | Authenticated Application (`/student/*`, `/instructor/*`, `/admin/*`) |
| :--- | :--- | :--- |
| **Navigation Shell** | Top sticky marketing navbar (`LandingNavbar.vue`) or centered auth card (`AuthLayout.vue`). | Persistent left sidebar (`lg:w-64`) + compact top bar with breadcrumbs, unread notification bell, theme toggle, and user role menu. |
| **Visual Density** | Spacious marketing rhythm (`py-16 sm:py-24`), large display headings, illustrative preview cards. | Task-focused workspace density (`p-4 sm:p-6`), scannable schedule cards/tables, immediate filter bars and action buttons. |
| **Data Privacy Boundary** | Strictly static/fictional demo data labeled *"Preview with sample data for illustration only."* Zero API calls to schedule or student endpoints. | Strictly authenticated session data enforced by `/api/v1` RBAC and resource-ownership policies. |

