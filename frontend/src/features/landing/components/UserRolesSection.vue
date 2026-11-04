<script setup lang="ts">
const roles = [
  {
    name: 'Students',
    description: 'Find your next class, check where to be, and keep up with every change.',
    initials: 'S',
    tone: 'student',
  },
  {
    name: 'Instructors',
    description: 'See your teaching timetable and share timely notices with your classes.',
    initials: 'I',
    tone: 'instructor',
  },
  {
    name: 'Administrators',
    description: 'Keep academic schedules organized and campus information up to date.',
    initials: 'A',
    tone: 'admin',
  },
]
</script>

<template>
  <section id="campus" class="section roles-section">
    <div class="container">
      <div class="roles-heading">
        <p class="eyebrow">Made for your campus</p>
        <h2>One campus. <span>Every role in sync.</span></h2>
        <p>Useful tools for the people who plan, teach, and take classes.</p>
      </div>

      <div class="roles-grid">
        <article v-for="role in roles" :key="role.name" class="role-card">
          <span class="role-avatar" :class="`avatar-${role.tone}`" aria-hidden="true">
            {{ role.initials }}
          </span>
          <div>
            <h3>{{ role.name }}</h3>
            <p>{{ role.description }}</p>
          </div>
          <span class="role-arrow" aria-hidden="true">↗</span>
        </article>
      </div>
    </div>
  </section>
</template>

<style scoped>
.roles-section {
  background: #ffffff;
}

.roles-heading {
  max-width: 720px;
  margin: 0 auto 2.5rem;
  text-align: center;
}

.roles-heading .eyebrow {
  justify-content: center;
  margin-bottom: 0.85rem;
}

h2 {
  margin: 0;
  color: var(--color-brand);
  font-size: clamp(2.05rem, 4.5vw, 3.2rem);
  letter-spacing: -0.06em;
  line-height: 1.12;
}

h2 span {
  color: #2563eb;
}

.roles-heading > p:last-child {
  margin: 0.9rem 0 0;
  color: #64748b;
  line-height: 1.65;
}

.roles-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.role-card {
  display: grid;
  grid-template-columns: 3.25rem minmax(0, 1fr) 1rem;
  gap: 1rem;
  align-items: start;
  min-height: 155px;
  padding: 1.35rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  background: #ffffff;
}

.role-avatar {
  display: grid;
  width: 3.1rem;
  height: 3.1rem;
  place-items: center;
  border-radius: 1rem;
  font-size: 1rem;
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

.avatar-admin {
  color: #6d28d9;
  background: #f5f3ff;
}

.role-card h3 {
  margin: 0.15rem 0 0;
  color: #123b78;
  font-size: 1.05rem;
}

.role-card p {
  margin: 0.55rem 0 0;
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.65;
}

.role-arrow {
  color: #94a3b8;
}

@media (max-width: 850px) {
  .roles-grid {
    grid-template-columns: 1fr;
  }

  .role-card {
    min-height: auto;
  }
}
</style>
