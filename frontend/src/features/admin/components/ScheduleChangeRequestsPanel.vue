<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { apiClient } from '@/services/api/client'
import { ApiError } from '@/services/api/errors'
import AppIcon from '@/components/common/AppIcon.vue'
import PaginationBar from '@/features/academic/components/PaginationBar.vue'
import type { PaginationMeta } from '@/features/academic/types'

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
  meta: PaginationMeta
}

const requests = ref<ScheduleChangeRequest[]>([])
const meta = ref<PaginationMeta>({ page: 1, per_page: 5, total: 0, total_pages: 0 })
const isLoading = ref(true)
const busyId = ref<number | null>(null)
const error = ref<string | null>(null)
const notes = ref<Record<number, string>>({})

async function fetchRequestPage(page: number): Promise<void> {
  const response = await apiClient.get<RequestResponse>(
    `${API_ENDPOINTS.scheduling.scheduleChangeRequests}?status=pending&page=${page}&per_page=${meta.value.per_page}`,
  )

  // The last request on a page may have just been reviewed; step back to the last page.
  if (response.data.length === 0 && response.meta.total_pages > 0 && page > response.meta.total_pages) {
    await fetchRequestPage(response.meta.total_pages)
    return
  }

  requests.value = response.data
  meta.value = { ...response.meta, page: response.meta.total === 0 ? 1 : response.meta.page }
}

async function loadRequests(page = meta.value.page): Promise<void> {
  isLoading.value = true
  error.value = null
  try {
    await fetchRequestPage(page)
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Unable to load schedule change requests.'
  } finally {
    isLoading.value = false
  }
}

function handlePageChange(page: number): void {
  void loadRequests(page)
}

function handlePerPageChange(perPage: number): void {
  meta.value.per_page = perPage
  void loadRequests(1)
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
  <section class="mb-8 overflow-hidden rounded-2xl border border-hairline bg-surface shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-hairline px-5 py-4 sm:px-6">
      <div class="flex items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
          <AppIcon name="clock" class="h-5 w-5" />
        </span>
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-base font-bold text-ink">Student schedule change requests</h2>
            <span class="inline-flex items-center rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-amber-700 dark:text-amber-300">
              {{ meta.total }}
            </span>
          </div>
          <p class="mt-0.5 text-xs text-subtle">Approving or rejecting a request does not alter the section schedule.</p>
        </div>
      </div>
      <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/25 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
        <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true" />
        Pending review
      </span>
    </div>

    <p
      v-if="error"
      class="m-5 rounded-xl border border-rose-200 bg-rose-50/90 p-3 text-xs text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200"
      role="alert"
    >
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="loadRequests()">Retry</button>
    </p>
    <div v-else-if="isLoading" class="space-y-2.5 p-5" role="status">
      <div class="h-16 animate-pulse rounded-xl bg-canvas" />
    </div>
    <div
      v-else-if="requests.length === 0"
      class="flex flex-col items-center justify-center px-6 py-9 text-center"
    >
      <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-canvas text-emerald-600 ring-1 ring-hairline dark:text-emerald-400">
        <AppIcon name="check" class="h-5 w-5" />
      </span>
      <p class="mt-3 text-sm font-semibold text-ink">All caught up</p>
      <p class="mt-1 text-xs text-subtle">There are no pending schedule change requests right now.</p>
    </div>

    <ul v-else class="divide-y divide-hairline">
      <li v-for="request in requests" :key="request.id" class="space-y-3 p-5 sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="text-sm font-bold text-ink">{{ request.student_name }} &middot; {{ request.student_number }}</h3>
            <p class="mt-1 text-xs text-muted">{{ request.section_name }} &middot; {{ request.subject_code }} — {{ request.subject_name }}</p>
          </div>
          <time class="text-[11px] text-subtle">{{ request.created_at }}</time>
        </div>
        <div class="grid gap-3 text-xs sm:grid-cols-2">
          <div class="rounded-xl border border-hairline/70 bg-canvas/60 p-3">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-subtle">Current</span>
            <p class="mt-1 font-medium text-ink">
              {{ request.current_day_of_week }}, {{ request.current_start_time.slice(0, 5) }}–{{ request.current_end_time.slice(0, 5) }}
              &middot; {{ request.current_room_code }}
            </p>
          </div>
          <div class="rounded-xl border border-hairline/70 bg-canvas/60 p-3">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-subtle">Preferred</span>
            <p class="mt-1 font-medium text-ink">
              {{ request.preferred_day_of_week || 'No day preference' }}
              <template v-if="request.preferred_start_time && request.preferred_end_time">
                , {{ request.preferred_start_time.slice(0, 5) }}–{{ request.preferred_end_time.slice(0, 5) }}
              </template>
              <template v-if="request.preferred_room_code"> &middot; {{ request.preferred_room_code }}</template>
            </p>
          </div>
        </div>
        <p class="whitespace-pre-wrap text-xs text-muted"><span class="font-semibold text-ink">Reason:</span> {{ request.reason }}</p>
        <label class="block text-xs font-semibold text-ink">
          Review note (optional)
          <textarea
            v-model="notes[request.id]"
            rows="2"
            maxlength="1000"
            class="mt-1.5 w-full rounded-xl border border-hairline bg-canvas px-3 py-2 text-xs font-normal text-ink focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          />
        </label>
        <div class="flex flex-wrap justify-end gap-2">
          <button
            type="button"
            :disabled="busyId === request.id"
            class="rounded-lg border border-rose-300 bg-canvas px-3.5 py-2 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50 disabled:opacity-50 dark:border-rose-500/40 dark:text-rose-400 dark:hover:bg-rose-950/40"
            @click="review(request, 'rejected')"
          >
            Reject
          </button>
          <button
            type="button"
            :disabled="busyId === request.id"
            class="rounded-lg bg-teal-600 px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-teal-700 disabled:opacity-50"
            @click="review(request, 'approved')"
          >
            Approve
          </button>
        </div>
      </li>
    </ul>

    <PaginationBar
      v-if="!error && meta.total > 0"
      class="border-t border-hairline bg-canvas"
      :meta="meta"
      :disabled="isLoading"
      @change-page="handlePageChange"
      @change-per-page="handlePerPageChange"
    />
  </section>
</template>
