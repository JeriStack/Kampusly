<script setup lang="ts">
import { RouterLink } from 'vue-router'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'

/**
 * Placeholder destination for the landing page's Access / Log In CTAs.
 * The real authentication screen is implemented in the Identity & Access
 * phase; this keeps the public landing links functional in the meantime.
 */
</script>

<template>
  <main
    class="relative flex min-h-screen flex-col items-center justify-center gap-6 bg-canvas px-6 text-center"
  >
    <div class="absolute right-4 top-4 sm:right-6 sm:top-6">
      <ThemeToggle />
    </div>
    <KampuslyLogo size="lg" />
    <div class="max-w-md space-y-3">
      <h1 class="text-2xl font-semibold text-ink">Sign in to Kampusly</h1>
      <p class="text-muted">
        The sign-in experience is coming soon. Accounts are provisioned by your
        campus administrator.
      </p>
    </div>
    <RouterLink
      to="/"
      class="inline-flex items-center justify-center rounded-lg border border-hairline bg-surface px-5 py-2.5 text-sm font-semibold text-navy transition-colors hover:bg-navy-50 dark:text-white dark:hover:bg-white/10"
    >
      Back to home
    </RouterLink>
  </main>
</template>
