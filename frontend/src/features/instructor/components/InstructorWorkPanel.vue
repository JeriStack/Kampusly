<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { apiClient } from '@/services/api/client'
import { ApiError } from '@/services/api/errors'
import {
  MAX_FONT_SIZE,
  MIN_FONT_SIZE,
  applyTag,
  clampFontSize,
  clearFormatting,
  tinyTextRange,
  toggleBullets,
  toggleTag,
  type EditResult,
  type RichTag,
} from '@/utils/richText'
import AppIcon from '@/components/common/AppIcon.vue'
import FormattedText from '@/components/common/FormattedText.vue'
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

const messageField = ref<HTMLTextAreaElement | null>(null)
const formatUndo = ref<string | null>(null)

const MESSAGE_MAX_LENGTH = 5000
const formatError = ref<string | null>(null)
const fontSize = ref(18)
const textColor = ref('#dc2626')
const highlightColor = ref('#fde047')
const FONT_SIZE_PRESETS = [10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 40, 48, 56, 64, 72, 96]
const toolButtonClass =
  'rounded-md border border-hairline bg-surface px-2.5 py-1 text-[11px] font-semibold text-ink hover:bg-canvas disabled:opacity-50'

/**
 * Runs a formatting edit on the selected text, or on the whole message when nothing is selected.
 * The previous text is kept so the change can be undone.
 */
async function editMessage(edit: (text: string, start: number, end: number) => EditResult): Promise<void> {
  const field = messageField.value
  if (!field || !form.message) return
  let start = field.selectionStart ?? 0
  let end = field.selectionEnd ?? 0
  if (start === end) {
    start = 0
    end = form.message.length
  }

  const result = edit(form.message, start, end)
  if (result.text.length > MESSAGE_MAX_LENGTH) {
    formatError.value = `Formatting would make the message longer than ${MESSAGE_MAX_LENGTH} characters.`
    return
  }
  formatError.value = null
  formatUndo.value = form.message
  form.message = result.text
  await nextTick()
  field.focus()
  field.setSelectionRange(result.selectionStart, result.selectionEnd)
}

function toggleFormat(tag: RichTag): Promise<void> {
  return editMessage((text, start, end) => toggleTag(text, start, end, tag))
}

function applyFontSize(): Promise<void> {
  const size = clampFontSize(Number(fontSize.value) || 16)
  fontSize.value = size
  return editMessage((text, start, end) => applyTag(text, start, end, 'size', size))
}

function applyColor(tag: 'color' | 'bg', value: string): Promise<void> {
  return editMessage((text, start, end) => applyTag(text, start, end, tag, value))
}

function alignMessage(tag: 'left' | 'center' | 'right'): Promise<void> {
  return editMessage((text, start, end) => applyTag(text, start, end, tag))
}

function applyTinyText(): Promise<void> {
  return editMessage(tinyTextRange)
}

function applyBullets(): Promise<void> {
  return editMessage(toggleBullets)
}

function removeFormatting(): Promise<void> {
  return editMessage(clearFormatting)
}

function undoFormat(): void {
  if (formatUndo.value === null) return
  form.message = formatUndo.value
  formatUndo.value = null
  formatError.value = null
  messageField.value?.focus()
}

function resetForm(): void {
  formatUndo.value = null
  formatError.value = null
  editingAnnouncementId.value = null
  form.title = ''
  form.message = ''
  form.target = assignedClasses.value.length
    ? `${assignedClasses.value[0].section_id}:${assignedClasses.value[0].subject_id}`
    : ''
}

