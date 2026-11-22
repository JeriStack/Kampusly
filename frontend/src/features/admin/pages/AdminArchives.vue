<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { apiClient } from '@/services/api/client'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { ApiError } from '@/services/api/errors'
import AcademicLayout from '@/features/academic/components/AcademicLayout.vue'
import SearchBar from '@/features/academic/components/SearchBar.vue'
import PaginationBar from '@/features/academic/components/PaginationBar.vue'
import ConfirmDialog from '@/features/academic/components/ConfirmDialog.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import type { PaginationMeta } from '@/features/academic/types'

type ArchiveEntityType = 'users' | 'subjects' | 'rooms' | 'sections' | 'schedules'
type ArchiveFilterType = 'all' | ArchiveEntityType

interface ArchivedRecord {
  entity_type: ArchiveEntityType
  id: number
  title: string
  subtitle: string
  badge: string
  archived_at: string
  can_permanently_delete: boolean
  delete_block_reason: string | null
}

interface ArchiveCounts {
  all: number
  users: number
  subjects: number
  rooms: number
  sections: number
  schedules: number
}

interface ArchiveListResponse {
  data: ArchivedRecord[]
  meta: PaginationMeta
  counts: ArchiveCounts
}

const filterOptions: { key: ArchiveFilterType; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'users', label: 'Users' },
  { key: 'subjects', label: 'Subjects' },
  { key: 'rooms', label: 'Rooms' },
  { key: 'sections', label: 'Sections' },
  { key: 'schedules', label: 'Schedules' },
]

const items = ref<ArchivedRecord[]>([])
const counts = ref<ArchiveCounts>({
  all: 0,
  users: 0,
  subjects: 0,
  rooms: 0,
  sections: 0,
  schedules: 0,
})
const selectedType = ref<ArchiveFilterType>('all')
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = ref(10)
const total = ref(0)
const totalPages = ref(1)

const isLoading = ref(true)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const busyKey = ref<string | null>(null)

const isDeleteDialogOpen = ref(false)
const deletingRecord = ref<ArchivedRecord | null>(null)
const isDeleting = ref(false)

function recordKey(item: ArchivedRecord): string {
  return `${item.entity_type}:${item.id}`
}

function typeLabel(type: ArchiveEntityType): string {
  switch (type) {
    case 'users':
      return 'User'
    case 'subjects':
      return 'Subject'
    case 'rooms':
      return 'Room'
    case 'sections':
      return 'Section'
    case 'schedules':
      return 'Schedule'
  }
}

function typeBadgeClasses(type: ArchiveEntityType): string {
  switch (type) {
    case 'users':
      return 'border-sky-500/25 bg-sky-500/10 text-sky-700 dark:text-sky-300'
    case 'subjects':
      return 'border-violet-500/25 bg-violet-500/10 text-violet-700 dark:text-violet-300'
    case 'rooms':
      return 'border-teal-500/25 bg-teal-500/10 text-teal-700 dark:text-teal-300'
    case 'sections':
      return 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300'
    case 'schedules':
      return 'border-blue-500/25 bg-blue-500/10 text-blue-700 dark:text-blue-300'
  }
}

