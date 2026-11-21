<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import { ApiError } from '@/services/api/errors'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import AppIcon from '@/components/common/AppIcon.vue'

const router = useRouter()
const route = useRoute()
const { login, getDashboardRoute } = useAuth()

const formRef = ref<HTMLFormElement | null>(null)
const emailInputRef = ref<HTMLInputElement | null>(null)
const passwordInputRef = ref<HTMLInputElement | null>(null)

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const capsLockOn = ref(false)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const liveAnnouncement = ref('')

const hasPasswordEdgeSpaces = computed(
  () => password.value.length > 0 && /^\s|\s$/.test(password.value),
)

let announceTimer: ReturnType<typeof setTimeout> | undefined
const flaggedFields = new WeakSet<HTMLInputElement>()
const announcedFields = new WeakSet<HTMLInputElement>()

function linkErrorDescribedBy(el: HTMLInputElement, shown: boolean): void {
  const errorId = el.dataset.error
  if (!errorId) return
  const ids = (el.getAttribute('aria-describedby') || '')
    .split(/\s+/)
    .filter((id) => id && id !== errorId)
  if (shown) ids.push(errorId)
  if (ids.length > 0) {
    el.setAttribute('aria-describedby', ids.join(' '))
  } else {
    el.removeAttribute('aria-describedby')
  }
}

function announceFieldError(el: HTMLInputElement): void {
  const errorId = el.dataset.error
  if (!errorId) return
  const errorNode = document.getElementById(errorId)
  if (!errorNode) return
  const labelText = el.labels?.[0]?.textContent?.trim()
  const messageText = errorNode.textContent?.replace(/\s+/g, ' ').trim() ?? ''
  if (!messageText) return
  clearTimeout(announceTimer)
  liveAnnouncement.value = ''
  announceTimer = setTimeout(() => {
    liveAnnouncement.value = labelText ? `${labelText}: ${messageText}` : messageText
  }, 450)
}

function syncFieldAria(el: HTMLInputElement | null, announce = false): void {
  if (!el) return
  const supportsUserInvalid = typeof CSS !== 'undefined' && CSS.supports('selector(:user-invalid)')
  const isInvalid = supportsUserInvalid
    ? el.matches(':user-invalid')
    : el.classList.contains('user-invalid-fallback')

  if (isInvalid) {
    if (announce && !announcedFields.has(el)) {
      announceFieldError(el)
      announcedFields.add(el)
    }
    el.setAttribute('aria-invalid', 'true')
    linkErrorDescribedBy(el, true)
    flaggedFields.add(el)
  } else if (flaggedFields.has(el) || el.getAttribute('aria-invalid') !== 'true') {
    el.setAttribute('aria-invalid', 'false')
    linkErrorDescribedBy(el, false)
    flaggedFields.delete(el)
    announcedFields.delete(el)
  }
}

function handleFieldBlur(event: FocusEvent): void {
  const target = event.target as HTMLInputElement | null
  if (!target) return
  if (typeof CSS !== 'undefined' && !CSS.supports('selector(:user-invalid)')) {
    target.classList.toggle('user-invalid-fallback', !target.validity.valid)
  }
  syncFieldAria(target, true)
}

function handleFieldInput(event: Event): void {
  const target = event.target as HTMLInputElement | null
  if (!target) return
  if (errorMessage.value) {
    errorMessage.value = null
  }
  if (typeof CSS !== 'undefined' && !CSS.supports('selector(:user-invalid)')) {
    if (target.classList.contains('user-invalid-fallback') && target.validity.valid) {
      target.classList.remove('user-invalid-fallback')
    }
  }
  syncFieldAria(target, false)
}

function handlePasswordKeyEvent(event: KeyboardEvent): void {
  if (typeof event.getModifierState === 'function') {
    capsLockOn.value = event.getModifierState('CapsLock')
  }
}

function trimPasswordSpaces(): void {
  password.value = password.value.trim()
  passwordInputRef.value?.focus()
}

function togglePasswordVisibility(): void {
  showPassword.value = !showPassword.value
  passwordInputRef.value?.focus()
}

