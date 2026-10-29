<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import { ApiError } from '@/services/api/errors'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import AppIcon from '@/components/common/AppIcon.vue'

const router = useRouter()
const route = useRoute()
const { login, getDashboardRoute } = useAuth()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

async function handleSubmit(): Promise<void> {
  errorMessage.value = null

  if (!email.value.trim()) {
    errorMessage.value = 'Please enter your institutional email.'
    return
  }

  if (!password.value) {
    errorMessage.value = 'Please enter your password.'
    return
  }

  isLoading.value = true

  try {
    const user = await login({
      email: email.value.trim(),
      password: password.value,
    })

    const redirectPath = route.query.redirect
    if (typeof redirectPath === 'string' && redirectPath.startsWith('/')) {
      await router.push(redirectPath)
    } else {
      await router.push(getDashboardRoute(user.role))
    }
  } catch (err) {
    if (err instanceof ApiError) {
      errorMessage.value = err.message
    } else if (err instanceof Error) {
      errorMessage.value = err.message
    } else {
      errorMessage.value = 'Unable to sign in. Please verify your connection.'
    }
  } finally {
    isLoading.value = false
  }
}

function fillCredentials(demoEmail: string): void {
  email.value = demoEmail
  password.value = 'ChangeMe123!'
  errorMessage.value = null
}
</script>

<template>
  <main class="relative flex min-h-screen flex-col items-center justify-center bg-canvas px-4 py-12 sm:px-6 lg:px-8">
    <div class="absolute right-4 top-4 sm:right-6 sm:top-6">
      <ThemeToggle />
    </div>

    <div class="w-full max-w-md space-y-8">
      <div class="text-center">
        <div class="inline-flex justify-center mb-3">
          <KampuslyLogo size="lg" />
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
          Sign in to Kampusly
        </h1>
        <p class="mt-2 text-sm text-muted">
          Your Campus. Your Classes. Your Time.
        </p>
      </div>

      <div class="rounded-2xl border border-hairline bg-surface p-6 shadow-card sm:p-8">
        <!-- Error Alert Banner -->
        <div
          v-if="errorMessage"
          class="mb-6 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50/80 p-4 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300"
          role="alert"
        >
          <AppIcon name="alert-circle" class="mt-0.5 h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400" />
          <div class="flex-1">
            <p class="font-medium">{{ errorMessage }}</p>
          </div>
          <button
            type="button"
            class="text-rose-600 hover:text-rose-800 dark:text-rose-400"
            aria-label="Dismiss error"
            @click="errorMessage = null"
          >
            <AppIcon name="close" class="h-4 w-4" />
          </button>
        </div>

        <form class="space-y-5" @submit.prevent="handleSubmit">
          <div>
            <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-muted">
              Institutional Email
            </label>
            <div class="mt-1.5 relative">
              <input
                id="email"
                v-model="email"
                type="email"
                autocomplete="username"
                required
                placeholder="user@kampusly.edu.ph"
                :disabled="isLoading"
                class="w-full rounded-lg border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-muted/60 transition-colors focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/20 dark:focus:border-primary-400 dark:focus:ring-primary-400/20"
              />
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between">
              <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-muted">
                Password
              </label>
            </div>
            <div class="mt-1.5 relative">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                required
                placeholder="••••••••••••"
                :disabled="isLoading"
                class="w-full rounded-lg border border-hairline bg-canvas px-3.5 py-2.5 pr-10 text-sm text-ink placeholder-muted/60 transition-colors focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/20 dark:focus:border-primary-400 dark:focus:ring-primary-400/20"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink focus:outline-none"
                tabindex="-1"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                @click="showPassword = !showPassword"
              >
                <AppIcon :name="showPassword ? 'eye-off' : 'eye'" class="h-4 w-4" />
              </button>
            </div>
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="flex w-full items-center justify-center gap-2 rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-navy-700 focus:outline-none focus:ring-2 focus:ring-navy/50 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-primary-500 dark:hover:bg-primary-600 dark:text-navy-950"
          >
            <AppIcon
              v-if="isLoading"
              name="loader"
              class="h-4 w-4 animate-spin text-current"
            />
            <span>{{ isLoading ? 'Signing in...' : 'Sign In' }}</span>
          </button>
        </form>

        <!-- Development Quick Demo Credential Helper -->
        <div class="mt-8 border-t border-hairline pt-6">
          <p class="text-xs font-semibold uppercase tracking-wider text-muted text-center mb-3">
            Quick Demo Sign-In (Local Environment)
          </p>
          <div class="grid grid-cols-3 gap-2">
            <button
              type="button"
              class="flex flex-col items-center rounded-lg border border-hairline bg-surface p-2 text-center transition-colors hover:border-navy hover:bg-navy-50/50 dark:hover:bg-navy-900/40"
              @click="fillCredentials('admin@kampusly.edu.ph')"
            >
              <span class="text-xs font-bold text-ink">Admin</span>
              <span class="text-[10px] text-muted truncate w-full">Administrator</span>
            </button>
            <button
              type="button"
              class="flex flex-col items-center rounded-lg border border-hairline bg-surface p-2 text-center transition-colors hover:border-navy hover:bg-navy-50/50 dark:hover:bg-navy-900/40"
              @click="fillCredentials('cruz@kampusly.edu.ph')"
            >
              <span class="text-xs font-bold text-ink">Instructor</span>
              <span class="text-[10px] text-muted truncate w-full">Prof. Cruz</span>
            </button>
            <button
              type="button"
              class="flex flex-col items-center rounded-lg border border-hairline bg-surface p-2 text-center transition-colors hover:border-navy hover:bg-navy-50/50 dark:hover:bg-navy-900/40"
              @click="fillCredentials('santos@kampusly.edu.ph')"
            >
              <span class="text-xs font-bold text-ink">Student</span>
              <span class="text-[10px] text-muted truncate w-full">Santos</span>
            </button>
          </div>
        </div>
      </div>

      <p class="text-center text-xs text-muted">
        <RouterLink to="/" class="hover:text-ink underline">
          &larr; Back to public homepage
        </RouterLink>
      </p>
    </div>
  </main>
</template>
