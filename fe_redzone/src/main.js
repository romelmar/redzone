/* eslint-disable import/order */
import '@/@iconify/icons-bundle'
import App from '@/App.vue'
import pinia from '@/plugins/pinia'
import vuetify from '@/plugins/vuetify'
import { loadFonts } from '@/plugins/webfontloader'
import router from '@/router'
import { setSessionExpiredHandler } from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'
import '@core/scss/template/index.scss'
import '@layouts/styles/index.scss'
import '@styles/styles.scss'
import '@/enterprise.css'

import { createApp } from 'vue'
loadFonts()
// Create vue app
const app = createApp(App)


// Use plugins
app.use(vuetify)
app.use(pinia)
app.use(router)

setSessionExpiredHandler(() => {
  // Login handles its own errors; avoid redirect loops and duplicate navigation.
  if (router.currentRoute.value.path === '/login') return
  useAuthStore(pinia).clearSession()
  router.replace({ path: '/login', query: { reason: 'session-expired' } })
})

// Mount vue app
app.mount('#app')
