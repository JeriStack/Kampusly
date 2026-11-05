<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { apiClient } from '@/services/api/client'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { ApiError } from '@/services/api/errors'
import AcademicLayout from '../components/AcademicLayout.vue'
import SearchBar from '../components/SearchBar.vue'
import PaginationBar from '../components/PaginationBar.vue'
import StatusBadge from '../components/StatusBadge.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import SubjectFormModal from './SubjectFormModal.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import type { Subject, PaginatedResponse, CreateSubjectPayload, UpdateSubjectPayload } from '../types'

const subjects = ref<Subject[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)
const searchQuery = ref('')
const selectedStatus = ref('')
const currentPage = ref(1)
const perPage = ref(10)
const total = ref(0)
const totalPages = ref(1)

// Modal state
const isFormOpen = ref(false)
const editingSubject = ref<Subject | null>(null)
const isSubmitting = ref(false)
const formError = ref<string | null>(null)

// Delete Dialog
const isConfirmOpen = ref(false)
const deletingSubject = ref<Subject | null>(null)
const isDeleting = ref(false)

async function fetchSubjects(): Promise<void> {
  isLoading.value = true
  error.value = null

  try {
    const params = new URLSearchParams({
      page: String(currentPage.value),
      per_page: String(perPage.value),
    })

    if (searchQuery.value.trim()) {
      params.append('q', searchQuery.value.trim())
    }
    if (selectedStatus.value) {
      params.append('status', selectedStatus.value)
    }

    const url = `${API_ENDPOINTS.academic.subjects}?${params.toString()}`
    const response = await apiClient.get<PaginatedResponse<Subject>>(url)

    subjects.value = response.data
    total.value = response.meta.total
    totalPages.value = response.meta.total_pages
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Failed to fetch academic subjects.'
  } finally {
    isLoading.value = false
  }
}

function handleSearch(q: string): void {
  searchQuery.value = q
  currentPage.value = 1
  fetchSubjects()
}

watch(selectedStatus, () => {
  currentPage.value = 1
  fetchSubjects()
})

function handlePageChange(newPage: number): void {
  currentPage.value = newPage
  fetchSubjects()
}

function handlePerPageChange(newLimit: number): void {
  perPage.value = newLimit
  currentPage.value = 1
  fetchSubjects()
}

function openCreateModal(): void {
  editingSubject.value = null
  formError.value = null
  isFormOpen.value = true
}

function openEditModal(sub: Subject): void {
  editingSubject.value = sub
  formError.value = null
  isFormOpen.value = true
}

async function handleSaveSubject(payload: CreateSubjectPayload | UpdateSubjectPayload): Promise<void> {
  isSubmitting.value = true
  formError.value = null

  try {
    if (editingSubject.value) {
      await apiClient.put(API_ENDPOINTS.academic.subject(editingSubject.value.id), payload)
    } else {
      await apiClient.post(API_ENDPOINTS.academic.subjects, payload)
    }
    isFormOpen.value = false
    await fetchSubjects()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save subject record.'
  } finally {
    isSubmitting.value = false
  }
}

function openDeleteDialog(sub: Subject): void {
  deletingSubject.value = sub
  isConfirmOpen.value = true
}

async function handleConfirmDelete(): Promise<void> {
  if (!deletingSubject.value) return
  isDeleting.value = true

  try {
    await apiClient.delete(API_ENDPOINTS.academic.subject(deletingSubject.value.id))
    isConfirmOpen.value = false
    deletingSubject.value = null
    await fetchSubjects()
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Failed to delete subject.'
    isConfirmOpen.value = false
  } finally {
    isDeleting.value = false
  }
}

onMounted(() => {
  fetchSubjects()
})
</script>

<template>
  <AcademicLayout
    title="Subject Management"
    subtitle="Configure curriculum subjects, course units, and academic catalogs."
  >
    <template #actions>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg bg-navy px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-navy-700"
        @click="openCreateModal"
      >
        <AppIcon name="plus" class="h-3.5 w-3.5" />
        <span>Add Subject</span>
      </button>
    </template>

    <!-- Filter & Search Controls -->
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <SearchBar
        :model-value="searchQuery"
        placeholder="Search code or subject title..."
        @search="handleSearch"
      />

      <div class="flex items-center gap-2">
        <select
          v-model="selectedStatus"
          class="rounded-lg border border-hairline bg-surface px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
        >
          <option value="">All Statuses</option>
          <option value="active">Active Only</option>
          <option value="inactive">Inactive Only</option>
        </select>
      </div>
    </div>

    <!-- Error Alert -->
    <div
      v-if="error"
      class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300"
    >
      {{ error }}
    </div>

    <!-- Data Table -->
    <div class="overflow-hidden rounded-2xl border border-hairline bg-surface shadow-card">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-hairline bg-canvas/60 text-muted uppercase font-semibold">
            <tr>
              <th scope="col" class="px-4 py-3">Code</th>
              <th scope="col" class="px-4 py-3">Subject Name</th>
              <th scope="col" class="px-4 py-3">Units</th>
              <th scope="col" class="px-4 py-3">Status</th>
              <th scope="col" class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline">
            <tr v-if="isLoading">
              <td colspan="5" class="py-12 text-center text-muted">
                <AppIcon name="loader" class="mx-auto h-5 w-5 animate-spin text-navy" />
                <span class="mt-2 block text-xs">Loading curriculum subjects...</span>
              </td>
            </tr>

            <tr v-else-if="subjects.length === 0">
              <td colspan="5" class="py-12 text-center text-muted">
                <AppIcon name="book" class="mx-auto h-6 w-6 text-muted/60" />
                <p class="mt-1 font-semibold text-ink">No subjects found</p>
                <p class="text-xs">Adjust your search filter or add a new academic subject.</p>
              </td>
            </tr>

            <tr
              v-for="sub in subjects"
              :key="sub.id"
              class="transition-colors hover:bg-canvas/50"
            >
              <td class="px-4 py-3 font-mono font-bold text-ink">
                {{ sub.subject_code }}
              </td>
              <td class="px-4 py-3">
                <div class="font-semibold text-ink">{{ sub.subject_name }}</div>
                <div v-if="sub.description" class="line-clamp-1 text-[11px] text-muted">
                  {{ sub.description }}
                </div>
              </td>
              <td class="px-4 py-3 font-semibold text-ink">
                {{ sub.units }} units
              </td>
              <td class="px-4 py-3">
                <StatusBadge :status="sub.status" />
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    type="button"
                    class="rounded p-1 text-muted hover:bg-canvas hover:text-ink"
                    title="Edit subject"
                    @click="openEditModal(sub)"
                  >
                    <AppIcon name="edit" class="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    class="rounded p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-700"
                    title="Delete subject"
                    @click="openDeleteDialog(sub)"
                  >
                    <AppIcon name="trash" class="h-3.5 w-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <PaginationBar
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

    <!-- Modals -->
    <SubjectFormModal
      :open="isFormOpen"
      :subject="editingSubject"
      :is-loading="isSubmitting"
      :error="formError"
      @save="handleSaveSubject"
      @close="isFormOpen = false"
    />

    <ConfirmDialog
      :open="isConfirmOpen"
      title="Delete Subject"
      :message="`Are you sure you want to delete ${deletingSubject?.subject_code} (${deletingSubject?.subject_name})? Subjects tied to active schedules cannot be deleted.`"
      confirm-label="Delete Subject"
      :is-loading="isDeleting"
      @confirm="handleConfirmDelete"
      @cancel="isConfirmOpen = false"
    />
  </AcademicLayout>
</template>

