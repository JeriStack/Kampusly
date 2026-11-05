<script setup lang="ts">
import AppIcon from '@/components/common/AppIcon.vue'

withDefaults(
  defineProps<{
    open: boolean
    title: string
    message: string
    confirmLabel?: string
    cancelLabel?: string
    variant?: 'danger' | 'warning' | 'primary'
    isLoading?: boolean
  }>(),
  {
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    variant: 'danger',
    isLoading: false,
  }
)

const emit = defineEmits<{
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
  >
    <div
      class="w-full max-w-md rounded-2xl border border-hairline bg-surface p-6 shadow-card transition-all"
    >
      <div class="flex items-start gap-3">
        <div
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
          :class="{
            'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400': variant === 'danger',
            'bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400': variant === 'warning',
            'bg-navy-100 text-navy-700 dark:bg-navy-900/60 dark:text-primary-300': variant === 'primary',
          }"
        >
          <AppIcon
            :name="variant === 'danger' ? 'alert-triangle' : 'info'"
            class="h-5 w-5"
          />
        </div>
        <div class="flex-1">
          <h3 class="text-base font-bold text-ink">{{ title }}</h3>
          <p class="mt-1 text-xs text-muted leading-relaxed">{{ message }}</p>
        </div>
      </div>

      <div class="mt-6 flex items-center justify-end gap-2.5">
        <button
          type="button"
          :disabled="isLoading"
          class="rounded-lg border border-hairline bg-surface px-3.5 py-2 text-xs font-semibold text-ink transition-colors hover:bg-canvas disabled:opacity-50"
          @click="emit('cancel')"
        >
          {{ cancelLabel }}
        </button>
        <button
          type="button"
          :disabled="isLoading"
          class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors disabled:opacity-50"
          :class="{
            'bg-rose-600 hover:bg-rose-700': variant === 'danger',
            'bg-amber-600 hover:bg-amber-700': variant === 'warning',
            'bg-navy hover:bg-navy-700': variant === 'primary',
          }"
          @click="emit('confirm')"
        >
          <AppIcon v-if="isLoading" name="loader" class="h-3.5 w-3.5 animate-spin" />
          <span>{{ confirmLabel }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

