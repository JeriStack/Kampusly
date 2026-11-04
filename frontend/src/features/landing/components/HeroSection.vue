<script setup lang="ts">
import SchedulePreview from './SchedulePreview.vue'
</script>

<template>
  <section id="top" class="hero-section">
    <div class="container hero-grid">
      <div class="hero-copy">
        <p class="eyebrow"><span aria-hidden="true">✦</span> A calmer way to campus</p>
        <h1>Your Campus.<br />Your Classes.<br /><span>Your Time.</span></h1>
        <p class="hero-description">
          Stay on top of your class schedule, keep up with announcements, and get notified when
          your classes change—all in one place.
        </p>
        <div class="hero-actions">
          <a class="button button-primary" href="#campus-access">
            Access My Schedule
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
              <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </a>
          <a class="button button-secondary" href="#features">Explore Features</a>
        </div>
        <p class="hero-trust">
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="m5 10 3.2 3.2L15.5 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Your personal schedule stays private
        </p>
      </div>
      <SchedulePreview />
    </div>
    <div class="hero-bottom container" aria-hidden="true">
      <span></span><span></span><span></span><span></span><span></span>
    </div>
  </section>
</template>

<style scoped>
.hero-section {
  position: relative;
  overflow: hidden;
  padding: clamp(4.5rem, 9vw, 7.5rem) 0 clamp(4.5rem, 8vw, 7rem);
  background:
    radial-gradient(ellipse at 82% 45%, rgb(219 234 254 / 42%), transparent 42%),
    linear-gradient(180deg, #f8fafc 0%, #f5f9ff 100%);
}

.hero-grid {
  display: grid;
  grid-template-columns: minmax(0, 0.94fr) minmax(370px, 1fr);
  align-items: center;
  gap: clamp(3rem, 7vw, 7rem);
}

.hero-copy {
  position: relative;
  z-index: 1;
}

.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  margin: 0 0 1.25rem;
  color: #0f766e;
  font-size: 0.76rem;
  font-weight: 750;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.eyebrow span {
  color: #0d9488;
  font-size: 1rem;
}

h1 {
  margin: 0;
  color: var(--color-brand);
  font-size: clamp(3.2rem, 6.8vw, 5.65rem);
  font-weight: 760;
  letter-spacing: -0.075em;
  line-height: 0.99;
}

h1 span {
  color: var(--color-action);
}

.hero-description {
  max-width: 530px;
  margin: 1.5rem 0 0;
  color: #475569;
  font-size: clamp(1rem, 1.45vw, 1.12rem);
  line-height: 1.75;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 2rem;
}

.hero-trust {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 1.25rem 0 0;
  color: #64748b;
  font-size: 0.78rem;
}

.hero-trust svg {
  width: 1.1rem;
  height: 1.1rem;
  color: #0f766e;
}

.hero-bottom {
  position: absolute;
  right: 0;
  bottom: 1.5rem;
  left: 0;
  display: flex;
  justify-content: center;
  gap: 0.35rem;
  opacity: 0.45;
}

.hero-bottom span {
  width: 0.28rem;
  height: 0.28rem;
  border-radius: 50%;
  background: #93c5fd;
}

@media (max-width: 900px) {
  .hero-grid {
    grid-template-columns: 1fr;
    gap: 3.5rem;
  }

  .hero-copy {
    max-width: 650px;
  }

  .preview-wrap {
    max-width: 580px;
  }
}

@media (max-width: 520px) {
  .hero-section {
    padding-top: 3.5rem;
  }

  h1 {
    font-size: clamp(3rem, 14vw, 4.25rem);
  }

  .hero-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .hero-actions .button {
    width: 100%;
  }
}
</style>
