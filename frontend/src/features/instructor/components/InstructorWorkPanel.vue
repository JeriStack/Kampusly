<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { apiClient } from '@/services/api/client'
import { ApiError } from '@/services/api/errors'
import type { Schedule } from '@/features/academic/types'

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
const announcementTab = ref<'active' | 'archived'>('active')
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

async function loadAnnouncements(): Promise<void> {
  try {
    const [activeResponse, archivedResponse] = await Promise.all([
      apiClient.get<{ data: Announcement[] }>(
        `${API_ENDPOINTS.instructor.announcements}?page=1&per_page=100`,
        { signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS) },
      ),
      apiClient.get<{ data: Announcement[] }>(
        `${API_ENDPOINTS.instructor.announcements}?page=1&per_page=100&archived=1`,
        { signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS) },
      ),
    ])
    announcements.value = activeResponse.data
    archivedAnnouncements.value = archivedResponse.data
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
      announcements.value = [response.data, ...announcements.value]
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
    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800" role="alert">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="loadData">Retry</button>
    </div>
    <p v-if="success" class="rounded-lg border border-teal-200 bg-teal-50 p-3 text-xs text-teal-900" role="status">{{ success }}</p>
    <p v-if="isLoading" class="py-8 text-center text-sm text-muted" role="status">Loading your teaching schedule…</p>

    <section v-else class="rounded-xl border border-hairline bg-surface">
      <div class="flex flex-wrap items-end justify-between gap-3 border-b border-hairline p-4">
        <div>
          <h2 class="text-sm font-bold text-ink">My Teaching Schedule</h2>
          <p class="mt-1 text-xs text-muted">Only classes assigned to your instructor account are shown.</p>
        </div>
        <div class="flex gap-2">
          <label class="text-[11px] font-semibold text-muted">Day
            <select v-model="dayFilter" class="ml-1 rounded-lg border border-hairline bg-canvas px-2 py-1.5 text-xs text-ink">
              <option value="">All days</option>
              <option v-for="day in days" :key="day" :value="day">{{ day }}</option>
            </select>
          </label>
          <label class="text-[11px] font-semibold text-muted">Section
            <select v-model="sectionFilter" class="ml-1 rounded-lg border border-hairline bg-canvas px-2 py-1.5 text-xs text-ink">
              <option value="">All</option>
              <option v-for="section in sections" :key="section.id" :value="section.id">{{ section.name }}</option>
            </select>
          </label>
        </div>
      </div>
      <p v-if="schedules.length === 0" class="p-6 text-center text-xs text-muted">No teaching schedules are assigned to your account.</p>
      <div v-else class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
        <section v-for="group in scheduleGroups" :key="group.day" class="overflow-hidden rounded-lg border border-hairline">
          <h3 class="bg-canvas px-3 py-2 text-xs font-bold text-ink">{{ group.day }}</h3>
          <ul class="divide-y divide-hairline">
            <li v-for="schedule in group.entries" :key="schedule.id" class="p-3">
              <p class="text-xs font-semibold text-ink">{{ schedule.subject_code }} · {{ schedule.subject_name }}</p>
              <p class="mt-1 text-[11px] text-muted">{{ schedule.section_name }} · {{ schedule.room_code }}</p>
              <div class="mt-2 flex items-center justify-between gap-2">
                <span class="text-xs font-semibold tabular-nums text-ink">{{ schedule.start_time.slice(0, 5) }}–{{ schedule.end_time.slice(0, 5) }}</span>
                <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize"
                  :class="schedule.status === 'published' ? 'border-teal-300 bg-teal-50 text-teal-900' : schedule.status === 'draft' ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-rose-300 bg-rose-50 text-rose-900'">{{ schedule.status }}</span>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </section>

    <section class="rounded-xl border border-hairline bg-surface">
      <div class="border-b border-hairline p-4">
        <h2 class="text-sm font-bold text-ink">Section Announcements</h2>
        <p class="mt-1 text-xs text-muted">Students enrolled in the selected section receive an in-app notification.</p>
      </div>
      <form class="grid gap-3 p-4" @submit.prevent="saveAnnouncement">
        <label class="text-xs font-semibold text-ink">Assigned section and subject
          <select v-model="form.target" required :disabled="!assignedClasses.length || editingAnnouncementId !== null" class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs">
            <option v-for="assignment in assignedClasses" :key="`${assignment.section_id}:${assignment.subject_id}`" :value="`${assignment.section_id}:${assignment.subject_id}`">
              {{ assignment.section_name }} · {{ assignment.subject_code }} — {{ assignment.subject_name }}
            </option>
          </select>
        </label>
        <label class="text-xs font-semibold text-ink">Title
          <input v-model="form.title" required minlength="3" maxlength="180" class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs" />
        </label>
        <label class="text-xs font-semibold text-ink">Message
          <textarea v-model="form.message" required minlength="3" maxlength="5000" rows="4" class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs font-normal" />
        </label>
        <div class="flex flex-wrap gap-2">
          <button type="submit" :disabled="isSaving || !assignedClasses.length" class="rounded-lg bg-navy px-4 py-2 text-xs font-semibold text-white disabled:opacity-50">
            {{ isSaving ? 'Saving…' : editingAnnouncementId ? 'Save announcement' : 'Publish announcement' }}
          </button>
          <button v-if="editingAnnouncementId" type="button" class="rounded-lg border border-hairline px-4 py-2 text-xs font-semibold" @click="resetForm">Cancel edit</button>
        </div>
      </form>

      <div class="border-t border-hairline p-4">
        <h3 class="text-xs font-bold text-ink">Announcement history</h3>
        <div class="mt-3 flex gap-1 rounded-lg bg-canvas p-1 text-xs font-semibold" role="tablist">
          <button
            type="button"
            role="tab"
            class="flex-1 rounded-md px-3 py-1.5 transition-colors"
            :class="announcementTab === 'active' ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink'"
            :aria-selected="announcementTab === 'active'"
            @click="announcementTab = 'active'"
          >Active ({{ announcements.length }})</button>
          <button
            type="button"
            role="tab"
            class="flex-1 rounded-md px-3 py-1.5 transition-colors"
            :class="announcementTab === 'archived' ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink'"
            :aria-selected="announcementTab === 'archived'"
            @click="announcementTab = 'archived'"
          >Archived ({{ archivedAnnouncements.length }})</button>
        </div>
        <p v-if="announcementTab === 'archived'" class="mt-3 text-[11px] text-muted">
          Archived announcements are hidden from students and deleted automatically after 7 days.
        </p>
        <ul v-if="visibleAnnouncements.length" class="mt-3 divide-y divide-hairline">
          <li v-for="announcement in visibleAnnouncements" :key="announcement.id" class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
            <div class="min-w-0 flex-1">
              <p class="text-xs font-semibold text-ink">{{ announcement.title }}</p>
              <p class="mt-1 whitespace-pre-wrap text-xs text-muted">{{ announcement.message }}</p>
              <p class="mt-1 text-[10px] text-muted">
                {{ assignedClasses.find((item) => item.section_id === announcement.section_id && item.subject_id === announcement.subject_id)?.section_name || 'Assigned section' }}
                · {{ announcement.status === 'withdrawn' ? 'Deleted' : announcement.status }}
              </p>
            </div>
            <div v-if="announcementTab === 'archived'" class="flex gap-2">
              <button
                type="button"
                class="text-xs font-semibold text-primary-700 hover:underline disabled:opacity-50"
                :disabled="busyAnnouncementId === announcement.id"
                @click="setAnnouncementArchived(announcement, false)"
              >Restore</button>
            </div>
            <div v-else-if="announcement.status !== 'withdrawn'" class="flex gap-2">
              <button type="button" class="text-xs font-semibold text-primary-700 hover:underline" @click="editAnnouncement(announcement)">Edit</button>
              <button
                type="button"
                class="text-xs font-semibold text-rose-700 hover:underline disabled:opacity-50"
                :disabled="busyAnnouncementId === announcement.id"
                @click="setAnnouncementArchived(announcement, true)"
              >Archive</button>
            </div>
          </li>
        </ul>
        <p v-else-if="isAnnouncementsLoading" class="mt-2 text-xs text-muted">Loading announcements…</p>
        <p v-else class="mt-2 text-xs text-muted">
          {{ announcementTab === 'active' ? 'No announcements published yet.' : 'No archived announcements.' }}
        </p>
      </div>
    </section>
  </div>
</template>
