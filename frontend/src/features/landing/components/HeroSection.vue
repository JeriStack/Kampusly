<script setup lang="ts">
import { computed } from 'vue'
import BaseButton from '@/components/common/BaseButton.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import SchedulePreview from './SchedulePreview.vue'

const props = defineProps<{ ready: boolean }>()
const titleWords = computed(() => 'Your Campus. Your Classes. Your Time.'.split(' '))
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
      <div class="hero-copy text-center lg:text-left" :class="{ 'hero-copy--ready': props.ready }">
        <p
          class="hero-eyebrow inline-flex items-center rounded-full bg-accent-soft px-3 py-1 text-xs font-bold uppercase tracking-wide text-accent dark:bg-accent/15 dark:text-teal-300"
        >
          Smarter Campus Scheduling
        </p>
        <h1
          class="hero-title mt-5 text-center text-4xl font-extrabold leading-tight tracking-tight text-navy sm:text-5xl lg:text-left dark:text-white"
          aria-label="Your Campus. Your Classes. Your Time."
        >
          <span
            v-for="(word, index) in titleWords"
            :key="word"
            class="hero-word-clip"
            :style="{ '--word-index': index }"
            aria-hidden="true"
          >
            <span class="hero-word">{{ word }}</span>
          </span>
        </h1>
        <p class="hero-description mx-auto mt-5 max-w-xl text-justify text-lg leading-relaxed text-muted lg:mx-0">
          Stay on top of your class schedule, keep up with announcements, and get
          notified when your classes change, all in one place.
        </p>
        <div class="hero-actions mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center lg:justify-start">
          <BaseButton to="/login" variant="primary" size="lg">
            Access My Schedule
            <AppIcon name="arrow-right" class="h-5 w-5" />
          </BaseButton>
          <BaseButton href="#features" variant="secondary" size="lg">
            Explore Features
          </BaseButton>
        </div>
      </div>

      <!-- Right: product preview -->
      <div class="hero-preview lg:pl-6" :class="{ 'hero-preview--ready': props.ready }">
        <SchedulePreview />
      </div>
    </div>
  </section>
</template>

<style scoped>
.hero-eyebrow,
.hero-description,
.hero-actions,
.hero-preview {
  opacity: 0;
  translate: 0 20px;
}

.hero-eyebrow,
.hero-description,
.hero-actions,
.hero-preview {
  transition:
    opacity 700ms cubic-bezier(0.16, 1, 0.3, 1),
    translate 700ms cubic-bezier(0.16, 1, 0.3, 1);
}

.hero-description {
  transition-delay: 350ms;
}

.hero-actions {
  transition-delay: 480ms;
}

.hero-preview {
  transition-delay: 650ms;
}

.hero-copy--ready .hero-eyebrow,
.hero-copy--ready .hero-description,
.hero-copy--ready .hero-actions,
.hero-preview--ready {
  opacity: 1;
  translate: 0 0;
}

.hero-title {
  overflow: visible;
}

.hero-word-clip {
  display: inline-block;
  overflow: hidden;
  margin-right: 0.22em;
  margin-bottom: -0.1em;
  padding-bottom: 0.1em;
  white-space: pre;
}

.hero-word-clip:last-child {
  margin-right: 0;
}

.hero-word {
  display: inline-block;
  transform: translateY(115%);
  opacity: 0;
  transition:
    transform 1100ms cubic-bezier(0.16, 1, 0.3, 1),
    opacity 1100ms cubic-bezier(0.16, 1, 0.3, 1);
  transition-delay: calc(var(--word-index) * 140ms);
}

.hero-copy--ready .hero-word {
  transform: translateY(0);
  opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
  .hero-eyebrow,
  .hero-description,
  .hero-actions,
  .hero-preview,
  .hero-word {
    transition-duration: 0.01ms;
    transition-delay: 0ms;
  }
}
</style>
