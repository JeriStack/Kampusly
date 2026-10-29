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
  last_login_at: string | null
  created_at: string
  updated_at: string
}

export interface AuthPayload {
  user: AuthUser
  csrf_token: string
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
        const response = await apiClient.get<{ data: AuthPayload }>(API_ENDPOINTS.auth.me)
        if (response?.data?.user) {
          user.value = response.data.user
          csrfToken.value = response.data.csrf_token
          setCsrfToken(response.data.csrf_token)
          status.value = 'authenticated'
          return user.value
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
      const response = await apiClient.post<{ data: AuthPayload }>(
        API_ENDPOINTS.auth.login,
        credentials
      )

      const authData = response.data
      user.value = authData.user
      csrfToken.value = authData.csrf_token
      setCsrfToken(authData.csrf_token)
      status.value = 'authenticated'
      return authData.user
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

