<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import AppIcon from '@/components/common/AppIcon.vue'
import ScheduleChangeRequestsPanel from '@/features/admin/components/ScheduleChangeRequestsPanel.vue'
import AcademicLayout from '@/features/academic/components/AcademicLayout.vue'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { apiClient } from '@/services/api/client'
import { ApiError } from '@/services/api/errors'
import type {
  Instructor,
  PaginatedResponse,
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

async function loadData(): Promise<void> {
  isLoading.value = true
  error.value = null

  try {
    const [subjectResponse, instructorResponse, roomResponse, sectionResponse, scheduleResponse] =
      await Promise.all([
        apiClient.get<PaginatedResponse<Subject>>(collectionUrl(API_ENDPOINTS.academic.subjects)),
        apiClient.get<PaginatedResponse<Instructor>>(
          collectionUrl(API_ENDPOINTS.academic.instructors),
        ),
        apiClient.get<PaginatedResponse<Room>>(collectionUrl(API_ENDPOINTS.academic.rooms)),
        apiClient.get<PaginatedResponse<Section>>(collectionUrl(API_ENDPOINTS.academic.sections)),
        apiClient.get<PaginatedResponse<Schedule>>(
          `${API_ENDPOINTS.scheduling.schedules}?page=1&per_page=100`,
        ),
      ])

    subjects.value = subjectResponse.data
    instructors.value = instructorResponse.data
    rooms.value = roomResponse.data
    sections.value = sectionResponse.data
    schedules.value = scheduleResponse.data

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
      schedules.value = [response.data, ...schedules.value]
      successMessage.value = `${response.data.subject_code ?? 'Class'} added as ${response.data.status}.`
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
        class="inline-flex items-center gap-1.5 rounded-lg bg-navy px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-navy-700"
        @click="openCreateForm"
      >
        <AppIcon name="plus" class="h-3.5 w-3.5" />
        <span>Add meeting</span>
      </button>
    </template>

    <div
      v-if="error"
      role="alert"
      class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200"
    >
      <div class="flex items-start justify-between gap-3">
        <p>{{ error }}</p>
        <button type="button" class="font-semibold underline" @click="loadData">Retry</button>
      </div>
    </div>

    <div
      v-if="successMessage"
      role="status"
      class="mb-5 rounded-xl border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900 dark:border-teal-900 dark:bg-teal-950/30 dark:text-teal-200"
    >
      {{ successMessage }}
    </div>

    <ScheduleChangeRequestsPanel />

    <section class="mb-8 rounded-2xl border border-hairline bg-surface p-5 shadow-sm sm:p-6">
      <div class="mb-5 flex items-start gap-3">
        <div class="rounded-xl bg-navy-50 p-2 text-navy dark:bg-navy-900/40 dark:text-primary-300">
          <AppIcon name="calendar" class="h-5 w-5" />
        </div>
        <div>
          <h2 class="text-base font-bold text-ink">Add a class meeting</h2>
          <p class="mt-1 text-xs text-muted">Each day/time meeting is managed as a separate entry.</p>
        </div>
      </div>

      <div
        v-if="formError"
        role="alert"
        class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200"
      >
        {{ formError }}
      </div>

      <div v-if="isLoading" class="py-8 text-center text-sm text-muted" role="status">
        Loading academic catalogs…
      </div>

      <form v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="saveSchedule">
        <label class="text-xs font-semibold text-ink">
          Subject
          <select
            v-model.number="form.subject_id"
            required
            class="mt-1.5 w-full rounded-lg border border-hairline bg-canvas px-3 py-2.5 text-sm focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/20"
          >
            <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
              {{ subject.subject_code }} — {{ subject.subject_name }}
            </option>
          </select>
        </label>

        <label class="text-xs font-semibold text-ink">
          Instructor
          <select
            v-model.number="form.instructor_id"
            required
            class="mt-1.5 w-full rounded-lg border border-hairline bg-canvas px-3 py-2.5 text-sm focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/20"
          >
            <option v-for="instructor in instructors" :key="instructor.id" :value="instructor.id">
              {{ instructor.full_name }}{{ instructor.employee_number.startsWith('TEMP-') ? ' (placeholder)' : '' }}
            </option>
          </select>
        </label>

        <label class="text-xs font-semibold text-ink">
          Room
          <select
            v-model.number="form.room_id"
            required
            class="mt-1.5 w-full rounded-lg border border-hairline bg-canvas px-3 py-2.5 text-sm focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/20"
          >
            <option v-for="room in rooms" :key="room.id" :value="room.id">
              {{ room.room_code }} — {{ room.room_name }}
            </option>
          </select>
        </label>

        <label class="text-xs font-semibold text-ink">
          Section
          <select
            v-model.number="form.section_id"
            required
            class="mt-1.5 w-full rounded-lg border border-hairline bg-canvas px-3 py-2.5 text-sm focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/20"
            @change="updateSelectedSection"
          >
            <option v-for="section in sections" :key="section.id" :value="section.id">
              {{ section.section_name }}
            </option>
          </select>
        </label>

        <div class="rounded-lg border border-hairline bg-canvas px-3 py-2.5 text-xs text-muted">
          Academic term
          <p class="mt-1 text-sm font-semibold text-ink">
            {{ selectedSection ? `${selectedSection.school_year} · ${selectedSection.semester} semester` : 'Select a section' }}
          </p>
        </div>

        <label class="text-xs font-semibold text-ink">
          Day
          <select
            v-model="form.day_of_week"
            required
            class="mt-1.5 w-full rounded-lg border border-hairline bg-canvas px-3 py-2.5 text-sm focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/20"
          >
            <option v-for="day in days" :key="day" :value="day">{{ day }}</option>
          </select>
        </label>

        <label class="text-xs font-semibold text-ink">
          Start time
          <input
            v-model="form.start_time"
            type="time"
            required
            class="mt-1.5 w-full rounded-lg border border-hairline bg-canvas px-3 py-2.5 text-sm focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/20"
          />
        </label>

        <label class="text-xs font-semibold text-ink">
          End time
          <input
            v-model="form.end_time"
            type="time"
            required
            class="mt-1.5 w-full rounded-lg border border-hairline bg-canvas px-3 py-2.5 text-sm focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/20"
          />
        </label>

        <label class="text-xs font-semibold text-ink">
          Status
          <select
            v-model="form.status"
            class="mt-1.5 w-full rounded-lg border border-hairline bg-canvas px-3 py-2.5 text-sm focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/20"
          >
            <option value="draft">Draft — not shown as confirmed</option>
            <option value="published">Published — visible to students</option>
          </select>
        </label>

        <div class="flex items-end sm:col-span-2 lg:col-span-1">
          <button
            type="submit"
            :disabled="isSubmitting || !selectedSection || !subjects.length || !instructors.length || !rooms.length"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-navy-700 focus:outline-none focus:ring-2 focus:ring-navy focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <AppIcon :name="isSubmitting ? 'loader' : 'plus'" class="h-4 w-4" />
            {{ isSubmitting ? 'Saving…' : editingSchedule ? 'Save Changes' : 'Add Meeting' }}
          </button>
        </div>
      </form>

      <p
        v-if="!isLoading && (!subjects.length || !instructors.length || !rooms.length || !sections.length)"
        class="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
      >
        Add at least one active subject, instructor, room, and section before creating a schedule.
      </p>
    </section>

    <section class="overflow-hidden rounded-2xl border border-hairline bg-surface shadow-sm">
      <div class="border-b border-hairline px-5 py-4">
        <h2 class="text-base font-bold text-ink">Schedule entries</h2>
        <p class="mt-1 text-xs text-muted">Use the actions to edit, publish, republish, or cancel entries.</p>
      </div>

      <div v-if="isLoading" class="p-8 text-center text-sm text-muted" role="status">
        Loading schedule entries…
      </div>
      <div v-else-if="schedules.length === 0" class="p-10 text-center">
        <p class="text-sm font-semibold text-ink">No schedule entries yet</p>
        <p class="mt-1 text-xs text-muted">Use the form above to add the first class meeting.</p>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
          <thead class="bg-canvas text-[11px] uppercase tracking-wide text-muted">
            <tr>
              <th scope="col" class="px-5 py-3 font-semibold">Class</th>
              <th scope="col" class="px-5 py-3 font-semibold">Section</th>
              <th scope="col" class="px-5 py-3 font-semibold">Meeting</th>
              <th scope="col" class="px-5 py-3 font-semibold">Room</th>
              <th scope="col" class="px-5 py-3 font-semibold">Instructor</th>
              <th scope="col" class="px-5 py-3 font-semibold">Status</th>
              <th scope="col" class="px-5 py-3 text-right font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline">
            <tr v-for="schedule in schedules" :key="schedule.id">
              <td class="px-5 py-3">
                <p class="font-semibold text-ink">{{ schedule.subject_code }}</p>
                <p class="mt-0.5 text-xs text-muted">{{ schedule.subject_name }}</p>
              </td>
              <td class="px-5 py-3 text-ink">{{ schedule.section_name }}</td>
              <td class="px-5 py-3 whitespace-nowrap text-ink">
                {{ schedule.day_of_week }}, {{ schedule.start_time.slice(0, 5) }}–{{ schedule.end_time.slice(0, 5) }}
              </td>
              <td class="px-5 py-3 text-ink">{{ schedule.room_code }}</td>
              <td class="px-5 py-3 text-ink">{{ schedule.instructor_name }}</td>
              <td class="px-5 py-3">
                <span
                  class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                  :class="{
                    'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200': schedule.status === 'draft',
                    'border-teal-300 bg-teal-50 text-teal-900 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-200': schedule.status === 'published',
                    'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200': schedule.status === 'cancelled',
                  }"
                >
                  {{ schedule.status }}
                </span>
              </td>
              <td class="px-5 py-3">
                <div class="flex justify-end gap-2">
                  <button
                    type="button"
                    class="rounded-md border border-hairline px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy"
                    @click="openEditForm(schedule)"
                  >
                    Edit
                  </button>
                  <button
                    v-if="schedule.status !== 'published'"
                    type="button"
                    class="rounded-md border border-teal-300 px-2.5 py-1.5 text-xs font-semibold text-teal-800 hover:bg-teal-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 dark:border-teal-800 dark:text-teal-200 dark:hover:bg-teal-950/40"
                    @click="changeScheduleStatus(schedule, 'published')"
                  >
                    {{ schedule.status === 'cancelled' ? 'Republish' : 'Publish' }}
                  </button>
                  <button
                    v-if="schedule.status === 'published'"
                    type="button"
                    class="rounded-md border border-rose-300 px-2.5 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 dark:border-rose-800 dark:text-rose-200 dark:hover:bg-rose-950/40"
                    @click="changeScheduleStatus(schedule, 'cancelled')"
                  >
                    Cancel
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AcademicLayout>
</template>
