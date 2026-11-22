<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
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

const STORAGE_KEY = 'kampusly-admin-sidebar-collapsed'

function readInitialCollapsed(): boolean {
  try {
    return window.localStorage.getItem(STORAGE_KEY) === 'true'
  } catch {
    return false
  }
}

const route = useRoute()
const router = useRouter()
const { user, logout } = useAuth()

const isCollapsed = ref<boolean>(readInitialCollapsed())
const isMobileOpen = ref(false)

const navItems: { name: string; path: string; icon: IconName }[] = [
  { name: 'Dashboard', path: '/admin/dashboard', icon: 'admin' },
  { name: 'Students', path: '/admin/students', icon: 'student' },
  { name: 'Instructors', path: '/admin/instructors', icon: 'instructor' },
  { name: 'Schedules', path: '/admin/schedules', icon: 'calendar' },
  { name: 'Subjects', path: '/admin/subjects', icon: 'book' },
  { name: 'Rooms', path: '/admin/rooms', icon: 'grid' },
  { name: 'Sections', path: '/admin/sections', icon: 'layers' },
  { name: 'Archives', path: '/admin/archives', icon: 'archive' },
]

function isActiveRoute(path: string): boolean {
  return route.path === path || route.path.startsWith(`${path}/`)
}

function toggleSidebar(): void {
  isCollapsed.value = !isCollapsed.value
  try {
    window.localStorage.setItem(STORAGE_KEY, String(isCollapsed.value))
  } catch {
    // Ignore storage quota or private-mode restrictions
  }
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && isMobileOpen.value) {
    isMobileOpen.value = false
  }
}

watch(
  () => route.path,
  () => {
    isMobileOpen.value = false
  },
)

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})

async function handleLogout(): Promise<void> {
  await logout()
  await router.push('/login')
}
</script>

