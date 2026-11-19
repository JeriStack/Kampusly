<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/common/AppIcon.vue'
import { apiClient } from '@/services/api/client'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { ApiError } from '@/services/api/errors'

interface NotificationItem {
  id: number
  title: string
  message: string
  type: string
  reference_type: string
  reference_id: number
  read_at: string | null
  created_at: string
}

interface NotificationResponse {
  data: NotificationItem[]
  unread_count: number
}

const router = useRouter()
const notifications = ref<NotificationItem[]>([])
const unreadCount = ref(0)
const isOpen = ref(false)
const error = ref<string | null>(null)
let refreshTimer: ReturnType<typeof setInterval> | undefined

async function loadNotifications(): Promise<void> {
  try {
    const response = await apiClient.get<NotificationResponse>(
      `${API_ENDPOINTS.notifications.list}?page=1&per_page=10`,
    )
    notifications.value = response.data
    unreadCount.value = response.unread_count
    error.value = null
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Unable to load notifications.'
  }
}

async function markRead(notification: NotificationItem): Promise<void> {
  if (notification.read_at) return
  try {
    await apiClient.patch(API_ENDPOINTS.notifications.read(notification.id), {})
    notification.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Unable to update this notification.'
  }
}

async function openNotification(notification: NotificationItem): Promise<void> {
  await markRead(notification)

  if (
    notification.type === 'announcement' &&
    notification.reference_type === 'announcement' &&
    notification.reference_id > 0
  ) {
    isOpen.value = false
    await router.push({
      name: 'student-dashboard',
      query: { announcement: String(notification.reference_id) },
    })
  }
}

async function markAllRead(): Promise<void> {
  try {
    await apiClient.post(API_ENDPOINTS.notifications.readAll, {})
    notifications.value.forEach((notification) => {
      notification.read_at ??= new Date().toISOString()
    })
    unreadCount.value = 0
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Unable to update notifications.'
  }
}

onMounted(() => {
  void loadNotifications()
  refreshTimer = setInterval(() => void loadNotifications(), 30_000)
})

onBeforeUnmount(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg border border-hairline bg-canvas text-ink hover:bg-surface"
      aria-label="Notifications"
      title="Notifications"
      :aria-expanded="isOpen"
      @click="isOpen = !isOpen"
    >
      <AppIcon name="bell" class="h-4 w-4" />
      <span
        v-if="unreadCount > 0"
        class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-rose-600 px-1 text-center text-[10px] leading-5 text-white"
        aria-label="Unread notifications"
      >{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
    </button>

    <section
      v-if="isOpen"
      class="absolute right-0 z-30 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-hairline bg-surface shadow-xl"
      aria-label="Notification list"
    >
      <div class="flex items-center justify-between border-b border-hairline px-4 py-3">
        <h2 class="text-sm font-bold text-ink">Notifications</h2>
        <button
          v-if="unreadCount > 0"
          type="button"
          class="text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300"
          @click="markAllRead"
        >Mark all read</button>
      </div>
      <p v-if="error" class="px-4 py-3 text-xs text-rose-700" role="alert">{{ error }}</p>
      <ul v-else-if="notifications.length" class="max-h-96 divide-y divide-hairline overflow-y-auto">
        <li v-for="notification in notifications" :key="notification.id">
          <button
            type="button"
            class="block w-full px-4 py-3 text-left hover:bg-canvas"
            :class="{ 'bg-primary-50/60 dark:bg-primary-950/20': !notification.read_at }"
            @click="openNotification(notification)"
          >
            <span class="flex items-center justify-between gap-2">
              <span class="text-xs font-semibold text-ink">{{ notification.title }}</span>
              <span v-if="!notification.read_at" class="h-2 w-2 shrink-0 rounded-full bg-primary-600" aria-label="Unread" />
            </span>
            <span class="mt-1 block text-xs text-muted">{{ notification.message }}</span>
            <time class="mt-1 block text-[10px] text-muted">{{ notification.created_at }}</time>
          </button>
        </li>
      </ul>
      <p v-else class="px-4 py-6 text-center text-xs text-muted">You’re all caught up.</p>
    </section>
  </div>
</template>
