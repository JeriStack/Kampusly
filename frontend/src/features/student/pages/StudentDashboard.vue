<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import NotificationCenter from '@/components/common/NotificationCenter.vue'
import { apiClient } from '@/services/api/client'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { ApiError } from '@/services/api/errors'
import type { Schedule, Section } from '@/features/academic/types'

const router = useRouter()
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
  published_at: string | null
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

async function loadStudentUpdates(): Promise<void> {
  try {
    const [requestResponse, announcementResponse] = await Promise.all([
      apiClient.get<{ data: ScheduleChangeRequest[] }>(
        `${API_ENDPOINTS.student.scheduleChangeRequests}?page=1&per_page=100`,
      ),
      apiClient.get<{ data: Announcement[] }>(
        `${API_ENDPOINTS.student.announcements}?page=1&per_page=100`,
      ),
    ])
    requests.value = requestResponse.data
    announcements.value = announcementResponse.data
    extrasError.value = null
  } catch (err) {
    extrasError.value = err instanceof ApiError ? err.message : 'Unable to load announcements or request history.'
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
  updatesTimer = setInterval(() => void loadStudentUpdates(), 30_000)
})

onBeforeUnmount(() => {
  if (updatesTimer) clearInterval(updatesTimer)
})
</script>

<template>
  <div class="dashboard-readable min-h-screen bg-canvas text-ink">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-20 border-b border-hairline bg-surface/90 backdrop-blur-md">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4">
          <KampuslyLogo size="sm" />
          <span class="rounded-full bg-navy-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-navy-800 dark:bg-navy-900/60 dark:text-primary-300">
            Student Portal
          </span>
        </div>
        <div class="flex items-center gap-3">
          <NotificationCenter />
          <ThemeToggle />
          <div class="hidden text-right sm:block">
            <div class="text-xs font-bold text-ink">{{ user?.name }}</div>
            <div class="text-[11px] text-muted">{{ user?.profile?.program || 'BSIT' }} &bull; {{ user?.profile?.section_name || 'Section' }}</div>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-hairline bg-canvas px-3 py-1.5 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="handleLogout"
          >
            <AppIcon name="logout" class="h-3.5 w-3.5" />
            <span>Sign Out</span>
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div class="mb-8">
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
          Mabuhay, {{ user?.name }}!
        </h1>
        <p class="mt-1 text-sm text-muted">
          Your Campus. Your Classes. Your Time.
        </p>
      </div>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <!-- Student Information Card -->
        <div class="rounded-2xl border border-hairline bg-surface p-6 shadow-card">
          <h2 class="text-base font-semibold text-ink">Student Profile</h2>
          <div class="mt-4 space-y-3 text-xs">
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">Student Number</span>
              <span class="font-mono font-semibold">{{ user?.profile?.student_number || '2026-0001' }}</span>
            </div>
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">Program</span>
              <span class="font-semibold">{{ user?.profile?.program || 'BS Information Technology' }}</span>
            </div>
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">Assigned Section</span>
              <span class="font-semibold text-primary-600 dark:text-primary-400">
                {{ user?.profile?.section_name || 'BSIT-3A' }}
              </span>
            </div>
            <div class="flex justify-between py-1.5">
              <span class="text-muted">Institutional Email</span>
              <span class="font-medium truncate max-w-[150px]">{{ user?.email }}</span>
            </div>
          </div>
        </div>

        <!-- Student Schedule View -->
        <div class="rounded-2xl border border-hairline bg-surface p-6 shadow-card md:col-span-2">
          <h2 class="text-base font-semibold text-ink">Enrolled Section Schedule</h2>
          <p class="mt-2 text-xs text-muted">
            Your schedule is automatically filtered to your assigned section ({{ user?.profile?.section_name || 'BSIT-3A' }}).
          </p>

          <div
            v-if="hasDraftSchedules || hasProvisionalInstructors"
            class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
            role="status"
          >
            <template v-if="hasDraftSchedules">
              This schedule contains draft meetings and is not yet fully confirmed.
            </template>
            <template v-else>
              This schedule is published, but some instructor assignments are provisional. Please check with the administrator.
            </template>
          </div>

          <div v-if="isScheduleLoading" class="mt-5 rounded-xl bg-canvas p-8 text-center" role="status">
            <AppIcon name="loader" class="mx-auto h-6 w-6 animate-spin text-muted" />
            <p class="mt-2 text-sm text-muted">Loading your schedule…</p>
          </div>

          <div
            v-else-if="scheduleError"
            class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200"
            role="alert"
          >
            <p>{{ scheduleError }}</p>
            <button type="button" class="mt-2 font-semibold underline" @click="loadSchedule">
              Try again
            </button>
          </div>

          <div v-else-if="schedules.length === 0" class="mt-5 rounded-xl border border-dashed border-hairline bg-canvas p-6 text-center">
            <AppIcon name="clock" class="mx-auto h-8 w-8 text-muted" />
            <div class="mt-2 text-sm font-semibold text-ink">No classes scheduled yet</div>
            <p class="mt-1 text-xs text-muted">
              Your section schedule will appear here when classes are added.
            </p>
          </div>

          <div v-else class="mt-5 space-y-4">
            <section
              v-for="group in schedulesByDay"
              :key="group.day"
              class="overflow-hidden rounded-xl border border-hairline"
              :aria-label="`${group.day}${group.day === today ? ', today' : ''} classes`"
            >
              <h3 class="flex items-center justify-between bg-canvas px-4 py-2.5 text-xs font-bold text-ink">
                {{ group.day }}
                <span v-if="group.day === today" class="rounded-full bg-primary-100 px-2 py-0.5 text-[10px] text-primary-800 dark:bg-primary-900/50 dark:text-primary-200">
                  Today
                </span>
              </h3>
              <ul class="divide-y divide-hairline">
                <li
                  v-for="schedule in group.entries"
                  :key="schedule.id"
                  class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-ink">
                      {{ schedule.subject_code }} — {{ schedule.subject_name }}
                    </p>
                    <p class="mt-1 text-xs text-muted">
                      {{ schedule.room_code }} · {{ instructorLabel(schedule) }}
                    </p>
                  </div>
                  <div class="flex shrink-0 items-center gap-2 sm:flex-col sm:items-end sm:gap-1">
                    <span class="text-xs font-semibold tabular-nums text-ink">
                      {{ schedule.start_time.slice(0, 5) }}–{{ schedule.end_time.slice(0, 5) }}
                    </span>
                    <span
                      class="rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize"
                      :class="{
                        'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200': schedule.status === 'draft',
                        'border-teal-300 bg-teal-50 text-teal-900 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-200': schedule.status === 'published',
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
      </div>

      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-base font-semibold text-ink">Class announcements</h2>
              <p class="mt-1 text-xs text-muted">Updates for {{ section?.section_name || 'your section' }}.</p>
            </div>
          </div>
          <p v-if="extrasError" class="mt-3 text-xs text-rose-700" role="alert">{{ extrasError }}</p>
          <ul v-else-if="announcements.length" class="mt-4 divide-y divide-hairline">
            <li v-for="announcement in announcements" :key="announcement.id" class="py-3 first:pt-0 last:pb-0">
              <h3 class="text-sm font-semibold text-ink">{{ announcement.title }}</h3>
              <p class="mt-1 whitespace-pre-wrap text-xs text-muted">{{ announcement.message }}</p>
              <time v-if="announcement.published_at" class="mt-1 block text-[10px] text-muted">{{ announcement.published_at }}</time>
            </li>
          </ul>
          <p v-else class="mt-4 rounded-lg bg-canvas p-4 text-center text-xs text-muted">
            No announcements for your section yet.
          </p>
        </section>

        <section class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-base font-semibold text-ink">Schedule change request</h2>
              <p v-if="scheduleChangeWindow.deadline" class="mt-1 text-xs text-muted">
                Deadline: {{ scheduleChangeWindow.deadline }} <span v-if="canRequestChange">· {{ scheduleChangeWindow.days_remaining }} day(s) remaining</span>
              </p>
              <p v-else class="mt-1 text-xs text-muted">The semester start date has not been configured.</p>
            </div>
            <span class="rounded-full border px-2 py-1 text-[10px] font-semibold"
              :class="canRequestChange ? 'border-teal-300 bg-teal-50 text-teal-900' : 'border-hairline bg-canvas text-muted'">
              {{ canRequestChange ? 'Open' : 'Closed' }}
            </span>
          </div>
          <p v-if="requestError" class="mt-3 text-xs text-rose-700" role="alert">{{ requestError }}</p>
          <p v-if="requestSuccess" class="mt-3 text-xs text-teal-700" role="status">{{ requestSuccess }}</p>
          <form v-if="canRequestChange && publishedSchedules.length" class="mt-4 space-y-3" @submit.prevent="submitScheduleChangeRequest">
            <label class="block text-xs font-semibold text-ink">
              Current class
              <select v-model.number="requestForm.schedule_id" required class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs">
                <option v-for="schedule in publishedSchedules" :key="schedule.id" :value="schedule.id">
                  {{ schedule.subject_code }} · {{ schedule.day_of_week }} {{ schedule.start_time.slice(0, 5) }}–{{ schedule.end_time.slice(0, 5) }}
                </option>
              </select>
            </label>
            <p v-if="selectedRequestSchedule" class="rounded-lg bg-canvas p-2 text-xs text-muted">
              Current: {{ selectedRequestSchedule.day_of_week }}, {{ selectedRequestSchedule.start_time.slice(0, 5) }}–{{ selectedRequestSchedule.end_time.slice(0, 5) }} · {{ selectedRequestSchedule.room_code }}
            </p>
            <div class="grid gap-3 sm:grid-cols-3">
              <label class="text-xs font-semibold text-ink">Preferred day
                <select v-model="requestForm.preferred_day_of_week" class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-2 py-2 text-xs">
                  <option value="">No preference</option>
                  <option v-for="day in days" :key="day" :value="day">{{ day }}</option>
                </select>
              </label>
              <label class="text-xs font-semibold text-ink">Preferred start
                <input v-model="requestForm.preferred_start_time" type="time" class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-2 py-2 text-xs" />
              </label>
              <label class="text-xs font-semibold text-ink">Preferred end
                <input v-model="requestForm.preferred_end_time" type="time" class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-2 py-2 text-xs" />
              </label>
            </div>
            <label class="block text-xs font-semibold text-ink">Reason
              <textarea v-model="requestForm.reason" required minlength="10" maxlength="1000" rows="3" class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs font-normal" placeholder="Explain why you need this change." />
            </label>
            <button type="submit" :disabled="isSubmittingRequest" class="rounded-lg bg-navy px-4 py-2 text-xs font-semibold text-white disabled:opacity-50">
              {{ isSubmittingRequest ? 'Submitting…' : 'Submit request' }}
            </button>
          </form>
          <p v-else-if="canRequestChange" class="mt-4 text-xs text-muted">There are no published class schedules to request changes for.</p>
          <p v-else class="mt-4 text-xs text-muted">Requests are available only during the first month of the semester.</p>

          <div class="mt-5 border-t border-hairline pt-4">
            <h3 class="text-xs font-bold text-ink">Your requests</h3>
            <ul v-if="requests.length" class="mt-2 space-y-2">
              <li v-for="request in requests" :key="request.id" class="rounded-lg bg-canvas p-3">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-semibold text-ink">{{ request.subject_code }} · {{ request.current_day_of_week }} {{ request.current_start_time.slice(0, 5) }}</span>
                  <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize"
                    :class="{
                      'border-amber-300 bg-amber-50 text-amber-900': request.status === 'pending',
                      'border-teal-300 bg-teal-50 text-teal-900': request.status === 'approved',
                      'border-rose-300 bg-rose-50 text-rose-900': request.status === 'rejected',
                    }">{{ request.status }}</span>
                </div>
                <p class="mt-1 text-[11px] text-muted">{{ request.reason }}</p>
                <p v-if="request.review_note" class="mt-1 text-[11px] text-muted">Review note: {{ request.review_note }}</p>
              </li>
            </ul>
            <p v-else class="mt-2 text-xs text-muted">No schedule change requests yet.</p>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>
