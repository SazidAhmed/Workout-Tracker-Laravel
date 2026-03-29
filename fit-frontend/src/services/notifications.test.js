import { describe, expect, it, vi } from 'vitest'

const getMock = vi.fn()
const postMock = vi.fn()

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args) => getMock(...args),
    post: (...args) => postMock(...args),
  },
}))

import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '@/services/notifications'

describe('notifications service endpoint contracts', () => {
  it('targets expected notifications endpoints', async () => {
    const params = { page: 2, per_page: 10 }

    await listNotifications(params)
    await markNotificationRead('abc-123')
    await markAllNotificationsRead()

    expect(getMock).toHaveBeenCalledWith('/notifications', { params })
    expect(postMock).toHaveBeenCalledWith('/notifications/abc-123/read')
    expect(postMock).toHaveBeenCalledWith('/notifications/read-all')
  })
})
