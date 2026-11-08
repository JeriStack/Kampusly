<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import AppIcon from '@/components/common/AppIcon.vue'
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
  }
}

const schedules = ref<Schedule[]>([])
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

function instructorLabel(schedule: Schedule): string {
  const name = schedule.instructor_name
  return !name || name.startsWith('Temporary ') ? 'Instructor to be confirmed' : name
}

async function loadSchedule(): Promise<void> {
  isScheduleLoading.value = true
  scheduleError.value = null

  try {
    const response = await apiClient.get<StudentScheduleResponse>(API_ENDPOINTS.student.schedule)
    schedules.value = response.data.schedules
  } catch (err) {
    scheduleError.value =
      err instanceof ApiError ? err.message : 'Unable to load your class schedule.'
  } finally {
    isScheduleLoading.value = false
  }
}

async function handleLogout(): Promise<void> {
  await logout()
  await router.push('/login')
}

onMounted(loadSchedule)
</script>

<template>
  <div class="min-h-screen bg-canvas text-ink">
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
            v-if="schedules.some((schedule) => schedule.status === 'draft')"
            class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
            role="status"
          >
            This is a preliminary schedule. Draft class details are awaiting instructor and room confirmation.
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
                      {{ schedule.status === 'draft' ? 'Draft · unconfirmed' : 'Published' }}
                    </span>
                  </div>
                </li>
              </ul>
            </section>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
