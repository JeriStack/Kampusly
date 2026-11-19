<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

type Variant = 'primary' | 'secondary' | 'ghost'
type Size = 'md' | 'lg'

const props = withDefaults(
  defineProps<{
    variant?: Variant
    size?: Size
    /** Internal route target; renders a RouterLink when set. */
    to?: string
    /** External/anchor href; renders an <a> when set. */
    href?: string
    disabled?: boolean
    type?: 'button' | 'submit'
  }>(),
  { variant: 'primary', size: 'md', type: 'button', disabled: false },
)

const base =
  'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:cursor-not-allowed disabled:opacity-60'

const variants: Record<Variant, string> = {
  primary:
    'bg-action text-white shadow-sm hover:bg-action-hover active:bg-action-active disabled:hover:bg-action',
  secondary:
    'border border-hairline bg-surface text-navy hover:bg-navy-50 active:bg-navy-100 dark:text-white dark:hover:bg-white/10 dark:active:bg-white/5',
  ghost:
    'text-navy hover:bg-navy-50 active:bg-navy-100 dark:text-white dark:hover:bg-white/10',
}

const sizes: Record<Size, string> = {
  md: 'px-5 py-2.5 text-sm',
  lg: 'px-6 py-3 text-base',
}

const classes = computed(() => [base, variants[props.variant], sizes[props.size]])

const tag = computed(() => {
  if (props.to) return RouterLink
  if (props.href) return 'a'
  return 'button'
})
</script>

<template>
  <component
    :is="tag"
    :class="classes"
    :to="to"
    :href="href"
    :type="to || href ? undefined : type"
    :disabled="tag === 'button' ? disabled : undefined"
    :aria-disabled="disabled ? 'true' : undefined"
  >
    <slot />
  </component>
</template>
