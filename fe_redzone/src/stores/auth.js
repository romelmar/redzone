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
        await api.post('/logout')
        this.user = null
      } finally {
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