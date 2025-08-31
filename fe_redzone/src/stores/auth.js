// src/store/auth.js

import axios from 'axios'
import { defineStore } from 'pinia'

axios.defaults.baseURL = 'http://localhost:8000'
axios.defaults.withCredentials = true

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
    loading: false,  // 👈 NEW loading state
  }),
persist: {
  storage: sessionStorage,
  paths: ['token', 'user'],
},
  actions: {
    setAxiosToken(token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    },
    async getUser() {
      // try {
      //   this.loading = true
      //   const response = await axios.get('/api/user')
      //   this.user = response.data
      // } catch (error) {
      //   console.error('Failed to fetch user', error)
      // }

      try {
        this.loading = true
        const response = await axios.get('/api/user')
        this.user = response.data
      } finally {
        this.loading = false
      }
    },
    async login(credentials) {
      try {
        this.loading = true
        const response = await axios.post('/api/login', credentials)
        this.token = response.data.token
        localStorage.setItem('token', this.token)
        this.setAxiosToken(this.token) // 👈 set token immediately
        await this.getUser()
      } finally {
        this.loading = false
      }
    },
    async logout() {

      try {
        this.loading = true
        await axios.post('/api/logout')
        this.token = null
        this.user = null
        localStorage.removeItem('token')
        delete axios.defaults.headers.common['Authorization'] // 👈 clear token
      } finally {
        this.loading = false
      }
    },
    async register(payload) {
      try {
        this.loading = true
        await axios.post('/api/register', payload)
      } finally {
        this.loading = false
      }
    },
  }
})
