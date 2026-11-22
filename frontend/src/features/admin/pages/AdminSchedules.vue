<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import AppIcon from '@/components/common/AppIcon.vue'
import ScheduleChangeRequestsPanel from '@/features/admin/components/ScheduleChangeRequestsPanel.vue'
import AcademicLayout from '@/features/academic/components/AcademicLayout.vue'
import PaginationBar from '@/features/academic/components/PaginationBar.vue'
import ConfirmDialog from '@/features/academic/components/ConfirmDialog.vue'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { apiClient } from '@/services/api/client'
import { ApiError } from '@/services/api/errors'
import type {
  Instructor,
  PaginatedResponse,
  PaginationMeta,
  Room,
  Schedule,
  Section,
  Subject,
} from '@/features/academic/types'

const subjects = ref<Subject[]>([])
const instructors = ref<Instructor[]>([])
const rooms = ref<Room[]>([])
const sections = ref<Section[]>([])
const schedules = ref<Schedule[]>([])
const scheduleMeta = ref<PaginationMeta>({ page: 1, per_page: 10, total: 0, total_pages: 0 })
const isSchedulesLoading = ref(false)
const isLoading = ref(true)
const isSubmitting = ref(false)
const error = ref<string | null>(null)
const formError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

const form = reactive({
  subject_id: 0,
  instructor_id: 0,
  room_id: 0,
  section_id: 0,
  day_of_week: 'Monday',
  start_time: '08:00',
  end_time: '09:00',
  status: 'draft' as 'draft' | 'published',
})

const selectedSection = ref<Section | undefined>(undefined)
const editingSchedule = ref<Schedule | null>(null)
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

function collectionUrl(path: string): string {
  return `${path}?page=1&per_page=100&status=active`
}

async function fetchSchedules(page: number): Promise<void> {
  const response = await apiClient.get<PaginatedResponse<Schedule>>(
    `${API_ENDPOINTS.scheduling.schedules}?page=${page}&per_page=${scheduleMeta.value.per_page}`,
  )

  // The last entry on a page may have just been removed; step back to the last page.
  if (response.data.length === 0 && response.meta.total_pages > 0 && page > response.meta.total_pages) {
    await fetchSchedules(response.meta.total_pages)
    return
  }

  schedules.value = response.data
  scheduleMeta.value = { ...response.meta, page: response.meta.total === 0 ? 1 : response.meta.page }
}

/** Reloads the schedule table (e.g. after adding an entry) and reports failures on the page. */
async function refreshSchedules(page = scheduleMeta.value.page): Promise<void> {
  isSchedulesLoading.value = true
  try {
    await fetchSchedules(page)
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Failed to load schedule entries.'
  } finally {
    isSchedulesLoading.value = false
  }
}

function handleSchedulePageChange(page: number): void {
  void refreshSchedules(page)
}

function handleSchedulePerPageChange(perPage: number): void {
  scheduleMeta.value.per_page = perPage
  void refreshSchedules(1)
}

async function loadData(): Promise<void> {
  isLoading.value = true
  error.value = null

  try {
    const [subjectResponse, instructorResponse, roomResponse, sectionResponse] =
      await Promise.all([
        apiClient.get<PaginatedResponse<Subject>>(collectionUrl(API_ENDPOINTS.academic.subjects)),
        apiClient.get<PaginatedResponse<Instructor>>(
          collectionUrl(API_ENDPOINTS.academic.instructors),
        ),
        apiClient.get<PaginatedResponse<Room>>(collectionUrl(API_ENDPOINTS.academic.rooms)),
        apiClient.get<PaginatedResponse<Section>>(collectionUrl(API_ENDPOINTS.academic.sections)),
        fetchSchedules(scheduleMeta.value.page),
      ])

    subjects.value = subjectResponse.data
    instructors.value = instructorResponse.data
    rooms.value = roomResponse.data
    sections.value = sectionResponse.data

    if (sections.value.length > 0) {
      form.section_id = sections.value[0].id
    }
    if (subjects.value.length > 0) {
      form.subject_id = subjects.value[0].id
    }
    if (instructors.value.length > 0) {
      form.instructor_id = instructors.value[0].id
    }
    if (rooms.value.length > 0) {
      form.room_id = rooms.value[0].id
    }
  } catch (err) {
    error.value =
      err instanceof ApiError ? err.message : 'Failed to load schedule and academic catalog data.'
  } finally {
    isLoading.value = false
  }
}

