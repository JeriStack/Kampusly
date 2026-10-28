import {
  createRouter,
  createWebHistory,
  type RouteRecordRaw,
} from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'landing',
    component: () => import('@/features/landing/pages/LandingPage.vue'),
    meta: { title: 'Kampusly — Your Campus. Your Classes. Your Time.' },
  },
  {
    // Placeholder target for Access / Log In CTAs. The authenticated area and
    // real login screen are built separately from the public landing page.
    path: '/login',
    name: 'login',
    component: () => import('@/features/auth/pages/LoginPlaceholder.vue'),
    meta: { title: 'Log in — Kampusly' },
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

router.afterEach((to) => {
  const title = to.meta.title
  if (typeof title === 'string') {
    document.title = title
  }
})
