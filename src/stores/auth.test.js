import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

const getTokenMock = vi.fn(() => null)
const setTokenMock = vi.fn()
const clearTokenMock = vi.fn()

const getMock = vi.fn()
const postMock = vi.fn()

vi.mock('@/lib/session', () => ({
  getToken: () => getTokenMock(),
  setToken: (...args) => setTokenMock(...args),
  clearToken: () => clearTokenMock(),
}))

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args) => getMock(...args),
    post: (...args) => postMock(...args),
  },
}))

import { useAuthStore } from '@/stores/auth'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    getTokenMock.mockReset()
    setTokenMock.mockReset()
    clearTokenMock.mockReset()
    getMock.mockReset()
    postMock.mockReset()
    getTokenMock.mockReturnValue(null)
  })

  it('initializes as unauthenticated when no token exists', async () => {
    const store = useAuthStore()

    await store.initialize()

    expect(store.initialized).toBe(true)
    expect(store.isAuthenticated).toBe(false)
  })

  it('logs in and stores token and user', async () => {
    const store = useAuthStore()

    postMock.mockResolvedValue({
      data: {
        token: 'abc123',
        user: { id: 1, role: 'trainer', email_verified_at: '2026-01-01T00:00:00Z' },
      },
    })

    await store.login({ email: 'a@b.com', password: 'secret' })

    expect(setTokenMock).toHaveBeenCalledWith('abc123')
    expect(store.isAuthenticated).toBe(true)
    expect(store.role).toBe('trainer')
    expect(store.isVerified).toBe(true)
  })

  it('logs out and clears session token', async () => {
    const store = useAuthStore()

    postMock.mockResolvedValue({
      data: {
        token: 'abc123',
        user: { id: 1, role: 'client', email_verified_at: null },
      },
    })

    await store.login({ email: 'a@b.com', password: 'secret' })

    postMock.mockResolvedValue({ data: { message: 'Logged out' } })
    await store.logout()

    expect(clearTokenMock).toHaveBeenCalled()
    expect(store.isAuthenticated).toBe(false)
  })
})
