<script setup lang="ts">
import { ref, watch } from 'vue'
import AppIcon from '@/components/common/AppIcon.vue'
import type { Room, CreateRoomPayload, UpdateRoomPayload } from '../types'

const props = defineProps<{
  open: boolean
  room?: Room | null
  isLoading?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  (e: 'save', payload: CreateRoomPayload | UpdateRoomPayload): void
  (e: 'close'): void
}>()

const roomCode = ref('')
const roomName = ref('')
const building = ref('')
const capacity = ref(40)
const status = ref<'active' | 'inactive'>('active')

watch(
  () => props.room,
  (val) => {
    if (val) {
      roomCode.value = val.room_code
      roomName.value = val.room_name
      building.value = val.building
      capacity.value = val.capacity
      status.value = val.status
    } else {
      roomCode.value = ''
      roomName.value = ''
      building.value = ''
      capacity.value = 40
      status.value = 'active'
    }
  },
  { immediate: true }
)

function handleSubmit(): void {
  emit('save', {
    room_code: roomCode.value.trim().toUpperCase(),
    room_name: roomName.value.trim(),
    building: building.value.trim(),
    capacity: Number(capacity.value),
    status: status.value,
  })
}
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
    role="dialog"
  >
    <div class="w-full max-w-lg rounded-2xl border border-hairline bg-surface p-6 shadow-card">
      <div class="flex items-center justify-between border-b border-hairline pb-4">
        <h2 class="text-base font-bold text-ink">
          {{ room ? 'Edit Classroom / Room' : 'Add New Instructional Space' }}
        </h2>
        <button
          type="button"
          class="text-muted hover:text-ink"
          @click="emit('close')"
        >
          <AppIcon name="close" class="h-4 w-4" />
        </button>
      </div>

      <div
        v-if="error"
        class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300"
      >
        {{ error }}
      </div>

      <form class="mt-4 space-y-4" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Room Code *
            </label>
            <input
              v-model="roomCode"
              type="text"
              required
              placeholder="e.g. RM-301"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs font-mono uppercase text-ink focus:border-navy focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Capacity (Seats) *
            </label>
            <input
              v-model.number="capacity"
              type="number"
              min="1"
              max="1000"
              required
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
            Room Description / Name *
          </label>
          <input
            v-model="roomName"
            type="text"
            required
            placeholder="e.g. Room 301 Lecture Hall"
            class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
            Building / Wing *
          </label>
          <input
            v-model="building"
            type="text"
            required
            placeholder="e.g. Main Academic Building"
            class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
            Status
          </label>
          <select
            v-model="status"
            class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
          >
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div class="mt-6 flex items-center justify-end gap-2 pt-2 border-t border-hairline">
          <button
            type="button"
            class="rounded-lg border border-hairline px-3.5 py-2 text-xs font-semibold text-ink hover:bg-canvas"
            @click="emit('close')"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isLoading"
            class="inline-flex items-center gap-1.5 rounded-lg bg-navy px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-navy-700 disabled:opacity-50"
          >
            <AppIcon v-if="isLoading" name="loader" class="h-3.5 w-3.5 animate-spin" />
            <span>{{ room ? 'Save Changes' : 'Create Room' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

