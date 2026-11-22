<script setup lang="ts">
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import NotificationCenter from '@/components/common/NotificationCenter.vue'
import UserSessionPopover from '@/components/common/UserSessionPopover.vue'
import AppIcon, { type IconName } from '@/components/common/AppIcon.vue'

defineProps<{
  title: string
  subtitle?: string
}>()

const route = useRoute()
const router = useRouter()
const { user, logout } = useAuth()

const tabs: { name: string; path: string; icon: IconName }[] = [
  { name: 'Dashboard', path: '/admin/dashboard', icon: 'admin' },
  { name: 'Students', path: '/admin/students', icon: 'student' },
  { name: 'Instructors', path: '/admin/instructors', icon: 'instructor' },
  { name: 'Schedules', path: '/admin/schedules', icon: 'calendar' },
  { name: 'Subjects', path: '/admin/subjects', icon: 'book' },
  { name: 'Rooms', path: '/admin/rooms', icon: 'grid' },
  { name: 'Sections', path: '/admin/sections', icon: 'layers' },
  { name: 'Archives', path: '/admin/archives', icon: 'archive' },
]

async function handleLogout(): Promise<void> {
  await logout()
  await router.push('/login')
}
</script>

<template>
  <div class="dashboard-readable min-h-screen bg-canvas text-ink flex flex-col">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-30 border-b border-hairline bg-surface/95 backdrop-blur-md">
      <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
          <RouterLink to="/admin/dashboard" class="flex shrink-0 items-center gap-2">
            <KampuslyLogo size="sm" />
          </RouterLink>
          <span class="hidden sm:inline-flex items-center rounded-full border border-navy/15 bg-navy-50 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-navy-800 dark:border-sky-400/25 dark:bg-sky-500/15 dark:text-sky-200">
            Administrator Portal
          </span>
        </div>

        <div class="flex items-center gap-2.5">
          <NotificationCenter />
          <ThemeToggle />
          <div aria-hidden="true" class="hidden h-6 w-px bg-hairline sm:block" />
          <UserSessionPopover :subtitle="user?.email" />
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

      <!-- Navigation Tabs -->
      <nav
        class="mx-auto flex max-w-7xl items-center gap-1.5 overflow-x-auto border-t border-hairline/60 px-4 py-2 sm:px-6 lg:px-8"
        aria-label="Admin navigation"
      >
        <RouterLink
          v-for="tab in tabs"
          :key="tab.path"
          :to="tab.path"
          :aria-current="route.path.startsWith(tab.path) ? 'page' : undefined"
          class="group inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
          :class="
            route.path.startsWith(tab.path)
              ? 'bg-action/10 text-navy ring-1 ring-inset ring-action/20 shadow-sm dark:bg-sky-500/15 dark:text-sky-200 dark:ring-sky-400/30'
              : 'text-muted hover:bg-canvas hover:text-ink'
          "
        >
          <AppIcon
            :name="tab.icon"
            class="h-4 w-4 transition-colors"
            :class="
              route.path.startsWith(tab.path)
                ? 'text-action dark:text-sky-300'
                : 'text-subtle group-hover:text-ink'
            "
          />
          <span>{{ tab.name }}</span>
        </RouterLink>
      </nav>
    </header>

    <!-- Page Content Container -->
    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
      <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
            {{ title }}
          </h1>
          <p v-if="subtitle" class="mt-1 text-xs text-muted">
            {{ subtitle }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <slot name="actions" />
        </div>
      </div>

      <slot />
    </main>
  </div>
</template>
