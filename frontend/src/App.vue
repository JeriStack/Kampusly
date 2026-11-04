<template>
  <main class="app-placeholder">
    <p class="eyebrow">Kampusly</p>
    <h1>Your Campus. Your Classes. Your Time.</h1>
    <p>The public landing experience is coming together.</p>
  </main>
</template>

<style scoped>
.app-placeholder {
  display: grid;
  min-height: 100vh;
  align-content: center;
  justify-items: center;
  gap: 0.75rem;
  padding: 2rem;
  text-align: center;
}

.app-placeholder h1,
.app-placeholder p {
  margin: 0;
}

.eyebrow {
  color: var(--color-action);
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
</style>
