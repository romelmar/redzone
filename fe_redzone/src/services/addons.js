import api from '@/plugins/axios'

export function fetchAddons(params = {}) {
  return api.get('/api/addons', { params })
}
export function createAddon(data) {
  return api.post('/api/addons', data)
}
export function updateAddon(id, data) {
  return api.put(`/api/addons/${id}`, data)
}
export function deleteAddon(id) {
  return api.delete(`/api/addons/${id}`)
}
export function showAddon(id) {
  return api.get(`/api/addons/${id}`)
}
