<script setup lang="ts">
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
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
]

async function handleLogout(): Promise<void> {
  await logout()
  await router.push('/login')
}
</script>

<template>
  <div class="dashboard-readable min-h-screen bg-canvas text-ink flex flex-col">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-30 border-b border-hairline bg-surface/90 backdrop-blur-md">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4">
          <RouterLink to="/admin/dashboard" class="flex items-center gap-2">
            <KampuslyLogo size="sm" />
          </RouterLink>
          <span class="rounded-full bg-navy-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-navy-800 dark:bg-navy-900/60 dark:text-primary-300">
            Admin Portal
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

      <!-- Navigation Tabs -->
      <nav class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8">
        <RouterLink
          v-for="tab in tabs"
          :key="tab.path"
          :to="tab.path"
          class="relative flex items-center gap-1.5 border-b-2 px-3.5 py-2.5 text-xs font-semibold transition-colors whitespace-nowrap"
          :class="[
            route.path.startsWith(tab.path)
              ? 'border-navy text-navy dark:border-primary-400 dark:text-primary-400 font-bold'
              : 'border-transparent text-muted hover:text-ink',
          ]"
        >
          <AppIcon :name="tab.icon" class="h-4 w-4" />
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
