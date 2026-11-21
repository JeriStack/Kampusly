<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { apiClient } from '@/services/api/client'
import { ApiError } from '@/services/api/errors'
import { stripRichText } from '@/utils/richText'
import AppIcon from '@/components/common/AppIcon.vue'
import FormattedText from '@/components/common/FormattedText.vue'
import RichTextEditor from '@/components/common/RichTextEditor.vue'
import PaginationBar from '@/features/academic/components/PaginationBar.vue'
import type { PaginationMeta, Schedule } from '@/features/academic/types'

interface Announcement {
  id: number
  subject_id: number
  section_id: number | null
  title: string
  message: string
  status: string
  published_at: string | null
  archived_at?: string | null
}

interface AssignedClass {
  section_id: number
  section_name: string
  subject_id: number
  subject_code: string | null
  subject_name: string | null
}

const schedules = ref<Schedule[]>([])
const announcements = ref<Announcement[]>([])
const archivedAnnouncements = ref<Announcement[]>([])
type AnnouncementTab = 'active' | 'archived'
const announcementTab = ref<AnnouncementTab>('active')
const isHistoryOpen = ref(false)
const emptyMeta = (): PaginationMeta => ({ page: 1, per_page: 10, total: 0, total_pages: 0 })
const activeMeta = ref<PaginationMeta>(emptyMeta())
const archivedMeta = ref<PaginationMeta>(emptyMeta())
const busyAnnouncementId = ref<number | null>(null)
const isLoading = ref(true)
const isAnnouncementsLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const dayFilter = ref('')
const sectionFilter = ref('')
const editingAnnouncementId = ref<number | null>(null)
const form = reactive({ target: '', title: '', message: '' })
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
const REQUEST_TIMEOUT_MS = 15000
const assignedClasses = computed<AssignedClass[]>(() => {
  const classes = new Map<string, AssignedClass>()
  for (const schedule of schedules.value) {
    if (schedule.status === 'cancelled') continue
    const key = `${schedule.section_id}:${schedule.subject_id}`
    classes.set(key, {
      section_id: schedule.section_id,
      section_name: schedule.section_name ?? `Section ${schedule.section_id}`,
      subject_id: schedule.subject_id,
      subject_code: schedule.subject_code,
      subject_name: schedule.subject_name,
    })
  }
  return [...classes.values()]
})
const sections = computed(() => {
  const unique = new Map<number, string>()
  for (const assignment of assignedClasses.value) unique.set(assignment.section_id, assignment.section_name)
  return [...unique.entries()].map(([id, name]) => ({ id, name }))
})
const filteredSchedules = computed(() =>
  schedules.value.filter((schedule) =>
    (!dayFilter.value || schedule.day_of_week === dayFilter.value)
    && (!sectionFilter.value || schedule.section_id === Number(sectionFilter.value)),
  ),
)
const scheduleGroups = computed(() =>
  days
    .map((day) => ({
      day,
      entries: filteredSchedules.value
        .filter((schedule) => schedule.day_of_week === day)
        .sort((a, b) => a.start_time.localeCompare(b.start_time)),
    }))
    .filter((group) => group.entries.length > 0),
)

async function loadData(): Promise<void> {
  isLoading.value = true
  isAnnouncementsLoading.value = true
  error.value = null
  await Promise.all([loadSchedules(), loadAnnouncements()])
}

async function loadSchedules(): Promise<void> {
  try {
    const response = await apiClient.get<{ data: Schedule[] }>(
      `${API_ENDPOINTS.scheduling.instructorSchedule}?page=1&per_page=100`,
      { signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS) },
    )
    schedules.value = response.data
    if (!form.target && assignedClasses.value.length > 0) {
      form.target = `${assignedClasses.value[0].section_id}:${assignedClasses.value[0].subject_id}`
    }
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Unable to load your teaching schedule.'
  } finally {
    isLoading.value = false
  }
}

