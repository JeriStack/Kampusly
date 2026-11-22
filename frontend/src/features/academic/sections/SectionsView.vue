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
import SectionFormModal from './SectionFormModal.vue'
import SectionStudentsModal from './SectionStudentsModal.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import type { Section, PaginatedResponse, CreateSectionPayload, UpdateSectionPayload } from '../types'

const sections = ref<Section[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)
const searchQuery = ref('')
const selectedStatus = ref('')
const selectedSemester = ref('')
const currentPage = ref(1)
const perPage = ref(10)
const total = ref(0)
const totalPages = ref(1)

// Modal state
const isFormOpen = ref(false)
const editingSection = ref<Section | null>(null)
const isSubmitting = ref(false)
const formError = ref<string | null>(null)

// Student list modal
const isStudentsOpen = ref(false)
const viewingSection = ref<Section | null>(null)

// Delete Dialog
const isConfirmOpen = ref(false)
const deletingSection = ref<Section | null>(null)
const isDeleting = ref(false)

async function fetchSections(): Promise<void> {
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
    if (selectedSemester.value) {
      params.append('semester', selectedSemester.value)
    }

    const url = `${API_ENDPOINTS.academic.sections}?${params.toString()}`
    const response = await apiClient.get<PaginatedResponse<Section>>(url)

    sections.value = response.data
    total.value = response.meta.total
    totalPages.value = response.meta.total_pages
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Failed to fetch sections.'
  } finally {
    isLoading.value = false
  }
}

function handleSearch(q: string): void {
  searchQuery.value = q
  currentPage.value = 1
  fetchSections()
}

watch([selectedStatus, selectedSemester], () => {
  currentPage.value = 1
  fetchSections()
})

function handlePageChange(newPage: number): void {
  currentPage.value = newPage
  fetchSections()
}

function handlePerPageChange(newLimit: number): void {
  perPage.value = newLimit
  currentPage.value = 1
  fetchSections()
}

function openCreateModal(): void {
  editingSection.value = null
  formError.value = null
  isFormOpen.value = true
}

function openEditModal(sec: Section): void {
  editingSection.value = sec
  formError.value = null
  isFormOpen.value = true
}

function openStudentsModal(sec: Section): void {
  viewingSection.value = sec
  isStudentsOpen.value = true
}

async function handleSaveSection(payload: CreateSectionPayload | UpdateSectionPayload): Promise<void> {
  isSubmitting.value = true
  formError.value = null

  try {
    if (editingSection.value) {
      await apiClient.put(API_ENDPOINTS.academic.section(editingSection.value.id), payload)
    } else {
      await apiClient.post(API_ENDPOINTS.academic.sections, payload)
    }
    isFormOpen.value = false
    await fetchSections()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save section.'
  } finally {
    isSubmitting.value = false
  }
}

function openDeleteDialog(sec: Section): void {
  deletingSection.value = sec
  isConfirmOpen.value = true
}

async function handleConfirmDelete(): Promise<void> {
  if (!deletingSection.value) return
  isDeleting.value = true

  try {
    await apiClient.post(API_ENDPOINTS.admin.archiveRecord('sections', deletingSection.value.id), {})
    isConfirmOpen.value = false
    deletingSection.value = null
    if (sections.value.length === 1 && currentPage.value > 1) {
      currentPage.value -= 1
    }
    await fetchSections()
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Failed to archive section.'
    isConfirmOpen.value = false
  } finally {
    isDeleting.value = false
  }
}

onMounted(() => {
  fetchSections()
})
</script>

