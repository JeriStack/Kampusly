<script setup lang="ts">
import AppIcon from '@/components/common/AppIcon.vue'
import { hoverSpringDirective as vHoverSpring, revealDirective as vReveal } from '@/directives/motion'
import { features } from '../content'
</script>

<template>
  <section id="features" class="scroll-mt-20 py-16 sm:py-24">
    <div class="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
      <div v-reveal class="mx-auto max-w-2xl text-center">
        <h2
          class="text-3xl font-bold tracking-tight text-navy sm:text-4xl dark:text-white"
        >
          Everything You Need to Stay on Schedule
        </h2>
        <p class="mt-4 text-lg text-muted">
          Keep your academic day organized with the information you need in one
          place.
        </p>
      </div>

      <ul
        class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4"
      >
        <li
          v-for="(feature, index) in features"
          :key="feature.title"
          v-reveal="{ delay: index * 90 }"
          v-hover-spring="{ y: -5, scale: 1.02 }"
          class="group flex flex-col items-center rounded-card border border-hairline bg-surface p-6 text-center shadow-card transition-colors transition-shadow duration-200 hover:border-action/30 hover:shadow-card-hover"
        >
          <span
            class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-navy-50 text-navy transition-colors group-hover:bg-action group-hover:text-white dark:bg-white/10 dark:text-white"
          >
            <AppIcon :name="feature.icon" class="h-6 w-6" />
          </span>
          <h3 class="mt-5 text-lg font-semibold text-ink">
            {{ feature.title }}
          </h3>
          <p class="mt-2 text-sm leading-relaxed text-muted">
            {{ feature.description }}
          </p>
        </li>
      </ul>
    </div>
  </section>
</template>
