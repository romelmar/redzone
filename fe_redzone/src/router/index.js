import { useAuthStore } from "@/stores/auth";
import { createRouter, createWebHistory } from "vue-router";

import WithDues from "../pages/Subscribers/WithDues.vue";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: "/", redirect: "/dashboard", meta: { requiresAuth: true } },

    // 🔹 Group routes that use the default layout
    {
      path: "/",
      component: () => import("../layouts/default.vue"),
      meta: { requiresAuth: true },
      children: [
        {
          path: "dashboard",
          name: "dashboard",
          component: () => import("../pages/dashboard.vue"),
        },
        {
          path: "subscribers/with-dues",
          name: "with-dues",
          component: WithDues,
        },
      ],
    },

    // 🔹 Auth pages (blank layout)
    {
      path: "/",
      component: () => import("../layouts/blank.vue"),
      children: [
        {
          path: "login",
          name: "login",
          component: () => import("../pages/login.vue"),
        },
      ],
    },
  ],
});

// 🔐 Global Navigation Guard
router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore();

  if (to.meta.requiresAuth) {
    if (!auth.user) {
      try {
        await auth.fetchUser();
      } catch (error) {
        return next({ path: "/login" });
      }
    }
  }

  if (to.path === "/login" && auth.user) {
    return next({ path: "/dashboard" });
  }

  next();
});

export default router;
