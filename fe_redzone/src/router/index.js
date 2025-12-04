import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Plans from '@/pages/Plans/Index.vue'

import SubscriptionsIndex from '@/pages/Subscriptions/Index.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/plans', meta: { requiresAuth: true } },
    {
      path: '/',
      component: () => import('../layouts/default.vue'),
      children: [
        {
          path: 'plans',
          component: () => import('@/views/plans/Plans.vue'),
          meta: { requiresAuth: true },
        },
      ],
    },
    {
      path: '/',
      component: () => import('../layouts/default.vue'),
      children: [
        {
          path: 'addons',
          component: () => import('@/views/addons/Addons.vue'),
          meta: { requiresAuth: true },
        },
      ],
    },
    {
      path: '/',
      component: () => import('../layouts/default.vue'),
      children: [
        {
          path: 'payments',
          component: () => import('@/views/payments/Payments.vue'),
          meta: { requiresAuth: true },
        },
      ],
    },
    {
      path: '/',
      component: () => import('../layouts/default.vue'),
      children: [
        {
          path: 'service-credits',
          component: () => import('@/views/service-credits/ServiceCredits.vue'),
          meta: { requiresAuth: true },
        },
      ],
    },
    {
      path: '/',
      component: () => import('../layouts/default.vue'),
      children: [
        {
          path: 'subscribers-with-dues',
          component: () => import('@/views/billing/SubscribersWithDues.vue'),
          meta: { requiresAuth: true },
        },
      ],
    },
    {
      path: '/',
      component: () => import('../layouts/default.vue'),
      children: [
        {
          path: '/subscribers',
          name: 'subscribers',
          component: () => import('@/views/subscribers/Subscribers.vue'),
          meta: { requiresAuth: true },
        },
      ],
    },
    
    {
      path: '/',
      component: () => import('../layouts/default.vue'),
      children: [
        {
          path: '/subscriptions',
          name: 'subscriptions',
          component: () => import('@/views/subscriptions/Subscriptions.vue'),
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
  ],
})

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.user) {
    try {
      await auth.fetchUser()
    } catch (e) {
      return next({ path: '/login' })
    }
  }

  next()
})

export default router
