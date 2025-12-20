import axios from 'axios'

const api = axios.create({
  baseURL: 'https://api.rosnel-partnership.com',
  withCredentials: false, // 🔥 forces no credentials
})

export default api
