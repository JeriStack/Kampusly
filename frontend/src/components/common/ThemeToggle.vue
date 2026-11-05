<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/common/AppIcon.vue'
import { useTheme } from '@/composables/useTheme'

const { theme, toggle } = useTheme()

const isDark = computed(() => theme.value === 'dark')
const label = computed(() =>
  isDark.value ? 'Switch to light mode' : 'Switch to dark mode',
)
</script>

<template>
  <button
    type="button"
    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-hairline bg-surface text-muted transition-colors hover:text-navy focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-canvas dark:hover:text-white"
    :aria-label="label"
    :title="label"
    @click="toggle"
  >
    <AppIcon :name="isDark ? 'sun' : 'moon'" class="h-5 w-5" />
  </button>
</template>
