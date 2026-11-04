<script setup lang="ts">
const steps = [
  {
    number: '01',
    title: 'Access Kampusly',
    description: 'Sign in with the account provided by your campus.',
  },
  {
    number: '02',
    title: 'Check your schedule',
    description: 'See your classes, rooms, and instructors in one clear view.',
  },
  {
    number: '03',
    title: 'Stay in the loop',
    description: 'Get the latest announcements and class-change updates.',
  },
]
</script>

<template>
  <section id="how-it-works" class="section steps-section">
    <div class="container steps-layout">
      <div class="steps-heading">
        <p class="eyebrow">A better rhythm</p>
        <h2>Three steps.<br /><span>One less thing to worry about.</span></h2>
        <p>
          A simple home for your class day—so you can spend less time checking and more time
          getting where you need to be.
        </p>
      </div>
      <ol class="steps-list">
        <li v-for="step in steps" :key="step.number">
          <span class="step-number">{{ step.number }}</span>
          <div>
            <h3>{{ step.title }}</h3>
            <p>{{ step.description }}</p>
          </div>
          <span class="step-arrow" aria-hidden="true">↗</span>
        </li>
      </ol>
    </div>
  </section>
</template>

<style scoped>
.steps-section {
  background: #f1f6fc;
}

.steps-layout {
  display: grid;
  grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
  gap: clamp(3rem, 8vw, 8rem);
  align-items: center;
}

.steps-heading .eyebrow {
  margin-bottom: 0.85rem;
}

h2 {
  margin: 0;
  color: var(--color-brand);
  font-size: clamp(2.1rem, 4.3vw, 3.2rem);
  letter-spacing: -0.06em;
  line-height: 1.1;
}

h2 span {
  color: #2563eb;
}

.steps-heading > p:last-child {
  max-width: 430px;
  margin: 1.1rem 0 0;
  color: #64748b;
  line-height: 1.75;
}

.steps-list {
  display: grid;
  gap: 0.7rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.steps-list li {
  display: grid;
  grid-template-columns: 2.9rem minmax(0, 1fr) 1.5rem;
  gap: 1rem;
  align-items: center;
  padding: 1.1rem 1.2rem;
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  background: #ffffff;
}

.step-number {
  display: grid;
  width: 2.75rem;
  height: 2.75rem;
  place-items: center;
  border-radius: 0.85rem;
  color: #1d4ed8;
  background: #eff6ff;
  font-size: 0.8rem;
  font-weight: 750;
}

.steps-list h3 {
  margin: 0;
  color: #123b78;
  font-size: 1rem;
}

.steps-list p {
  margin: 0.35rem 0 0;
  color: #64748b;
  font-size: 0.84rem;
  line-height: 1.5;
}

.step-arrow {
  color: #94a3b8;
  font-size: 1.1rem;
}

@media (max-width: 850px) {
  .steps-layout {
    grid-template-columns: 1fr;
    gap: 2.5rem;
  }
}

@media (max-width: 480px) {
  .steps-list li {
    grid-template-columns: 2.75rem minmax(0, 1fr);
    gap: 0.8rem;
    padding: 0.9rem;
  }

  .step-arrow {
    display: none;
  }
}
</style>
