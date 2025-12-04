import api from '@/plugins/axios'

export function fetchPlans(params = {}) {
  return api.get('/api/plans', { params })
}
export function createPlan(data) {
  return api.post('/api/plans', data)
}
export function updatePlan(id, data) {
  return api.put(`/api/plans/${id}`, data)
}
export function deletePlan(id) {
  return api.delete(`/api/plans/${id}`)
}
export function showPlan(id) {
  return api.get(`/api/plans/${id}`)
}
