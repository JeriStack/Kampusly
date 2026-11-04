<script setup lang="ts">
import AppIcon from '@/components/common/AppIcon.vue'
import { userRoles } from '../content'
</script>

<template>
  <section id="about" class="scroll-mt-20 py-16 sm:py-24">
    <div class="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-2xl text-center">
        <h2
          class="text-3xl font-bold tracking-tight text-navy sm:text-4xl dark:text-white"
        >
          Built for Your Campus Community
        </h2>
        <p class="mt-4 text-lg text-muted">
          Every role gets the tools that matter most to their day.
        </p>
      </div>

      <ul class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-3">
        <li
          v-for="role in userRoles"
          :key="role.title"
          class="group flex flex-col items-center rounded-card border border-hairline bg-surface p-7 text-center shadow-card transition-all duration-200 hover:-translate-y-1 hover:border-accent/30 hover:shadow-card-hover"
        >
          <span
            class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-soft text-accent transition-colors group-hover:bg-accent group-hover:text-white dark:bg-accent/15 dark:text-teal-300"
          >
            <AppIcon :name="role.icon" class="h-7 w-7" />
          </span>
          <h3 class="mt-5 text-xl font-semibold text-ink">{{ role.title }}</h3>
          <p class="mt-3 text-sm leading-relaxed text-muted">
            {{ role.description }}
          </p>
        </li>
      </ul>
    </div>
  </section>
</template>
