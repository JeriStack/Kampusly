<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import LandingNavbar from '../components/LandingNavbar.vue'
import HeroSection from '../components/HeroSection.vue'
import FeatureGrid from '../components/FeatureGrid.vue'
import HowItWorks from '../components/HowItWorks.vue'
import UserRolesSection from '../components/UserRolesSection.vue'
import FinalCta from '../components/FinalCta.vue'
import LandingFooter from '../components/LandingFooter.vue'

const MIN_VISIBLE_MS = 1400
const MAX_VISIBLE_MS = 2600
const EXIT_MS = 850
const isReady = ref(false)
const isLoaderVisible = ref(true)
const isLoaderLeaving = ref(false)
let minimumTimer: number | undefined
let maximumTimer: number | undefined
let exitTimer: number | undefined

function finishLoading(exitDuration: number): void {
  if (isReady.value) return
  if (minimumTimer !== undefined) window.clearTimeout(minimumTimer)
  if (maximumTimer !== undefined) window.clearTimeout(maximumTimer)

  isReady.value = true
  isLoaderLeaving.value = true
  exitTimer = window.setTimeout(() => {
    isLoaderVisible.value = false
  }, exitDuration)
}

function handlePageLoad(): void {
  if (minimumTimer !== undefined || isReady.value) return
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  minimumTimer = window.setTimeout(
    () => finishLoading(reducedMotion ? 0 : EXIT_MS),
    reducedMotion ? 200 : MIN_VISIBLE_MS,
  )
}

onMounted(() => {
  window.scrollTo(0, 0)
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  maximumTimer = window.setTimeout(() => {
    finishLoading(reducedMotion ? 0 : EXIT_MS)
  }, reducedMotion ? 200 : MAX_VISIBLE_MS)

  if (document.readyState === 'complete') {
    handlePageLoad()
  } else {
    window.addEventListener('load', handlePageLoad, { once: true })
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('load', handlePageLoad)
  if (minimumTimer !== undefined) window.clearTimeout(minimumTimer)
  if (maximumTimer !== undefined) window.clearTimeout(maximumTimer)
  if (exitTimer !== undefined) window.clearTimeout(exitTimer)
})
</script>

<template>
  <div class="min-h-screen bg-canvas" :inert="!isReady">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <LandingNavbar />

    <main id="main-content">
      <HeroSection :ready="isReady" />
      <FeatureGrid />
      <HowItWorks />
      <UserRolesSection />
      <FinalCta />
    </main>

    <LandingFooter />
  </div>

  <Transition name="intro-curtain">
    <div
      v-if="isLoaderVisible"
      class="intro-curtain"
      :class="{ 'intro-curtain--leaving': isLoaderLeaving }"
      aria-hidden="true"
    >
      <div class="intro-curtain__content">
        <div class="intro-curtain__brand">
          <KampuslyLogo size="sm" />
        </div>
        <div class="intro-curtain__track">
          <span class="intro-curtain__progress" />
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.intro-curtain {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: grid;
  place-items: center;
  color: #fff;
  background: #123b78;
  transition: transform 850ms cubic-bezier(0.65, 0, 0.35, 1);
}

.intro-curtain--leaving {
  transform: translateY(-105%);
}

.intro-curtain__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2rem;
}

.intro-curtain__brand {
  animation: intro-brand-in 600ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

.intro-curtain__track {
  width: 10rem;
  height: 1px;
  overflow: hidden;
  border-radius: 9999px;
  background: rgb(255 255 255 / 20%);
}

.intro-curtain__progress {
  display: block;
  width: 100%;
  height: 100%;
  transform: scaleX(0);
  transform-origin: left;
  background: #fff;
  animation: intro-progress 1280ms cubic-bezier(0.65, 0, 0.35, 1) 120ms forwards;
}

@keyframes intro-brand-in {
  from {
    transform: translateY(16px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

@keyframes intro-progress {
  to {
    transform: scaleX(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .intro-curtain {
    transition-duration: 0.01ms;
  }

  .intro-curtain__brand,
  .intro-curtain__progress {
    animation-duration: 80ms;
    animation-delay: 0ms;
  }
}
</style>
