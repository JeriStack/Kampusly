<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/common/AppIcon.vue'
import type { PaginationMeta } from '../types'

const props = defineProps<{
  meta: PaginationMeta
  disabled?: boolean
}>()

const emit = defineEmits<{
  (e: 'changePage', page: number): void
  (e: 'changePerPage', perPage: number): void
}>()

const from = computed(() => {
  if (props.meta.total === 0) return 0
  return (props.meta.page - 1) * props.meta.per_page + 1
})

const to = computed(() => {
  return Math.min(props.meta.page * props.meta.per_page, props.meta.total)
})

function goTo(page: number): void {
  if (props.disabled || page < 1 || page > props.meta.total_pages || page === props.meta.page) return
  emit('changePage', page)
}
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-t border-hairline px-4 py-3">
    <div class="whitespace-nowrap text-xs text-muted">
      Showing <span class="font-semibold text-ink">{{ from }}</span> to
      <span class="font-semibold text-ink">{{ to }}</span> of
      <span class="font-semibold text-ink">{{ meta.total }}</span> results
    </div>

    <div class="flex flex-wrap items-center gap-1.5">
      <button
        type="button"
        :disabled="disabled || meta.page <= 1"
        class="inline-flex h-8 items-center justify-center rounded-lg border border-hairline bg-surface px-2.5 text-xs text-ink transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="First page"
        @click="goTo(1)"
      >
        <span>&laquo;</span>
      </button>

      <button
        type="button"
        :disabled="disabled || meta.page <= 1"
        class="inline-flex h-8 items-center justify-center rounded-lg border border-hairline bg-surface px-2.5 text-xs text-ink transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="Previous page"
        @click="goTo(meta.page - 1)"
      >
        <AppIcon name="chevron-left" class="h-3.5 w-3.5" />
      </button>

      <span class="whitespace-nowrap px-2 text-xs text-muted">
        Page <span class="font-semibold text-ink">{{ meta.page }}</span> of
        <span class="font-semibold text-ink">{{ Math.max(1, meta.total_pages) }}</span>
      </span>

      <button
        type="button"
        :disabled="disabled || meta.page >= meta.total_pages"
        class="inline-flex h-8 items-center justify-center rounded-lg border border-hairline bg-surface px-2.5 text-xs text-ink transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="Next page"
        @click="goTo(meta.page + 1)"
      >
        <AppIcon name="chevron-right" class="h-3.5 w-3.5" />
      </button>

      <button
        type="button"
        :disabled="disabled || meta.page >= meta.total_pages"
        class="inline-flex h-8 items-center justify-center rounded-lg border border-hairline bg-surface px-2.5 text-xs text-ink transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="Last page"
        @click="goTo(meta.total_pages)"
      >
        <span>&raquo;</span>
      </button>

      <select
        :value="meta.per_page"
        :disabled="disabled"
        class="h-8 shrink-0 rounded-lg border border-hairline bg-surface px-2 text-xs text-ink focus:border-navy focus:outline-none"
        @change="(e) => emit('changePerPage', Number((e.target as HTMLSelectElement).value))"
      >
        <option :value="5">5 / page</option>
        <option :value="10">10 / page</option>
        <option :value="25">25 / page</option>
        <option :value="50">50 / page</option>
      </select>
    </div>
  </div>
</template>
