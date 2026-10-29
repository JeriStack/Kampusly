import {
  createRouter,
  createWebHistory,
  type RouteRecordRaw,
} from 'vue-router'
import { authNavigationGuard } from './guards'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'landing',
    component: () => import('@/features/landing/pages/LandingPage.vue'),
    meta: { title: 'Kampusly — Your Campus. Your Classes. Your Time.' },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/features/auth/pages/LoginPage.vue'),
    meta: {
      title: 'Sign in — Kampusly',
      requiresGuest: true,
    },
  },
  {
    path: '/admin/dashboard',
    name: 'admin-dashboard',
    component: () => import('@/features/admin/pages/AdminDashboard.vue'),
    meta: {
      title: 'Admin Dashboard — Kampusly',
      requiresAuth: true,
      allowedRoles: ['admin'],
    },
  },
  {
    path: '/instructor/dashboard',
    name: 'instructor-dashboard',
    component: () => import('@/features/instructor/pages/InstructorDashboard.vue'),
    meta: {
      title: 'Instructor Dashboard — Kampusly',
      requiresAuth: true,
      allowedRoles: ['instructor'],
    },
  },
  {
    path: '/student/dashboard',
    name: 'student-dashboard',
    component: () => import('@/features/student/pages/StudentDashboard.vue'),
    meta: {
      title: 'Student Dashboard — Kampusly',
      requiresAuth: true,
      allowedRoles: ['student'],
    },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: { name: 'landing' },
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, _from, savedPosition) {
    if (to.hash) {
      return { el: to.hash, behavior: 'smooth', top: 80 }
    }
    if (savedPosition) {
      return savedPosition
    }
    return { top: 0 }
  },
})

router.beforeEach(authNavigationGuard)

router.afterEach((to) => {
  const title = to.meta.title
  if (typeof title === 'string') {
    document.title = title
  }
})

