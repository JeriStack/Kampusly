<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue'
import { useAuth } from '@/features/auth/stores/useAuth'
import AppIcon from '@/components/common/AppIcon.vue'

const props = defineProps<{
  subtitle?: string
}>()

const { user } = useAuth()
const uid = useId()
const popoverId = `user-session-popover-${uid}`

const wrapperRef = ref<HTMLElement | null>(null)
const supportsPopover = ref(true)
const fallbackOpen = ref(false)

const displaySubtitle = computed(() => props.subtitle || user.value?.email || '')

function handleTriggerClick(): void {
  if (!supportsPopover.value) {
    fallbackOpen.value = !fallbackOpen.value
  }
}

function handleDocumentClick(event: MouseEvent): void {
  if (supportsPopover.value || !fallbackOpen.value) return
  if (wrapperRef.value && !wrapperRef.value.contains(event.target as Node)) {
    fallbackOpen.value = false
  }
}

function handleDocumentKeydown(event: KeyboardEvent): void {
  if (!supportsPopover.value && fallbackOpen.value && event.key === 'Escape') {
    fallbackOpen.value = false
  }
}

onMounted(() => {
  supportsPopover.value =
    typeof HTMLElement !== 'undefined' &&
    Object.prototype.hasOwnProperty.call(HTMLElement.prototype, 'popover')
  if (!supportsPopover.value) {
    document.addEventListener('click', handleDocumentClick)
    document.addEventListener('keydown', handleDocumentKeydown)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick)
  document.removeEventListener('keydown', handleDocumentKeydown)
})
</script>

<template>
  <div ref="wrapperRef" class="relative">
    <button
      type="button"
      :popovertarget="supportsPopover ? popoverId : undefined"
      :aria-expanded="!supportsPopover ? fallbackOpen : undefined"
      title="View session details"
      class="group flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-right transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
      @click="handleTriggerClick"
    >
      <div class="leading-tight">
        <div class="text-xs font-semibold text-ink group-hover:text-navy dark:group-hover:text-sky-200">
          {{ user?.name }}
        </div>
        <div v-if="displaySubtitle" class="hidden text-[11px] text-subtle sm:block">
          {{ displaySubtitle }}
        </div>
      </div>
    </button>

    <div
      :id="popoverId"
      :popover="supportsPopover ? 'auto' : undefined"
      :class="[
        'session-popover w-72 overflow-hidden rounded-2xl border border-hairline bg-surface p-0 text-ink shadow-xl',
        !supportsPopover && (fallbackOpen ? 'absolute right-0 top-full z-40 mt-2 block' : 'hidden'),
      ]"
    >
      <div class="h-1 w-full bg-gradient-to-r from-navy via-action to-accent" />
      <div class="p-4">
        <div class="flex items-start justify-between gap-3 border-b border-hairline pb-3">
          <div class="min-w-0">
            <h2 class="text-sm font-bold text-ink">Your Session</h2>
            <p class="truncate text-xs text-subtle">{{ user?.email }}</p>
          </div>
          <button
            type="button"
            :popovertarget="supportsPopover ? popoverId : undefined"
            :popovertargetaction="supportsPopover ? 'hide' : undefined"
            aria-label="Close session details"
            class="rounded-md p-1 text-subtle transition-colors hover:bg-canvas hover:text-ink"
            @click="!supportsPopover && (fallbackOpen = false)"
          >
            <AppIcon name="close" class="h-4 w-4" />
          </button>
        </div>

        <dl class="mt-3 space-y-2.5 text-xs">
          <div class="flex items-center justify-between border-b border-hairline/70 pb-2">
            <dt class="text-muted">Account Role</dt>
            <dd class="rounded-md bg-navy-50 px-2 py-0.5 font-bold uppercase tracking-wide text-navy dark:bg-sky-500/15 dark:text-sky-300">
              {{ user?.role }}
            </dd>
          </div>
          <div class="flex items-center justify-between border-b border-hairline/70 pb-2">
            <dt class="text-muted">Status</dt>
            <dd class="inline-flex items-center gap-1.5 font-semibold capitalize text-emerald-600 dark:text-emerald-400">
              <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true" />
              {{ user?.status }}
            </dd>
          </div>
          <div class="flex items-center justify-between pt-0.5">
            <dt class="text-muted">User ID</dt>
            <dd class="font-mono font-semibold text-ink">{{ user?.id }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</template>

<style scoped>
@supports (position-area: block-end) {
  .session-popover[popover] {
    inset: auto;
    margin: 0;
    margin-block-start: 0.5rem;
    position-area: block-end span-inline-start;
    position-try-fallbacks: flip-block, flip-inline, flip-block flip-inline;
  }
}
</style>
