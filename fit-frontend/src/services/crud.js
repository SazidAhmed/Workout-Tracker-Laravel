import { api } from '@/lib/api'

export function createProgram(payload) {
  return api.post('/programs', payload)
}

export function updateProgram(programId, payload) {
  return api.put(`/programs/${programId}`, payload)
}

export function deleteProgramDay(programDayId) {
  return api.delete(`/program-days/${programDayId}`)
}

export function createSession(payload) {
  return api.post('/workout-sessions', payload)
}

export function updateSession(sessionId, payload) {
  return api.put(`/workout-sessions/${sessionId}`, payload)
}

export function completeSession(sessionId) {
  return api.post(`/workout-sessions/${sessionId}/complete`)
}

export function createExercise(payload) {
  return api.post('/exercises', payload)
}

export function updateUser(userId, payload) {
  return api.put(`/users/${userId}`, payload)
}
