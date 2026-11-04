<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import DemoLoginPage from '@/features/auth/pages/DemoLoginPage.vue'
import type { DemoUser } from '../demoData'
import WorkspacePage from './WorkspacePage.vue'

const router = useRouter()
const user = ref<DemoUser | null>(null)

function signIn(demoUser: DemoUser) {
  user.value = demoUser
}

function returnToLanding() {
  void router.push({ name: 'landing' })
}
</script>

<template>
  <WorkspacePage
    v-if="user"
    :user="user"
    @sign-out="returnToLanding"
    @switch-user="user = $event"
  />
  <DemoLoginPage
    v-else
    @authenticated="signIn"
    @back="returnToLanding"
  />
</template>
