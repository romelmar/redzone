import axios from 'axios'
import { defineStore } from 'pinia'

axios.defaults.baseURL = 'http://localhost:8000'
axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true

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
        const response = await axios.get('/api/user')
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

        await axios.get('/sanctum/csrf-cookie')
        await axios.post('/login', credentials)

        await this.getUser()
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        this.loading = true
        await axios.post('/logout')
        this.user = null
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      try {
        this.loading = true
        await axios.get('/sanctum/csrf-cookie')
        await axios.post('/register', payload)
      } finally {
        this.loading = false
      }
    },
  },
})