<template>
  <div class="dashboard-readable flex min-h-screen bg-canvas text-ink">
    <!-- Desktop Collapsible Sidebar -->
    <aside
      aria-label="Admin sidebar"
      class="sticky top-0 z-30 hidden h-screen shrink-0 flex-col border-r border-hairline bg-surface transition-[width] duration-200 ease-out lg:flex"
      :class="isCollapsed ? 'w-[4.75rem]' : 'w-64'"
    >
      <!-- Sidebar Brand Header -->
      <div
        class="flex h-16 shrink-0 items-center border-b border-hairline px-3"
        :class="isCollapsed ? 'justify-center' : 'justify-between gap-2 px-4'"
      >
        <RouterLink
          v-if="!isCollapsed"
          to="/admin/dashboard"
          class="flex min-w-0 items-center gap-2 overflow-hidden"
        >
          <KampuslyLogo size="sm" />
        </RouterLink>
        <button
          type="button"
          :aria-label="isCollapsed ? 'Expand sidebar' : 'Minimize sidebar'"
          :title="isCollapsed ? 'Expand sidebar' : 'Minimize sidebar'"
          :aria-expanded="!isCollapsed"
          class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-hairline bg-canvas text-muted transition-colors hover:border-action/40 hover:text-action dark:hover:text-sky-300"
          @click="toggleSidebar"
        >
          <AppIcon :name="isCollapsed ? 'chevron-right' : 'chevron-left'" class="h-4 w-4" />
        </button>
      </div>

      <!-- Sidebar Navigation -->
      <nav
        class="flex-1 space-y-1 overflow-y-auto px-2.5 py-4"
        aria-label="Admin navigation"
      >
        <p
          v-if="!isCollapsed"
          class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-subtle"
        >
          Navigation
        </p>
        <RouterLink
          v-for="tab in navItems"
          :key="tab.path"
          :to="tab.path"
          :title="isCollapsed ? tab.name : undefined"
          :aria-label="isCollapsed ? tab.name : undefined"
          :aria-current="isActiveRoute(tab.path) ? 'page' : undefined"
          class="group flex items-center rounded-xl py-2.5 text-xs font-semibold transition-all"
          :class="[
            isCollapsed ? 'justify-center px-2' : 'gap-3 px-3',
            isActiveRoute(tab.path)
              ? 'bg-action/10 text-navy ring-1 ring-inset ring-action/20 shadow-sm dark:bg-sky-500/15 dark:text-sky-200 dark:ring-sky-400/30'
              : 'text-muted hover:bg-canvas hover:text-ink',
          ]"
        >
          <AppIcon
            :name="tab.icon"
            class="h-[1.15rem] w-[1.15rem] shrink-0 transition-colors"
            :class="
              isActiveRoute(tab.path)
                ? 'text-action dark:text-sky-300'
                : 'text-subtle group-hover:text-ink'
            "
          />
          <span v-if="!isCollapsed" class="truncate">{{ tab.name }}</span>
        </RouterLink>
      </nav>

      <!-- Sidebar Footer Minimize Button -->
      <div class="shrink-0 border-t border-hairline p-2.5">
        <button
          type="button"
          :title="isCollapsed ? 'Expand sidebar' : 'Minimize sidebar'"
          class="flex w-full items-center rounded-xl border border-hairline bg-canvas py-2 text-xs font-semibold text-muted transition-colors hover:border-action/40 hover:text-ink"
          :class="isCollapsed ? 'justify-center px-2' : 'justify-center gap-2 px-3'"
          @click="toggleSidebar"
        >
          <AppIcon :name="isCollapsed ? 'chevron-right' : 'chevron-left'" class="h-4 w-4 shrink-0" />
          <span v-if="!isCollapsed">Minimize</span>
        </button>
      </div>
    </aside>

    <!-- Mobile Slide-Over Sidebar Drawer -->
    <div
      v-if="isMobileOpen"
      class="fixed inset-0 z-50 flex lg:hidden"
      role="dialog"
      aria-modal="true"
      aria-label="Admin navigation drawer"
    >
      <div
        class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs"
        @click="isMobileOpen = false"
      />
      <aside class="relative z-10 flex h-full w-64 flex-col border-r border-hairline bg-surface shadow-xl">
        <div class="flex h-16 items-center justify-between border-b border-hairline px-4">
          <RouterLink to="/admin/dashboard" class="flex items-center gap-2">
            <KampuslyLogo size="sm" />
          </RouterLink>
          <button
            type="button"
            aria-label="Close navigation menu"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-hairline bg-canvas text-muted hover:text-ink"
            @click="isMobileOpen = false"
          >
            <AppIcon name="close" class="h-4 w-4" />
          </button>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Mobile admin navigation">
          <RouterLink
            v-for="tab in navItems"
            :key="tab.path"
            :to="tab.path"
            :aria-current="isActiveRoute(tab.path) ? 'page' : undefined"
            class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold transition-all"
            :class="
              isActiveRoute(tab.path)
                ? 'bg-action/10 text-navy ring-1 ring-inset ring-action/20 shadow-sm dark:bg-sky-500/15 dark:text-sky-200 dark:ring-sky-400/30'
                : 'text-muted hover:bg-canvas hover:text-ink'
            "
          >
            <AppIcon
              :name="tab.icon"
              class="h-4 w-4 shrink-0"
              :class="
                isActiveRoute(tab.path)
                  ? 'text-action dark:text-sky-300'
                  : 'text-subtle group-hover:text-ink'
              "
            />
            <span>{{ tab.name }}</span>
          </RouterLink>
        </nav>
      </aside>
    </div>

    <!-- Main Right Column (Top Header + Page Content) -->
    <div class="flex min-w-0 flex-1 flex-col">
      <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-4 border-b border-hairline bg-surface/95 px-4 backdrop-blur-md sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
          <button
            type="button"
            aria-label="Open navigation menu"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-hairline bg-canvas text-ink transition-colors hover:border-action/40 hover:text-action lg:hidden"
            @click="isMobileOpen = true"
          >
            <AppIcon name="menu" class="h-5 w-5" />
          </button>
          <RouterLink to="/admin/dashboard" class="flex shrink-0 items-center gap-2 lg:hidden">
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
      </header>

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
  </div>
</template>
