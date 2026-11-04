<script setup lang="ts">
import { ref } from 'vue'
import { demoUsers, type DemoUser } from '../../demo/demoData'

const emit = defineEmits<{
  authenticated: [user: DemoUser]
  back: []
}>()

const email = ref('')
const password = ref('')
const errorMessage = ref('')

function chooseDemo(user: DemoUser) {
  email.value = user.email
  password.value = 'demo'
  errorMessage.value = ''
}

function signIn() {
  const user = demoUsers.find((item) => item.email.toLowerCase() === email.value.trim().toLowerCase())

  if (!user || !password.value.trim()) {
    errorMessage.value = 'Choose a sample account and enter the demo password to continue.'
    return
  }

  emit('authenticated', user)
}
</script>

<template>
  <main class="login-page">
    <section class="login-story" aria-labelledby="login-story-title">
      <a class="login-brand" href="#home" aria-label="Back to Kampusly home" @click.prevent="emit('back')">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 32 32" fill="none">
            <rect x="3" y="4" width="26" height="25" rx="8" fill="currentColor" />
            <path d="M10 12.5h12M10 17h8M10 21.5h5" stroke="white" stroke-linecap="round" stroke-width="2" />
            <circle cx="23" cy="21.5" r="2" fill="#5EEAD4" />
          </svg>
        </span>
        <span>Kampusly</span>
      </a>

      <div class="story-copy">
        <p class="story-eyebrow"><span aria-hidden="true">✦</span> YOUR CAMPUS, IN SYNC</p>
        <h1 id="login-story-title">Your Campus.<br />Your Classes.<br /><span>Your Time.</span></h1>
        <p>One calm place to find your schedule, catch important updates, and stay a step ahead.</p>
      </div>

      <div class="story-bottom">
        <div class="story-avatars" aria-hidden="true">
          <span>AM</span><span>JR</span><span>CB</span>
        </div>
        <p>For students, instructors, and campus teams</p>
      </div>
    </section>

    <section class="login-panel" aria-labelledby="login-title">
      <div class="login-card">
        <button class="back-link" type="button" @click="emit('back')">← Back to Kampusly</button>
        <div class="demo-banner">
          <span class="demo-banner-dot" aria-hidden="true"></span>
          Interactive prototype · Demo sign-in only
        </div>

        <header class="login-heading">
          <p class="eyebrow">WELCOME BACK</p>
          <h2 id="login-title">Sign in to Kampusly</h2>
          <p>Choose a sample profile to explore each role’s experience.</p>
        </header>

        <div class="sample-users" aria-label="Sample demo accounts">
          <button
            v-for="user in demoUsers"
            :key="user.role"
            class="sample-user"
            :class="{ 'sample-user-selected': email === user.email }"
            type="button"
            @click="chooseDemo(user)"
          >
            <span class="sample-avatar" :class="`avatar-${user.role}`">{{ user.initials }}</span>
            <span class="sample-user-copy">
              <strong>{{ user.title }}</strong>
              <span>{{ user.name }}</span>
            </span>
            <span class="sample-arrow" aria-hidden="true">↗</span>
          </button>
        </div>

        <div class="form-divider"><span>or sign in with a demo account</span></div>

        <form class="login-form" @submit.prevent="signIn">
          <label for="email">Campus email</label>
          <input
            id="email"
            v-model="email"
            autocomplete="username"
            type="email"
            placeholder="you@campus.edu"
            required
          />

          <div class="password-label">
            <label for="password">Password</label>
            <span>Demo only</span>
          </div>
          <input
            id="password"
            v-model="password"
            autocomplete="current-password"
            type="password"
            placeholder="Enter any demo password"
            required
            @input="errorMessage = ''"
          />

          <p v-if="errorMessage" class="form-error" role="alert">{{ errorMessage }}</p>
          <button class="sign-in-button" type="submit">
            Continue to demo
            <span aria-hidden="true">→</span>
          </button>
        </form>

        <p class="privacy-note">
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <rect x="4.25" y="8.5" width="11.5" height="8" rx="2" stroke="currentColor" stroke-width="1.5" />
            <path d="M6.75 8.5V6a3.25 3.25 0 0 1 6.5 0v2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
          </svg>
          Demo mode does not verify credentials or connect to a server. Never enter a real password.
        </p>
      </div>
      <p class="login-footer">© {{ new Date().getFullYear() }} Kampusly · Your Campus. Your Classes. Your Time.</p>
    </section>
  </main>
