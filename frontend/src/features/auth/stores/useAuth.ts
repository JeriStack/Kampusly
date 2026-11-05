import { computed, ref } from 'vue'
import { apiClient, setCsrfToken } from '@/services/api/client'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import { ApiError } from '@/services/api/errors'

export type UserRole = 'admin' | 'instructor' | 'student'
export type AccountStatus = 'active' | 'inactive' | 'suspended'

export interface AuthUserProfile {
  student_number?: string | null
  section_id?: number | null
  section_name?: string | null
  program?: string | null
  employee_number?: string | null
  department?: string | null
  [key: string]: unknown
}

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  status: AccountStatus
  avatar_url: string | null
  profile: AuthUserProfile
  student_profile?: Record<string, unknown> | null
  instructor_profile?: Record<string, unknown> | null
  last_login_at: string | null
  created_at: string
  updated_at: string
}

export interface AuthPayload {
  user?: AuthUser
  id?: number
  full_name?: string
  name?: string
  email?: string
  role?: UserRole
  status?: AccountStatus
  csrf_token?: string
  [key: string]: unknown
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface ChangePasswordPayload {
  current_password: string
  new_password: string
  new_password_confirmation: string
}

function normalizeAuthUser(raw: Record<string, unknown>): AuthUser {
  const source = (typeof raw.user === 'object' && raw.user !== null ? raw.user : raw) as Record<string, unknown>
  return {
    id: Number(source.id ?? 0),
    name: String(source.name ?? source.full_name ?? 'User'),
    email: String(source.email ?? ''),
    role: (source.role as UserRole) ?? 'student',
    status: (source.status as AccountStatus) ?? 'active',
    avatar_url: typeof source.avatar_url === 'string' ? source.avatar_url : null,
    profile: (typeof source.profile === 'object' && source.profile !== null
      ? source.profile
      : {
          ...((source.student_profile as Record<string, unknown>) ?? {}),
          ...((source.instructor_profile as Record<string, unknown>) ?? {}),
        }) as AuthUserProfile,
    student_profile: (source.student_profile as Record<string, unknown>) ?? null,
    instructor_profile: (source.instructor_profile as Record<string, unknown>) ?? null,
    last_login_at: typeof source.last_login_at === 'string' ? source.last_login_at : null,
    created_at: typeof source.created_at === 'string' ? source.created_at : '',
    updated_at: typeof source.updated_at === 'string' ? source.updated_at : '',
  }
}

function extractCsrfToken(raw: Record<string, unknown>): string | null {
  if (typeof raw.csrf_token === 'string' && raw.csrf_token) {
    return raw.csrf_token
  }
  if (
    typeof raw.user === 'object' &&
    raw.user !== null &&
    typeof (raw.user as Record<string, unknown>).csrf_token === 'string'
  ) {
    return (raw.user as Record<string, unknown>).csrf_token as string
  }
  return null
}

// Global reactive auth state across the application
const user = ref<AuthUser | null>(null)
const csrfToken = ref<string | null>(null)
const status = ref<'idle' | 'loading' | 'authenticated' | 'unauthenticated'>('idle')
const lastError = ref<string | null>(null)
let initPromise: Promise<AuthUser | null> | null = null

export function useAuth() {
  const isAuthenticated = computed(() => user.value !== null)
  const role = computed<UserRole | null>(() => user.value?.role ?? null)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isInstructor = computed(() => user.value?.role === 'instructor')
  const isStudent = computed(() => user.value?.role === 'student')

  function getDashboardRoute(targetRole?: UserRole | null): string {
    const activeRole = targetRole ?? user.value?.role
    switch (activeRole) {
      case 'admin':
        return '/admin/dashboard'
      case 'instructor':
        return '/instructor/dashboard'
      case 'student':
        return '/student/dashboard'
      default:
        return '/login'
    }
  }

  async function initAuth(force = false): Promise<AuthUser | null> {
    if (!force && status.value !== 'idle' && initPromise !== null) {
      return initPromise
    }

    status.value = 'loading'
    lastError.value = null

    initPromise = (async () => {
      try {
        const response = await apiClient.get<{ data: Record<string, unknown> }>(API_ENDPOINTS.auth.me)
        const payload = response?.data
        if (payload && (payload.id !== undefined || payload.user !== undefined)) {
          const normalized = normalizeAuthUser(payload)
          const token = extractCsrfToken(payload)
          user.value = normalized
          csrfToken.value = token
          if (token) setCsrfToken(token)
          status.value = 'authenticated'
          return normalized
        }
        user.value = null
        csrfToken.value = null
        setCsrfToken(null)
        status.value = 'unauthenticated'
        return null
      } catch (err) {
        user.value = null
        csrfToken.value = null
        setCsrfToken(null)
        status.value = 'unauthenticated'
        if (err instanceof ApiError && err.status !== 401) {
          lastError.value = err.message
        }
        return null
      }
    })()

    return initPromise
  }

  async function login(credentials: LoginCredentials): Promise<AuthUser> {
    status.value = 'loading'
    lastError.value = null

    try {
      const response = await apiClient.post<{ data: Record<string, unknown> }>(
        API_ENDPOINTS.auth.login,
        credentials
      )

      const payload = response.data
      const normalized = normalizeAuthUser(payload)
      const token = extractCsrfToken(payload)
      user.value = normalized
      csrfToken.value = token
      if (token) setCsrfToken(token)
      status.value = 'authenticated'
      return normalized
    } catch (err) {
      status.value = 'unauthenticated'
      if (err instanceof ApiError) {
        lastError.value = err.message
      } else {
        lastError.value = 'An unexpected error occurred during login.'
      }
      throw err
    }
  }

  async function logout(): Promise<void> {
    try {
      await apiClient.post(API_ENDPOINTS.auth.logout, {})
    } catch {
      // Ignore network / session expiration errors during logout
    } finally {
      user.value = null
      csrfToken.value = null
      setCsrfToken(null)
      status.value = 'unauthenticated'
      lastError.value = null
    }
  }

  async function changePassword(payload: ChangePasswordPayload): Promise<void> {
    await apiClient.post(API_ENDPOINTS.auth.changePassword, payload)
  }

  return {
    user,
    csrfToken,
    status,
    lastError,
    isAuthenticated,
    role,
    isAdmin,
    isInstructor,
    isStudent,
    getDashboardRoute,
    initAuth,
    login,
    logout,
    changePassword,
  }
}
