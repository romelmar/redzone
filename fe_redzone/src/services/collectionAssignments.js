import api from "@/plugins/axios"

export function fetchCollectionAssignments(params = {}) {
  return api.get("/api/collection-assignments", { params })
}

export function createCollectionAssignment(payload) {
  return api.post("/api/collection-assignments", payload)
}

export function deleteCollectionAssignment(id) {
  return api.delete(`/api/collection-assignments/${id}`)
}

export function bulkReassignCollectionAssignments(payload) {
  return api.post("/api/collection-assignments/bulk-reassign", payload)
}

export function bulkDeleteCollectionAssignments(payload) {
  return api.post("/api/collection-assignments/bulk-delete", payload)
}

export function assignCollectorFromCollectionSheet(payload) {
  return api.post('/api/collection-sheet/assign-collector', payload)
}

export function removeCollectorAssignment(id) {
  return api.delete(`/api/collection-assignments/${id}`)
}