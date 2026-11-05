<script setup lang="ts">
import { RouterLink } from 'vue-router'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import { navLinks } from '../content'

const year = new Date().getFullYear()
</script>

<template>
  <footer class="border-t border-hairline bg-surface">
    <div class="mx-auto max-w-content px-4 py-14 sm:px-6 lg:px-8">
      <div class="grid gap-10 md:grid-cols-12">
        <!-- Brand -->
        <div class="flex flex-col items-center text-center md:col-span-5 md:items-start md:text-left">
          <KampuslyLogo />
          <p class="mt-3 max-w-xs text-justify text-sm leading-relaxed text-muted">
            A web-based class schedule management and notification system.
            Your Campus. Your Classes. Your Time.
          </p>
        </div>

        <!-- Link columns -->
        <div class="grid grid-cols-2 gap-8 text-center md:col-span-7 md:justify-items-end">
          <nav aria-label="Explore">
            <h2 class="text-sm font-semibold text-ink">Explore</h2>
            <ul class="mt-4 space-y-3">
              <li v-for="link in navLinks" :key="link.href">
                <a
                  :href="link.href"
                  class="text-sm text-muted transition-colors hover:text-navy dark:hover:text-white"
                >
                  {{ link.label }}
                </a>
              </li>
            </ul>
          </nav>

          <nav aria-label="Get started">
            <h2 class="text-sm font-semibold text-ink">Get started</h2>
            <ul class="mt-4 space-y-3">
              <li>
                <RouterLink
                  to="/login"
                  class="text-sm text-muted transition-colors hover:text-navy dark:hover:text-white"
                >
                  Log In
                </RouterLink>
              </li>
            </ul>
          </nav>
        </div>
      </div>

      <div
        class="mt-12 flex flex-col gap-3 border-t border-hairline-soft pt-6 text-center text-sm text-subtle sm:flex-row sm:items-center sm:justify-between sm:text-left"
      >
        <p>&copy; {{ year }} Kampusly. All rights reserved.</p>
        <p>
          Your data is used only to deliver your schedules, announcements, and
          notifications.
        </p>
      </div>
    </div>
  </footer>
</template>
