import api from '@/plugins/axios'

export function fetchSubscribers(params = {}) {
  return api.get('/api/subscribers', { params })
}

export function createSubscriber(data) {
  return api.post('/api/subscribers', data)
}

export function updateSubscriber(id, data) {
  return api.put(`/api/subscribers/${id}`, data)
}

export function deleteSubscriber(id) {
  return api.delete(`/api/subscribers/${id}`)
}

export function showSubscriber(id) {
  return api.get(`/api/subscribers/${id}`)
}
