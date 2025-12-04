import api from '@/plugins/axios'

export function fetchServiceCredits(params = {}) {
  return api.get('/api/serviceCredits', { params })
}
export function createServiceCredit(data) {
  return api.post('/api/serviceCredits', data)
}
export function updateServiceCredit(id, data) {
  return api.put(`/api/serviceCredits/${id}`, data)
}
export function deleteServiceCredit(id) {
  return api.delete(`/api/serviceCredits/${id}`)
}
export function showServiceCredit(id) {
  return api.get(`/api/serviceCredits/${id}`)
}
