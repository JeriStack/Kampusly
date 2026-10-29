<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import { apiClient } from '@/services/api/client'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import AppIcon from '@/components/common/AppIcon.vue'

const router = useRouter()
const { user, logout } = useAuth()

interface DashboardSummary {
  total_students: number
  total_instructors: number
  total_subjects: number
  total_rooms: number
  todays_classes: number
  upcoming_classes: number
  active_announcements: number
  cancelled_or_pending_notices: number
}

const summary = ref<DashboardSummary | null>(null)
const isLoading = ref(true)
const error = ref<string | null>(null)

async function fetchSummary(): Promise<void> {
  isLoading.value = true
  error.value = null
  try {
    const response = await apiClient.get<{ data: DashboardSummary }>(API_ENDPOINTS.admin.dashboardSummary)
    summary.value = response.data
  } catch (err: unknown) {
    error.value = err instanceof Error ? err.message : 'Failed to load dashboard metrics.'
  } finally {
    isLoading.value = false
  }
}

async function handleLogout(): Promise<void> {
  await logout()
  await router.push('/login')
}

onMounted(() => {
  fetchSummary()
})
</script>

<template>
  <div class="min-h-screen bg-canvas text-ink">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-20 border-b border-hairline bg-surface/90 backdrop-blur-md">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4">
          <KampuslyLogo size="sm" />
          <span class="rounded-full bg-navy-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-navy-800 dark:bg-navy-900/60 dark:text-primary-300">
            Administrator Portal
          </span>
        </div>
        <div class="flex items-center gap-3">
          <ThemeToggle />
          <div class="hidden text-right sm:block">
            <div class="text-xs font-bold text-ink">{{ user?.name }}</div>
            <div class="text-[11px] text-muted">{{ user?.email }}</div>
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
      <!-- Welcome Header -->
      <div class="mb-8">
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
          Welcome back, {{ user?.name }}
        </h1>
        <p class="mt-1 text-sm text-muted">
          Kampusly Administration System &bull; Schedule & System Overview
        </p>
      </div>

      <!-- KPI Metrics Cards -->
      <div v-if="isLoading" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="i in 4" :key="i" class="h-28 animate-pulse rounded-2xl border border-hairline bg-surface p-5" />
      </div>

      <div v-else-if="summary" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="text-xs font-semibold uppercase tracking-wider text-muted">Total Students</div>
          <div class="mt-2 text-3xl font-bold text-ink">{{ summary.total_students }}</div>
          <div class="mt-1 text-xs text-muted">Active enrolled students</div>
        </div>

        <div class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="text-xs font-semibold uppercase tracking-wider text-muted">Instructors</div>
          <div class="mt-2 text-3xl font-bold text-ink">{{ summary.total_instructors }}</div>
          <div class="mt-1 text-xs text-muted">Faculty members</div>
        </div>

        <div class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="text-xs font-semibold uppercase tracking-wider text-muted">Today's Classes</div>
          <div class="mt-2 text-3xl font-bold text-primary-600 dark:text-primary-400">
            {{ summary.todays_classes }}
          </div>
          <div class="mt-1 text-xs text-muted">Scheduled for today</div>
        </div>

        <div class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="text-xs font-semibold uppercase tracking-wider text-muted">Active Subjects</div>
          <div class="mt-2 text-3xl font-bold text-ink">{{ summary.total_subjects }}</div>
          <div class="mt-1 text-xs text-muted">Curriculum catalog</div>
        </div>
      </div>

      <div v-else-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300">
        {{ error }}
      </div>

      <!-- Quick Action Panels -->
      <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-hairline bg-surface p-6 shadow-card lg:col-span-2">
          <h2 class="text-base font-semibold text-ink">Administrative Capabilities</h2>
          <p class="mt-1 text-xs text-muted">
            Phase 5 provides secure role-based access control, CSRF synchronizer token verification, rate limiting, and account administration.
          </p>
          <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-hairline bg-canvas p-4">
              <div class="text-xs font-bold text-ink">Account & Role Management</div>
              <p class="mt-1 text-[11px] text-muted">
                Admins can create accounts, toggle active/inactive statuses with self-deactivation lockout guards.
              </p>
            </div>
            <div class="rounded-xl border border-hairline bg-canvas p-4">
              <div class="text-xs font-bold text-ink">Conflict-Free Scheduling</div>
              <p class="mt-1 text-[11px] text-muted">
                Room, instructor, and section overlap checks prevent scheduling clashes across semesters.
              </p>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-hairline bg-surface p-6 shadow-card">
          <h2 class="text-base font-semibold text-ink">Your Session</h2>
          <div class="mt-4 space-y-2 text-xs">
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">Account Role</span>
              <span class="font-bold uppercase text-navy dark:text-primary-400">{{ user?.role }}</span>
            </div>
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">Status</span>
              <span class="font-semibold text-emerald-600 dark:text-emerald-400 capitalize">{{ user?.status }}</span>
            </div>
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">User ID</span>
              <span class="font-mono">{{ user?.id }}</span>
            </div>
            <div class="flex justify-between py-1.5">
              <span class="text-muted">Last Signed In</span>
              <span class="text-muted">{{ user?.last_login_at || 'Just now' }}</span>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