function formatArchivedDate(value: string): string {
  const normalized = value.includes('T') ? value : value.replace(' ', 'T')
  const date = new Date(normalized)
  if (Number.isNaN(date.getTime())) {
    return value
  }
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

async function fetchArchives(): Promise<void> {
  isLoading.value = true
  error.value = null

  try {
    const params = new URLSearchParams({
      type: selectedType.value,
      page: String(currentPage.value),
      per_page: String(perPage.value),
    })
    if (searchQuery.value.trim()) {
      params.append('q', searchQuery.value.trim())
    }

    const res = await apiClient.get<ArchiveListResponse>(
      `${API_ENDPOINTS.admin.archives}?${params.toString()}`
    )
    items.value = res.data
    total.value = res.meta.total
    totalPages.value = Math.max(1, res.meta.total_pages)
    counts.value = res.counts
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Failed to load archived records.'
  } finally {
    isLoading.value = false
  }
}

function handleSearch(q: string): void {
  searchQuery.value = q
  currentPage.value = 1
  fetchArchives()
}

watch(selectedType, () => {
  currentPage.value = 1
  fetchArchives()
})

function handlePageChange(newPage: number): void {
  currentPage.value = newPage
  fetchArchives()
}

function handlePerPageChange(newLimit: number): void {
  perPage.value = newLimit
  currentPage.value = 1
  fetchArchives()
}

async function handleRestore(item: ArchivedRecord): Promise<void> {
  const key = recordKey(item)
  busyKey.value = key
  error.value = null
  notice.value = null

  try {
    await apiClient.post(API_ENDPOINTS.admin.restoreRecord(item.entity_type, item.id), {})
    notice.value = `Restored "${item.title}" to active records.`
    if (items.value.length === 1 && currentPage.value > 1) {
      currentPage.value -= 1
    }
    await fetchArchives()
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Failed to restore archived record.'
  } finally {
    busyKey.value = null
  }
}

function openPermanentDeleteDialog(item: ArchivedRecord): void {
  if (!item.can_permanently_delete) return
  deletingRecord.value = item
  isDeleteDialogOpen.value = true
}

async function handleConfirmPermanentDelete(): Promise<void> {
  if (!deletingRecord.value) return
  isDeleting.value = true
  error.value = null
  notice.value = null

  const target = deletingRecord.value
  try {
    await apiClient.delete(API_ENDPOINTS.admin.archiveRecord(target.entity_type, target.id))
    isDeleteDialogOpen.value = false
    deletingRecord.value = null
    notice.value = `Permanently deleted "${target.title}".`
    if (items.value.length === 1 && currentPage.value > 1) {
      currentPage.value -= 1
    }
    await fetchArchives()
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Failed to permanently delete record.'
    isDeleteDialogOpen.value = false
  } finally {
    isDeleting.value = false
  }
}

onMounted(() => {
  fetchArchives()
})
</script>

<template>
  <AcademicLayout
    title="Archive Records"
    subtitle="Review, restore, or permanently remove archived user accounts, academic catalogs, and schedules."
  >
    <!-- Category Filter Pills -->
    <div class="mb-4 flex flex-wrap items-center gap-2" role="tablist" aria-label="Archive categories">
      <button
        v-for="opt in filterOptions"
        :key="opt.key"
        type="button"
        role="tab"
        :aria-selected="selectedType === opt.key"
        class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-semibold transition-all"
        :class="
          selectedType === opt.key
            ? 'border-action/30 bg-action/10 text-navy shadow-sm dark:border-sky-400/35 dark:bg-sky-500/15 dark:text-sky-200'
            : 'border-hairline bg-surface text-muted hover:bg-canvas hover:text-ink'
        "
        @click="selectedType = opt.key"
      >
        <span>{{ opt.label }}</span>
        <span
          class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold tabular-nums"
          :class="
            selectedType === opt.key
              ? 'bg-action/15 text-action dark:bg-sky-400/20 dark:text-sky-200'
              : 'bg-canvas text-subtle'
          "
        >
          {{ counts[opt.key] }}
        </span>
      </button>
    </div>

    <!-- Search & Retention Note -->
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <SearchBar
        :model-value="searchQuery"
        placeholder="Search archived title, email, code, or schedule..."
        @search="handleSearch"
      />
      <p class="inline-flex items-center gap-1.5 text-xs text-subtle">
        <AppIcon name="info" class="h-4 w-4 shrink-0 text-action dark:text-sky-300" />
        <span>Archived records stay stored indefinitely until restored or permanently deleted.</span>
      </p>
    </div>

    <!-- Feedback Banners -->
    <div
      v-if="notice"
      role="status"
      class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 text-xs font-medium text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-200"
    >
      {{ notice }}
    </div>

    <div
      v-if="error"
      role="alert"
      class="mb-4 rounded-xl border border-rose-200 bg-rose-50/90 p-4 text-xs text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200"
    >
      {{ error }}
    </div>

    <!-- Archived Records Table -->
    <div class="overflow-hidden rounded-2xl border border-hairline bg-surface shadow-card">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[780px] text-center text-xs">
          <thead class="border-b border-hairline bg-canvas/70 text-[11px] font-semibold uppercase tracking-wider text-subtle">
            <tr>
              <th scope="col" class="px-4 py-3.5">Type</th>
              <th scope="col" class="px-4 py-3.5 text-left">Record</th>
              <th scope="col" class="px-4 py-3.5">Archived On</th>
              <th scope="col" class="px-4 py-3.5">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline/70">
            <tr v-if="isLoading">
              <td colspan="4" class="py-12 text-center text-muted">
                <AppIcon name="loader" class="mx-auto h-5 w-5 animate-spin text-action dark:text-sky-300" />
                <span class="mt-2 block text-xs">Loading archived records...</span>
              </td>
            </tr>

            <tr v-else-if="items.length === 0">
              <td colspan="4" class="py-12 text-center text-muted">
                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-canvas text-subtle ring-1 ring-hairline">
                  <AppIcon name="archive" class="h-5 w-5" />
                </span>
                <p class="mt-3 font-semibold text-ink">No archived records found</p>
                <p class="mt-0.5 text-xs text-subtle">
                  Records archived from Users, Subjects, Rooms, Sections, or Schedules will appear here.
                </p>
              </td>
            </tr>

            <tr
              v-for="item in items"
              :key="recordKey(item)"
              class="transition-colors hover:bg-canvas/60"
            >
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span
                  class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                  :class="typeBadgeClasses(item.entity_type)"
                >
                  {{ typeLabel(item.entity_type) }}
                </span>
              </td>
              <td class="px-4 py-3.5 text-left">
                <div class="font-semibold text-ink">{{ item.title }}</div>
                <div class="mt-0.5 text-[11px] text-subtle">{{ item.subtitle }}</div>
                <div
                  v-if="!item.can_permanently_delete && item.delete_block_reason"
                  class="mt-1 inline-flex items-center gap-1 text-[11px] text-amber-700 dark:text-amber-300"
                >
                  <AppIcon name="info" class="h-3.5 w-3.5 shrink-0" />
                  <span>Protected from permanent delete: {{ item.delete_block_reason }}</span>
                </div>
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap text-muted tabular-nums">
                {{ formatArchivedDate(item.archived_at) }}
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <div class="inline-flex items-center justify-center gap-2">
                  <button
                    type="button"
                    :disabled="busyKey === recordKey(item)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-500/30 bg-canvas px-3 py-1.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                    @click="handleRestore(item)"
                  >
                    <AppIcon
                      :name="busyKey === recordKey(item) ? 'loader' : 'rotate-ccw'"
                      :class="['h-3.5 w-3.5', busyKey === recordKey(item) && 'animate-spin']"
                    />
                    <span>Restore</span>
                  </button>
                  <button
                    type="button"
                    :disabled="!item.can_permanently_delete || busyKey === recordKey(item)"
                    :title="
                      item.can_permanently_delete
                        ? 'Delete permanently'
                        : item.delete_block_reason || 'Referenced by other records'
                    "
                    aria-label="Delete permanently"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-hairline bg-canvas text-rose-600 transition-colors hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-rose-400 dark:hover:border-rose-500/40 dark:hover:bg-rose-950/40"
                    @click="openPermanentDeleteDialog(item)"
                  >
                    <AppIcon name="trash" class="h-3.5 w-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationBar
        v-if="total > 0"
        class="border-t border-hairline bg-canvas"
        :meta="{
          page: currentPage,
          per_page: perPage,
          total,
          total_pages: totalPages,
        }"
        @change-page="handlePageChange"
        @change-per-page="handlePerPageChange"
      />
    </div>

    <ConfirmDialog
      :open="isDeleteDialogOpen"
      title="Permanently Delete Record"
      :message="`Permanently delete ${deletingRecord?.title}? This action cannot be undone and the record will no longer be recoverable.`"
      confirm-label="Delete Permanently"
      variant="danger"
      :is-loading="isDeleting"
      @confirm="handleConfirmPermanentDelete"
      @cancel="isDeleteDialogOpen = false"
    />
  </AcademicLayout>
</template>
