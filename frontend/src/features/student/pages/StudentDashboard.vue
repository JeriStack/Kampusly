<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import NotificationCenter from '@/components/common/NotificationCenter.vue'
import UserSessionPopover from '@/components/common/UserSessionPopover.vue'
import FormattedText from '@/components/common/FormattedText.vue'
import { apiClient } from '@/services/api/client'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { ApiError } from '@/services/api/errors'
import type { Schedule, Section } from '@/features/academic/types'

const router = useRouter()
const route = useRoute()
const { user, logout } = useAuth()

interface StudentScheduleResponse {
  data: {
    section: Section
    schedules: Schedule[]
    schedule_change_window: {
      starts_on: string | null
      deadline: string | null
      is_open: boolean
      days_remaining: number
    }
  }
}

interface ScheduleChangeRequest {
  id: number
  status: 'pending' | 'approved' | 'rejected'
  reason: string
  review_note: string | null
  subject_code: string
  section_name: string
  current_day_of_week: string
  current_start_time: string
  current_end_time: string
  preferred_day_of_week: string | null
  preferred_start_time: string | null
  preferred_end_time: string | null
  created_at: string
}

interface Announcement {
  id: number
  title: string
  message: string
  subject_id: number
  subject_code: string | null
  subject_name: string | null
  author_name: string | null
  published_at: string | null
  archived_at?: string | null
}

const schedules = ref<Schedule[]>([])
const section = ref<Section | null>(null)
const scheduleChangeWindow = ref<StudentScheduleResponse['data']['schedule_change_window']>({
  starts_on: null,
  deadline: null,
  is_open: false,
  days_remaining: 0,
})
const requests = ref<ScheduleChangeRequest[]>([])
const announcements = ref<Announcement[]>([])
const archivedAnnouncements = ref<Announcement[]>([])
const announcementTab = ref<'inbox' | 'archived'>('inbox')
const expandedAnnouncements = ref<number[]>([])
const busyAnnouncementId = ref<number | null>(null)
const announcementActionError = ref<string | null>(null)
const highlightedAnnouncementId = ref<number | null>(null)
let highlightTimer: ReturnType<typeof setTimeout> | undefined
const isSubmittingRequest = ref(false)
const requestError = ref<string | null>(null)
const requestSuccess = ref<string | null>(null)
const extrasError = ref<string | null>(null)
const requestForm = ref({
  schedule_id: 0,
  preferred_day_of_week: '',
  preferred_start_time: '',
  preferred_end_time: '',
  reason: '',
})
const isScheduleLoading = ref(true)
const scheduleError = ref<string | null>(null)
const today = new Intl.DateTimeFormat('en-US', { weekday: 'long' }).format(new Date())
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
const expandedDays = ref<string[]>([today])
const isAnnouncementsOpen = ref(false)
const isChangeRequestOpen = ref(false)

function toggleDay(day: string): void {
  expandedDays.value = expandedDays.value.includes(day)
    ? expandedDays.value.filter((expanded) => expanded !== day)
    : [...expandedDays.value, day]
}
const schedulesByDay = computed(() =>
  days
    .map((day) => ({
      day,
      entries: schedules.value.filter((schedule) => schedule.day_of_week === day),
    }))
    .filter((group) => group.entries.length > 0),
)
const hasDraftSchedules = computed(() =>
  schedules.value.some((schedule) => schedule.status === 'draft'),
)
const hasProvisionalInstructors = computed(() =>
  schedules.value.some((schedule) => schedule.instructor_name?.startsWith('Temporary ')),
)
const publishedSchedules = computed(() =>
  schedules.value.filter((schedule) => schedule.status === 'published'),
)
const canRequestChange = computed(() => scheduleChangeWindow.value.is_open)
const selectedRequestSchedule = computed(() =>
  publishedSchedules.value.find((schedule) => schedule.id === requestForm.value.schedule_id),
)
let updatesTimer: ReturnType<typeof setInterval> | undefined
let announcementsTimer: ReturnType<typeof setInterval> | undefined
let isPollingAnnouncements = false

