<script setup lang="ts">
const features = [
  {
    title: 'Class schedules',
    description: 'See your week clearly, with the time, room, and instructor right where you need them.',
    icon: 'M6.5 3.75v2.5M13.5 3.75v2.5M4.25 8h11.5M5 5h10a1.75 1.75 0 0 1 1.75 1.75v8.5A1.75 1.75 0 0 1 15 17H5a1.75 1.75 0 0 1-1.75-1.75v-8.5A1.75 1.75 0 0 1 5 5Zm2 6h2m2 0h2m-6 3h2',
    color: 'blue',
  },
  {
    title: 'Smart notifications',
    description: 'Know when a room changes or a class is cancelled, with useful details and timestamps.',
    icon: 'M15 7.5a5 5 0 0 0-10 0c0 5-2 5.5-2 7h14c0-1.5-2-2-2-7Zm-6.5 9.5a1.5 1.5 0 0 0 3 0',
    color: 'teal',
  },
  {
    title: 'Class announcements',
    description: 'Keep course updates and instructor notices together, so important information is easy to find.',
    icon: 'M4 8.5h3l6-3v11l-6-3H4a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1Zm3 5 1.5 4h2l-1.5-4m6-4a3 3 0 0 1 0 6m2-8a6 6 0 0 1 0 10',
    color: 'violet',
  },
  {
    title: 'PDF schedule export',
    description: 'Take a printable copy of your timetable with you, whenever and wherever you need it.',
    icon: 'M5 3.5h6l4 4V16a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 5 16V5a1.5 1.5 0 0 1 1.5-1.5Zm6 0V8h4m-8 4h5m-5 3h5',
    color: 'amber',
  },
]
</script>

<template>
  <section id="features" class="section features-section">
    <div class="container">
      <div class="section-intro">
        <p class="eyebrow">Everything in sync</p>
        <h2>Your campus day,<br /><span>without the guesswork.</span></h2>
        <p class="section-description">
          The details you need to get where you’re going—and the updates that help you get there.
        </p>
      </div>

      <div class="feature-grid">
        <article v-for="feature in features" :key="feature.title" class="feature-card">
          <span class="feature-icon" :class="`icon-${feature.color}`" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="none">
              <path :d="feature.icon" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
          <h3>{{ feature.title }}</h3>
          <p>{{ feature.description }}</p>
        </article>
      </div>
    </div>
  </section>
</template>

<style scoped>
.features-section {
  background: #ffffff;
}

.section-intro {
  max-width: 650px;
  margin-bottom: 2.75rem;
}

.section-intro .eyebrow {
  margin-bottom: 0.85rem;
}

h2 {
  margin: 0;
  color: var(--color-brand);
  font-size: clamp(2.15rem, 4.6vw, 3.45rem);
  letter-spacing: -0.06em;
  line-height: 1.08;
}

h2 span {
  color: #2563eb;
}

.section-description {
  max-width: 540px;
  margin: 1rem 0 0;
  color: #64748b;
  font-size: 1rem;
  line-height: 1.7;
}

.feature-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
}

.feature-card {
  min-height: 220px;
  padding: 1.45rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  background: #ffffff;
  transition:
    border-color 180ms ease,
    box-shadow 180ms ease,
    transform 180ms ease;
}

.feature-card:hover {
  border-color: #cbd5e1;
  box-shadow: 0 12px 30px rgb(15 23 42 / 6%);
  transform: translateY(-3px);
}

.feature-icon,
.feature-icon svg {
  display: block;
  width: 2.75rem;
  height: 2.75rem;
}

.feature-icon {
  display: grid;
  place-items: center;
  border-radius: 0.9rem;
}

.feature-icon svg {
  width: 1.35rem;
  height: 1.35rem;
}

.icon-blue {
  color: #1d4ed8;
  background: #eff6ff;
}

.icon-teal {
  color: #0f766e;
  background: #f0fdfa;
}

.icon-violet {
  color: #6d28d9;
  background: #f5f3ff;
}

.icon-amber {
  color: #92400e;
  background: #fffbeb;
}

.feature-card h3 {
  margin: 1.2rem 0 0.5rem;
  color: #123b78;
  font-size: 1.02rem;
  letter-spacing: -0.02em;
}

.feature-card p {
  margin: 0;
  color: #64748b;
  font-size: 0.87rem;
  line-height: 1.65;
}

@media (max-width: 900px) {
  .feature-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 540px) {
  .feature-grid {
    grid-template-columns: 1fr;
  }

  .feature-card {
    min-height: auto;
  }
}
</style>
