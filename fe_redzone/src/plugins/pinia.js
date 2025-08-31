// plugins/pinia.js
import { createPinia } from 'pinia'

const pinia = createPinia()

// Simple persistence plugin
pinia.use(({ store }) => {
  const stored = localStorage.getItem(store.$id)
  if (stored) {
    store.$patch(JSON.parse(stored))
  }

  store.$subscribe((_mutation, state) => {
    localStorage.setItem(store.$id, JSON.stringify(state))
  })
})

export default pinia
