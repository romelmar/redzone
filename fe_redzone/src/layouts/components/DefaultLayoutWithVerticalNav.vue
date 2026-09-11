<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import VerticalNavLayout from '@layouts/components/VerticalNavLayout.vue'
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'
import VerticalNavSectionTitle from '@layouts/components/VerticalNavSectionTitle.vue'
import Footer from './Footer.vue'
import NavbarThemeSwitcher from './NavbarThemeSwitcher.vue'
import UserProfile from './UserProfile.vue'
const route = useRoute()
const groups = [
  { title: 'Overview', items: [['Dashboard', 'bx-grid-alt', '/dashboard', 'Monitor collections, balances, and upcoming due dates.']] },
  { title: 'Customers & services', items: [['Subscribers', 'bx-user', '/subscribers', 'Manage customer records and contact information.'], ['Subscriptions', 'bx-wifi', '/subscriptions', 'Manage connections, billing, and service status.'], ['Plans', 'bx-list-ul', '/plans', 'Maintain your service plans and monthly rates.'], ['Add-ons', 'bx-extension', '/addons', 'Manage additional services and charges.']] },
  { title: 'Billing & collections', items: [['Payments', 'bx-wallet-alt', '/payments', 'Record payments and review collection history.'], ['Service credits', 'bx-coin', '/service-credits', 'Review billing credits and account adjustments.'], ['Billing & statements', 'bx-receipt', '/statements', 'Prepare monthly bills and detailed statements of account.'], ['Collection sheet', 'bx-file', '/collection-sheet', 'Prepare collection routes and assign collectors.'], ['Cash reconciliation', 'bx-check-shield', '/reconciliation', 'Compare recorded collections with actual remittances.']] },
  { title: 'Reports & oversight', items: [['Overdue accounts', 'bx-time-five', '/account-reports?type=overdue', 'Review overdue balances.'], ['Disconnected accounts', 'bx-power-off', '/account-reports?type=disconnected', 'Review disconnected services.'], ['Payment audit', 'bx-history', '/payment-audit', 'Trace payment changes and corrections.']] },
]
const current = computed(() => groups.flatMap(g => g.items).find(i => i[2] === route.fullPath) || groups.flatMap(g => g.items).find(i => i[2].split('?')[0] === route.path))
const ownHeading = computed(() => ['/dashboard', '/account-reports', '/reconciliation', '/payment-audit'].includes(route.path))
</script>
<template>
  <VerticalNavLayout>
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center gap-3">
        <IconBtn class="d-lg-none" aria-label="Open navigation" @click="toggleVerticalOverlayNavActive(true)"><VIcon icon="bx-menu" /></IconBtn>
        <div class="workspace-context"><span>REDZONE / WORKSPACE</span><strong>{{ current?.[0] || 'Account management' }}</strong></div>
        <VSpacer />
        <NavbarThemeSwitcher aria-label="Change color theme" />
        <UserProfile />
      </div>
    </template>
    <template #vertical-nav-content>
      <template v-for="group in groups" :key="group.title">
        <VerticalNavSectionTitle :item="{ heading: group.title }" />
        <VerticalNavLink v-for="item in group.items" :key="item[2]" :item="{ title: item[0], icon: item[1], to: item[2] }" />
      </template>
    </template>
    <a class="skip-link" href="#workspace-main">Skip to page content</a>
    <div id="workspace-main" class="enterprise-page" tabindex="-1">
      <header v-if="!ownHeading" class="page-heading"><h1>{{ current?.[0] || 'Collector assignments' }}</h1><p>{{ current?.[3] || 'Organize collection responsibilities and assignments.' }}</p></header>
      <slot />
    </div>
    <template #footer><Footer /></template>
  </VerticalNavLayout>
</template>