function openCreateForm(): void {
  editingSchedule.value = null
  form.subject_id = subjects.value[0]?.id ?? 0
  form.instructor_id = instructors.value[0]?.id ?? 0
  form.room_id = rooms.value[0]?.id ?? 0
  form.section_id = sections.value[0]?.id ?? 0
  form.day_of_week = 'Monday'
  form.start_time = '08:00'
  form.end_time = '09:00'
  form.status = 'draft'
  updateSelectedSection()
}

function openEditForm(schedule: Schedule): void {
  editingSchedule.value = schedule
  form.subject_id = schedule.subject_id
  form.instructor_id = schedule.instructor_id
  form.room_id = schedule.room_id
  form.section_id = schedule.section_id
  form.day_of_week = schedule.day_of_week
  form.start_time = schedule.start_time.slice(0, 5)
  form.end_time = schedule.end_time.slice(0, 5)
  form.status = schedule.status === 'published' ? 'published' : 'draft'
  updateSelectedSection()
  formError.value = null
  successMessage.value = null
}

async function saveSchedule(): Promise<void> {
  if (!selectedSection.value) {
    formError.value = 'Select a section with an academic term before saving.'
    return
  }
  if (form.status === 'published' && !window.confirm('Publish this class meeting to student schedules?')) {
    return
  }

  isSubmitting.value = true
  formError.value = null
  successMessage.value = null

  try {
    const payload = {
      ...form,
      school_year: selectedSection.value.school_year,
      semester: selectedSection.value.semester,
    }
    let response: { data: Schedule }
    if (editingSchedule.value) {
      response = await apiClient.patch<{ message: string; data: Schedule }>(
        API_ENDPOINTS.scheduling.schedule(editingSchedule.value.id),
        payload,
      )
      schedules.value = schedules.value.map((schedule) =>
        schedule.id === response.data.id ? response.data : schedule,
      )
      successMessage.value = `${response.data.subject_code ?? 'Class'} meeting updated.`
      editingSchedule.value = null
    } else {
      response = await apiClient.post<{ message: string; data: Schedule }>(
        API_ENDPOINTS.scheduling.schedules,
        payload,
      )
      successMessage.value = `${response.data.subject_code ?? 'Class'} added as ${response.data.status}.`
      await refreshSchedules()
    }
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save the schedule entry.'
  } finally {
    isSubmitting.value = false
  }
}

function updateSelectedSection(): void {
  selectedSection.value = sections.value.find((section) => section.id === form.section_id)
}

async function changeScheduleStatus(schedule: Schedule, status: Schedule['status']): Promise<void> {
  const action = status === 'published' ? 'publish' : 'cancel'
  if (!window.confirm(`Are you sure you want to ${action} ${schedule.subject_code ?? 'this class'}?`)) {
    return
  }

  formError.value = null
  successMessage.value = null
  try {
    const response = await apiClient.patch<{ message: string; data: Schedule }>(
      API_ENDPOINTS.scheduling.schedule(schedule.id),
      {
        subject_id: schedule.subject_id,
        instructor_id: schedule.instructor_id,
        room_id: schedule.room_id,
        section_id: schedule.section_id,
        school_year: schedule.school_year,
        semester: schedule.semester,
        day_of_week: schedule.day_of_week,
        start_time: schedule.start_time.slice(0, 5),
        end_time: schedule.end_time.slice(0, 5),
        status,
      },
    )
    schedules.value = schedules.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
    successMessage.value = `${response.data.subject_code ?? 'Class'} ${status}.`
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : `Failed to ${action} the class.`
  }
}

const isArchiveDialogOpen = ref(false)
const archivingSchedule = ref<Schedule | null>(null)
const isArchivingSchedule = ref(false)

function openArchiveScheduleDialog(schedule: Schedule): void {
  archivingSchedule.value = schedule
  isArchiveDialogOpen.value = true
}

