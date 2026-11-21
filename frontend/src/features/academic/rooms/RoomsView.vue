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
import RoomFormModal from './RoomFormModal.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import type { Room, PaginatedResponse, CreateRoomPayload, UpdateRoomPayload } from '../types'

const rooms = ref<Room[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)
const searchQuery = ref('')
const selectedStatus = ref('')
const selectedBuilding = ref('')
const currentPage = ref(1)
const perPage = ref(10)
const total = ref(0)
const totalPages = ref(1)

// Modal state
const isFormOpen = ref(false)
const editingRoom = ref<Room | null>(null)
const isSubmitting = ref(false)
const formError = ref<string | null>(null)

// Delete Dialog
const isConfirmOpen = ref(false)
const deletingRoom = ref<Room | null>(null)
const isDeleting = ref(false)

async function fetchRooms(): Promise<void> {
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
    if (selectedBuilding.value) {
      params.append('building', selectedBuilding.value)
    }

    const url = `${API_ENDPOINTS.academic.rooms}?${params.toString()}`
    const response = await apiClient.get<PaginatedResponse<Room>>(url)

    rooms.value = response.data
    total.value = response.meta.total
    totalPages.value = response.meta.total_pages
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Failed to fetch rooms.'
  } finally {
    isLoading.value = false
  }
}

function handleSearch(q: string): void {
  searchQuery.value = q
  currentPage.value = 1
  fetchRooms()
}

watch([selectedStatus, selectedBuilding], () => {
  currentPage.value = 1
  fetchRooms()
})

function handlePageChange(newPage: number): void {
  currentPage.value = newPage
  fetchRooms()
}

function handlePerPageChange(newLimit: number): void {
  perPage.value = newLimit
  currentPage.value = 1
  fetchRooms()
}

function openCreateModal(): void {
  editingRoom.value = null
  formError.value = null
  isFormOpen.value = true
}

function openEditModal(room: Room): void {
  editingRoom.value = room
  formError.value = null
  isFormOpen.value = true
}

async function handleSaveRoom(payload: CreateRoomPayload | UpdateRoomPayload): Promise<void> {
  isSubmitting.value = true
  formError.value = null

  try {
    if (editingRoom.value) {
      await apiClient.put(API_ENDPOINTS.academic.room(editingRoom.value.id), payload)
    } else {
      await apiClient.post(API_ENDPOINTS.academic.rooms, payload)
    }
    isFormOpen.value = false
    await fetchRooms()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save room.'
  } finally {
    isSubmitting.value = false
  }
}

function openDeleteDialog(room: Room): void {
  deletingRoom.value = room
  isConfirmOpen.value = true
}

async function handleConfirmDelete(): Promise<void> {
  if (!deletingRoom.value) return
  isDeleting.value = true

  try {
    await apiClient.delete(API_ENDPOINTS.academic.room(deletingRoom.value.id))
    isConfirmOpen.value = false
    deletingRoom.value = null
    await fetchRooms()
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Failed to delete room.'
    isConfirmOpen.value = false
  } finally {
    isDeleting.value = false
  }
}

onMounted(() => {
  fetchRooms()
})
</script>

<template>
  <AcademicLayout
    title="Room Management"
    subtitle="Manage instructional spaces, laboratory capacities, and academic facilities."
  >
    <template #actions>
      <button
        type="button"
        class="inline-flex h-11 min-w-[9.5rem] items-center justify-center gap-2 rounded-xl bg-action px-4 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-action-hover active:bg-action-active focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-canvas"
        @click="openCreateModal"
      >
        <AppIcon name="plus" class="h-4 w-4" />
        <span>Add Room</span>
      </button>
    </template>

    <!-- Filter & Search Controls -->
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <SearchBar
        :model-value="searchQuery"
        placeholder="Search code, name, or building..."
        @search="handleSearch"
      />

      <div class="flex items-center gap-2">
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
        <table class="w-full min-w-[720px] text-center text-xs">
          <thead class="border-b border-hairline bg-canvas/70 text-[11px] font-semibold uppercase tracking-wider text-subtle">
            <tr>
              <th scope="col" class="px-4 py-3.5">Room Code</th>
              <th scope="col" class="px-4 py-3.5 text-left">Name / Description</th>
              <th scope="col" class="px-4 py-3.5 text-left">Building</th>
              <th scope="col" class="px-4 py-3.5">Capacity</th>
              <th scope="col" class="px-4 py-3.5">Status</th>
              <th scope="col" class="px-4 py-3.5">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline/70">
            <tr v-if="isLoading">
              <td colspan="6" class="py-12 text-center text-muted">
                <AppIcon name="loader" class="mx-auto h-5 w-5 animate-spin text-action dark:text-sky-300" />
                <span class="mt-2 block text-xs">Loading rooms...</span>
              </td>
            </tr>

            <tr v-else-if="rooms.length === 0">
              <td colspan="6" class="py-12 text-center text-muted">
                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-canvas text-subtle ring-1 ring-hairline">
                  <AppIcon name="grid" class="h-5 w-5" />
                </span>
                <p class="mt-3 font-semibold text-ink">No rooms found</p>
                <p class="mt-0.5 text-xs text-subtle">Adjust your search query or register a new room.</p>
              </td>
            </tr>

            <tr
              v-for="room in rooms"
              :key="room.id"
              class="transition-colors hover:bg-canvas/60"
            >
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span class="inline-block rounded-md border border-hairline bg-canvas px-2.5 py-0.5 font-mono text-xs font-bold text-ink">
                  {{ room.room_code }}
                </span>
              </td>
              <td class="px-4 py-3.5 text-left font-semibold text-ink">
                {{ room.room_name }}
              </td>
              <td class="px-4 py-3.5 text-left text-muted">
                {{ room.building }}
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span class="inline-flex rounded-full border border-hairline bg-canvas px-2.5 py-0.5 text-xs font-semibold tabular-nums text-ink">
                  {{ room.capacity }} seats
                </span>
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <StatusBadge :status="room.status" />
              </td>
              <td class="px-4 py-3.5 whitespace-nowrap">
                <div class="inline-flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-hairline bg-canvas text-muted transition-colors hover:border-action/40 hover:text-action dark:hover:text-sky-300"
                    title="Edit room"
                    aria-label="Edit room"
                    @click="openEditModal(room)"
                  >
                    <AppIcon name="edit" class="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-hairline bg-canvas text-rose-600 transition-colors hover:border-rose-300 hover:bg-rose-50 dark:text-rose-400 dark:hover:border-rose-500/40 dark:hover:bg-rose-950/40"
                    title="Delete room"
                    aria-label="Delete room"
                    @click="openDeleteDialog(room)"
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
    <RoomFormModal
      :open="isFormOpen"
      :room="editingRoom"
      :is-loading="isSubmitting"
      :error="formError"
      @save="handleSaveRoom"
      @close="isFormOpen = false"
    />

    <ConfirmDialog
      :open="isConfirmOpen"
      title="Delete Room"
      :message="`Are you sure you want to delete ${deletingRoom?.room_code} (${deletingRoom?.room_name})? Rooms tied to active schedules cannot be deleted.`"
      confirm-label="Delete Room"
      :is-loading="isDeleting"
      @confirm="handleConfirmDelete"
      @cancel="isConfirmOpen = false"
    />
  </AcademicLayout>
</template>

