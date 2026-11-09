<script setup lang="ts">
import { ref, watch } from 'vue'
import AppIcon from '@/components/common/AppIcon.vue'
import type { Section, CreateSectionPayload, UpdateSectionPayload } from '../types'

const props = defineProps<{
  open: boolean
  section?: Section | null
  isLoading?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  (e: 'save', payload: CreateSectionPayload | UpdateSectionPayload): void
  (e: 'close'): void
}>()

const sectionName = ref('')
const program = ref('')
const yearLevel = ref(1)
const schoolYear = ref('2026-2027')
const semester = ref<'1st' | '2nd' | 'Summer'>('1st')
const termStartDate = ref('')
const status = ref<'active' | 'inactive'>('active')

watch(
  () => props.section,
  (val) => {
    if (val) {
      sectionName.value = val.section_name
      program.value = val.program
      yearLevel.value = val.year_level
      schoolYear.value = val.school_year
      semester.value = val.semester
      termStartDate.value = val.term_start_date ?? ''
      status.value = val.status
    } else {
      sectionName.value = ''
      program.value = 'BS Computer Science'
      yearLevel.value = 1
      schoolYear.value = '2026-2027'
      semester.value = '1st'
      termStartDate.value = ''
      status.value = 'active'
    }
  },
  { immediate: true }
)

function handleSubmit(): void {
  emit('save', {
    section_name: sectionName.value.trim().toUpperCase(),
    program: program.value.trim(),
    year_level: Number(yearLevel.value),
    school_year: schoolYear.value.trim(),
    semester: semester.value,
    term_start_date: termStartDate.value || null,
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
          {{ section ? 'Edit Section' : 'Create Class Section' }}
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
              Section Name *
            </label>
            <input
              v-model="sectionName"
              type="text"
              required
              placeholder="e.g. BSCS 2A"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs font-mono uppercase text-ink focus:border-navy focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Year Level *
            </label>
            <select
              v-model.number="yearLevel"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
            >
              <option :value="1">1st Year</option>
              <option :value="2">2nd Year</option>
              <option :value="3">3rd Year</option>
              <option :value="4">4th Year</option>
              <option :value="5">5th Year</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
            First day of semester
          </label>
          <input
            v-model="termStartDate"
            type="date"
            class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
          />
          <p class="mt-1 text-[11px] text-muted">
            Student schedule-change requests are allowed through one month after this date.
          </p>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
            Academic Program *
          </label>
          <input
            v-model="program"
            type="text"
            required
            placeholder="e.g. BS Computer Science"
            class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
          />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              School Year *
            </label>
            <input
              v-model="schoolYear"
              type="text"
              required
              placeholder="2026-2027"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Semester *
            </label>
            <select
              v-model="semester"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
            >
              <option value="1st">1st Semester</option>
              <option value="2nd">2nd Semester</option>
              <option value="Summer">Summer</option>
            </select>
          </div>
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
            <span>{{ section ? 'Save Changes' : 'Create Section' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