async function handleSubmit(): Promise<void> {
  errorMessage.value = null

  const form = formRef.value
  if (form && !form.checkValidity()) {
    const inputs = [emailInputRef.value, passwordInputRef.value].filter(
      (el): el is HTMLInputElement => el !== null,
    )
    for (const input of inputs) {
      if (!input.validity.valid) {
        if (typeof CSS !== 'undefined' && !CSS.supports('selector(:user-invalid)')) {
          input.classList.add('user-invalid-fallback')
        }
        announcedFields.add(input)
        syncFieldAria(input, false)
      }
    }
    const firstInvalid = inputs.find((input) => !input.validity.valid)
    firstInvalid?.focus()
    return
  }

  const trimmedEmail = email.value.trim()
  if (!trimmedEmail) {
    errorMessage.value = 'Please enter your institutional email.'
    emailInputRef.value?.focus()
    return
  }

  if (!password.value) {
    errorMessage.value = 'Please enter your password.'
    passwordInputRef.value?.focus()
    return
  }

  isLoading.value = true

  try {
    const user = await login({
      email: trimmedEmail,
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

onMounted(() => {
  syncFieldAria(emailInputRef.value, false)
  syncFieldAria(passwordInputRef.value, false)
})

onUnmounted(() => {
  clearTimeout(announceTimer)
})
</script>

<template>
  <main class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-canvas px-4 py-10 sm:px-6">
    <!-- Subtle ambient glow -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
      <div
        class="absolute left-1/2 top-1/2 h-[30rem] w-[30rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-action/10 blur-3xl dark:bg-action/15"
      />
      <div
        class="absolute left-1/2 top-1/3 h-64 w-64 -translate-x-1/4 rounded-full bg-accent/10 blur-3xl dark:bg-accent/15"
      />
    </div>

    <div class="absolute right-4 top-4 z-10 sm:right-6 sm:top-6">
      <ThemeToggle />
    </div>

    <div class="relative z-10 w-full max-w-[420px] space-y-7">
      <!-- Brand Header -->
      <div class="text-center">
        <div class="mb-3 inline-flex justify-center">
          <KampuslyLogo size="lg" />
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
          Sign in to Kampusly
        </h1>
        <p class="mt-1.5 text-sm text-muted">
          Your Campus. Your Classes. Your Time.
        </p>
      </div>

      <!-- Elevated Modern Sign-In Card -->
      <div class="overflow-hidden rounded-2xl border border-hairline bg-surface/95 shadow-card-hover backdrop-blur-md">
        <div class="h-1 w-full bg-gradient-to-r from-navy via-action to-accent" />

        <div class="p-6 sm:p-8">
          <!-- Error Alert Banner -->
          <div
            v-if="errorMessage"
            class="mb-5 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50/90 p-3.5 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/50 dark:text-rose-200"
            role="alert"
          >
            <AppIcon
              name="alert-circle"
              class="mt-0.5 h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400"
            />
            <p class="flex-1 font-medium leading-snug">{{ errorMessage }}</p>
            <button
              type="button"
              class="rounded-md p-0.5 text-rose-600 transition-colors hover:bg-rose-100 hover:text-rose-800 dark:text-rose-300 dark:hover:bg-rose-900/60"
              aria-label="Dismiss error"
              @click="errorMessage = null"
            >
              <AppIcon name="close" class="h-4 w-4" />
            </button>
          </div>

          <form
            ref="formRef"
            class="space-y-5"
            novalidate
            :aria-busy="isLoading ? 'true' : 'false'"
            @submit.prevent="handleSubmit"
          >
            <!-- Institutional Email -->
            <div class="login-field">
              <label
                for="email"
                class="block text-xs font-semibold uppercase tracking-wider text-muted"
              >
                Institutional Email
              </label>
              <div class="relative mt-2">
                <input
                  id="email"
                  ref="emailInputRef"
                  v-model="email"
                  name="email"
                  type="email"
                  inputmode="email"
                  autocomplete="username"
                  spellcheck="false"
                  enterkeyhint="next"
                  required
                  placeholder="user@kampusly.edu.ph"
                  data-error="email-error"
                  :disabled="isLoading"
                  class="login-input w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/70 transition-all focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25 disabled:cursor-not-allowed disabled:opacity-60"
                  @blur="handleFieldBlur"
                  @input="handleFieldInput"
                />
              </div>
              <p
                id="email-error"
                class="login-error-msg mt-1.5 items-center gap-1.5 text-xs font-medium text-rose-600 dark:text-rose-400"
              >
                <AppIcon name="alert-circle" label="Error" class="h-3.5 w-3.5 shrink-0" />
                <span>Enter a valid email address in the format user@kampusly.edu.ph.</span>
              </p>
            </div>

            <!-- Password -->
            <div class="login-field">
              <label
                for="password"
                class="block text-xs font-semibold uppercase tracking-wider text-muted"
              >
                Password
              </label>
              <div class="relative mt-2">
                <input
                  id="password"
                  ref="passwordInputRef"
                  v-model="password"
                  name="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  enterkeyhint="done"
                  required
                  placeholder="••••••••••••"
                  data-error="password-error"
                  :disabled="isLoading"
                  class="login-input w-full rounded-xl border border-hairline bg-canvas py-2.5 pl-3.5 pr-11 text-sm text-ink placeholder-subtle/70 transition-all focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25 disabled:cursor-not-allowed disabled:opacity-60"
                  @blur="handleFieldBlur"
                  @input="handleFieldInput"
                  @keydown="handlePasswordKeyEvent"
                  @keyup="handlePasswordKeyEvent"
                />
                <button
                  type="button"
                  class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-subtle transition-colors hover:bg-surface hover:text-ink"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  :aria-pressed="showPassword"
                  :disabled="isLoading"
                  @click="togglePasswordVisibility"
                >
                  <AppIcon :name="showPassword ? 'eye-off' : 'eye'" class="h-4 w-4" />
                </button>
              </div>
              <p
                id="password-error"
                class="login-error-msg mt-1.5 items-center gap-1.5 text-xs font-medium text-rose-600 dark:text-rose-400"
              >
                <AppIcon name="alert-circle" label="Error" class="h-3.5 w-3.5 shrink-0" />
                <span>Please enter your password.</span>
              </p>

              <div
                v-if="capsLockOn"
                class="mt-2 flex items-center gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-300"
              >
                <AppIcon name="alert-triangle" class="h-3.5 w-3.5 shrink-0" />
                <span>Caps Lock is on.</span>
              </div>
              <div
                v-if="hasPasswordEdgeSpaces"
                class="mt-2 flex items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50/80 px-2.5 py-1.5 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-200"
              >
                <span class="flex items-center gap-1.5">
                  <AppIcon name="info" class="h-3.5 w-3.5 shrink-0" />
                  Password has a leading or trailing space.
                </span>
                <button
                  type="button"
                  class="shrink-0 font-semibold underline hover:no-underline"
                  @click="trimPasswordSpaces"
                >
                  Trim
                </button>
              </div>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              :disabled="isLoading"
              class="flex w-full items-center justify-center gap-2 rounded-xl bg-action px-4 py-3 text-sm font-semibold text-white shadow-sm transition-all hover:bg-action-hover active:bg-action-active focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-60"
            >
              <AppIcon
                v-if="isLoading"
                name="loader"
                class="h-4 w-4 animate-spin text-current"
              />
              <span>{{ isLoading ? 'Signing in...' : 'Sign In' }}</span>
            </button>
          </form>
        </div>
      </div>

      <!-- Back Link -->
      <p class="text-center text-xs text-muted">
        <RouterLink
          to="/"
          class="inline-flex items-center gap-1 font-medium transition-colors hover:text-ink hover:underline"
        >
          &larr; Back to public homepage
        </RouterLink>
      </p>
    </div>

    <div aria-live="polite" class="sr-only">
      {{ liveAnnouncement }}
    </div>
  </main>
</template>

<style scoped>
.login-error-msg {
  display: none;
}

.login-field:has(.login-input:is(:user-invalid, .user-invalid-fallback)) .login-error-msg {
  display: flex;
}

.login-input:is(:user-invalid, .user-invalid-fallback) {
  border-color: #e11d48;
}

.login-input:is(:user-valid) {
  border-color: #0d9488;
}

.login-input:is(:autofill, :-webkit-autofill) {
  border-color: var(--color-action);
  box-shadow:
    inset 0 0 0 2px rgba(37, 99, 235, 0.35),
    inset 0 0 0 100vmax rgb(var(--canvas));
  -webkit-text-fill-color: rgb(var(--ink));
  caret-color: rgb(var(--ink));
}
</style>
