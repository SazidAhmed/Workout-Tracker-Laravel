import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '@/services/notifications'

export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref([])
  const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    unread_count: 0,
  })
  const loading = ref(false)
  const initialized = ref(false)

  const unreadCount = computed(() => Number(meta.value.unread_count || 0))

  function clear() {
    items.value = []
    meta.value = {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
      unread_count: 0,
    }
    initialized.value = false
  }

  function applyResponse(payload) {
    items.value = payload.data || []
    meta.value = {
      ...meta.value,
      ...(payload.meta || {}),
    }
    initialized.value = true
  }

  async function fetchNotifications(params = {}, options = {}) {
    if (!options.silent) {
      loading.value = true
    }

    try {
      const response = await listNotifications(params)
      applyResponse(response.data)
      return response.data
    } finally {
      loading.value = false
    }
  }

  async function refreshUnreadCount() {
    const response = await listNotifications({ page: 1, per_page: 1 })
    meta.value = {
      ...meta.value,
      ...(response.data.meta || {}),
    }
  }

  async function markAsRead(notificationId) {
    const response = await markNotificationRead(notificationId)

    items.value = items.value.map((item) =>
      item.id === notificationId
        ? { ...item, read_at: response.data.data?.read_at || new Date().toISOString() }
        : item,
    )

    meta.value = {
      ...meta.value,
      unread_count: response.data.meta?.unread_count ?? meta.value.unread_count,
    }

    return response.data
  }

  async function markAllAsRead() {
    const response = await markAllNotificationsRead()
    const readAt = new Date().toISOString()

    items.value = items.value.map((item) => ({ ...item, read_at: item.read_at || readAt }))
    meta.value = {
      ...meta.value,
      unread_count: response.data.meta?.unread_count ?? 0,
    }

    return response.data
  }

  return {
    items,
    meta,
    loading,
    initialized,
    unreadCount,
    clear,
    fetchNotifications,
    refreshUnreadCount,
    markAsRead,
    markAllAsRead,
  }
})