async function fetchAnnouncementPage(tab: AnnouncementTab, page: number): Promise<void> {
  const meta = tab === 'active' ? activeMeta : archivedMeta
  const query = new URLSearchParams({ page: String(page), per_page: String(meta.value.per_page) })
  if (tab === 'archived') query.set('archived', '1')
  const response = await apiClient.get<{ data: Announcement[]; meta: PaginationMeta }>(
    `${API_ENDPOINTS.instructor.announcements}?${query.toString()}`,
    { signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS) },
  )

  // The last item on a page may have just been archived or restored; step back to the last page.
  if (response.data.length === 0 && response.meta.total_pages > 0 && page > response.meta.total_pages) {
    await fetchAnnouncementPage(tab, response.meta.total_pages)
    return
  }

  if (tab === 'active') {
    announcements.value = response.data
  } else {
    archivedAnnouncements.value = response.data
  }
  meta.value = { ...response.meta, page: response.meta.total === 0 ? 1 : response.meta.page }
}

/** Reloads both tabs on the page each one is currently showing (keeps the tab counts accurate). */
async function loadAnnouncements(): Promise<void> {
  try {
    await Promise.all([
      fetchAnnouncementPage('active', activeMeta.value.page),
      fetchAnnouncementPage('archived', archivedMeta.value.page),
    ])
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Unable to load your announcements.'
  } finally {
    isAnnouncementsLoading.value = false
  }
}

async function goToAnnouncementPage(page: number, perPage?: number): Promise<void> {
  const tab = announcementTab.value
  if (perPage !== undefined) (tab === 'active' ? activeMeta : archivedMeta).value.per_page = perPage
  isAnnouncementsLoading.value = true
  error.value = null
  try {
    await fetchAnnouncementPage(tab, page)
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Unable to load your announcements.'
  } finally {
    isAnnouncementsLoading.value = false
  }
}

function resetForm(): void {
  editingAnnouncementId.value = null
  form.title = ''
  form.message = ''
  form.target = assignedClasses.value.length
    ? `${assignedClasses.value[0].section_id}:${assignedClasses.value[0].subject_id}`
    : ''
}

function editAnnouncement(announcement: Announcement): void {
  editingAnnouncementId.value = announcement.id
  form.title = announcement.title
  form.message = announcement.message
  form.target = `${announcement.section_id}:${announcement.subject_id}`
  success.value = null
  error.value = null
}

async function saveAnnouncement(): Promise<void> {
  const [sectionId, subjectId] = form.target.split(':').map(Number)
  if (!sectionId || !subjectId) {
    error.value = 'Select one of your assigned sections and subjects.'
    return
  }
  if (stripRichText(form.message).trim().length < 3) {
    error.value = 'The message must contain at least 3 characters of text.'
    return
  }
  if (form.message.trim().length > 5000) {
    error.value = 'The message is longer than 5000 characters. Shorten the text or remove some formatting.'
    return
  }
  isSaving.value = true
  error.value = null
  success.value = null
  try {
    const payload = { section_id: sectionId, subject_id: subjectId, title: form.title.trim(), message: form.message.trim() }
    const response = editingAnnouncementId.value
      ? await apiClient.put<{ data: Announcement }>(
          API_ENDPOINTS.instructor.announcement(editingAnnouncementId.value),
          payload,
        )
      : await apiClient.post<{ data: Announcement }>(API_ENDPOINTS.instructor.announcements, payload)

    if (editingAnnouncementId.value) {
      announcements.value = announcements.value.map((item) =>
        item.id === response.data.id ? response.data : item,
      )
      success.value = 'Announcement updated and students notified.'
    } else {
      // Newest first: jump back to page 1 so the new announcement is on the list.
      activeMeta.value.page = 1
      await loadAnnouncements()
      success.value = 'Announcement published to the selected section.'
    }
    resetForm()
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Unable to publish this announcement.'
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  void loadData()
})

