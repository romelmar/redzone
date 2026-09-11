import { defineStore } from 'pinia'
import api from '@/plugins/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loading: false,
  }),

  persist: {
    storage: sessionStorage,
    paths: ['user'],
  },

  actions: {
    clearSession() {
      this.user = null
      sessionStorage.removeItem('auth')
      localStorage.removeItem('auth')
    },
    async getUser() {
      try {
        this.loading = true
        const response = await api.get('/api/user')
        this.user = response.data
      } catch (error) {
        this.user = null
        throw error
      } finally {
        this.loading = false
      }
    },

    async login(credentials) {
      try {
        this.loading = true

        await api.get('/sanctum/csrf-cookie')
        await api.post('/login', credentials)

        await this.getUser()
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        this.loading = true
        try {
          await api.post('/logout', {}, { timeout: 15000 })
        } catch (error) {
          if (error.response?.status === 419) {
            await api.get('/sanctum/csrf-cookie', { timeout: 15000 })
            await api.post('/logout', {}, { timeout: 15000 })
          } else {
            throw error
          }
        }
      } catch (error) {
        // A 401 means the server session is already signed out.
        if (error.response?.status !== 401) throw error
      } finally {
        this.clearSession()
        this.loading = false
      }
    },

    async register(payload) {
      try {
        this.loading = true
        await api.get('/sanctum/csrf-cookie')
        await api.post('/register', payload)
      } finally {
        this.loading = false
      }
    },
  },
})
