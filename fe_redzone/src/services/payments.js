import api from '@/plugins/axios'

export function fetchPayments(params = {}) {
  return api.get('/api/payments', { params })
}
export function createPayment(data) {
  return api.post('/api/payments', data)
}
export function updatePayment(id, data) {
  return api.put(`/api/payments/${id}`, data)
}
export function deletePayment(id) {
  return api.delete(`/api/payments/${id}`)
}
export function showPayment(id) {
  return api.get(`/api/payments/${id}`)
}
