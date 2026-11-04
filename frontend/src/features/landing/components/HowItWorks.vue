<script setup lang="ts">
import AppIcon from '@/components/common/AppIcon.vue'
import { steps } from '../content'
</script>

<template>
  <section
    id="how-it-works"
    class="scroll-mt-20 border-y border-hairline bg-navy-50/40 py-16 sm:py-24 dark:bg-white/[0.03]"
  >
    <div class="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-2xl text-center">
        <h2
          class="text-3xl font-bold tracking-tight text-navy sm:text-4xl dark:text-white"
        >
          Your Schedule, Simplified
        </h2>
        <p class="mt-4 text-lg text-muted">
          Get set up and stay informed in three simple steps.
        </p>
      </div>

      <ol class="mt-14 grid grid-cols-1 gap-12 md:grid-cols-3 md:gap-8">
        <li
          v-for="(step, index) in steps"
          :key="step.number"
          class="group relative flex flex-col items-center text-center"
        >
          <!--
            Connector between adjacent steps on desktop. Starts just past the
            current circle and stops just before the next one, so it never
            runs behind a number badge.
          -->
          <span
            v-if="index < steps.length - 1"
            class="absolute left-[calc(50%+2.5rem)] right-[calc(-50%+2.5rem)] top-8 hidden h-0.5 -translate-y-1/2 bg-gradient-to-r from-action/40 to-action/10 md:block"
            aria-hidden="true"
          />

          <!-- Numbered badge with a supporting icon -->
          <span class="relative z-10 inline-flex">
            <span
              class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-action text-white shadow-md transition-transform duration-200 group-hover:-translate-y-1"
            >
              <AppIcon :name="step.icon" class="h-7 w-7" />
            </span>
            <span
              class="absolute -right-1.5 -top-1.5 inline-flex h-6 w-6 items-center justify-center rounded-full border-2 border-canvas bg-navy text-xs font-bold text-white dark:bg-action"
            >
              {{ step.number }}
            </span>
          </span>

          <h3 class="mt-6 text-lg font-semibold text-ink">{{ step.title }}</h3>
          <p class="mt-2 max-w-xs text-sm leading-relaxed text-muted">
            {{ step.description }}
          </p>
        </li>
      </ol>
    </div>
  </section>
</template>
