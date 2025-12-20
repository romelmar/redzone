import api from '@/plugins/axios'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
    loading: false,
  }),

  persist: {
    storage: sessionStorage,
    paths: ['token', 'user'],
  },

  actions: {
    setAxiosToken(token) {
      api.defaults.headers.common['Authorization'] = `Bearer ${token}`
    },

    async getUser() {
      try {
        this.loading = true
        const response = await api.get('/api/user')
        this.user = response.data
      } finally {
        this.loading = false
      }
    },

    async login(credentials) {
      try {
        this.loading = true
        const response = await api.post('/api/auth/login', credentials)
        this.token = response.data.token
        localStorage.setItem('token', this.token)
        this.setAxiosToken(this.token)
        await this.getUser()
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        this.loading = true
        await api.post('/api/logout')
        this.token = null
        this.user = null
        localStorage.removeItem('token')
        delete api.defaults.headers.common['Authorization']
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      try {
        this.loading = true
        await api.post('/api/register', payload)
      } finally {
        this.loading = false
      }
    },
  }
})
