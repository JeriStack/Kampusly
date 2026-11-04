<script setup lang="ts">
import LandingNavbar from '../components/LandingNavbar.vue'
import HeroSection from '../components/HeroSection.vue'
import FeatureGrid from '../components/FeatureGrid.vue'
import HowItWorks from '../components/HowItWorks.vue'
import UserRolesSection from '../components/UserRolesSection.vue'
import FAQSection from '../components/FAQSection.vue'
import LandingFooter from '../components/LandingFooter.vue'
</script>

<template>
  <a class="skip-link" href="#main-content">Skip to content</a>
  <LandingNavbar />
  <main id="main-content">
    <HeroSection />
    <FeatureGrid />
    <HowItWorks />
    <UserRolesSection />
    <FAQSection />
  </main>
  <LandingFooter />
</template>
