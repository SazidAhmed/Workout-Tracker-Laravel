import { api } from '@/lib/api'

export function listNotifications(params = {}) {
  return api.get('/notifications', { params })
}

export function markNotificationRead(notificationId) {
  return api.post(`/notifications/${notificationId}/read`)
}

export function markAllNotificationsRead() {
  return api.post('/notifications/read-all')
}
