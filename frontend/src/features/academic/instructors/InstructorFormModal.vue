<script setup lang="ts">
import { ref, watch } from 'vue'
import AppIcon from '@/components/common/AppIcon.vue'
import type { Instructor, CreateInstructorPayload, UpdateInstructorPayload } from '../types'

const props = defineProps<{
  open: boolean
  instructor?: Instructor | null
  isLoading?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  (e: 'save', payload: CreateInstructorPayload | UpdateInstructorPayload): void
  (e: 'close'): void
}>()

const employeeNumber = ref('')
const firstName = ref('')
const lastName = ref('')
const email = ref('')
const department = ref('College of Computer Studies')
const password = ref('')
const status = ref('active')

watch(
  () => props.instructor,
  (val) => {
    if (val) {
      employeeNumber.value = val.employee_number
      firstName.value = val.first_name
      lastName.value = val.last_name
      email.value = val.email || ''
      department.value = val.department
      password.value = ''
      status.value = val.status
    } else {
      employeeNumber.value = ''
      firstName.value = ''
      lastName.value = ''
      email.value = ''
      department.value = 'College of Computer Studies'
      password.value = 'ChangeMe123!'
      status.value = 'active'
    }
  },
  { immediate: true }
)

function handleSubmit(): void {
  if (props.instructor) {
    emit('save', {
      employee_number: employeeNumber.value.trim().toUpperCase(),
      first_name: firstName.value.trim(),
      last_name: lastName.value.trim(),
      email: email.value.trim().toLowerCase(),
      department: department.value.trim(),
    })
  } else {
    emit('save', {
      employee_number: employeeNumber.value.trim().toUpperCase(),
      first_name: firstName.value.trim(),
      last_name: lastName.value.trim(),
      email: email.value.trim().toLowerCase(),
      department: department.value.trim(),
      password: password.value.trim() || undefined,
      status: status.value,
    })
  }
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
          {{ instructor ? 'Edit Faculty Member' : 'Register New Instructor' }}
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
              Employee ID *
            </label>
            <input
              v-model="employeeNumber"
              type="text"
              required
              placeholder="e.g. EMP-2026-001"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs font-mono uppercase text-ink focus:border-navy focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Department *
            </label>
            <input
              v-model="department"
              type="text"
              required
              placeholder="e.g. College of Computer Studies"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              First Name *
            </label>
            <input
              v-model="firstName"
              type="text"
              required
              placeholder="Dr. Elena"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Last Name *
            </label>
            <input
              v-model="lastName"
              type="text"
              required
              placeholder="Santos"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
            Institutional Email *
          </label>
          <input
            v-model="email"
            type="email"
            required
            placeholder="elena.santos@kampusly.edu.ph"
            class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
          />
        </div>

        <div v-if="!instructor" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Initial Password
            </label>
            <input
              v-model="password"
              type="text"
              placeholder="ChangeMe123!"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
            />
            <span class="mt-0.5 block text-[10px] text-muted">Defaults to ChangeMe123! if blank</span>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Account Status
            </label>
            <select
              v-model="status"
              class="mt-1 w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-xs text-ink focus:border-navy focus:outline-none"
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
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
            <span>{{ instructor ? 'Save Changes' : 'Register Instructor' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

