import { useAuthStore } from '@/stores/auth'
import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/dashboard', meta: { requiresAuth: true } },
    {
      path: '/',
      component: () => import('../layouts/default.vue'),
      children: [
        {
          path: 'dashboard',
          component: () => import('../pages/dashboard.vue'),
          meta: { requiresAuth: true },
        },
      ],
    },
    {
      path: '/',
      component: () => import('../layouts/blank.vue'),
      children: [
        {
          path: 'login',
          component: () => import('../pages/login.vue'),
        },
      ],
    },
  ],
})

// 🔐 Global Navigation Guard
router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()

  // If route requires auth
  if (to.meta.requiresAuth) {
    if (!auth.user) {
      try {
        await auth.fetchUser()
      } catch (error) {
        return next({ path: '/login' })
      }
    }
  }

  // If user is logged in and tries to go to login, redirect to dashboard
  if (to.path === '/login') {
    if (!auth.user) {
      try {
        await auth.fetchUser()
      } catch (error) {
        return next()
      }
    }

    if (auth.user) {
      return next({ path: '/dashboard' })
    }
  }

  next()
})

export default router