async function handleConfirmArchiveSchedule(): Promise<void> {
  if (!archivingSchedule.value) return
  isArchivingSchedule.value = true
  formError.value = null
  successMessage.value = null

  const target = archivingSchedule.value
  try {
    await apiClient.post(API_ENDPOINTS.admin.archiveRecord('schedules', target.id), {})
    isArchiveDialogOpen.value = false
    archivingSchedule.value = null
    successMessage.value = `${target.subject_code ?? 'Schedule'} (${target.section_name ?? ''}) moved to Archives.`
    await refreshSchedules()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to archive schedule.'
    isArchiveDialogOpen.value = false
  } finally {
    isArchivingSchedule.value = false
  }
}

onMounted(async () => {
  await loadData()
  updateSelectedSection()
})
</script>

<template>
  <AcademicLayout
    title="Schedule Management"
    subtitle="Create, edit, publish, and cancel class meetings. Published changes are checked for conflicts."
  >
    <template #actions>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl bg-action px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-action-hover active:bg-action-active focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-canvas"
        @click="openCreateForm"
      >
        <AppIcon name="plus" class="h-4 w-4" />
        <span>Add meeting</span>
      </button>
    </template>

    <div
      v-if="error"
      role="alert"
      class="mb-5 rounded-xl border border-rose-200 bg-rose-50/90 p-4 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200"
    >
      <div class="flex items-start justify-between gap-3">
        <p>{{ error }}</p>
        <button type="button" class="font-semibold underline" @click="loadData">Retry</button>
      </div>
    </div>

    <div
      v-if="successMessage"
      role="status"
      class="mb-5 rounded-xl border border-teal-200 bg-teal-50/90 p-3 text-sm text-teal-900 dark:border-teal-500/30 dark:bg-teal-950/40 dark:text-teal-200"
    >
      {{ successMessage }}
    </div>

    <ScheduleChangeRequestsPanel />

    <section class="mb-8 rounded-2xl border border-hairline bg-surface p-5 shadow-card sm:p-6">
      <div class="mb-5 flex items-center gap-3 border-b border-hairline pb-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300">
          <AppIcon name="calendar" class="h-5 w-5" />
        </span>
        <div>
          <h2 class="text-base font-bold text-ink">
            {{ editingSchedule ? 'Edit class meeting' : 'Add a class meeting' }}
          </h2>
          <p class="mt-0.5 text-xs text-subtle">Each day/time meeting is managed as a separate entry.</p>
        </div>
      </div>

      <div
        v-if="formError"
        role="alert"
        class="mb-4 rounded-xl border border-rose-200 bg-rose-50/90 px-3.5 py-2.5 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200"
      >
        {{ formError }}
      </div>

      <div v-if="isLoading" class="py-8 text-center text-sm text-muted" role="status">
        Loading academic catalogs…
      </div>

      <form v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="saveSchedule">
        <label class="block text-xs font-semibold text-ink">
          Subject
          <select
            v-model.number="form.subject_id"
            required
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          >
            <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
              {{ subject.subject_code }} — {{ subject.subject_name }}
            </option>
          </select>
        </label>

        <label class="block text-xs font-semibold text-ink">
          Instructor
          <select
            v-model.number="form.instructor_id"
            required
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          >
            <option v-for="instructor in instructors" :key="instructor.id" :value="instructor.id">
              {{ instructor.full_name }}{{ instructor.employee_number.startsWith('TEMP-') ? ' (placeholder)' : '' }}
            </option>
          </select>
        </label>

        <label class="block text-xs font-semibold text-ink">
          Room
          <select
            v-model.number="form.room_id"
            required
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          >
            <option v-for="room in rooms" :key="room.id" :value="room.id">
              {{ room.room_code }} — {{ room.room_name }}
            </option>
          </select>
        </label>

        <label class="block text-xs font-semibold text-ink">
          Section
          <select
            v-model.number="form.section_id"
            required
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
            @change="updateSelectedSection"
          >
            <option v-for="section in sections" :key="section.id" :value="section.id">
              {{ section.section_name }}
            </option>
          </select>
        </label>

        <div class="block text-xs font-semibold text-ink">
          Academic term
          <div class="mt-1.5 flex w-full items-center rounded-xl border border-hairline/80 bg-canvas/70 px-3 py-2.5 text-sm font-medium text-muted">
            {{ selectedSection ? `${selectedSection.school_year} · ${selectedSection.semester} semester` : 'Select a section' }}
          </div>
        </div>

        <label class="block text-xs font-semibold text-ink">
          Day
          <select
            v-model="form.day_of_week"
            required
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          >
            <option v-for="day in days" :key="day" :value="day">{{ day }}</option>
          </select>
        </label>

        <label class="block text-xs font-semibold text-ink">
          Start time
          <input
            v-model="form.start_time"
            type="time"
            required
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          />
        </label>

        <label class="block text-xs font-semibold text-ink">
          End time
          <input
            v-model="form.end_time"
            type="time"
            required
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          />
        </label>

        <label class="block text-xs font-semibold text-ink">
          Status
          <select
            v-model="form.status"
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          >
            <option value="draft">Draft — not shown as confirmed</option>
            <option value="published">Published — visible to students</option>
          </select>
        </label>

        <div class="flex items-center justify-end gap-2 pt-2 sm:col-span-2 lg:col-span-3">
          <button
            v-if="editingSchedule"
            type="button"
            class="rounded-xl border border-hairline bg-canvas px-4 py-2.5 text-sm font-semibold text-muted transition-colors hover:text-ink"
            @click="openCreateForm"
          >
            Cancel edit
          </button>
          <button
            type="submit"
            :disabled="isSubmitting || !selectedSection || !subjects.length || !instructors.length || !rooms.length"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-action px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-action-hover active:bg-action-active focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-60"
          >
            <AppIcon :name="isSubmitting ? 'loader' : 'plus'" :class="['h-4 w-4', isSubmitting && 'animate-spin']" />
            {{ isSubmitting ? 'Saving…' : editingSchedule ? 'Save Changes' : 'Add Meeting' }}
          </button>
        </div>
      </form>

      <p
        v-if="!isLoading && (!subjects.length || !instructors.length || !rooms.length || !sections.length)"
        class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-200"
      >
        Add at least one active subject, instructor, room, and section before creating a schedule.
      </p>
    </section>

    <section class="overflow-hidden rounded-2xl border border-hairline bg-surface shadow-card">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-hairline px-5 py-4 sm:px-6">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300">
            <AppIcon name="calendar" class="h-5 w-5" />
          </span>
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-base font-bold text-ink">Schedule entries</h2>
              <span class="inline-flex items-center rounded-full border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-blue-700 dark:text-blue-300">
                {{ scheduleMeta.total }}
              </span>
            </div>
            <p class="mt-0.5 text-xs text-subtle">Use the actions to edit, publish, republish, or cancel entries.</p>
          </div>
        </div>
      </div>

      <div v-if="isLoading" class="space-y-2.5 p-5" role="status">
        <div v-for="n in 3" :key="n" class="h-14 animate-pulse rounded-xl bg-canvas" />
      </div>
      <div v-else-if="scheduleMeta.total === 0" class="flex flex-col items-center justify-center px-6 py-12 text-center">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-canvas text-subtle ring-1 ring-hairline">
          <AppIcon name="calendar" class="h-5 w-5" />
        </span>
        <p class="mt-3 text-base font-semibold text-ink">No schedule entries yet</p>
        <p class="mt-1 text-xs text-subtle">Use the form above to add the first class meeting.</p>
      </div>
      <div v-else class="overflow-x-auto" :class="{ 'opacity-60': isSchedulesLoading }">
        <table class="w-full min-w-[920px] text-center text-sm">
          <thead class="border-b border-hairline bg-canvas/70 text-[11px] uppercase tracking-wider text-subtle">
            <tr>
              <th scope="col" class="px-5 py-3.5 text-left font-semibold">Class</th>
              <th scope="col" class="px-4 py-3.5 font-semibold">Section</th>
              <th scope="col" class="px-4 py-3.5 font-semibold">Meeting</th>
              <th scope="col" class="px-4 py-3.5 font-semibold">Room</th>
              <th scope="col" class="px-4 py-3.5 font-semibold">Instructor</th>
              <th scope="col" class="px-4 py-3.5 font-semibold">Status</th>
              <th scope="col" class="px-5 py-3.5 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline/70">
            <tr
              v-for="schedule in schedules"
              :key="schedule.id"
              class="transition-colors hover:bg-canvas/60"
            >
              <td class="px-5 py-3.5 text-left">
                <span class="inline-block rounded-md border border-hairline bg-canvas px-2 py-0.5 font-mono text-xs font-bold text-ink">
                  {{ schedule.subject_code }}
                </span>
                <p class="mt-1 text-xs font-medium text-muted">{{ schedule.subject_name }}</p>
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span class="inline-flex rounded-md border border-hairline bg-canvas px-2.5 py-1 text-xs font-semibold text-ink">
                  {{ schedule.section_name }}
                </span>
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-500/20 bg-sky-500/10 px-2.5 py-1 text-xs font-semibold tabular-nums text-navy dark:text-sky-300">
                  <AppIcon name="clock" class="h-3.5 w-3.5" />
                  {{ schedule.day_of_week }}, {{ schedule.start_time.slice(0, 5) }}–{{ schedule.end_time.slice(0, 5) }}
                </span>
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span class="inline-block rounded-md border border-hairline bg-canvas px-2.5 py-1 font-mono text-xs font-semibold text-ink">
                  {{ schedule.room_code }}
                </span>
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap font-medium text-ink">{{ schedule.instructor_name }}</td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span
                  class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize"
                  :class="{
                    'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300': schedule.status === 'draft',
                    'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300': schedule.status === 'published',
                    'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-300': schedule.status === 'cancelled',
                  }"
                >
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="{
                      'bg-amber-500': schedule.status === 'draft',
                      'bg-emerald-500': schedule.status === 'published',
                      'bg-rose-500': schedule.status === 'cancelled',
                    }"
                    aria-hidden="true"
                  />
                  {{ schedule.status }}
                </span>
              </td>
              <td class="px-5 py-3.5 whitespace-nowrap">
                <div class="inline-flex items-center justify-center gap-2">
                  <button
                    type="button"
                    class="rounded-lg border border-hairline bg-canvas px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:border-action/40 hover:text-action dark:hover:text-sky-300"
                    @click="openEditForm(schedule)"
                  >
                    Edit
                  </button>
                  <button
                    v-if="schedule.status !== 'published'"
                    type="button"
                    class="rounded-lg border border-emerald-500/30 bg-canvas px-3 py-1.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                    @click="changeScheduleStatus(schedule, 'published')"
                  >
                    {{ schedule.status === 'cancelled' ? 'Republish' : 'Publish' }}
                  </button>
                  <button
                    v-if="schedule.status === 'published'"
                    type="button"
                    class="rounded-lg border border-rose-500/30 bg-canvas px-3 py-1.5 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                    @click="changeScheduleStatus(schedule, 'cancelled')"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    title="Archive schedule"
                    aria-label="Archive schedule"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-hairline bg-canvas text-amber-600 transition-colors hover:border-amber-300 hover:bg-amber-50 dark:text-amber-400 dark:hover:border-amber-500/40 dark:hover:bg-amber-950/40"
                    @click="openArchiveScheduleDialog(schedule)"
                  >
                    <AppIcon name="archive" class="h-3.5 w-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginationBar
        v-if="!isLoading && scheduleMeta.total > 0"
        class="border-t border-hairline bg-canvas"
        :meta="scheduleMeta"
        :disabled="isSchedulesLoading"
        @change-page="handleSchedulePageChange"
        @change-per-page="handleSchedulePerPageChange"
      />
    </section>

    <ConfirmDialog
      :open="isArchiveDialogOpen"
      title="Archive Schedule"
      :message="`Move ${archivingSchedule?.subject_code ?? 'this schedule'} (${archivingSchedule?.section_name ?? ''}) on ${archivingSchedule?.day_of_week ?? ''} to Archives? It will be hidden from timetables and freed from conflict checks until restored.`"
      confirm-label="Archive Schedule"
      variant="warning"
      :is-loading="isArchivingSchedule"
      @confirm="handleConfirmArchiveSchedule"
      @cancel="isArchiveDialogOpen = false"
    />
  </AcademicLayout>
</template>
