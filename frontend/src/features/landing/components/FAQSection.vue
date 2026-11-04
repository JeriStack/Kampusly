<template>
  <section class="section faq-section">
    <div class="container faq-layout">
      <div class="faq-heading">
        <p class="eyebrow">Good to know</p>
        <h2>Questions, <span>answered.</span></h2>
        <p>Quick answers about getting started and keeping your information private.</p>
      </div>

      <div class="faq-list">
        <details>
          <summary>How do I access my class schedule?</summary>
          <p>
            Sign in with the account provided by your school. Your personal timetable is not
            available on this public page.
          </p>
        </details>
        <details>
          <summary>Can I create my own account?</summary>
          <p>
            Account access is managed by each campus. Contact your school administrator if you
            need an account or help signing in.
          </p>
        </details>
        <details>
          <summary>How will I know if a class changes?</summary>
          <p>
            Kampusly brings class announcements and schedule updates together, so you can check
            what changed and when.
          </p>
        </details>
        <details>
          <summary>Can other people see my schedule?</summary>
          <p>
            Personal schedules and account details belong in the authenticated app—not on a
            public page or its illustrative preview.
          </p>
        </details>
      </div>

      <aside id="campus-access" class="access-note">
        <span class="access-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="5" y="10" width="14" height="10" rx="2.5" stroke="currentColor" stroke-width="1.7" />
            <path d="M8 10V7a4 4 0 1 1 8 0v3m-4 4v2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
          </svg>
        </span>
        <div>
          <h3>Access is managed by your campus</h3>
          <p>
            Sign-in requires a campus account. Kampusly doesn’t offer public self-registration,
            and personal schedules stay behind authentication.
          </p>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.faq-section {
  background: #f8fafc;
}

.faq-layout {
  display: grid;
  grid-template-columns: minmax(240px, 0.75fr) minmax(0, 1.25fr);
  column-gap: clamp(3rem, 8vw, 7rem);
  row-gap: 2rem;
  align-items: start;
}

.faq-heading .eyebrow {
  margin-bottom: 0.85rem;
}

h2 {
  margin: 0;
  color: var(--color-brand);
  font-size: clamp(2rem, 4vw, 3rem);
  letter-spacing: -0.06em;
  line-height: 1.1;
}

h2 span {
  color: #2563eb;
}

.faq-heading > p:last-child {
  max-width: 360px;
  margin: 1rem 0 0;
  color: #64748b;
  line-height: 1.7;
}

.faq-list {
  display: grid;
  gap: 0.65rem;
}

.faq-list details {
  padding: 1rem 1.2rem;
  border: 1px solid var(--color-border);
  border-radius: 0.9rem;
  background: #ffffff;
}

.faq-list summary {
  position: relative;
  padding-right: 1.7rem;
  color: #123b78;
  font-size: 0.92rem;
  font-weight: 700;
  line-height: 1.5;
  cursor: pointer;
  list-style: none;
}

.faq-list summary::-webkit-details-marker {
  display: none;
}

.faq-list summary::after {
  position: absolute;
  top: 0;
  right: 0;
  color: #2563eb;
  content: "+";
  font-size: 1.2rem;
  line-height: 1;
}

.faq-list details[open] summary::after {
  content: "−";
}

.faq-list details p {
  margin: 0.8rem 0 0;
  color: #64748b;
  font-size: 0.87rem;
  line-height: 1.7;
}

.access-note {
  grid-column: 1 / -1;
  display: flex;
  gap: 1rem;
  align-items: flex-start;
  margin-top: 1rem;
  padding: 1.4rem 1.55rem;
  border: 1px solid #bfdbfe;
  border-radius: 1rem;
  background: #eff6ff;
}

.access-icon,
.access-icon svg {
  display: block;
  width: 1.65rem;
  height: 1.65rem;
  flex: 0 0 auto;
}

.access-icon {
  color: #1d4ed8;
}

.access-note h3 {
  margin: 0;
  color: #123b78;
  font-size: 0.95rem;
}

.access-note p {
  max-width: 760px;
  margin: 0.4rem 0 0;
  color: #475569;
  font-size: 0.86rem;
  line-height: 1.65;
}

@media (max-width: 800px) {
  .faq-layout {
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  .access-note {
    grid-column: auto;
  }
}

@media (max-width: 480px) {
  .faq-list details {
    padding: 0.9rem 1rem;
  }

  .access-note {
    padding: 1.1rem;
  }
}
</style>
