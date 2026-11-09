<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { apiClient } from '@/services/api/client'
import { ApiError } from '@/services/api/errors'

interface ScheduleChangeRequest {
  id: number
  status: 'pending' | 'approved' | 'rejected'
  reason: string
  review_note: string | null
  student_name: string
  student_number: string
  section_name: string
  subject_code: string
  subject_name: string
  current_day_of_week: string
  current_start_time: string
  current_end_time: string
  current_room_code: string
  preferred_day_of_week: string | null
  preferred_start_time: string | null
  preferred_end_time: string | null
  preferred_room_code: string | null
  created_at: string
}

interface RequestResponse {
  data: ScheduleChangeRequest[]
  meta: { page: number; total_pages: number }
}

const requests = ref<ScheduleChangeRequest[]>([])
const page = ref(1)
const totalPages = ref(0)
const isLoading = ref(true)
const busyId = ref<number | null>(null)
const error = ref<string | null>(null)
const notes = ref<Record<number, string>>({})

async function loadRequests(): Promise<void> {
  isLoading.value = true
  error.value = null
  try {
    const response = await apiClient.get<RequestResponse>(
      `${API_ENDPOINTS.scheduling.scheduleChangeRequests}?status=pending&page=${page.value}&per_page=20`,
    )
    requests.value = response.data
    totalPages.value = response.meta.total_pages
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Unable to load schedule change requests.'
  } finally {
    isLoading.value = false
  }
}

async function review(request: ScheduleChangeRequest, status: 'approved' | 'rejected'): Promise<void> {
  const action = status === 'approved' ? 'approve' : 'reject'
  if (!window.confirm(`Are you sure you want to ${action} this request?`)) return
  busyId.value = request.id
  error.value = null
  try {
    await apiClient.patch(API_ENDPOINTS.scheduling.scheduleChangeRequest(request.id), {
      status,
      review_note: notes.value[request.id]?.trim() || null,
    })
    requests.value = requests.value.filter((item) => item.id !== request.id)
    if (requests.value.length === 0 && page.value > 1) page.value -= 1
    await loadRequests()
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : `Unable to ${action} this request.`
  } finally {
    busyId.value = null
  }
}

onMounted(loadRequests)
</script>

<template>
  <section class="mb-8 overflow-hidden rounded-2xl border border-hairline bg-surface shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-hairline px-5 py-4">
      <div>
        <h2 class="text-base font-bold text-ink">Student schedule change requests</h2>
        <p class="mt-1 text-xs text-muted">Approving or rejecting a request does not alter the section schedule.</p>
      </div>
      <span class="rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
        Pending review
      </span>
    </div>

    <p v-if="error" class="m-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800" role="alert">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="loadRequests">Retry</button>
    </p>
    <p v-else-if="isLoading" class="p-8 text-center text-sm text-muted" role="status">Loading requests…</p>
    <p v-else-if="requests.length === 0" class="p-8 text-center text-sm text-muted">
      There are no pending schedule change requests.
    </p>

    <ul v-else class="divide-y divide-hairline">
      <li v-for="request in requests" :key="request.id" class="space-y-3 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="text-sm font-bold text-ink">{{ request.student_name }} · {{ request.student_number }}</h3>
            <p class="mt-1 text-xs text-muted">{{ request.section_name }} · {{ request.subject_code }} — {{ request.subject_name }}</p>
          </div>
          <time class="text-[11px] text-muted">{{ request.created_at }}</time>
        </div>
        <div class="grid gap-3 text-xs sm:grid-cols-2">
          <p class="rounded-lg bg-canvas p-3">
            <span class="block font-semibold text-ink">Current</span>
            {{ request.current_day_of_week }}, {{ request.current_start_time.slice(0, 5) }}–{{ request.current_end_time.slice(0, 5) }}
            · {{ request.current_room_code }}
          </p>
          <p class="rounded-lg bg-canvas p-3">
            <span class="block font-semibold text-ink">Preferred</span>
            {{ request.preferred_day_of_week || 'No day preference' }}
            <template v-if="request.preferred_start_time && request.preferred_end_time">
              , {{ request.preferred_start_time.slice(0, 5) }}–{{ request.preferred_end_time.slice(0, 5) }}
            </template>
            <template v-if="request.preferred_room_code"> · {{ request.preferred_room_code }}</template>
          </p>
        </div>
        <p class="whitespace-pre-wrap text-xs text-muted"><span class="font-semibold text-ink">Reason:</span> {{ request.reason }}</p>
        <label class="block text-xs font-semibold text-ink">
          Review note (optional)
          <textarea
            v-model="notes[request.id]"
            rows="2"
            maxlength="1000"
            class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs font-normal"
          />
        </label>
        <div class="flex flex-wrap justify-end gap-2">
          <button
            type="button"
            :disabled="busyId === request.id"
            class="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 disabled:opacity-50"
            @click="review(request, 'rejected')"
          >Reject</button>
          <button
            type="button"
            :disabled="busyId === request.id"
            class="rounded-lg bg-teal-700 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
            @click="review(request, 'approved')"
          >Approve</button>
        </div>
      </li>
    </ul>

    <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-hairline px-5 py-3">
      <button type="button" :disabled="page <= 1 || isLoading" class="text-xs font-semibold disabled:opacity-40" @click="page--; loadRequests()">Previous</button>
      <span class="text-xs text-muted">Page {{ page }} of {{ totalPages }}</span>
      <button type="button" :disabled="page >= totalPages || isLoading" class="text-xs font-semibold disabled:opacity-40" @click="page++; loadRequests()">Next</button>
    </div>
  </section>
</template>
