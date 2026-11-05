import { describe, it, expect } from './harness'
import { useAuth } from '@/features/auth/stores/useAuth'
import { setCsrfToken, getCsrfToken } from '@/services/api/client'
import { authNavigationGuard } from '@/app/router/guards'
import type { RouteLocationNormalized, NavigationGuardNext } from 'vue-router'

describe('Kampusly Frontend Auth Store & Navigation Guards', () => {
  it('initializes with unauthenticated defaults and accurate role helpers', () => {
    const auth = useAuth()
    expect(auth.isAuthenticated.value).toBe(false)
    expect(auth.isAdmin.value).toBe(false)
    expect(auth.isInstructor.value).toBe(false)
    expect(auth.isStudent.value).toBe(false)
  })

  it('resolves correct role dashboard routes', () => {
    const auth = useAuth()
    expect(auth.getDashboardRoute('admin')).toBe('/admin/dashboard')
    expect(auth.getDashboardRoute('instructor')).toBe('/instructor/dashboard')
    expect(auth.getDashboardRoute('student')).toBe('/student/dashboard')
    expect(auth.getDashboardRoute(null)).toBe('/login')
  })

  it('manages CSRF token storage and retrieval in memory', () => {
    setCsrfToken('test-csrf-token-12345')
    expect(getCsrfToken()).toBe('test-csrf-token-12345')
    setCsrfToken(null)
    expect(getCsrfToken()).toBe(null)
  })

  it('navigation guard allows unauthenticated access to guest routes', async () => {
    const auth = useAuth()
    auth.user.value = null
    auth.status.value = 'unauthenticated'

    const to = {
      path: '/login',
      fullPath: '/login',
      meta: { requiresGuest: true },
    } as unknown as RouteLocationNormalized

    const from = {} as unknown as RouteLocationNormalized

    let nextCalledWith: unknown = 'NOT_CALLED'
    const next: NavigationGuardNext = (arg?: unknown) => {
      nextCalledWith = arg ?? undefined
    }

    await authNavigationGuard(to, from, next)
    expect(nextCalledWith).toBe(undefined)
  })

  it('navigation guard redirects unauthenticated user from protected route to login', async () => {
    const auth = useAuth()
    auth.user.value = null
    auth.status.value = 'unauthenticated'

    const to = {
      path: '/admin/dashboard',
      fullPath: '/admin/dashboard',
      meta: { requiresAuth: true, allowedRoles: ['admin'] },
    } as unknown as RouteLocationNormalized

    const from = {} as unknown as RouteLocationNormalized

    let nextCalledWith: unknown = null
    const next: NavigationGuardNext = (arg?: unknown) => {
      nextCalledWith = arg
    }

    await authNavigationGuard(to, from, next)
    expect(typeof nextCalledWith).toBe('object')
    const redirectObj = nextCalledWith as { path: string; query: { redirect: string } }
    expect(redirectObj.path).toBe('/login')
    expect(redirectObj.query.redirect).toBe('/admin/dashboard')
  })

  it('navigation guard blocks role mismatch and redirects to appropriate dashboard', async () => {
    const auth = useAuth()
    auth.user.value = {
      id: 5,
      name: 'Maria Santos',
      email: 'santos@kampusly.edu.ph',
      role: 'student',
      status: 'active',
      avatar_url: null,
      profile: {},
      last_login_at: null,
      created_at: '2026-10-28 00:00:00',
      updated_at: '2026-10-28 00:00:00',
    }
    auth.status.value = 'authenticated'

    const to = {
      path: '/admin/dashboard',
      fullPath: '/admin/dashboard',
      meta: { requiresAuth: true, allowedRoles: ['admin'] },
    } as unknown as RouteLocationNormalized

    const from = {} as unknown as RouteLocationNormalized

    let nextCalledWith: unknown = null
    const next: NavigationGuardNext = (arg?: unknown) => {
      nextCalledWith = arg
    }

    await authNavigationGuard(to, from, next)
    expect(nextCalledWith).toBe('/student/dashboard')
  })
})

