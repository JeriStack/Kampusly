<script setup lang="ts">
const classes = [
  {
    time: '8:30',
    period: 'AM',
    title: 'Human-Computer Interaction',
    detail: 'Room 204 · Prof. Rivera',
    tone: 'blue',
  },
  {
    time: '10:00',
    period: 'AM',
    title: 'Database Systems',
    detail: 'Lab 3 · Prof. Santos',
    tone: 'teal',
  },
  {
    time: '1:00',
    period: 'PM',
    title: 'Software Engineering',
    detail: 'Room 118 · Prof. Chen',
    tone: 'violet',
  },
]
</script>

<template>
  <div class="preview-wrap" aria-label="Illustrative weekly schedule preview">
    <div class="preview-glow" aria-hidden="true"></div>
    <section class="schedule-card" aria-labelledby="schedule-preview-title">
      <div class="preview-topline">
        <div class="preview-label">
          <span class="live-dot" aria-hidden="true"></span>
          <span>YOUR WEEK, AT A GLANCE</span>
        </div>
        <span class="preview-menu" aria-hidden="true">•••</span>
      </div>

      <div class="schedule-heading">
        <div>
          <h2 id="schedule-preview-title">Monday schedule</h2>
          <p>Week of October 12</p>
        </div>
        <span class="week-chip">Week 04</span>
      </div>

      <div class="week-strip" aria-label="Selected day: Monday">
        <span class="day-item selected"><span>MON</span><strong>12</strong></span>
        <span class="day-item"><span>TUE</span><strong>13</strong></span>
        <span class="day-item"><span>WED</span><strong>14</strong></span>
        <span class="day-item"><span>THU</span><strong>15</strong></span>
        <span class="day-item"><span>FRI</span><strong>16</strong></span>
      </div>

      <ol class="class-list">
        <li v-for="item in classes" :key="item.title" class="class-item">
          <div class="class-time">
            <strong>{{ item.time }}</strong>
            <span>{{ item.period }}</span>
          </div>
          <div class="class-details" :class="`tone-${item.tone}`">
            <div class="class-title-row">
              <h3>{{ item.title }}</h3>
              <span class="published-label">Published</span>
            </div>
            <p>{{ item.detail }}</p>
          </div>
        </li>
      </ol>

      <div class="preview-foot">
        <span class="privacy-icon" aria-hidden="true">
          <svg viewBox="0 0 20 20" fill="none">
            <rect x="4.25" y="8.5" width="11.5" height="8" rx="2" stroke="currentColor" stroke-width="1.5" />
            <path d="M6.75 8.5V6a3.25 3.25 0 0 1 6.5 0v2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
          </svg>
        </span>
        <span>Illustrative demo data · No student information</span>
      </div>
    </section>
  </div>
</template>

<style scoped>
.preview-wrap {
  position: relative;
  width: 100%;
  max-width: 560px;
  margin-inline: auto;
}

.preview-glow {
  position: absolute;
  inset: 12% 2%;
  border-radius: 50%;
  background: linear-gradient(135deg, rgb(37 99 235 / 17%), rgb(13 148 136 / 15%));
  filter: blur(55px);
}

.schedule-card {
  position: relative;
  padding: clamp(1.15rem, 3vw, 1.8rem);
  border: 1px solid rgb(226 232 240 / 88%);
  border-radius: 1.35rem;
  background: var(--color-surface);
  box-shadow: 0 28px 80px rgb(18 59 120 / 15%), 0 4px 12px rgb(15 23 42 / 4%);
  transform: rotate(1deg);
}

.preview-topline,
.schedule-heading,
.class-title-row,
.preview-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.preview-label {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  color: #64748b;
  font-size: 0.64rem;
  font-weight: 750;
  letter-spacing: 0.1em;
}

.live-dot {
  width: 0.48rem;
  height: 0.48rem;
  border-radius: 50%;
  background: var(--color-accent);
  box-shadow: 0 0 0 3px #ccfbf1;
}

.preview-menu {
  color: #94a3b8;
  font-size: 1.15rem;
  letter-spacing: 0.1em;
}

.schedule-heading {
  align-items: flex-start;
  margin-top: 1.45rem;
}

.schedule-heading h2 {
  margin: 0;
  color: var(--color-brand);
  font-size: clamp(1.25rem, 2.5vw, 1.55rem);
  letter-spacing: -0.04em;
}

.schedule-heading p {
  margin: 0.4rem 0 0;
  color: var(--color-muted);
  font-size: 0.8rem;
}

.week-chip {
  padding: 0.42rem 0.65rem;
  border: 1px solid #dbeafe;
  border-radius: 999px;
  color: #1d4ed8;
  background: #eff6ff;
  font-size: 0.68rem;
  font-weight: 700;
}

.week-strip {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.4rem;
  margin: 1.35rem 0 1.1rem;
  padding: 0.55rem;
  border-radius: 0.9rem;
  background: #f8fafc;
}

.day-item {
  display: grid;
  justify-items: center;
  gap: 0.35rem;
  padding: 0.48rem 0.2rem;
  border-radius: 0.65rem;
  color: #64748b;
}

.day-item span {
  font-size: 0.58rem;
  font-weight: 750;
  letter-spacing: 0.06em;
}

.day-item strong {
  font-size: 0.92rem;
}

.day-item.selected {
  color: white;
  background: var(--color-brand);
  box-shadow: 0 4px 10px rgb(18 59 120 / 18%);
}

.class-list {
  display: grid;
  gap: 0.7rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.class-item {
  display: grid;
  grid-template-columns: 3.25rem minmax(0, 1fr);
  gap: 0.7rem;
  align-items: stretch;
}

.class-time {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: center;
  padding-top: 0.1rem;
  color: #334155;
}

.class-time strong {
  font-size: 0.79rem;
}

.class-time span {
  margin-top: 0.08rem;
  color: var(--color-muted);
  font-size: 0.63rem;
}

.class-details {
  min-width: 0;
  padding: 0.75rem 0.8rem;
  border-left: 3px solid;
  border-radius: 0 0.7rem 0.7rem 0;
}

.tone-blue {
  border-color: #2563eb;
  background: #eff6ff;
}

.tone-teal {
  border-color: #0d9488;
  background: #f0fdfa;
}

.tone-violet {
  border-color: #7c3aed;
  background: #f5f3ff;
}

.class-title-row {
  justify-content: flex-start;
  gap: 0.5rem;
}

.class-details h3 {
  overflow: hidden;
  margin: 0;
  color: #1e293b;
  font-size: clamp(0.69rem, 1.4vw, 0.79rem);
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.published-label {
  flex-shrink: 0;
  padding: 0.16rem 0.38rem;
  border-radius: 999px;
  color: #166534;
  background: #dcfce7;
  font-size: 0.54rem;
  font-weight: 700;
}

.class-details p {
  margin: 0.35rem 0 0;
  color: #64748b;
  font-size: 0.66rem;
}

.preview-foot {
  justify-content: flex-start;
  gap: 0.45rem;
  margin-top: 1rem;
  color: #64748b;
  font-size: 0.62rem;
}

.privacy-icon,
.privacy-icon svg {
  display: block;
  width: 1rem;
  height: 1rem;
  flex: 0 0 auto;
}

.privacy-icon {
  color: #0f766e;
}

@media (max-width: 460px) {
  .schedule-card {
    transform: none;
  }

  .published-label {
    display: none;
  }
}
</style>
