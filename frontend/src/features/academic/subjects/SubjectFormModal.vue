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
    class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm"
    @click.self="emit('close')"
  >
    <section
      role="dialog"
      aria-modal="true"
      aria-labelledby="subject-modal-title"
      class="my-8 w-full max-w-lg overflow-hidden rounded-2xl border border-hairline bg-surface shadow-2xl"
    >
      <div class="h-1 w-full bg-gradient-to-r from-navy via-action to-accent" />

      <div class="flex items-center justify-between gap-4 border-b border-hairline px-6 py-4">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300">
            <AppIcon name="book" class="h-5 w-5" />
          </span>
          <div>
            <h2 id="subject-modal-title" class="text-lg font-bold text-ink">
              {{ subject ? 'Edit Subject' : 'Create New Subject' }}
            </h2>
            <p class="mt-0.5 text-xs text-subtle">
              {{ subject ? 'Update curriculum subject details and unit load.' : 'Add a curriculum subject to the academic catalog.' }}
            </p>
          </div>
        </div>
        <button
          type="button"
          aria-label="Close modal"
          class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-hairline bg-canvas text-subtle transition-colors hover:text-ink"
          @click="emit('close')"
        >
          <AppIcon name="close" class="h-4 w-4" />
        </button>
      </div>

      <form class="space-y-4 p-6" @submit.prevent="handleSubmit">
        <div
          v-if="error"
          role="alert"
          class="rounded-xl border border-rose-200 bg-rose-50/90 p-3 text-xs text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200"
        >
          {{ error }}
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div class="sm:col-span-2">
            <label for="subject-code" class="mb-1.5 block text-xs font-semibold text-ink">
              Subject Code <span class="text-rose-500">*</span>
            </label>
            <input
              id="subject-code"
              v-model="subjectCode"
              type="text"
              required
              placeholder="e.g. CS 201"
              class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 font-mono text-sm uppercase text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
            />
          </div>
          <div>
            <label for="subject-units" class="mb-1.5 block text-xs font-semibold text-ink">
              Units <span class="text-rose-500">*</span>
            </label>
            <input
              id="subject-units"
              v-model.number="units"
              type="number"
              min="1"
              max="12"
              required
              class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm tabular-nums text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
            />
          </div>
        </div>

        <div>
          <label for="subject-name" class="mb-1.5 block text-xs font-semibold text-ink">
            Subject Title / Name <span class="text-rose-500">*</span>
          </label>
          <input
            id="subject-name"
            v-model="subjectName"
            type="text"
            required
            placeholder="e.g. Data Structures and Algorithms"
            class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          />
        </div>

        <div>
          <label for="subject-description" class="mb-1.5 block text-xs font-semibold text-ink">
            Description
          </label>
          <textarea
            id="subject-description"
            v-model="description"
            rows="3"
            placeholder="Course syllabus description..."
            class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          />
        </div>

        <div>
          <span class="mb-1.5 block text-xs font-semibold text-ink">Status</span>
          <div class="grid grid-cols-2 gap-2.5">
            <button
              type="button"
              class="flex items-center justify-center gap-2 rounded-xl border px-3.5 py-2.5 text-xs font-semibold transition-all"
              :class="
                status === 'active'
                  ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 ring-1 ring-inset ring-emerald-500/20 dark:text-emerald-300'
                  : 'border-hairline bg-canvas text-muted hover:text-ink'
              "
              @click="status = 'active'"
            >
              <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true" />
              <span>Active</span>
            </button>
            <button
              type="button"
              class="flex items-center justify-center gap-2 rounded-xl border px-3.5 py-2.5 text-xs font-semibold transition-all"
              :class="
                status === 'inactive'
                  ? 'border-hairline bg-surface text-ink ring-1 ring-inset ring-hairline'
                  : 'border-hairline bg-canvas text-muted hover:text-ink'
              "
              @click="status = 'inactive'"
            >
              <span class="h-2 w-2 rounded-full bg-slate-400" aria-hidden="true" />
              <span>Inactive</span>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2.5 border-t border-hairline pt-4">
          <button
            type="button"
            class="rounded-xl border border-hairline bg-canvas px-4 py-2.5 text-xs font-semibold text-ink transition-colors hover:bg-surface"
            @click="emit('close')"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isLoading"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-action px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-action-hover active:bg-action-active disabled:cursor-not-allowed disabled:opacity-60"
          >
            <AppIcon v-if="isLoading" name="loader" class="h-4 w-4 animate-spin" />
            <span>{{ subject ? 'Save Changes' : 'Create Subject' }}</span>
          </button>
        </div>
      </form>
    </section>
  </div>
</template>

