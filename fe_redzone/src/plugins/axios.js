import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

api.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
api.defaults.headers.common.Accept = 'application/json'

// Deletion may be blocked to preserve billing history. Surface the API reason.
api.interceptors.response.use(response => response, error => {
  if (error.config?.method === 'delete') {
    window.alert(error.response?.data?.message || 'Unable to delete this record. Please try again.')
  }
  return Promise.reject(error)
})

export default api
