<script setup lang="ts">
import { ref, watch } from 'vue'
import AppIcon from '@/components/common/AppIcon.vue'
import type { Subject, CreateSubjectPayload, UpdateSubjectPayload } from '../types'

const props = defineProps<{
  open: boolean
  subject?: Subject | null
  isLoading?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  (e: 'save', payload: CreateSubjectPayload | UpdateSubjectPayload): void
  (e: 'close'): void
}>()

const subjectCode = ref('')
const subjectName = ref('')
const units = ref(3)
const description = ref('')
const status = ref<'active' | 'inactive'>('active')

watch(
  () => props.subject,
  (val) => {
    if (val) {
      subjectCode.value = val.subject_code
      subjectName.value = val.subject_name
      units.value = val.units
      description.value = val.description || ''
      status.value = val.status
    } else {
      subjectCode.value = ''
      subjectName.value = ''
      units.value = 3
      description.value = ''
      status.value = 'active'
    }
  },
  { immediate: true }
)

function handleSubmit(): void {
  emit('save', {
    subject_code: subjectCode.value.trim().toUpperCase(),
    subject_name: subjectName.value.trim(),
    units: Number(units.value),
    description: description.value.trim() || undefined,
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
          {{ subject ? 'Edit Subject' : 'Create New Subject' }}
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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Subject Code *
            </label>
            <input
              v-model="subjectCode"
              type="text"
              required
              placeholder="e.g. CS 201"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs font-mono uppercase text-ink focus:border-navy focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Units *
            </label>
            <input
              v-model.number="units"
              type="number"
              min="1"
              max="12"
              required
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
            Subject Title / Name *
          </label>
          <input
            v-model="subjectName"
            type="text"
            required
            placeholder="e.g. Data Structures and Algorithms"
            class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
            Description
          </label>
          <textarea
            v-model="description"
            rows="3"
            placeholder="Course syllabus description..."
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
            <span>{{ subject ? 'Save Changes' : 'Create Subject' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