function instructorLabel(schedule: Schedule): string {
  const name = schedule.instructor_name
  return !name || name.startsWith('Temporary ') ? 'Instructor to be confirmed' : name
}

async function loadSchedule(): Promise<void> {
  isScheduleLoading.value = true
  scheduleError.value = null

  try {
    const response = await apiClient.get<StudentScheduleResponse>(API_ENDPOINTS.student.schedule)
    section.value = response.data.section
    schedules.value = response.data.schedules
    scheduleChangeWindow.value = response.data.schedule_change_window
    if (requestForm.value.schedule_id === 0) {
      requestForm.value.schedule_id = publishedSchedules.value[0]?.id ?? 0
    }
  } catch (err) {
    scheduleError.value =
      err instanceof ApiError ? err.message : 'Unable to load your class schedule.'
  } finally {
    isScheduleLoading.value = false
  }

  await loadStudentUpdates()
}

async function loadAnnouncements(): Promise<void> {
  const [activeResponse, archivedResponse] = await Promise.all([
    apiClient.get<{ data: Announcement[] }>(
      `${API_ENDPOINTS.student.announcements}?page=1&per_page=100`,
    ),
    apiClient.get<{ data: Announcement[] }>(
      `${API_ENDPOINTS.student.announcements}?page=1&per_page=100&archived=1`,
    ),
  ])
  announcements.value = activeResponse.data
  archivedAnnouncements.value = archivedResponse.data
}

async function loadStudentUpdates(): Promise<void> {
  try {
    const [requestResponse] = await Promise.all([
      apiClient.get<{ data: ScheduleChangeRequest[] }>(
        `${API_ENDPOINTS.student.scheduleChangeRequests}?page=1&per_page=100`,
      ),
      loadAnnouncements(),
    ])
    requests.value = requestResponse.data
    extrasError.value = null
  } catch (err) {
    extrasError.value = err instanceof ApiError ? err.message : 'Unable to load announcements or request history.'
  }
}

/**
 * Real-time announcement refresh. Runs only while the tab is visible and keeps the current
 * list when a poll fails, so a brief network blip does not replace the card with an error.
 */
async function pollAnnouncements(): Promise<void> {
  if (document.visibilityState !== 'visible' || isPollingAnnouncements) return
  isPollingAnnouncements = true
  try {
    await loadAnnouncements()
    extrasError.value = null
  } catch {
    // Keep showing the last known announcements; the next poll will retry.
  } finally {
    isPollingAnnouncements = false
  }
}

function handleVisibilityChange(): void {
  if (document.visibilityState === 'visible') void pollAnnouncements()
}

const ANNOUNCEMENT_POLL_MS = 5_000
const manilaDateTime = new Intl.DateTimeFormat('en-PH', {
  timeZone: 'Asia/Manila',
  year: 'numeric',
  month: 'short',
  day: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
})

/** The API returns server time (Asia/Manila) as `YYYY-MM-DD HH:MM:SS` without an offset. */
function formatAnnouncedAt(value: string | null): string {
  if (!value) return ''
  const date = new Date(`${value.replace(' ', 'T')}+08:00`)
  return Number.isNaN(date.getTime()) ? value : manilaDateTime.format(date)
}

const visibleAnnouncements = computed(() =>
  announcementTab.value === 'inbox' ? announcements.value : archivedAnnouncements.value,
)

function announcementSubject(announcement: Announcement): string {
  if (!announcement.subject_code) return announcement.subject_name || 'Subject'
  return announcement.subject_name
    ? `${announcement.subject_code} — ${announcement.subject_name}`
    : announcement.subject_code
}

/**
 * Opens the announcement referenced by `?announcement=<id>` (set when a student clicks an
 * announcement notification): opens the card, picks the right tab, expands it and scrolls to it.
 */
