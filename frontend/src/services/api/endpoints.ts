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
 * Centralized API endpoint definitions aligned with `docs/phase-2/phase-2-api-contract.yaml`
 * and backend route definitions.
 */
export const API_ENDPOINTS = {
  root: '/',
  health: {
    live: '/health/live',
    ready: '/health/ready',
  },
  auth: {
    login: '/auth/login',
    logout: '/auth/logout',
    me: '/auth/me',
    changePassword: '/auth/change-password',
  },
  users: {
    list: '/users',
    create: '/users',
    get: (id: number | string) => `/users/${id}`,
    updateStatus: (id: number | string) => `/users/${id}/status`,
  },
  admin: {
    dashboardSummary: '/admin/dashboard-summary',
  },
  academic: {
    students: '/students',
    student: (id: number | string) => `/students/${id}`,
    studentStatus: (id: number | string) => `/students/${id}/status`,
    instructors: '/instructors',
    instructor: (id: number | string) => `/instructors/${id}`,
    instructorStatus: (id: number | string) => `/instructors/${id}/status`,
    subjects: '/subjects',
    subject: (id: number | string) => `/subjects/${id}`,
    rooms: '/rooms',
    room: (id: number | string) => `/rooms/${id}`,
    sections: '/sections',
    section: (id: number | string) => `/sections/${id}`,
    sectionStudents: (id: number | string) => `/sections/${id}/students`,
  },
  scheduling: {
    schedules: '/schedules',
    schedule: (id: number | string) => `/schedules/${id}`,
    validateConflicts: '/schedules/validate-conflicts',
  },
  student: {
    schedule: '/student/schedule',
  },
} as const
