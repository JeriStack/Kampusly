<script setup lang="ts">
import { ref, watch } from 'vue'
import AppIcon from '@/components/common/AppIcon.vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    debounceMs?: number
  }>(),
  {
    placeholder: 'Search records...',
    debounceMs: 350,
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'search', value: string): void
}>()

const localValue = ref(props.modelValue)
let timer: ReturnType<typeof setTimeout> | null = null

watch(
  () => props.modelValue,
  (newVal) => {
    localValue.value = newVal
  }
)

function onInput(e: Event): void {
  const target = e.target as HTMLInputElement
  localValue.value = target.value
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => {
    emit('update:modelValue', localValue.value)
    emit('search', localValue.value)
  }, props.debounceMs)
}

function handleClear(): void {
  localValue.value = ''
  if (timer) clearTimeout(timer)
  emit('update:modelValue', '')
  emit('search', '')
}
</script>

<template>
  <div class="relative w-full max-w-sm">
    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted">
      <AppIcon name="search" class="h-4 w-4" />
    </div>
    <input
      :value="localValue"
      type="text"
      :placeholder="placeholder"
      class="w-full rounded-lg border border-hairline bg-canvas py-2 pl-9 pr-8 text-xs text-ink placeholder-muted/60 transition-colors focus:border-navy focus:outline-none focus:ring-1 focus:ring-navy/20 dark:focus:border-primary-400"
      @input="onInput"
    />
    <button
      v-if="localValue"
      type="button"
      class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-muted hover:text-ink"
      aria-label="Clear search"
      @click="handleClear"
    >
      <AppIcon name="close" class="h-3.5 w-3.5" />
    </button>
  </div>
</template>