</template>

<style scoped>
.login-page {
  display: grid;
  min-height: 100vh;
  grid-template-columns: minmax(0, 0.88fr) minmax(500px, 1.12fr);
  background: #ffffff;
}

.login-story {
  position: relative;
  display: flex;
  min-height: 100vh;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
  padding: clamp(2rem, 5vw, 4rem);
  color: #ffffff;
  background:
    radial-gradient(ellipse at 82% 78%, rgb(37 99 235 / 38%), transparent 45%),
    radial-gradient(ellipse at 15% 15%, rgb(13 148 136 / 20%), transparent 36%),
    #102f5d;
}

.login-story::after {
  position: absolute;
  right: -9rem;
  bottom: -11rem;
  width: 35rem;
  height: 35rem;
  border: 1px solid rgb(255 255 255 / 9%);
  border-radius: 50%;
  box-shadow:
    0 0 0 3rem rgb(255 255 255 / 3%),
    0 0 0 7rem rgb(255 255 255 / 2%);
  content: "";
  pointer-events: none;
}

.login-brand {
  position: relative;
  z-index: 1;
  display: inline-flex;
  width: fit-content;
  align-items: center;
  gap: 0.7rem;
  color: #ffffff;
  font-size: 1.25rem;
  font-weight: 750;
  letter-spacing: -0.035em;
}

.brand-mark,
.brand-mark svg {
  display: block;
  width: 2.1rem;
  height: 2.1rem;
}

.story-copy {
  position: relative;
  z-index: 1;
  max-width: 520px;
  margin-block: 4rem;
}

.story-eyebrow {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin: 0 0 1.3rem;
  color: #a7f3d0;
  font-size: 0.72rem;
  font-weight: 750;
  letter-spacing: 0.12em;
}

.story-eyebrow span {
  font-size: 1rem;
}

.story-copy h1 {
  margin: 0;
  font-size: clamp(3rem, 5.2vw, 5rem);
  font-weight: 760;
  letter-spacing: -0.075em;
  line-height: 1;
}

.story-copy h1 span {
  color: #93c5fd;
}

.story-copy > p:last-child {
  max-width: 410px;
  margin: 1.5rem 0 0;
  color: #dbeafe;
  font-size: 1rem;
  line-height: 1.75;
}

.story-bottom {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.story-avatars {
  display: flex;
  padding-left: 0.4rem;
}

.story-avatars span {
  display: grid;
  width: 2.2rem;
  height: 2.2rem;
  place-items: center;
  margin-left: -0.4rem;
  border: 2px solid #102f5d;
  border-radius: 50%;
  color: #123b78;
  background: #dbeafe;
  font-size: 0.62rem;
  font-weight: 750;
}

.story-avatars span:nth-child(2) {
  background: #ccfbf1;
}

.story-avatars span:nth-child(3) {
  background: #ede9fe;
}

.story-bottom p {
  margin: 0;
  color: #dbeafe;
  font-size: 0.8rem;
}

.login-panel {
  display: flex;
  min-height: 100vh;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2.5rem;
  background: #f8fafc;
}

.login-card {
  width: min(100%, 440px);
  padding: clamp(1.5rem, 4vw, 2.4rem);
  border: 1px solid #e2e8f0;
  border-radius: 1.35rem;
  background: #ffffff;
  box-shadow: 0 18px 55px rgb(15 23 42 / 7%);
}

.back-link {
  margin: 0 0 0.9rem;
  padding: 0;
  border: 0;
  color: #64748b;
  background: transparent;
  font-size: 0.7rem;
  cursor: pointer;
}

.back-link:hover {
  color: #1d4ed8;
}

.demo-banner {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.45rem 0.7rem;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  color: #1d4ed8;
  background: #eff6ff;
  font-size: 0.68rem;
  font-weight: 700;
}

.demo-banner-dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 50%;
  background: #2563eb;
}

.login-heading {
  margin: 1.5rem 0 1.2rem;
}

.eyebrow {
  margin: 0 0 0.55rem;
  color: #0f766e;
  font-size: 0.67rem;
  font-weight: 750;
  letter-spacing: 0.11em;
}

.login-heading h2 {
  margin: 0;
  color: #123b78;
  font-size: 1.65rem;
  letter-spacing: -0.05em;
}

