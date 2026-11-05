<script setup lang="ts">
import { computed, useId } from 'vue'

const props = withDefaults(
  defineProps<{
    /** Render only the mark without the wordmark. */
    iconOnly?: boolean
    /** Mark size in Tailwind spacing scale (h-/w-). */
    size?: 'sm' | 'md' | 'lg'
  }>(),
  { iconOnly: false, size: 'md' },
)

// Unique gradient IDs so multiple instances on one page never collide.
const uid = useId()
const capId = `kl-cap-${uid}`
const bodyId = `kl-body-${uid}`
const kId = `kl-k-${uid}`
const clockId = `kl-clock-${uid}`

const markClass = computed(
  () =>
    ({
      sm: 'h-7 w-7',
      md: 'h-9 w-9',
      lg: 'h-12 w-12',
    })[props.size],
)

const wordClass = computed(
  () =>
    ({
      sm: 'text-base',
      md: 'text-lg',
      lg: 'text-2xl',
    })[props.size],
)
</script>

<template>
  <span class="inline-flex items-center gap-2.5">
    <!--
      Kampusly mark: a graduation cap over a calendar carrying a "K", a
      schedule dot-grid, and a clock badge — education + scheduling + time.
    -->
    <svg
      :class="['shrink-0', markClass]"
      viewBox="0 0 64 64"
      fill="none"
      role="img"
      aria-label="Kampusly logo"
    >
      <defs>
        <linearGradient :id="capId" x1="12" y1="12" x2="52" y2="30" gradientUnits="userSpaceOnUse">
          <stop stop-color="#2563EB" />
          <stop offset="1" stop-color="#123B78" />
        </linearGradient>
        <linearGradient :id="bodyId" x1="14" y1="22" x2="50" y2="56" gradientUnits="userSpaceOnUse">
          <stop stop-color="#2563EB" />
          <stop offset="1" stop-color="#123B78" />
        </linearGradient>
        <linearGradient :id="kId" x1="20" y1="28" x2="38" y2="50" gradientUnits="userSpaceOnUse">
          <stop stop-color="#2563EB" />
          <stop offset="1" stop-color="#0C274D" />
        </linearGradient>
        <linearGradient :id="clockId" x1="40" y1="40" x2="56" y2="56" gradientUnits="userSpaceOnUse">
          <stop stop-color="#14B8A6" />
          <stop offset="1" stop-color="#0D9488" />
        </linearGradient>
      </defs>

      <!-- Calendar body -->
      <rect x="12" y="20" width="34" height="34" rx="9" :fill="`url(#${bodyId})`" />
      <rect x="17" y="25" width="24" height="24" rx="6" fill="#FFFFFF" />

      <!-- Calendar top rings -->
      <rect x="21" y="16" width="4" height="9" rx="2" fill="#FFFFFF" />
      <rect x="33" y="16" width="4" height="9" rx="2" fill="#FFFFFF" />

      <!-- "K" -->
      <path
        d="M23 29.5v15M23 37l6.5-7.5M25.5 36l5.5 8.5"
        :stroke="`url(#${kId})`"
        stroke-width="3.6"
        stroke-linecap="round"
        stroke-linejoin="round"
      />

      <!-- Schedule dot-grid -->
      <g fill="#BBD3F2">
        <rect x="33.5" y="30" width="3.2" height="3.2" rx="1" />
        <rect x="38" y="30" width="3.2" height="3.2" rx="1" />
        <rect x="33.5" y="34.5" width="3.2" height="3.2" rx="1" />
        <rect x="38" y="34.5" width="3.2" height="3.2" rx="1" fill="#0D9488" />
      </g>

      <!-- Graduation cap -->
      <path d="M32 6 54 16 32 26 10 16 32 6Z" :fill="`url(#${capId})`" />
      <path
        d="M20 20.5V26c0 2.5 5.4 4.5 12 4.5S44 28.5 44 26v-5.5L32 25.5 20 20.5Z"
        fill="#123B78"
      />
      <!-- Tassel -->
      <path d="M52 16v9" stroke="#123B78" stroke-width="1.6" stroke-linecap="round" />
      <circle cx="52" cy="26" r="2" fill="#123B78" />

      <!-- Clock badge -->
      <circle cx="48" cy="48" r="9" :fill="`url(#${clockId})`" stroke="#FFFFFF" stroke-width="2.5" />
      <path
        d="M48 44v4.2l2.8 1.8"
        stroke="#FFFFFF"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>

    <span
      v-if="!iconOnly"
      :class="['font-bold tracking-tight text-navy dark:text-white', wordClass]"
    >
      Kampusly
    </span>
  </span>
</template>
