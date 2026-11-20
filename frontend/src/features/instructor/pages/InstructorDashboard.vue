<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import NotificationCenter from '@/components/common/NotificationCenter.vue'
import InstructorWorkPanel from '@/features/instructor/components/InstructorWorkPanel.vue'

const router = useRouter()
const { user, logout } = useAuth()

async function handleLogout(): Promise<void> {
  await logout()
  await router.push('/login')
}
</script>

<template>
  <div class="dashboard-readable min-h-screen bg-canvas text-ink">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-20 border-b border-hairline bg-surface/95 backdrop-blur-md">
      <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
          <KampuslyLogo size="sm" />
          <span class="hidden sm:inline-flex items-center rounded-full border border-navy/15 bg-navy-50 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-navy-800 dark:border-sky-400/25 dark:bg-sky-500/15 dark:text-sky-200">
            Instructor Portal
          </span>
        </div>
        <div class="flex items-center gap-2.5">
          <NotificationCenter />
          <ThemeToggle />
          <div aria-hidden="true" class="hidden h-6 w-px bg-hairline sm:block" />
          <div class="hidden text-right leading-tight sm:block">
            <div class="text-xs font-semibold text-ink">{{ user?.name }}</div>
            <div class="text-[11px] text-subtle">{{ user?.profile?.department || 'Faculty' }}</div>
          </div>
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
      <div class="mb-8">
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
          {{ user?.name }}
        </h1>
        <p class="mt-1 text-sm text-muted">
          Your Schedule
        </p>
      </div>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <!-- Faculty Information Card -->
        <div class="rounded-2xl border border-hairline bg-surface p-6 shadow-card">
          <h2 class="text-base font-semibold text-ink">Faculty Profile</h2>
          <div class="mt-4 space-y-3 text-xs">
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">Employee ID</span>
              <span class="font-mono font-semibold">{{ user?.profile?.employee_number || 'EMP-001' }}</span>
            </div>
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">Department</span>
              <span class="font-semibold">{{ user?.profile?.department || 'College of Computer Studies' }}</span>
            </div>
            <div class="flex justify-between gap-4 border-b border-hairline py-1.5">
              <span class="shrink-0 text-muted">Institutional Email</span>
              <span class="min-w-0 break-all text-right font-medium">{{ user?.email }}</span>
            </div>
            <div class="flex justify-between py-1.5">
              <span class="text-muted">Account Status</span>
              <span class="font-semibold text-emerald-600 dark:text-emerald-400 capitalize">{{ user?.status }}</span>
            </div>
          </div>
        </div>

        <!-- Instructor Capabilities -->
        <div class="rounded-2xl border border-hairline bg-surface p-6 shadow-card md:col-span-2">
          <InstructorWorkPanel />
        </div>
      </div>
    </main>
  </div>
</template>
