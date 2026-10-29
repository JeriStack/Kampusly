import type { NavigationGuardNext, RouteLocationNormalized } from 'vue-router'
import { useAuth, type UserRole } from '@/features/auth/stores/useAuth'

export async function authNavigationGuard(
  to: RouteLocationNormalized,
  _from: RouteLocationNormalized,
  next: NavigationGuardNext
): Promise<void> {
  const auth = useAuth()

  // Ensure auth session is initialized on first route navigation
  if (auth.status.value === 'idle') {
    await auth.initAuth()
  }

  const requiresAuth = Boolean(to.meta.requiresAuth)
  const requiresGuest = Boolean(to.meta.requiresGuest)
  const allowedRoles = to.meta.allowedRoles as UserRole[] | undefined

  if (requiresGuest && auth.isAuthenticated.value) {
    return next(auth.getDashboardRoute())
  }

  if (requiresAuth && !auth.isAuthenticated.value) {
    return next({
      path: '/login',
      query: { redirect: to.fullPath },
    })
  }

  if (requiresAuth && allowedRoles && allowedRoles.length > 0) {
    const userRole = auth.role.value
    if (!userRole || !allowedRoles.includes(userRole)) {
      // Forbidden: redirect to their own role's home dashboard
      return next(auth.getDashboardRoute())
    }
  }

  return next()
}

