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
    path: '/admin/schedules',
    name: 'admin-schedules',
    component: () => import('@/features/admin/pages/AdminSchedules.vue'),
    meta: {
      title: 'Schedule Management — Kampusly',
      requiresAuth: true,
      allowedRoles: ['admin'],
    },
  },
  {
    path: '/admin/subjects',
    name: 'admin-subjects',
    component: () => import('@/features/academic/subjects/SubjectsView.vue'),
    meta: { title: 'Subject Management — Kampusly', requiresAuth: true, allowedRoles: ['admin'] },
  },
  {
    path: '/admin/rooms',
    name: 'admin-rooms',
    component: () => import('@/features/academic/rooms/RoomsView.vue'),
    meta: { title: 'Room Management — Kampusly', requiresAuth: true, allowedRoles: ['admin'] },
  },
  {
    path: '/admin/sections',
    name: 'admin-sections',
    component: () => import('@/features/academic/sections/SectionsView.vue'),
    meta: { title: 'Section Management — Kampusly', requiresAuth: true, allowedRoles: ['admin'] },
  },
  {
    path: '/admin/students',
    name: 'admin-students',
    component: () => import('@/features/admin/pages/AdminDashboard.vue'),
    meta: { title: 'Student Accounts — Kampusly', requiresAuth: true, allowedRoles: ['admin'] },
  },
  {
    path: '/admin/instructors',
    name: 'admin-instructors',
    component: () => import('@/features/admin/pages/AdminDashboard.vue'),
    meta: { title: 'Instructor Accounts — Kampusly', requiresAuth: true, allowedRoles: ['admin'] },
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