const visibleAnnouncements = computed(() =>
  announcementTab.value === 'active' ? announcements.value : archivedAnnouncements.value,
)
const currentMeta = computed(() =>
  announcementTab.value === 'active' ? activeMeta.value : archivedMeta.value,
)

async function setAnnouncementArchived(announcement: Announcement, archive: boolean): Promise<void> {
  if (
    archive
    && !window.confirm(
      'Archive this announcement? Students will no longer see it, and it will be deleted automatically after 7 days.',
    )
  ) return
  busyAnnouncementId.value = announcement.id
  error.value = null
  success.value = null
  try {
    const path = API_ENDPOINTS.instructor.announcementArchive(announcement.id)
    if (archive) {
      await apiClient.post(path)
    } else {
      await apiClient.delete(path)
    }
    if (archive && editingAnnouncementId.value === announcement.id) resetForm()
    await loadAnnouncements()
    success.value = archive
      ? 'Announcement archived. It will be deleted automatically after 7 days.'
      : 'Announcement restored and visible to students again.'
  } catch (err) {
    error.value = err instanceof ApiError
      ? err.message
      : `Unable to ${archive ? 'archive' : 'restore'} this announcement.`
  } finally {
    busyAnnouncementId.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="error"
      class="rounded-xl border border-rose-200 bg-rose-50/90 p-3.5 text-xs text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200"
      role="alert"
    >
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="loadData">Retry</button>
    </div>
    <p
      v-if="success"
      class="rounded-xl border border-teal-200 bg-teal-50/90 p-3.5 text-xs text-teal-900 dark:border-teal-500/30 dark:bg-teal-950/40 dark:text-teal-200"
      role="status"
    >
      {{ success }}
    </p>
    <div v-if="isLoading" class="space-y-3 rounded-2xl border border-hairline bg-surface p-6 shadow-card" role="status">
      <div class="h-6 w-48 animate-pulse rounded-lg bg-canvas" />
      <div class="h-24 animate-pulse rounded-xl bg-canvas" />
    </div>

    <section v-else class="overflow-hidden rounded-2xl border border-hairline bg-surface shadow-card">
      <div class="flex flex-wrap items-center justify-between gap-4 border-b border-hairline px-5 py-4 sm:px-6">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300">
            <AppIcon name="calendar" class="h-5 w-5" />
          </span>
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-base font-bold text-ink">My Teaching Schedule</h2>
              <span class="inline-flex items-center rounded-full border border-sky-500/20 bg-sky-500/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                {{ schedules.length }}
              </span>
            </div>
            <p class="mt-0.5 text-xs text-subtle">Only classes assigned to your instructor account are shown.</p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <select
            v-model="dayFilter"
            aria-label="Filter by day"
            class="h-10 rounded-xl border border-hairline bg-canvas px-3 text-xs font-medium text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          >
            <option value="">All days</option>
            <option v-for="day in days" :key="day" :value="day">{{ day }}</option>
          </select>
          <select
            v-model="sectionFilter"
            aria-label="Filter by section"
            class="h-10 rounded-xl border border-hairline bg-canvas px-3 text-xs font-medium text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          >
            <option value="">All sections</option>
            <option v-for="section in sections" :key="section.id" :value="section.id">{{ section.name }}</option>
          </select>
        </div>
      </div>

      <div
        v-if="schedules.length === 0"
        class="flex flex-col items-center justify-center px-6 py-10 text-center"
      >
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-canvas text-subtle ring-1 ring-hairline">
          <AppIcon name="calendar" class="h-5 w-5" />
        </span>
        <p class="mt-3 text-sm font-semibold text-ink">No teaching schedules assigned</p>
        <p class="mt-1 max-w-sm text-xs text-subtle">
          Classes assigned to your instructor account will appear here automatically.
        </p>
      </div>
      <div v-else class="grid gap-3 p-5 sm:px-6 md:grid-cols-2">
        <section v-for="group in scheduleGroups" :key="group.day" class="overflow-hidden rounded-xl border border-hairline">
          <h3 class="border-b border-hairline bg-canvas/70 px-3.5 py-2 text-xs font-bold uppercase tracking-wider text-ink">
            {{ group.day }}
          </h3>
          <ul class="divide-y divide-hairline/70">
            <li v-for="schedule in group.entries" :key="schedule.id" class="p-3.5">
              <div class="flex items-center gap-2">
                <span class="rounded-md border border-hairline bg-canvas px-2 py-0.5 font-mono text-[11px] font-bold text-ink">
                  {{ schedule.subject_code }}
                </span>
                <p class="truncate text-xs font-semibold text-ink">{{ schedule.subject_name }}</p>
              </div>
              <p class="mt-1.5 text-xs text-muted">{{ schedule.section_name }} &middot; {{ schedule.room_code }}</p>
              <div class="mt-2.5 flex items-center justify-between gap-2">
                <span class="inline-flex items-center gap-1 text-xs font-semibold tabular-nums text-navy dark:text-sky-300">
                  <AppIcon name="clock" class="h-3.5 w-3.5" />
                  {{ schedule.start_time.slice(0, 5) }}–{{ schedule.end_time.slice(0, 5) }}
                </span>
                <span
                  class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold capitalize"
                  :class="
                    schedule.status === 'published'
                      ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                      : schedule.status === 'draft'
                        ? 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                        : 'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-300'
                  "
                >
                  {{ schedule.status }}
                </span>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-hairline bg-surface shadow-card">
      <div class="flex items-center gap-3 border-b border-hairline px-5 py-4 sm:px-6">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300">
          <AppIcon name="megaphone" class="h-5 w-5" />
        </span>
        <div>
          <h2 class="text-base font-bold text-ink">Section Announcements</h2>
          <p class="mt-0.5 text-xs text-subtle">Students enrolled in the selected section receive an in-app notification.</p>
        </div>
      </div>

      <form class="space-y-4 p-5 sm:px-6" @submit.prevent="saveAnnouncement">
        <label class="block text-xs font-semibold text-ink">
          Assigned section and subject
          <select
            v-model="form.target"
            required
            :disabled="!assignedClasses.length || editingAnnouncementId !== null"
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <option v-if="!assignedClasses.length" value="" disabled>No assigned classes available</option>
            <option v-for="assignment in assignedClasses" :key="`${assignment.section_id}:${assignment.subject_id}`" :value="`${assignment.section_id}:${assignment.subject_id}`">
              {{ assignment.section_name }} · {{ assignment.subject_code }} — {{ assignment.subject_name }}
            </option>
          </select>
        </label>
        <label class="block text-xs font-semibold text-ink">
          Title
          <input
            v-model="form.title"
            required
            minlength="3"
            maxlength="180"
            placeholder="e.g. Midterm project guidelines"
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          />
        </label>
        <RichTextEditor v-model="form.message" />
        <div class="flex flex-wrap items-center justify-end gap-2.5 pt-1">
          <button
            v-if="editingAnnouncementId"
            type="button"
            class="rounded-xl border border-hairline bg-canvas px-4 py-2.5 text-xs font-semibold text-ink transition-colors hover:bg-surface"
            @click="resetForm"
          >
            Cancel edit
          </button>
          <button
            type="submit"
            :disabled="isSaving || !assignedClasses.length"
            class="inline-flex items-center gap-2 rounded-xl bg-action px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-action-hover active:bg-action-active disabled:cursor-not-allowed disabled:opacity-50"
          >
            <AppIcon v-if="isSaving" name="loader" class="h-4 w-4 animate-spin" />
            <span>{{ isSaving ? 'Saving…' : editingAnnouncementId ? 'Save announcement' : 'Publish announcement' }}</span>
          </button>
        </div>
      </form>

      <div class="border-t border-hairline px-5 py-4 sm:px-6">
        <h3>
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 text-left"
            :aria-expanded="isHistoryOpen"
            @click="isHistoryOpen = !isHistoryOpen"
          >
            <span class="flex items-center gap-2 text-xs font-bold text-ink">
              <AppIcon
                name="chevron-right"
                class="h-3.5 w-3.5 shrink-0 transition-transform"
                :class="{ 'rotate-90': isHistoryOpen }"
              />
              Announcement history
            </span>
            <span class="rounded-full border border-hairline bg-canvas px-2.5 py-0.5 text-[11px] font-semibold tabular-nums text-muted">
              {{ activeMeta.total }}
            </span>
          </button>
        </h3>
        <div v-show="isHistoryOpen">
          <div class="mt-3 flex gap-1 rounded-xl bg-canvas p-1 text-xs font-semibold" role="tablist">
            <button
              type="button"
              role="tab"
              class="flex-1 rounded-lg px-3 py-1.5 transition-colors"
              :class="announcementTab === 'active' ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink'"
              :aria-selected="announcementTab === 'active'"
              @click="announcementTab = 'active'"
            >
              Active ({{ activeMeta.total }})
            </button>
            <button
              type="button"
              role="tab"
              class="flex-1 rounded-lg px-3 py-1.5 transition-colors"
              :class="announcementTab === 'archived' ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink'"
              :aria-selected="announcementTab === 'archived'"
              @click="announcementTab = 'archived'"
            >
              Archived ({{ archivedMeta.total }})
            </button>
          </div>
          <p v-if="announcementTab === 'archived'" class="mt-3 text-[11px] text-subtle">
            Archived announcements are hidden from students and deleted automatically after 7 days.
          </p>
          <ul v-if="visibleAnnouncements.length" class="mt-3 divide-y divide-hairline">
            <li v-for="announcement in visibleAnnouncements" :key="announcement.id" class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-ink">{{ announcement.title }}</p>
                <FormattedText :text="announcement.message" class="mt-1 text-xs text-muted" />
                <p class="mt-1 text-[11px] text-subtle">
                  {{ assignedClasses.find((item) => item.section_id === announcement.section_id && item.subject_id === announcement.subject_id)?.section_name || 'Assigned section' }}
                  &middot; {{ announcement.status === 'withdrawn' ? 'Deleted' : announcement.status }}
                </p>
              </div>
              <div v-if="announcementTab === 'archived'" class="flex gap-2">
                <button
                  type="button"
                  class="text-xs font-semibold text-action hover:underline disabled:opacity-50 dark:text-sky-300"
                  :disabled="busyAnnouncementId === announcement.id"
                  @click="setAnnouncementArchived(announcement, false)"
                >
                  Restore
                </button>
              </div>
              <div v-else-if="announcement.status !== 'withdrawn'" class="flex gap-2">
                <button
                  type="button"
                  class="text-xs font-semibold text-action hover:underline dark:text-sky-300"
                  @click="editAnnouncement(announcement)"
                >
                  Edit
                </button>
                <button
                  type="button"
                  class="text-xs font-semibold text-rose-600 hover:underline disabled:opacity-50 dark:text-rose-400"
                  :disabled="busyAnnouncementId === announcement.id"
                  @click="setAnnouncementArchived(announcement, true)"
                >
                  Archive
                </button>
              </div>
            </li>
          </ul>
          <p v-else-if="isAnnouncementsLoading" class="mt-3 text-xs text-muted">Loading announcements…</p>
          <p v-else class="mt-3 text-xs text-subtle">
            {{ announcementTab === 'active' ? 'No announcements published yet.' : 'No archived announcements.' }}
          </p>
        <PaginationBar
          v-if="currentMeta.total > 0"
          class="mt-4 rounded-lg border border-hairline bg-canvas"
          :meta="currentMeta"
          :disabled="isAnnouncementsLoading"
          @change-page="(page: number) => goToAnnouncementPage(page)"
          @change-per-page="(perPage: number) => goToAnnouncementPage(1, perPage)"
        />
        </div>
      </div>
    </section>
  </div>
</template>