.login-heading > p:last-child {
  margin: 0.5rem 0 0;
  color: #64748b;
  font-size: 0.86rem;
  line-height: 1.5;
}

.sample-users {
  display: grid;
  gap: 0.55rem;
}

.sample-user {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.8rem;
  text-align: left;
  background: #ffffff;
  cursor: pointer;
  transition:
    border-color 150ms ease,
    background 150ms ease;
}

.sample-user:hover,
.sample-user-selected {
  border-color: #93c5fd;
  background: #f8fbff;
}

.sample-avatar {
  display: grid;
  width: 2.4rem;
  height: 2.4rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 0.75rem;
  font-size: 0.7rem;
  font-weight: 750;
}

.avatar-student {
  color: #1d4ed8;
  background: #eff6ff;
}

.avatar-instructor {
  color: #0f766e;
  background: #f0fdfa;
}

.avatar-administrator {
  color: #6d28d9;
  background: #f5f3ff;
}

.sample-user-copy {
  display: grid;
  min-width: 0;
  gap: 0.15rem;
}

.sample-user-copy strong {
  color: #1e293b;
  font-size: 0.81rem;
}

.sample-user-copy > span {
  overflow: hidden;
  color: #64748b;
  font-size: 0.72rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sample-arrow {
  margin-left: auto;
  color: #94a3b8;
}

.form-divider {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 1.2rem 0;
  color: #94a3b8;
  font-size: 0.67rem;
}

.form-divider::before,
.form-divider::after {
  height: 1px;
  flex: 1;
  background: #e2e8f0;
  content: "";
}

.login-form {
  display: grid;
  gap: 0.55rem;
}

.login-form label,
.password-label {
  color: #334155;
  font-size: 0.76rem;
  font-weight: 650;
}

.login-form input {
  width: 100%;
  min-height: 2.9rem;
  margin-bottom: 0.5rem;
  padding: 0.7rem 0.85rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.7rem;
  color: #0f172a;
  background: #ffffff;
  font-size: 0.82rem;
}

.login-form input::placeholder {
  color: #94a3b8;
}

.login-form input:focus {
  border-color: #2563eb;
  outline: 3px solid rgb(37 99 235 / 14%);
}

.password-label {
  display: flex;
  justify-content: space-between;
}

.password-label span {
  color: #94a3b8;
  font-size: 0.67rem;
  font-weight: 500;
}

.form-error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.76rem;
  line-height: 1.5;
}

.sign-in-button {
  display: flex;
  min-height: 3rem;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  margin-top: 0.25rem;
  border: 0;
  border-radius: 0.75rem;
  color: #ffffff;
  background: #2563eb;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 150ms ease;
}

.sign-in-button:hover {
  background: #1d4ed8;
}

.privacy-note {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin: 1rem 0 0;
  color: #64748b;
  font-size: 0.67rem;
  line-height: 1.55;
}

.privacy-note svg {
  width: 1rem;
  height: 1rem;
  flex: 0 0 auto;
  color: #0f766e;
}

.login-footer {
  margin: 1.2rem 0 0;
  color: #94a3b8;
  font-size: 0.68rem;
  text-align: center;
}

@media (max-width: 900px) {
  .login-page {
    grid-template-columns: minmax(0, 0.8fr) minmax(430px, 1.2fr);
  }

  .login-story {
    padding: 2rem;
  }
}

@media (max-width: 700px) {
  .login-page {
    grid-template-columns: 1fr;
  }

  .login-story {
    min-height: auto;
    padding: 1.4rem 1.25rem 1.7rem;
  }

  .login-story::after {
    right: -20rem;
    bottom: -24rem;
  }

  .login-brand .brand-mark,
  .login-brand .brand-mark svg {
    width: 1.8rem;
    height: 1.8rem;
  }

  .login-brand {
    font-size: 1.1rem;
  }

  .story-copy {
    margin: 2.4rem 0 1.7rem;
  }

  .story-copy h1 {
    font-size: clamp(2.6rem, 12vw, 4rem);
  }

  .story-copy > p:last-child {
    max-width: 410px;
    margin-top: 1rem;
    font-size: 0.88rem;
  }

  .story-bottom {
    display: none;
  }

  .login-panel {
    min-height: auto;
    padding: 1.5rem 1rem 2rem;
  }

  .login-footer {
    margin-top: 1rem;
  }
}
</style>