<template>
  <AcademicLayout
    title="Section Management"
    subtitle="Configure student cohorts, year-level groupings, and academic enrollment rosters."
  >
    <template #actions>
      <button
        type="button"
        class="inline-flex h-11 min-w-[9.5rem] items-center justify-center gap-2 rounded-xl bg-action px-4 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-action-hover active:bg-action-active focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-canvas"
        @click="openCreateModal"
      >
        <AppIcon name="plus" class="h-4 w-4" />
        <span>Add Section</span>
      </button>
    </template>

    <!-- Filter & Search Controls -->
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <SearchBar
        :model-value="searchQuery"
        placeholder="Search section name or academic program..."
        @search="handleSearch"
      />

      <div class="flex items-center gap-2">
        <select
          v-model="selectedSemester"
          class="h-11 min-w-[9.5rem] rounded-xl border border-hairline bg-surface px-3.5 text-xs font-medium text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
        >
          <option value="">All Semesters</option>
          <option value="1st">1st Semester</option>
          <option value="2nd">2nd Semester</option>
          <option value="Summer">Summer</option>
        </select>
        <select
          v-model="selectedStatus"
          class="h-11 min-w-[9.5rem] rounded-xl border border-hairline bg-surface px-3.5 text-xs font-medium text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
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
      class="mb-4 rounded-xl border border-rose-200 bg-rose-50/90 p-4 text-xs text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200"
    >
      {{ error }}
    </div>

    <!-- Data Table -->
    <div class="overflow-hidden rounded-2xl border border-hairline bg-surface shadow-card">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-center text-xs">
          <thead class="border-b border-hairline bg-canvas/70 text-[11px] font-semibold uppercase tracking-wider text-subtle">
            <tr>
              <th scope="col" class="px-4 py-3.5">Section</th>
              <th scope="col" class="px-4 py-3.5 text-left">Academic Program</th>
              <th scope="col" class="px-4 py-3.5">Year Level</th>
              <th scope="col" class="px-4 py-3.5">Term</th>
              <th scope="col" class="px-4 py-3.5">Students</th>
              <th scope="col" class="px-4 py-3.5">Status</th>
              <th scope="col" class="px-4 py-3.5">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline/70">
            <tr v-if="isLoading">
              <td colspan="7" class="py-12 text-center text-muted">
                <AppIcon name="loader" class="mx-auto h-5 w-5 animate-spin text-action dark:text-sky-300" />
                <span class="mt-2 block text-xs">Loading sections...</span>
              </td>
            </tr>

            <tr v-else-if="sections.length === 0">
              <td colspan="7" class="py-12 text-center text-muted">
                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-canvas text-subtle ring-1 ring-hairline">
                  <AppIcon name="layers" class="h-5 w-5" />
                </span>
                <p class="mt-3 font-semibold text-ink">No sections found</p>
                <p class="mt-0.5 text-xs text-subtle">Adjust your search query or create a new section.</p>
              </td>
            </tr>

            <tr
              v-for="sec in sections"
              :key="sec.id"
              class="transition-colors hover:bg-canvas/60"
            >
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span class="inline-block rounded-md border border-hairline bg-canvas px-2.5 py-0.5 font-mono text-xs font-bold text-ink">
                  {{ sec.section_name }}
                </span>
              </td>
              <td class="px-4 py-3.5 text-left font-semibold text-ink">
                {{ sec.program }}
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span class="inline-flex rounded-full border border-hairline bg-canvas px-2.5 py-0.5 text-xs font-semibold text-muted">
                  Year {{ sec.year_level }}
                </span>
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap text-muted">
                {{ sec.school_year }} &bull; {{ sec.semester }}
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 rounded-full border border-sky-500/25 bg-sky-500/10 px-2.5 py-0.5 text-xs font-semibold text-navy transition-colors hover:bg-sky-500/20 dark:text-sky-300"
                  @click="openStudentsModal(sec)"
                >
                  <AppIcon name="users" class="h-3.5 w-3.5" />
                  <span>{{ sec.enrolled_students_count ?? 0 }} enrolled</span>
                </button>
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <StatusBadge :status="sec.status" />
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <div class="inline-flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-hairline bg-canvas text-muted transition-colors hover:border-action/40 hover:text-action dark:hover:text-sky-300"
                    title="View students"
                    aria-label="View students"
                    @click="openStudentsModal(sec)"
                  >
                    <AppIcon name="users" class="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-hairline bg-canvas text-muted transition-colors hover:border-action/40 hover:text-action dark:hover:text-sky-300"
                    title="Edit section"
                    aria-label="Edit section"
                    @click="openEditModal(sec)"
                  >
                    <AppIcon name="edit" class="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-hairline bg-canvas text-amber-600 transition-colors hover:border-amber-300 hover:bg-amber-50 dark:text-amber-400 dark:hover:border-amber-500/40 dark:hover:bg-amber-950/40"
                    title="Archive section"
                    aria-label="Archive section"
                    @click="openDeleteDialog(sec)"
                  >
                    <AppIcon name="archive" class="h-3.5 w-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <PaginationBar
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

    <!-- Modals -->
    <SectionFormModal
      :open="isFormOpen"
      :section="editingSection"
      :is-loading="isSubmitting"
      :error="formError"
      @save="handleSaveSection"
      @close="isFormOpen = false"
    />

    <SectionStudentsModal
      :open="isStudentsOpen"
      :section="viewingSection"
      @close="isStudentsOpen = false"
    />

    <ConfirmDialog
      :open="isConfirmOpen"
      title="Archive Section"
      :message="`Move section ${deletingSection?.section_name} to Archives? You can restore it or permanently delete it later from the Archives tab.`"
      confirm-label="Archive Section"
      variant="warning"
      :is-loading="isDeleting"
      @confirm="handleConfirmDelete"
      @cancel="isConfirmOpen = false"
    />
  </AcademicLayout>
</template>

