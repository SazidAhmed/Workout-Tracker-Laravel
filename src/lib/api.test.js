import { describe, expect, it, vi } from 'vitest'
import { requestWithRetry } from '@/lib/api'

describe('requestWithRetry', () => {
  it('retries once for retryable failures', async () => {
    const fn = vi.fn()
      .mockRejectedValueOnce({ response: { status: 500 } })
      .mockResolvedValueOnce({ data: { ok: true } })

    const response = await requestWithRetry(fn, { retries: 1, delayMs: 1 })

    expect(fn).toHaveBeenCalledTimes(2)
    expect(response.data.ok).toBe(true)
  })

  it('does not retry non-retryable status', async () => {
    const fn = vi.fn().mockRejectedValue({ response: { status: 403 } })

    await expect(requestWithRetry(fn, { retries: 2, delayMs: 1 })).rejects.toEqual({
      response: { status: 403 },
    })

    expect(fn).toHaveBeenCalledTimes(1)
  })
})
