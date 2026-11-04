<script setup lang="ts">
import { ref } from 'vue'

const isMenuOpen = ref(false)

const links = [
  { label: 'Features', href: '#features' },
  { label: 'How it works', href: '#how-it-works' },
  { label: 'For your campus', href: '#campus' },
]
</script>

<template>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="#top" aria-label="Kampusly home">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 32 32" fill="none">
            <rect x="3" y="4" width="26" height="25" rx="8" fill="currentColor" />
            <path
              d="M10 12.5h12M10 17h8M10 21.5h5"
              stroke="white"
              stroke-linecap="round"
              stroke-width="2"
            />
            <circle cx="23" cy="21.5" r="2" fill="#5EEAD4" />
          </svg>
        </span>
        <span>Kampusly</span>
      </a>

      <nav class="desktop-nav" aria-label="Main navigation">
        <a v-for="link in links" :key="link.href" :href="link.href">{{ link.label }}</a>
      </nav>

      <a class="header-login" href="#campus-access">Log in <span aria-hidden="true">↗</span></a>

      <button
        class="menu-toggle"
        type="button"
        :aria-expanded="isMenuOpen"
        aria-controls="mobile-navigation"
        :aria-label="isMenuOpen ? 'Close navigation menu' : 'Open navigation menu'"
        @click="isMenuOpen = !isMenuOpen"
      >
        <svg v-if="!isMenuOpen" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
        <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
      </button>
    </div>

    <nav
      v-show="isMenuOpen"
      id="mobile-navigation"
      class="mobile-nav"
      aria-label="Mobile navigation"
    >
      <a
        v-for="link in links"
        :key="link.href"
        :href="link.href"
        @click="isMenuOpen = false"
      >
        {{ link.label }}
      </a>
      <a href="#campus-access" @click="isMenuOpen = false">Log in</a>
    </nav>
  </header>
</template>

<style scoped>
.site-header {
  position: relative;
  z-index: 2;
  background: rgb(248 250 252 / 92%);
  border-bottom: 1px solid rgb(226 232 240 / 75%);
}

.header-inner {
  display: flex;
  min-height: 76px;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
}

.brand {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.65rem;
  color: var(--color-brand);
  font-size: 1.2rem;
  font-weight: 750;
  letter-spacing: -0.035em;
}

.brand-mark,
.brand-mark svg {
  display: block;
  width: 2rem;
  height: 2rem;
}

.desktop-nav {
  display: flex;
  align-items: center;
  gap: clamp(1.25rem, 3vw, 2.5rem);
  margin-left: auto;
}

.desktop-nav a,
.header-login {
  color: #475569;
  font-size: 0.9rem;
  font-weight: 550;
  transition: color 160ms ease;
}

.desktop-nav a:hover,
.header-login:hover {
  color: var(--color-action);
}

.header-login {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  margin-left: 1rem;
  color: var(--color-brand);
  font-weight: 700;
}

.menu-toggle,
.mobile-nav {
  display: none;
}

@media (max-width: 700px) {
  .header-inner {
    min-height: 68px;
    gap: 1rem;
  }

  .desktop-nav,
  .header-login {
    display: none;
  }

  .menu-toggle {
    display: grid;
    width: 2.75rem;
    height: 2.75rem;
    place-items: center;
    border: 1px solid var(--color-border);
    border-radius: 0.8rem;
    color: var(--color-brand);
    background: var(--color-surface);
    cursor: pointer;
  }

  .menu-toggle svg {
    width: 1.35rem;
    height: 1.35rem;
  }

  .mobile-nav {
    display: grid;
    gap: 0.25rem;
    padding: 0.5rem max(1.25rem, calc((100% - 1200px) / 2)) 1rem;
    border-top: 1px solid var(--color-border);
    background: var(--color-surface);
  }

  .mobile-nav a {
    padding: 0.8rem 0.25rem;
    color: #334155;
    font-weight: 600;
  }
}
</style>
