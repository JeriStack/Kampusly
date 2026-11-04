<script setup lang="ts">
import AppIcon from '@/components/common/AppIcon.vue'

interface PreviewClass {
  time: string
  subject: string
  code: string
  room: string
  instructor: string
  tone: 'navy' | 'accent' | 'action'
}

// Fictional demonstration data — illustrative only, not a live dashboard.
const today: PreviewClass[] = [
  {
    time: '08:00',
    subject: 'Data Structures',
    code: 'CS 201',
    room: 'Room 304',
    instructor: 'Prof. Reyes',
    tone: 'navy',
  },
  {
    time: '10:00',
    subject: 'Discrete Mathematics',
    code: 'MATH 115',
    room: 'Room 212',
    instructor: 'Dr. Santos',
    tone: 'accent',
  },
  {
    time: '13:00',
    subject: 'Technical Writing',
    code: 'ENG 102',
    room: 'Room 118',
    instructor: 'Prof. Lim',
    tone: 'action',
  },
]

const toneClasses: Record<PreviewClass['tone'], string> = {
  navy: 'border-l-navy bg-navy-50 dark:border-l-blue-400 dark:bg-white/5',
  accent: 'border-l-accent bg-accent-soft dark:border-l-teal-400 dark:bg-accent/10',
  action: 'border-l-action bg-blue-50 dark:border-l-blue-400 dark:bg-action/10',
}
</script>

<template>
  <div role="group" aria-label="Illustrative schedule preview">
    <!--
      Reserve space at the bottom so the floating notification can overhang the
      card without colliding with the caption that follows it.
    -->
    <div class="relative pb-14 sm:pb-12">
      <!-- Main dashboard card -->
      <div
        class="rounded-card border border-hairline bg-surface shadow-card-hover"
      >
        <!-- Card header -->
        <div
          class="flex items-center justify-between border-b border-hairline-soft px-5 py-4"
        >
          <div>
            <p
              class="text-xs font-medium uppercase tracking-wide text-subtle"
            >
              My Schedule
            </p>
            <p class="text-sm font-semibold text-ink">Today · Monday</p>
          </div>
          <span
            class="inline-flex items-center gap-1.5 rounded-full bg-accent-soft px-3 py-1 text-xs font-semibold text-accent dark:bg-accent/15 dark:text-teal-300"
          >
            <span
              class="h-1.5 w-1.5 rounded-full bg-accent dark:bg-teal-300"
              aria-hidden="true"
            />
            3 classes
          </span>
        </div>

        <!-- Class rows -->
        <ul class="space-y-2.5 p-5">
          <li
            v-for="item in today"
            :key="item.code"
            class="flex items-center gap-4 rounded-xl border-l-4 px-4 py-3"
            :class="toneClasses[item.tone]"
          >
            <span class="w-12 shrink-0 text-sm font-bold text-ink">
              {{ item.time }}
            </span>
            <span class="h-8 w-px bg-hairline" aria-hidden="true" />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-semibold text-ink">
                {{ item.subject }}
              </span>
              <span class="block truncate text-xs text-muted">
                {{ item.code }} · {{ item.room }} · {{ item.instructor }}
              </span>
            </span>
          </li>
        </ul>
      </div>

      <!--
        Floating update notification. Anchored to the reserved padding at the
        bottom of the card so it overhangs without covering the caption.
      -->
      <div
        class="absolute bottom-0 left-2 flex max-w-[17rem] items-start gap-3 rounded-xl border border-hairline bg-surface px-4 py-3 shadow-card-hover sm:-left-4"
        role="status"
      >
        <span
          class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-action/10 text-action dark:bg-action/20 dark:text-blue-400"
        >
          <AppIcon name="bell" class="h-4 w-4" />
        </span>
        <span>
          <span class="block text-xs font-semibold text-ink">
            Schedule updated
          </span>
          <span class="block text-xs text-muted">
            ENG 102 moved to Room 118.
          </span>
        </span>
      </div>
    </div>

    <!-- Illustrative label — sits in normal flow, clear of the notification. -->
    <p class="mt-4 text-center text-xs text-subtle">
      Preview with sample data for illustration only.
    </p>
  </div>
</template>