async function focusAnnouncementFromRoute(): Promise<void> {
  const raw = Array.isArray(route.query.announcement)
    ? route.query.announcement[0]
    : route.query.announcement
  const id = Number(raw)
  if (!Number.isInteger(id) || id <= 0) return

  isAnnouncementsOpen.value = true
  const inInbox = announcements.value.some((announcement) => announcement.id === id)
  const inArchive = archivedAnnouncements.value.some((announcement) => announcement.id === id)
  announcementTab.value = !inInbox && inArchive ? 'archived' : 'inbox'
  if (inInbox || inArchive) {
    if (!expandedAnnouncements.value.includes(id)) {
      expandedAnnouncements.value = [...expandedAnnouncements.value, id]
    }
    highlightedAnnouncementId.value = id
    if (highlightTimer) clearTimeout(highlightTimer)
    highlightTimer = setTimeout(() => {
      highlightedAnnouncementId.value = null
    }, 3000)
  }

  await nextTick()
  document.getElementById(`announcement-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  // Drop the query so a page refresh does not re-trigger the focus.
  await router.replace({ name: 'student-dashboard' })
}

watch(
  () => route.query.announcement,
  async (value) => {
    if (!value) return
    await loadStudentUpdates()
    await focusAnnouncementFromRoute()
  },
)

function toggleAnnouncement(id: number): void {
  expandedAnnouncements.value = expandedAnnouncements.value.includes(id)
    ? expandedAnnouncements.value.filter((expandedId) => expandedId !== id)
    : [...expandedAnnouncements.value, id]
}

async function setAnnouncementArchived(announcement: Announcement, archive: boolean): Promise<void> {
  busyAnnouncementId.value = announcement.id
  announcementActionError.value = null
  try {
    const path = API_ENDPOINTS.student.announcementArchive(announcement.id)
    if (archive) {
      await apiClient.post(path)
    } else {
      await apiClient.delete(path)
    }
    await loadStudentUpdates()
  } catch (err) {
    announcementActionError.value =
      err instanceof ApiError
        ? err.message
        : `Unable to ${archive ? 'archive' : 'restore'} the announcement.`
  } finally {
    busyAnnouncementId.value = null
  }
}

async function submitScheduleChangeRequest(): Promise<void> {
  if (!canRequestChange.value) return
  if (!requestForm.value.preferred_day_of_week && !requestForm.value.preferred_start_time) {
    requestError.value = 'Choose a preferred day or provide a preferred start and end time.'
    return
  }
  if (Boolean(requestForm.value.preferred_start_time) !== Boolean(requestForm.value.preferred_end_time)) {
    requestError.value = 'Provide both a preferred start time and end time.'
    return
  }
  isSubmittingRequest.value = true
  requestError.value = null
  requestSuccess.value = null
  try {
    const payload = {
      schedule_id: requestForm.value.schedule_id,
      preferred_day_of_week: requestForm.value.preferred_day_of_week || undefined,
      preferred_start_time: requestForm.value.preferred_start_time || undefined,
      preferred_end_time: requestForm.value.preferred_end_time || undefined,
      reason: requestForm.value.reason.trim(),
    }
    const response = await apiClient.post<{ data: ScheduleChangeRequest }>(
      API_ENDPOINTS.student.scheduleChangeRequests,
      payload,
    )
    requests.value = [response.data, ...requests.value]
    requestSuccess.value = 'Your schedule change request was submitted.'
    requestForm.value.preferred_day_of_week = ''
    requestForm.value.preferred_start_time = ''
    requestForm.value.preferred_end_time = ''
    requestForm.value.reason = ''
  } catch (err) {
    requestError.value = err instanceof ApiError ? err.message : 'Unable to submit your schedule change request.'
  } finally {
    isSubmittingRequest.value = false
  }
}
async function handleLogout(): Promise<void> {
  await logout()
  await router.push('/login')
}

onMounted(async () => {
  await loadSchedule()
  await focusAnnouncementFromRoute()
  updatesTimer = setInterval(() => void loadStudentUpdates(), 30_000)
  announcementsTimer = setInterval(() => void pollAnnouncements(), ANNOUNCEMENT_POLL_MS)
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onBeforeUnmount(() => {
  if (updatesTimer) clearInterval(updatesTimer)
  if (announcementsTimer) clearInterval(announcementsTimer)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  if (highlightTimer) clearTimeout(highlightTimer)
})
</script>

<template>
  <div class="dashboard-readable min-h-screen bg-canvas text-ink">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-20 border-b border-hairline bg-surface/95 backdrop-blur-md">
      <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
          <KampuslyLogo size="sm" />
          <span class="hidden sm:inline-flex items-center rounded-full border border-navy/15 bg-navy-50 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-navy-800 dark:border-sky-400/25 dark:bg-sky-500/15 dark:text-sky-200">
            Student Portal
          </span>
        </div>
        <div class="flex items-center gap-2.5">
          <NotificationCenter />
          <ThemeToggle />
          <div aria-hidden="true" class="hidden h-6 w-px bg-hairline sm:block" />
          <UserSessionPopover
            :subtitle="`${user?.profile?.program || 'BSIT'} • ${user?.profile?.section_name || 'Section'}`"
          />
          <button
            type="button"
            aria-label="Sign out"
            title="Sign out"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-hairline bg-surface text-rose-600 transition-colors hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:border-rose-500/40 dark:hover:bg-rose-950/50 dark:hover:text-rose-300"
            @click="handleLogout"
          >
            <AppIcon name="logout" class="h-5 w-5" />
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div class="mb-7">
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
          {{ user?.name }}
        </h1>
        <p class="mt-1 text-sm text-muted">
          Student Workspace &bull; Enrolled Timetable &amp; Updates
        </p>
      </div>

      <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
        <!-- Student Information Card -->
        <section class="overflow-hidden rounded-2xl border border-hairline bg-surface shadow-card lg:col-span-4">
          <div class="h-1 w-full bg-gradient-to-r from-navy via-action to-accent" />
          <div class="p-5 sm:p-6">
            <div class="flex items-center gap-3 border-b border-hairline pb-4">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300">
                <AppIcon name="student" class="h-5 w-5" />
              </span>
              <div class="min-w-0">
                <h2 class="text-base font-bold text-ink">Student Profile</h2>
                <p class="truncate text-xs text-subtle">Enrolled academic profile</p>
              </div>
            </div>

            <dl class="mt-4 space-y-3.5 text-xs">
              <div class="flex items-center justify-between gap-3 border-b border-hairline/70 pb-3">
                <dt class="text-muted">Student Number</dt>
                <dd class="rounded-md border border-hairline bg-canvas px-2.5 py-0.5 font-mono font-semibold text-ink">
                  {{ user?.profile?.student_number || '2026-0001' }}
                </dd>
              </div>

              <div class="flex items-center justify-between gap-3 border-b border-hairline/70 pb-3">
                <dt class="text-muted">Assigned Section</dt>
                <dd class="rounded-full border border-sky-500/25 bg-sky-500/10 px-2.5 py-0.5 font-semibold text-navy dark:text-sky-300">
                  {{ user?.profile?.section_name || 'BSIT-3A' }}
                </dd>
              </div>

              <div class="border-b border-hairline/70 pb-3">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-subtle">Program</dt>
                <dd class="mt-1 font-semibold text-ink">
                  {{ user?.profile?.program || 'BS Information Technology' }}
                </dd>
              </div>

              <div class="pt-0.5">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-subtle">Institutional Email</dt>
                <dd class="mt-1 break-words font-medium text-ink">
                  {{ user?.email }}
                </dd>
              </div>
            </dl>
          </div>
        </section>

        <!-- Student Schedule View -->
        <section class="overflow-hidden rounded-2xl border border-hairline bg-surface shadow-card lg:col-span-8">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-hairline px-5 py-4 sm:px-6">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300">
                <AppIcon name="calendar" class="h-5 w-5" />
              </span>
              <div>
                <div class="flex items-center gap-2">
                  <h2 class="text-base font-bold text-ink">Enrolled Section Schedule</h2>
                  <span class="inline-flex items-center rounded-full border border-sky-500/20 bg-sky-500/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                    {{ schedules.length }}
                  </span>
                </div>
                <p class="mt-0.5 text-xs text-subtle">
                  Automatically filtered to your assigned section ({{ user?.profile?.section_name || 'BSIT-3A' }}).
                </p>
              </div>
            </div>
          </div>

          <div class="p-5 sm:p-6">
            <div
              v-if="hasDraftSchedules || hasProvisionalInstructors"
              class="mb-4 rounded-xl border border-amber-300/70 bg-amber-50/90 px-3.5 py-2.5 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-200"
              role="status"
            >
              <template v-if="hasDraftSchedules">
                This schedule contains draft meetings and is not yet fully confirmed.
              </template>
              <template v-else>
                This schedule is published, but some instructor assignments are provisional. Please check with the administrator.
              </template>
            </div>

            <div v-if="isScheduleLoading" class="space-y-2.5" role="status">
              <div v-for="n in 2" :key="n" class="h-14 animate-pulse rounded-xl bg-canvas" />
            </div>

            <div
              v-else-if="scheduleError"
              class="rounded-xl border border-rose-200 bg-rose-50/90 p-4 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200"
              role="alert"
            >
              <p>{{ scheduleError }}</p>
              <button type="button" class="mt-2 font-semibold underline" @click="loadSchedule">
                Try again
              </button>
            </div>

            <div
              v-else-if="schedules.length === 0"
              class="flex flex-col items-center justify-center rounded-xl border border-dashed border-hairline bg-canvas/50 px-6 py-10 text-center"
            >
              <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface text-subtle ring-1 ring-hairline">
                <AppIcon name="clock" class="h-5 w-5" />
              </span>
              <div class="mt-3 text-sm font-semibold text-ink">No classes scheduled yet</div>
              <p class="mt-1 text-xs text-subtle">
                Your section schedule will appear here when classes are added.
              </p>
            </div>

            <div v-else class="space-y-3">
              <section
                v-for="group in schedulesByDay"
                :key="group.day"
                class="overflow-hidden rounded-xl border border-hairline"
                :aria-label="`${group.day}${group.day === today ? ', today' : ''} classes`"
              >
                <h3>
                  <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 bg-canvas/70 px-4 py-3 text-left text-xs font-bold text-ink transition-colors hover:bg-canvas"
                    :aria-expanded="expandedDays.includes(group.day)"
                    @click="toggleDay(group.day)"
                  >
                    <span class="flex items-center gap-2">
                      <AppIcon
                        name="chevron-right"
                        class="h-3.5 w-3.5 text-subtle transition-transform"
                        :class="{ 'rotate-90': expandedDays.includes(group.day) }"
                      />
                      <span>{{ group.day }}</span>
                      <span
                        v-if="group.day === today"
                        class="rounded-full border border-sky-500/25 bg-sky-500/15 px-2 py-0.5 text-[10px] font-semibold text-navy dark:text-sky-200"
                      >
                        Today
                      </span>
                    </span>
                    <span class="rounded-full border border-hairline bg-surface px-2.5 py-0.5 text-[11px] font-semibold tabular-nums text-muted">
                      {{ group.entries.length }} {{ group.entries.length === 1 ? 'class' : 'classes' }}
                    </span>
                  </button>
                </h3>
                <ul v-show="expandedDays.includes(group.day)" class="divide-y divide-hairline/70">
                  <li
                    v-for="schedule in group.entries"
                    :key="schedule.id"
                    class="flex flex-col gap-2.5 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between"
                  >
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-md border border-hairline bg-canvas px-2 py-0.5 font-mono text-xs font-bold text-ink">
                          {{ schedule.subject_code }}
                        </span>
                        <p class="text-sm font-semibold text-ink">{{ schedule.subject_name }}</p>
                      </div>
                      <p class="mt-1.5 text-xs text-muted">
                        {{ schedule.room_code }} &middot; {{ instructorLabel(schedule) }}
                      </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 sm:flex-col sm:items-end sm:gap-1.5">
                      <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-500/20 bg-sky-500/10 px-2.5 py-0.5 text-xs font-semibold tabular-nums text-navy dark:text-sky-300">
                        <AppIcon name="clock" class="h-3.5 w-3.5" />
                        {{ schedule.start_time.slice(0, 5) }}–{{ schedule.end_time.slice(0, 5) }}
                      </span>
                      <span
                        class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold capitalize"
                        :class="{
                          'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300': schedule.status === 'draft',
                          'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300': schedule.status === 'published',
                        }"
                      >
                        {{
                          schedule.status === 'draft'
                            ? 'Draft · unconfirmed'
                            : schedule.instructor_name?.startsWith('Temporary ')
                              ? 'Published · instructor provisional'
                              : 'Published'
                        }}
                      </span>
                    </div>
                  </li>
                </ul>
              </section>
            </div>
          </div>
        </section>
      </div>

      <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
        <!-- Class Announcements -->
        <section class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="flex items-center justify-between gap-3">
            <h2 class="min-w-0 flex-1">
              <button
                type="button"
                class="flex w-full items-center gap-3 text-left"
                :aria-expanded="isAnnouncementsOpen"
                @click="isAnnouncementsOpen = !isAnnouncementsOpen"
              >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300">
                  <AppIcon name="megaphone" class="h-5 w-5" />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="flex items-center gap-1.5 text-base font-bold text-ink">
                    <span>Class announcements</span>
                    <AppIcon
                      name="chevron-right"
                      class="h-4 w-4 shrink-0 text-subtle transition-transform"
                      :class="{ 'rotate-90': isAnnouncementsOpen }"
                    />
                  </span>
                  <span class="mt-0.5 block text-xs font-normal text-subtle">
                    Updates for {{ section?.section_name || 'your section' }}
                  </span>
                </span>
              </button>
            </h2>
            <span
              v-if="announcements.length"
              class="inline-flex items-center rounded-full border border-teal-500/25 bg-teal-500/10 px-2.5 py-0.5 text-xs font-semibold tabular-nums text-teal-700 dark:text-teal-300"
            >
              {{ announcements.length }}
            </span>
          </div>
          <div v-show="isAnnouncementsOpen" class="mt-4 border-t border-hairline pt-4">
            <p v-if="extrasError" class="text-xs text-rose-600 dark:text-rose-400" role="alert">{{ extrasError }}</p>
            <template v-else>
              <div class="flex gap-1 rounded-xl bg-canvas p-1 text-xs font-semibold" role="tablist">
                <button
                  type="button"
                  role="tab"
                  class="flex-1 rounded-lg px-3 py-1.5 transition-colors"
                  :class="announcementTab === 'inbox' ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink'"
                  :aria-selected="announcementTab === 'inbox'"
                  @click="announcementTab = 'inbox'"
                >
                  Inbox ({{ announcements.length }})
                </button>
                <button
                  type="button"
                  role="tab"
                  class="flex-1 rounded-lg px-3 py-1.5 transition-colors"
                  :class="announcementTab === 'archived' ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink'"
                  :aria-selected="announcementTab === 'archived'"
                  @click="announcementTab = 'archived'"
                >
                  Archived ({{ archivedAnnouncements.length }})
                </button>
              </div>
              <p v-if="announcementTab === 'archived'" class="mt-3 text-[11px] text-subtle">
                Archived announcements are deleted automatically after 7 days.
              </p>
              <p v-if="announcementActionError" class="mt-3 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ announcementActionError }}</p>

              <ul v-if="visibleAnnouncements.length" class="mt-3 space-y-2">
                <li
                  v-for="announcement in visibleAnnouncements"
                  :id="`announcement-${announcement.id}`"
                  :key="announcement.id"
                  class="overflow-hidden rounded-xl border transition-shadow"
                  :class="highlightedAnnouncementId === announcement.id ? 'border-action ring-2 ring-action/30' : 'border-hairline'"
                >
                  <button
                    type="button"
                    class="flex w-full items-center gap-2.5 bg-canvas/70 px-3.5 py-3 text-left transition-colors hover:bg-canvas"
                    :aria-expanded="expandedAnnouncements.includes(announcement.id)"
                    @click="toggleAnnouncement(announcement.id)"
                  >
                    <AppIcon
                      name="chevron-right"
                      class="h-3.5 w-3.5 shrink-0 text-subtle transition-transform"
                      :class="{ 'rotate-90': expandedAnnouncements.includes(announcement.id) }"
                    />
                    <span class="min-w-0">
                      <span class="block truncate text-xs font-bold text-ink">{{ announcementSubject(announcement) }}</span>
                      <span class="block truncate text-[11px] text-muted">{{ announcement.author_name || 'Instructor' }}</span>
                      <span v-if="announcement.published_at" class="block truncate text-[10px] text-subtle">
                        Announced {{ formatAnnouncedAt(announcement.published_at) }}
                      </span>
                    </span>
                  </button>
                  <div v-show="expandedAnnouncements.includes(announcement.id)" class="border-t border-hairline px-3.5 py-3">
                    <h3 class="text-sm font-semibold text-ink">{{ announcement.title }}</h3>
                    <FormattedText :text="announcement.message" class="mt-1 text-xs text-muted" />
                    <div class="mt-3 flex items-center justify-between gap-3">
                      <time
                        v-if="announcement.published_at"
                        class="text-[11px] text-subtle"
                        :datetime="announcement.published_at.replace(' ', 'T') + '+08:00'"
                      >{{ formatAnnouncedAt(announcement.published_at) }}</time>
                      <span v-else />
                      <button
                        type="button"
                        class="rounded-lg border border-hairline bg-canvas px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:border-action/40 hover:text-action disabled:opacity-50 dark:hover:text-sky-300"
                        :disabled="busyAnnouncementId === announcement.id"
                        @click="setAnnouncementArchived(announcement, announcementTab === 'inbox')"
                      >
                        {{ announcementTab === 'inbox' ? 'Archive' : 'Restore' }}
                      </button>
                    </div>
                  </div>
                </li>
              </ul>
              <p v-else class="mt-3 rounded-xl border border-dashed border-hairline bg-canvas/50 p-5 text-center text-xs text-subtle">
                {{ announcementTab === 'inbox' ? 'No announcements for your section yet.' : 'No archived announcements.' }}
              </p>
            </template>
          </div>
        </section>

        <!-- Schedule Change Request -->
        <section class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="flex items-center justify-between gap-3">
            <h2 class="min-w-0 flex-1">
              <button
                type="button"
                class="flex w-full items-center gap-3 text-left"
                :aria-expanded="isChangeRequestOpen"
                @click="isChangeRequestOpen = !isChangeRequestOpen"
              >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                  <AppIcon name="clock" class="h-5 w-5" />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="flex items-center gap-1.5 text-base font-bold text-ink">
                    <span>Schedule change request</span>
                    <AppIcon
                      name="chevron-right"
                      class="h-4 w-4 shrink-0 text-subtle transition-transform"
                      :class="{ 'rotate-90': isChangeRequestOpen }"
                    />
                  </span>
                  <span v-if="scheduleChangeWindow.deadline" class="mt-0.5 block text-xs font-normal text-subtle">
                    Deadline: {{ scheduleChangeWindow.deadline }}
                    <span v-if="canRequestChange">&middot; {{ scheduleChangeWindow.days_remaining }} day(s) remaining</span>
                  </span>
                  <span v-else class="mt-0.5 block text-xs font-normal text-subtle">
                    The semester start date has not been configured.
                  </span>
                </span>
              </button>
            </h2>
            <span
              class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold"
              :class="
                canRequestChange
                  ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                  : 'border-hairline bg-canvas text-subtle'
              "
            >
              <span
                class="h-1.5 w-1.5 rounded-full"
                :class="canRequestChange ? 'bg-emerald-500' : 'bg-slate-400'"
                aria-hidden="true"
              />
              {{ canRequestChange ? 'Open' : 'Closed' }}
            </span>
          </div>
          <div v-show="isChangeRequestOpen" class="mt-4 border-t border-hairline pt-4">
            <p v-if="requestError" class="mb-3 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ requestError }}</p>
            <p v-if="requestSuccess" class="mb-3 text-xs text-emerald-600 dark:text-emerald-400" role="status">{{ requestSuccess }}</p>
            <form v-if="canRequestChange && publishedSchedules.length" class="space-y-3.5" @submit.prevent="submitScheduleChangeRequest">
              <label class="block text-xs font-semibold text-ink">
                Current class
                <select
                  v-model.number="requestForm.schedule_id"
                  required
                  class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
                >
                  <option v-for="schedule in publishedSchedules" :key="schedule.id" :value="schedule.id">
                    {{ schedule.subject_code }} · {{ schedule.day_of_week }} {{ schedule.start_time.slice(0, 5) }}–{{ schedule.end_time.slice(0, 5) }}
                  </option>
                </select>
              </label>
              <p v-if="selectedRequestSchedule" class="rounded-xl border border-hairline/70 bg-canvas/70 px-3 py-2 text-xs text-muted">
                Current: {{ selectedRequestSchedule.day_of_week }}, {{ selectedRequestSchedule.start_time.slice(0, 5) }}–{{ selectedRequestSchedule.end_time.slice(0, 5) }} &middot; {{ selectedRequestSchedule.room_code }}
              </p>
              <div class="grid gap-3 sm:grid-cols-3">
                <label class="text-xs font-semibold text-ink">
                  Preferred day
                  <select
                    v-model="requestForm.preferred_day_of_week"
                    class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-2.5 py-2 text-xs text-ink focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
                  >
                    <option value="">No preference</option>
                    <option v-for="day in days" :key="day" :value="day">{{ day }}</option>
                  </select>
                </label>
                <label class="text-xs font-semibold text-ink">
                  Preferred start
                  <input
                    v-model="requestForm.preferred_start_time"
                    type="time"
                    class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-2.5 py-2 text-xs text-ink focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
                  />
                </label>
                <label class="text-xs font-semibold text-ink">
                  Preferred end
                  <input
                    v-model="requestForm.preferred_end_time"
                    type="time"
                    class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-2.5 py-2 text-xs text-ink focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
                  />
                </label>
              </div>
              <label class="block text-xs font-semibold text-ink">
                Reason
                <textarea
                  v-model="requestForm.reason"
                  required
                  minlength="10"
                  maxlength="1000"
                  rows="3"
                  class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2 text-xs font-normal text-ink focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
                  placeholder="Explain why you need this change."
                />
              </label>
              <button
                type="submit"
                :disabled="isSubmittingRequest"
                class="rounded-xl bg-action px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-action-hover active:bg-action-active disabled:opacity-50"
              >
                {{ isSubmittingRequest ? 'Submitting…' : 'Submit request' }}
              </button>
            </form>
            <p v-else-if="canRequestChange" class="text-xs text-subtle">
              There are no published class schedules to request changes for.
            </p>
            <p v-else class="text-xs text-subtle">
              Requests are available only during the first month of the semester.
            </p>

            <div class="mt-4 border-t border-hairline pt-4">
              <h3 class="text-xs font-bold text-ink">Your requests</h3>
              <ul v-if="requests.length" class="mt-2.5 space-y-2">
                <li v-for="request in requests" :key="request.id" class="rounded-xl border border-hairline/70 bg-canvas/70 p-3">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-ink">{{ request.subject_code }} &middot; {{ request.current_day_of_week }} {{ request.current_start_time.slice(0, 5) }}</span>
                    <span
                      class="rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize"
                      :class="{
                        'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300': request.status === 'pending',
                        'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300': request.status === 'approved',
                        'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-300': request.status === 'rejected',
                      }"
                    >
                      {{ request.status }}
                    </span>
                  </div>
                  <p class="mt-1 text-[11px] text-muted">{{ request.reason }}</p>
                  <p v-if="request.review_note" class="mt-1 text-[11px] text-subtle">Review note: {{ request.review_note }}</p>
                </li>
              </ul>
              <p v-else class="mt-2 text-xs text-subtle">No schedule change requests yet.</p>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>
