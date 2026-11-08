<script setup lang="ts">
import BaseButton from '@/components/common/BaseButton.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import { revealDirective as vReveal } from '@/directives/motion'
</script>

<template>
  <section class="px-4 pb-16 sm:px-6 sm:pb-24 lg:px-8">
    <div
      class="relative mx-auto max-w-content overflow-hidden rounded-card bg-navy px-6 py-16 text-center shadow-card ring-1 ring-white/10 sm:px-12 sm:py-20"
    >
      <!-- Decorative accent glows — restrained, non-interactive. -->
      <div
        class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-accent/20 blur-3xl"
        aria-hidden="true"
      />
      <div
        class="pointer-events-none absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-action/25 blur-3xl"
        aria-hidden="true"
      />

      <div v-reveal class="relative">
        <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
          Make Every Class Count.
        </h2>
        <p class="mx-auto mt-4 max-w-xl text-lg text-slate-200">
          Access your schedule, stay informed, and make your campus day easier
          with Kampusly.
        </p>
        <div class="mt-8 flex justify-center">
          <BaseButton to="/login" variant="primary" size="lg">
            Access Kampusly
            <AppIcon name="arrow-right" class="h-5 w-5" />
          </BaseButton>
        </div>
      </div>
    </div>
  </section>
</template>
