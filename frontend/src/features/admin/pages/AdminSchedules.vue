<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import AppIcon from '@/components/common/AppIcon.vue'
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
})

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

async function createDraft(): Promise<void> {
  isSubmitting.value = true
  formError.value = null
  successMessage.value = null

  try {
    const response = await apiClient.post<{ message: string; data: Schedule }>(
      API_ENDPOINTS.scheduling.schedules,
      {
        ...form,
        school_year: selectedSection.value?.school_year,
        semester: selectedSection.value?.semester,
        status: 'draft',
      },
    )
    schedules.value = [response.data, ...schedules.value]
    successMessage.value = `${response.data.subject_code ?? 'Class'} added as a draft.`
  } catch (err) {
    formError.value =
      err instanceof ApiError ? err.message : 'Failed to create the schedule entry.'
  } finally {
    isSubmitting.value = false
  }
}

const selectedSection = ref<Section | undefined>(undefined)

function updateSelectedSection(): void {
  selectedSection.value = sections.value.find((section) => section.id === form.section_id)
}

onMounted(async () => {
  await loadData()
  updateSelectedSection()
})
</script>

<template>
  <AcademicLayout
    title="Schedule Management"
    subtitle="Create and review class meetings. Entries are saved as drafts until verified and published."
  >
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

    <section class="mb-8 rounded-2xl border border-hairline bg-surface p-5 shadow-sm sm:p-6">
      <div class="mb-5 flex items-start gap-3">
        <div class="rounded-xl bg-navy-50 p-2 text-navy dark:bg-navy-900/40 dark:text-primary-300">
          <AppIcon name="calendar" class="h-5 w-5" />
        </div>
        <div>
          <h2 class="text-base font-bold text-ink">Add a class meeting</h2>
          <p class="mt-1 text-xs text-muted">
            Add each day/time meeting separately. Drafts are not shown as confirmed student schedules.
          </p>
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

      <form v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="createDraft">
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

        <div class="flex items-end sm:col-span-2 lg:col-span-1">
          <button
            type="submit"
            :disabled="isSubmitting || !selectedSection || !subjects.length || !instructors.length || !rooms.length"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-navy-700 focus:outline-none focus:ring-2 focus:ring-navy focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <AppIcon :name="isSubmitting ? 'loader' : 'plus'" class="h-4 w-4" />
            {{ isSubmitting ? 'Saving…' : 'Add Draft Meeting' }}
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
        <p class="mt-1 text-xs text-muted">Draft status is shown with text and is not a published timetable.</p>
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
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AcademicLayout>
</template>
