<script setup lang="ts">
import LandingNavbar from '../components/LandingNavbar.vue'
import HeroSection from '../components/HeroSection.vue'
import FeatureGrid from '../components/FeatureGrid.vue'
import HowItWorks from '../components/HowItWorks.vue'
import UserRolesSection from '../components/UserRolesSection.vue'
import FinalCta from '../components/FinalCta.vue'
import LandingFooter from '../components/LandingFooter.vue'
</script>

<template>
  <div class="min-h-screen bg-canvas">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <LandingNavbar />

    <main id="main-content">
      <HeroSection />
      <FeatureGrid />
      <HowItWorks />
      <UserRolesSection />
      <FinalCta />
    </main>

    <LandingFooter />
  </div>
</template>
