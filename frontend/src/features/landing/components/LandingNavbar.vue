<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
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

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && mobileOpen.value) {
    closeMobile()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <header
    class="sticky top-0 z-40 border-b border-hairline bg-surface/85 shadow-sm backdrop-blur-md"
  >
    <nav
      class="relative z-50 mx-auto flex h-16 max-w-content items-center justify-between px-4 sm:px-6 lg:px-8"
      aria-label="Primary"
    >
      <a
        href="#top"
        class="flex items-center rounded-md"
        aria-label="Kampusly home"
        @click="closeMobile"
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
          class="inline-flex h-10 w-10 items-center justify-center rounded-lg border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-canvas"
          :class="
            mobileOpen
              ? 'border-action/30 bg-navy-50 text-navy dark:border-white/20 dark:bg-white/10 dark:text-white'
              : 'border-hairline bg-surface text-muted hover:bg-navy-50 hover:text-navy dark:hover:bg-white/10 dark:hover:text-white'
          "
          :aria-expanded="mobileOpen"
          aria-controls="mobile-menu"
          :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
          @click="mobileOpen = !mobileOpen"
        >
          <AppIcon :name="mobileOpen ? 'close' : 'menu'" class="h-5 w-5" />
        </button>
      </div>
    </nav>

    <!-- Mobile backdrop for light dismiss -->
    <Transition
      enter-active-class="transition-opacity duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="mobileOpen"
        class="fixed inset-x-0 bottom-0 top-16 z-30 bg-slate-900/20 backdrop-blur-[1px] dark:bg-black/40 md:hidden"
        aria-hidden="true"
        @click="closeMobile"
      />
    </Transition>

    <!-- Mobile menu panel -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="-translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="-translate-y-2 opacity-0"
    >
      <div
        v-show="mobileOpen"
        id="mobile-menu"
        class="absolute inset-x-0 top-full z-40 border-b border-hairline bg-surface px-4 pb-5 pt-3 shadow-card-hover md:hidden"
      >
        <ul class="space-y-1.5">
          <li v-for="link in navLinks" :key="link.href">
            <a
              :href="link.href"
              :aria-current="isActive(link.href) ? 'true' : undefined"
              class="group flex items-center justify-between rounded-xl px-4 py-3 text-base font-medium transition-colors"
              :class="
                isActive(link.href)
                  ? 'bg-navy-50 font-semibold text-navy dark:bg-white/10 dark:text-white'
                  : 'text-ink hover:bg-navy-50/70 hover:text-navy dark:text-slate-200 dark:hover:bg-white/5 dark:hover:text-white'
              "
              @click="closeMobile"
            >
              <span class="flex items-center gap-3">
                <span
                  class="h-1.5 w-1.5 rounded-full transition-colors"
                  :class="
                    isActive(link.href)
                      ? 'bg-action'
                      : 'bg-subtle/50 group-hover:bg-action'
                  "
                  aria-hidden="true"
                />
                {{ link.label }}
              </span>
              <AppIcon
                name="arrow-right"
                class="h-4 w-4 text-subtle transition-transform duration-150 group-hover:translate-x-0.5 group-hover:text-navy dark:group-hover:text-white"
              />
            </a>
          </li>
        </ul>

        <div class="mt-4 border-t border-hairline-soft pt-4">
          <BaseButton
            to="/login"
            variant="primary"
            size="lg"
            class="w-full"
            @click="closeMobile"
          >
            <AppIcon name="login" class="h-4 w-4" />
            Log In
          </BaseButton>
        </div>
      </div>
    </Transition>
  </header>
</template>
