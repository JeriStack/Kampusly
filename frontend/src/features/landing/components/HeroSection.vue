<script setup lang="ts">
import BaseButton from '@/components/common/BaseButton.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import SchedulePreview from './SchedulePreview.vue'
</script>

<template>
  <section
    id="top"
    class="relative overflow-hidden border-b border-hairline bg-gradient-to-b from-navy-50/60 to-canvas dark:from-navy-900/40 dark:to-canvas"
  >
    <div
      class="mx-auto grid max-w-content items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-24"
    >
      <!-- Left: copy + CTAs -->
      <div class="text-center lg:text-left">
        <p
          class="inline-flex items-center rounded-full bg-accent-soft px-3 py-1 text-xs font-bold uppercase tracking-wide text-accent dark:bg-accent/15 dark:text-teal-300"
        >
          Smarter Campus Scheduling
        </p>
        <h1
          class="mt-5 text-center text-4xl font-extrabold leading-tight tracking-tight text-navy sm:text-5xl lg:text-left dark:text-white"
        >
          Your Campus. Your Classes. Your Time.
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-justify text-lg leading-relaxed text-muted lg:mx-0">
          Stay on top of your class schedule, keep up with announcements, and get
          notified when your classes change, all in one place.
        </p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center lg:justify-start">
          <BaseButton to="/login" variant="primary" size="lg">
            Access My Schedule
            <AppIcon name="arrow-right" class="h-5 w-5" />
          </BaseButton>
          <BaseButton href="#features" variant="secondary" size="lg">
            Explore Features
          </BaseButton>
        </div>
        <RouterLink
          to="/demo"
          class="mt-4 inline-flex rounded-md text-sm font-medium text-action underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2"
        >
          Explore the interactive workflow demo
        </RouterLink>
      </div>

      <!-- Right: product preview -->
      <div class="lg:pl-6">
        <SchedulePreview />
      </div>
    </div>
  </section>
</template>
