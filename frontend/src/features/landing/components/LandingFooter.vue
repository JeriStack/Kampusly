<script setup lang="ts">
const year = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer">
    <div class="container footer-main">
      <a class="footer-brand" href="#top" aria-label="Kampusly, back to top">
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
      <p>Your Campus. Your Classes. Your Time.</p>
      <nav aria-label="Footer navigation">
        <a href="#features">Features</a>
        <a href="#how-it-works">How it works</a>
        <a href="#campus-access">Access &amp; help</a>
      </nav>
    </div>
    <div class="container footer-bottom">
      <p>© {{ year }} Kampusly. Built for better campus days.</p>
      <p>Personal schedules stay private. Preview data is illustrative only.</p>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  color: #cbd5e1;
  background: #102f5d;
}

.footer-main {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 1.5rem;
  align-items: center;
  padding-block: 2rem;
}

.footer-brand {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  color: #ffffff;
  font-size: 1.1rem;
  font-weight: 750;
  letter-spacing: -0.035em;
}

.brand-mark,
.brand-mark svg {
  display: block;
  width: 1.8rem;
  height: 1.8rem;
}

.footer-main > p {
  margin: 0;
  color: #cbd5e1;
  font-size: 0.83rem;
}

.footer-main nav {
  display: flex;
  gap: 1.4rem;
}

.footer-main nav a {
  color: #e2e8f0;
  font-size: 0.8rem;
}

.footer-main nav a:hover {
  color: #ffffff;
  text-decoration: underline;
  text-underline-offset: 0.2em;
}

.footer-bottom {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding-block: 1rem 1.35rem;
  border-top: 1px solid rgb(203 213 225 / 20%);
}

.footer-bottom p {
  margin: 0;
  color: #cbd5e1;
  font-size: 0.72rem;
}

@media (max-width: 800px) {
  .footer-main {
    grid-template-columns: 1fr auto;
  }

  .footer-main > p {
    grid-column: 1 / -1;
    grid-row: 2;
  }
}

@media (max-width: 540px) {
  .footer-main {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .footer-main > p {
    grid-column: auto;
    grid-row: auto;
  }

  .footer-main nav {
    flex-wrap: wrap;
    gap: 0.7rem 1.2rem;
  }

  .footer-bottom {
    flex-direction: column;
  }
}
</style>
