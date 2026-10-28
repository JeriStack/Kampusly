import { onBeforeUnmount, onMounted, readonly, ref } from 'vue'

export type Theme = 'light' | 'dark'

const STORAGE_KEY = 'kampusly-theme'
const DEFAULT_THEME: Theme = 'light'

// Shared across all callers so the toggle and any consumer stay in sync.
const theme = ref<Theme>(DEFAULT_THEME)
let activeConsumers = 0

function syncMeta(value: Theme) {
  if (typeof document === 'undefined') return
  const meta = document.querySelector<HTMLMetaElement>(
    'meta[name="color-scheme"]',
  )
  if (meta) {
    meta.setAttribute('content', value)
  }
}

function apply(value: Theme) {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  root.classList.toggle('dark', value === 'dark')
  root.style.colorScheme = value
  syncMeta(value)
}

function readStored(): Theme | null {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    return stored === 'light' || stored === 'dark' ? stored : null
  } catch {
    return null
  }
}

function resolveTheme() {
  const resolved: Theme = readStored() ?? DEFAULT_THEME
  theme.value = resolved
  apply(resolved)
}

function handleStorageChange(event: StorageEvent) {
  if (event.key !== null && event.key !== STORAGE_KEY) return
  resolveTheme()
}

if (typeof window !== 'undefined') {
  resolveTheme()
}

/**
 * Light/dark theme controller.
 *
 * Defaults to light mode on first visit and persists explicit user toggles in
 * localStorage (`kampusly-theme`).
 */
export function useTheme() {
  function setTheme(value: Theme, persist = true) {
    theme.value = value
    if (persist) {
      try {
        localStorage.setItem(STORAGE_KEY, value)
      } catch {
        // Storage may be unavailable (private mode); theme still applies.
      }
    }
    apply(value)
  }

  function toggle() {
    setTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  onMounted(() => {
    if (activeConsumers === 0 && typeof window !== 'undefined') {
      resolveTheme()
      window.addEventListener('storage', handleStorageChange)
    }
    activeConsumers += 1
  })

  onBeforeUnmount(() => {
    activeConsumers = Math.max(0, activeConsumers - 1)
    if (activeConsumers === 0 && typeof window !== 'undefined') {
      window.removeEventListener('storage', handleStorageChange)
    }
  })

  return {
    theme: readonly(theme),
    toggle,
    setTheme,
  }
}
