<script setup lang="ts">
import { ref, watch } from 'vue'
import { apiClient } from '@/services/api/client'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import AppIcon from '@/components/common/AppIcon.vue'
import StatusBadge from '../components/StatusBadge.vue'
import type { Section } from '../types'

interface EnrolledStudent {
  id: number
  student_number: string
  first_name: string
  last_name: string
  full_name: string
  program: string
  email: string
  status: string
}

const props = defineProps<{
  open: boolean
  section?: Section | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const students = ref<EnrolledStudent[]>([])
const isLoading = ref(false)
const error = ref<string | null>(null)

async function fetchStudents(): Promise<void> {
  if (!props.section) return
  isLoading.value = true
  error.value = null

  try {
    const res = await apiClient.get<{ data: EnrolledStudent[]; total: number }>(
      API_ENDPOINTS.academic.sectionStudents(props.section.id)
    )
    students.value = res.data || []
  } catch (err: unknown) {
    error.value = err instanceof Error ? err.message : 'Failed to load students for this section.'
  } finally {
    isLoading.value = false
  }
}

watch(
  () => props.open,
  (val) => {
    if (val && props.section) {
      fetchStudents()
    } else {
      students.value = []
    }
  }
)
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
    role="dialog"
  >
    <div class="w-full max-w-2xl rounded-2xl border border-hairline bg-surface p-6 shadow-card">
      <div class="flex items-center justify-between border-b border-hairline pb-4">
        <div>
          <h2 class="text-base font-bold text-ink">
            Enrolled Students &bull; {{ section?.section_name }}
          </h2>
          <p class="text-xs text-muted">
            {{ section?.program }} &bull; {{ section?.school_year }} ({{ section?.semester }} Semester)
          </p>
        </div>
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
        class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800"
      >
        {{ error }}
      </div>

      <div class="mt-4 max-h-96 overflow-y-auto">
        <div v-if="isLoading" class="py-12 text-center text-muted">
          <AppIcon name="loader" class="mx-auto h-5 w-5 animate-spin text-navy" />
          <span class="mt-2 block text-xs">Loading section roster...</span>
        </div>

        <div v-else-if="students.length === 0" class="py-12 text-center text-muted">
          <AppIcon name="users" class="mx-auto h-6 w-6 text-muted/60" />
          <p class="mt-1 font-semibold text-ink">No students enrolled yet</p>
          <p class="text-xs">Assign students to this section via the Students Management module.</p>
        </div>

        <table v-else class="w-full text-left text-xs">
          <thead class="border-b border-hairline bg-canvas/60 text-muted uppercase font-semibold">
            <tr>
              <th scope="col" class="px-3 py-2">Student No.</th>
              <th scope="col" class="px-3 py-2">Full Name</th>
              <th scope="col" class="px-3 py-2">Institutional Email</th>
              <th scope="col" class="px-3 py-2">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline">
            <tr v-for="st in students" :key="st.id" class="hover:bg-canvas/50">
              <td class="px-3 py-2.5 font-mono font-bold text-ink">{{ st.student_number }}</td>
              <td class="px-3 py-2.5 font-semibold text-ink">{{ st.full_name }}</td>
              <td class="px-3 py-2.5 text-muted">{{ st.email }}</td>
              <td class="px-3 py-2.5"><StatusBadge :status="st.status" /></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-6 flex justify-end border-t border-hairline pt-3">
        <button
          type="button"
          class="rounded-lg border border-hairline px-4 py-2 text-xs font-semibold text-ink hover:bg-canvas"
          @click="emit('close')"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

