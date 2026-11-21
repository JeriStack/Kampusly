<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import NotificationCenter from '@/components/common/NotificationCenter.vue'
import UserSessionPopover from '@/components/common/UserSessionPopover.vue'
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
          <UserSessionPopover :subtitle="String(user?.profile?.department || 'Faculty')" />
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
          Instructor Workspace &bull; Teaching Schedule &amp; Announcements
        </p>
      </div>

      <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
        <!-- Faculty Information Card -->
        <section class="overflow-hidden rounded-2xl border border-hairline bg-surface shadow-card lg:col-span-4">
          <div class="h-1 w-full bg-gradient-to-r from-navy via-action to-accent" />
          <div class="p-5 sm:p-6">
            <div class="flex items-center gap-3 border-b border-hairline pb-4">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300">
                <AppIcon name="instructor" class="h-5 w-5" />
              </span>
              <div class="min-w-0">
                <h2 class="text-base font-bold text-ink">Faculty Profile</h2>
                <p class="truncate text-xs text-subtle">Assigned instructor credentials</p>
              </div>
            </div>

            <dl class="mt-4 space-y-3.5 text-xs">
              <div class="flex items-center justify-between gap-3 border-b border-hairline/70 pb-3">
                <dt class="text-muted">Employee ID</dt>
                <dd class="rounded-md border border-hairline bg-canvas px-2.5 py-0.5 font-mono font-semibold text-ink">
                  {{ user?.profile?.employee_number || 'EMP-001' }}
                </dd>
              </div>

              <div class="border-b border-hairline/70 pb-3">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-subtle">Department</dt>
                <dd class="mt-1 font-semibold text-ink">
                  {{ user?.profile?.department || 'College of Computer Studies' }}
                </dd>
              </div>

              <div class="border-b border-hairline/70 pb-3">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-subtle">Institutional Email</dt>
                <dd class="mt-1 break-words font-medium text-ink">
                  {{ user?.email }}
                </dd>
              </div>

              <div class="flex items-center justify-between gap-3 pt-0.5">
                <dt class="text-muted">Account Status</dt>
                <dd class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-0.5 font-semibold capitalize text-emerald-700 dark:text-emerald-300">
                  <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true" />
                  {{ user?.status }}
                </dd>
              </div>
            </dl>
          </div>
        </section>

        <!-- Instructor Workspace -->
        <div class="lg:col-span-8">
          <InstructorWorkPanel />
        </div>
      </div>
    </main>
  </div>
</template>
