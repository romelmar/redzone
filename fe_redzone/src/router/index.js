import { useAuthStore } from "@/stores/auth";
import { createRouter, createWebHistory } from "vue-router";

import Show from "@/pages/Subscriptions/Show.vue";
import Subscriptions from "@/pages/Subscriptions/Subscriptions.vue";
import WithDues from "../pages/Subscribers/WithDues.vue";
import Index from "@/pages/Subscribers/Index.vue";

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
          path: "/dashboard",
          name: "dashboard",
          component: () => import("../pages/dashboard.vue"),
        },
        {
          path: "/subscribers",
          name: "index",
          component: Index,
        },
        {
          path: "/subscribers/account-settings",
          name: "account-settings",
          component: Index,
        },
        {
          path: "/subscribers/with-dues",
          name: "with-dues",
          component: WithDues,
        },
        {
          path: "/subscriptions",
          name: "subscriptions.index",
          component: Subscriptions,
        },
        {
          path: "/subscriptions/:id",
          name: "subscriptions.show",
          component: Show,
          props: true,
        },
        // ------------------------------------------------
        {
          path: "account-settings",
          component: () => import("../pages/account-settings.vue"),
        },
        {
          path: "typography",
          component: () => import("../pages/typography.vue"),
        },
        {
          path: "icons",
          component: () => import("../pages/icons.vue"),
        },
        {
          path: "cards",
          component: () => import("../pages/cards.vue"),
        },
        {
          path: "tables",
          component: () => import("../pages/tables.vue"),
        },
        {
          path: "form-layouts",
          component: () => import("../pages/form-layouts.vue"),
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
