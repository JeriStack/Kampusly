<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import AppIcon from '@/components/common/AppIcon.vue'

const router = useRouter()
const { user, logout } = useAuth()

async function handleLogout(): Promise<void> {
  await logout()
  await router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-canvas text-ink">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-20 border-b border-hairline bg-surface/90 backdrop-blur-md">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4">
          <KampuslyLogo size="sm" />
          <span class="rounded-full bg-navy-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-navy-800 dark:bg-navy-900/60 dark:text-primary-300">
            Instructor Portal
          </span>
        </div>
        <div class="flex items-center gap-3">
          <ThemeToggle />
          <div class="hidden text-right sm:block">
            <div class="text-xs font-bold text-ink">{{ user?.name }}</div>
            <div class="text-[11px] text-muted">{{ user?.profile?.department || 'Faculty' }}</div>
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
          Welcome, {{ user?.name }}
        </h1>
        <p class="mt-1 text-sm text-muted">
          Instructor Portal &bull; Department of {{ user?.profile?.department || 'Information Technology' }}
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
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">Institutional Email</span>
              <span class="font-medium">{{ user?.email }}</span>
            </div>
            <div class="flex justify-between py-1.5">
              <span class="text-muted">Account Status</span>
              <span class="font-semibold text-emerald-600 dark:text-emerald-400 capitalize">{{ user?.status }}</span>
            </div>
          </div>
        </div>

        <!-- Instructor Capabilities -->
        <div class="rounded-2xl border border-hairline bg-surface p-6 shadow-card md:col-span-2">
          <h2 class="text-base font-semibold text-ink">Teaching Schedule & Notices</h2>
          <p class="mt-2 text-xs text-muted">
            As an instructor, your account is authorized to view your teaching schedules and post class notices for cancellations, make-up classes, and room transfers.
          </p>
          <div class="mt-6 rounded-xl border border-dashed border-hairline bg-canvas p-6 text-center">
            <AppIcon name="calendar" class="mx-auto h-8 w-8 text-muted" />
            <div class="mt-2 text-sm font-semibold text-ink">Teaching Schedule Module</div>
            <p class="mt-1 text-xs text-muted">
              Schedule loading and notice dispatch workflows will be active in subsequent phases.
            </p>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
