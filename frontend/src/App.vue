<script setup lang="ts">
import { ref } from 'vue'
import DemoLoginPage from './features/auth/pages/DemoLoginPage.vue'
import type { DemoUser } from './features/demo/demoData'
import WorkspacePage from './features/demo/pages/WorkspacePage.vue'
import LandingPage from './features/landing/pages/LandingPage.vue'

const user = ref<DemoUser | null>(null)
const showLogin = ref(false)

function signIn(demoUser: DemoUser) {
  user.value = demoUser
  showLogin.value = false
}

function signOut() {
  user.value = null
  showLogin.value = false
}
</script>

<template>
  <WorkspacePage v-if="user" :user="user" @sign-out="signOut" @switch-user="user = $event" />
  <DemoLoginPage v-else-if="showLogin" @authenticated="signIn" @back="showLogin = false" />
  <LandingPage v-else @login="showLogin = true" />
</template>
