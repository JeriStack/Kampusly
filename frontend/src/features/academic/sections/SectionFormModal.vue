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
    class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm"
    @click.self="emit('close')"
  >
    <section
      role="dialog"
      aria-modal="true"
      aria-labelledby="section-modal-title"
      class="my-8 w-full max-w-lg overflow-hidden rounded-2xl border border-hairline bg-surface shadow-2xl"
    >
      <div class="h-1 w-full bg-gradient-to-r from-navy via-action to-accent" />

      <div class="flex items-center justify-between gap-4 border-b border-hairline px-6 py-4">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300">
            <AppIcon name="layers" class="h-5 w-5" />
          </span>
          <div>
            <h2 id="section-modal-title" class="text-lg font-bold text-ink">
              {{ section ? 'Edit Section' : 'Create Class Section' }}
            </h2>
            <p class="mt-0.5 text-xs text-subtle">
              {{ section ? 'Update cohort details, academic term, and status.' : 'Define a student cohort for scheduling and enrollment.' }}
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

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label for="section-name" class="mb-1.5 block text-xs font-semibold text-ink">
              Section Name <span class="text-rose-500">*</span>
            </label>
            <input
              id="section-name"
              v-model="sectionName"
              type="text"
              required
              placeholder="e.g. BSCS 2A"
              class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 font-mono text-sm uppercase text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
            />
          </div>
          <div>
            <label for="section-year-level" class="mb-1.5 block text-xs font-semibold text-ink">
              Year Level <span class="text-rose-500">*</span>
            </label>
            <select
              id="section-year-level"
              v-model.number="yearLevel"
              class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
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
          <label for="section-term-start" class="mb-1.5 block text-xs font-semibold text-ink">
            First day of semester
          </label>
          <input
            id="section-term-start"
            v-model="termStartDate"
            type="date"
            class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          />
          <p class="mt-1.5 text-[11px] text-subtle">
            Student schedule-change requests are allowed through one month after this date.
          </p>
        </div>

        <div>
          <label for="section-program" class="mb-1.5 block text-xs font-semibold text-ink">
            Academic Program <span class="text-rose-500">*</span>
          </label>
          <input
            id="section-program"
            v-model="program"
            type="text"
            required
            placeholder="e.g. BS Computer Science"
            class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
          />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label for="section-school-year" class="mb-1.5 block text-xs font-semibold text-ink">
              School Year <span class="text-rose-500">*</span>
            </label>
            <input
              id="section-school-year"
              v-model="schoolYear"
              type="text"
              required
              placeholder="2026-2027"
              class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
            />
          </div>
          <div>
            <label for="section-semester" class="mb-1.5 block text-xs font-semibold text-ink">
              Semester <span class="text-rose-500">*</span>
            </label>
            <select
              id="section-semester"
              v-model="semester"
              class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
            >
              <option value="1st">1st Semester</option>
              <option value="2nd">2nd Semester</option>
              <option value="Summer">Summer</option>
            </select>
          </div>
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
            <span>{{ section ? 'Save Changes' : 'Create Section' }}</span>
          </button>
        </div>
      </form>
    </section>
  </div>
</template>
