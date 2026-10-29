<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  status?: string | null
}>()

const normalized = computed(() => (props.status || 'unknown').toLowerCase())

const badgeClasses = computed(() => {
  switch (normalized.value) {
    case 'active':
    case 'available':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/60'
    case 'inactive':
    case 'archived':
      return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-900/60 dark:text-slate-400 dark:border-slate-800'
    case 'maintenance':
    case 'suspended':
      return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/60'
    case 'graduated':
      return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-800/60'
    default:
      return 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-900 dark:text-slate-400'
  }
})

const dotColorClass = computed(() => {
  switch (normalized.value) {
    case 'active':
    case 'available':
      return 'bg-emerald-500'
    case 'inactive':
    case 'archived':
      return 'bg-slate-400'
    case 'maintenance':
    case 'suspended':
      return 'bg-amber-500'
    case 'graduated':
      return 'bg-blue-500'
    default:
      return 'bg-slate-400'
  }
})
</script>

<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize tracking-wide',
      badgeClasses,
    ]"
  >
    <span
      class="h-1.5 w-1.5 rounded-full"
      :class="dotColorClass"
    />
    <span>{{ status || 'Unknown' }}</span>
  </span>
</template>
