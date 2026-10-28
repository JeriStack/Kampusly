const DEFAULT_API_BASE_URL = '/api/v1'

/**
 * Resolve the normalized Kampusly API base URL from a safe `VITE_API_BASE_URL`
 * value, falling back to `/api/v1`.
 */
export function resolveApiBaseUrl(rawBaseUrl?: string): string {
  const envValue =
    rawBaseUrl !== undefined ? rawBaseUrl : import.meta.env?.VITE_API_BASE_URL

  const trimmed = (envValue || DEFAULT_API_BASE_URL).trim()
  if (!trimmed) {
    return DEFAULT_API_BASE_URL
  }

  return trimmed.replace(/\/+$/, '')
}

/**
 * Build a full request URL for a given endpoint path.
 */
export function buildApiUrl(path: string, baseUrl?: string): string {
  const base = resolveApiBaseUrl(baseUrl)
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  if (normalizedPath === '/') {
    return base
  }

  return `${base}${normalizedPath}`
}

/**
 * Centralized API endpoint definitions aligned with `docs/phase-2/phase-2-api-contract.yaml`.
 */
export const API_ENDPOINTS = {
  root: '/',
  health: {
    live: '/health/live',
    ready: '/health/ready',
  },
} as const
