import api from '@/plugins/axios'

export function fetchSubscriptions(params = {}) {
  return api.get('/api/subscriptions', { params })
}
export function createSubscription(data) {
  return api.post('/api/subscriptions', data)
}
export function updateSubscription(id, data) {
  return api.put(`/api/subscriptions/${id}`, data)
}
export function deleteSubscription(id) {
  return api.delete(`/api/subscriptions/${id}`)
}
export function showSubscription(id) {
  return api.get(`/api/subscriptions/${id}`)
}

export function activateSubscription(id) {
  return api.post(`/api/subscriptions/${id}/activate`);
}

export function deactivateSubscription(id) {
  return api.post(`/api/subscriptions/${id}/deactivate`);
}