function editAnnouncement(announcement: Announcement): void {
  formatUndo.value = null
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
        <div>
          <label for="announcement-message" class="text-xs font-semibold text-ink">Message</label>

          <div
            class="mt-1 flex flex-wrap items-center gap-1.5 rounded-t-lg border border-b-0 border-hairline bg-canvas p-2"
            role="toolbar"
            aria-label="Message formatting. Applies to the selected text, or the whole message if nothing is selected."
          >
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Bold" @click="toggleFormat('b')"><strong>B</strong></button>
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Italic" @click="toggleFormat('i')"><em>I</em></button>
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Underline" @click="toggleFormat('u')"><u>U</u></button>
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Strikethrough" @click="toggleFormat('s')"><s>S</s></button>

            <span class="mx-1 h-5 w-px bg-hairline" aria-hidden="true" />

            <label class="flex items-center gap-1 text-[11px] font-semibold text-muted">
              Size
              <input
                v-model.number="fontSize"
                type="number"
                :min="MIN_FONT_SIZE"
                :max="MAX_FONT_SIZE"
                list="announcement-font-sizes"
                class="w-16 rounded-md border border-hairline bg-surface px-1.5 py-1 text-[11px] text-ink"
                aria-label="Font size in pixels"
                @keydown.enter.prevent="applyFontSize"
              />
              <span>px</span>
            </label>
            <datalist id="announcement-font-sizes">
              <option v-for="size in FONT_SIZE_PRESETS" :key="size" :value="size" />
            </datalist>
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Apply this font size" @click="applyFontSize">Set size</button>

            <span class="mx-1 h-5 w-px bg-hairline" aria-hidden="true" />

            <input v-model="textColor" type="color" class="h-7 w-8 cursor-pointer rounded border border-hairline bg-surface p-0.5" aria-label="Text color" />
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Apply text color" @click="applyColor('color', textColor)">Text color</button>
            <input v-model="highlightColor" type="color" class="h-7 w-8 cursor-pointer rounded border border-hairline bg-surface p-0.5" aria-label="Highlight color" />
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Apply highlight" @click="applyColor('bg', highlightColor)">Highlight</button>

            <span class="mx-1 h-5 w-px bg-hairline" aria-hidden="true" />

            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Align left" @click="alignMessage('left')">Left</button>
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Center" @click="alignMessage('center')">Center</button>
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Align right" @click="alignMessage('right')">Right</button>

            <span class="mx-1 h-5 w-px bg-hairline" aria-hidden="true" />

            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Add or remove bullets on the selected lines" @click="applyBullets">• List</button>
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Turns text into tiny text" @click="applyTinyText">ᵗⁱⁿʸ</button>
            <button type="button" :class="toolButtonClass" :disabled="!form.message" title="Remove all formatting" @click="removeFormatting">Clear</button>
            <button
              v-if="formatUndo !== null"
              type="button"
              class="text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
              @click="undoFormat"
            >
              Undo
            </button>
          </div>
          <textarea
            id="announcement-message"
            ref="messageField"
            v-model="form.message"
            required
            minlength="3"
            maxlength="5000"
            rows="5"
            class="w-full rounded-b-lg border border-hairline bg-canvas px-3 py-2 text-xs font-normal"
            @input="formatUndo = null"
          />
          <p v-if="formatError" class="mt-1 text-[11px] text-rose-700" role="alert">{{ formatError }}</p>
          <p class="mt-1 text-[11px] text-muted">
            Select text, then use the toolbar. With nothing selected, the whole message is changed.
          </p>

          <div v-if="form.message" class="mt-2 rounded-lg border border-dashed border-hairline bg-surface p-3">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-muted">Preview</p>
            <FormattedText :text="form.message" class="mt-1 text-xs text-ink" />
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="submit" :disabled="isSaving || !assignedClasses.length" class="rounded-lg bg-navy px-4 py-2 text-xs font-semibold text-white disabled:opacity-50">
            {{ isSaving ? 'Saving…' : editingAnnouncementId ? 'Save announcement' : 'Publish announcement' }}
          </button>
          <button v-if="editingAnnouncementId" type="button" class="rounded-lg border border-hairline px-4 py-2 text-xs font-semibold" @click="resetForm">Cancel edit</button>
        </div>
      </form>

      <div class="border-t border-hairline p-4">
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
            <span class="rounded-full border border-hairline bg-canvas px-2 py-0.5 text-[10px] font-semibold text-muted">
              {{ activeMeta.total }}
            </span>
          </button>
        </h3>
        <div v-show="isHistoryOpen">
        <div class="mt-3 flex gap-1 rounded-lg bg-canvas p-1 text-xs font-semibold" role="tablist">
          <button
            type="button"
            role="tab"
            class="flex-1 rounded-md px-3 py-1.5 transition-colors"
            :class="announcementTab === 'active' ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink'"
            :aria-selected="announcementTab === 'active'"
            @click="announcementTab = 'active'"
          >Active ({{ activeMeta.total }})</button>
          <button
            type="button"
            role="tab"
            class="flex-1 rounded-md px-3 py-1.5 transition-colors"
            :class="announcementTab === 'archived' ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink'"
            :aria-selected="announcementTab === 'archived'"
            @click="announcementTab = 'archived'"
          >Archived ({{ archivedMeta.total }})</button>
        </div>
        <p v-if="announcementTab === 'archived'" class="mt-3 text-[11px] text-muted">
          Archived announcements are hidden from students and deleted automatically after 7 days.
        </p>
        <ul v-if="visibleAnnouncements.length" class="mt-3 divide-y divide-hairline">
          <li v-for="announcement in visibleAnnouncements" :key="announcement.id" class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
            <div class="min-w-0 flex-1">
              <p class="text-xs font-semibold text-ink">{{ announcement.title }}</p>
              <FormattedText :text="announcement.message" class="mt-1 text-xs text-muted" />
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
