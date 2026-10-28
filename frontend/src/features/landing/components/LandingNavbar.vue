<script setup lang="ts">
import { ref } from 'vue'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import BaseButton from '@/components/common/BaseButton.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import { useScrollSpy } from '@/composables/useScrollSpy'
import { navLinks } from '../content'

const mobileOpen = ref(false)

// Section ids derived from the nav anchor hrefs ("#features" -> "features").
const sectionIds = navLinks.map((link) => link.href.replace('#', ''))
const activeId = useScrollSpy(sectionIds)

function isActive(href: string): boolean {
  return activeId.value === href.replace('#', '')
}

function closeMobile() {
  mobileOpen.value = false
}
</script>

<template>
  <header
    class="sticky top-0 z-40 border-b border-hairline bg-surface/80 shadow-sm backdrop-blur-md"
  >
    <nav
      class="relative mx-auto flex h-16 max-w-content items-center justify-between px-4 sm:px-6 lg:px-8"
      aria-label="Primary"
    >
      <a
        href="#top"
        class="flex items-center rounded-md"
        aria-label="Kampusly home"
      >
        <KampuslyLogo size="md" />
      </a>

      <!-- Desktop links — centered between the logo and the action. -->
      <ul
        class="absolute left-1/2 hidden -translate-x-1/2 items-center gap-8 md:flex"
      >
        <li v-for="link in navLinks" :key="link.href">
          <a
            :href="link.href"
            :aria-current="isActive(link.href) ? 'true' : undefined"
            class="group relative text-sm font-medium transition-colors hover:text-navy dark:hover:text-white"
            :class="
              isActive(link.href)
                ? 'text-navy dark:text-white'
                : 'text-muted'
            "
          >
            {{ link.label }}
            <span
              class="absolute -bottom-1.5 left-0 h-0.5 rounded-full bg-action transition-all duration-300 ease-out group-hover:w-full"
              :class="isActive(link.href) ? 'w-full' : 'w-0'"
              aria-hidden="true"
            />
          </a>
        </li>
      </ul>

      <div class="hidden items-center gap-3 md:flex">
        <ThemeToggle />
        <BaseButton to="/login" variant="primary">
          <AppIcon name="login" class="h-4 w-4" />
          Log In
        </BaseButton>
      </div>

      <!-- Mobile controls -->
      <div class="flex items-center gap-2 md:hidden">
        <ThemeToggle />
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md p-2 text-navy hover:bg-navy-50 dark:text-white dark:hover:bg-white/10"
          :aria-expanded="mobileOpen"
          aria-controls="mobile-menu"
          @click="mobileOpen = !mobileOpen"
        >
          <span class="sr-only">
            {{ mobileOpen ? 'Close menu' : 'Open menu' }}
          </span>
          <AppIcon :name="mobileOpen ? 'close' : 'menu'" class="h-6 w-6" />
        </button>
      </div>
    </nav>

    <!-- Mobile menu -->
    <div
      v-show="mobileOpen"
      id="mobile-menu"
      class="border-t border-hairline bg-surface md:hidden"
    >
      <ul class="space-y-1 px-4 py-3">
        <li v-for="link in navLinks" :key="link.href">
          <a
            :href="link.href"
            :aria-current="isActive(link.href) ? 'true' : undefined"
            class="block rounded-md border-l-2 px-3 py-2 text-base font-medium transition-colors hover:bg-navy-50 hover:text-navy dark:hover:bg-white/10 dark:hover:text-white"
            :class="
              isActive(link.href)
                ? 'border-action bg-navy-50 text-navy dark:bg-white/10 dark:text-white'
                : 'border-transparent text-muted'
            "
            @click="closeMobile"
          >
            {{ link.label }}
          </a>
        </li>
        <li class="pt-2">
          <BaseButton to="/login" variant="primary" class="w-full" @click="closeMobile">
            <AppIcon name="login" class="h-4 w-4" />
            Log In
          </BaseButton>
        </li>
      </ul>
    </div>
  </header>
</template>
