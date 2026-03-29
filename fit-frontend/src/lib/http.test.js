import { describe, expect, it } from 'vitest'
import { getApiErrorMessage } from '@/lib/http'

describe('getApiErrorMessage', () => {
  it('returns backend message when present', () => {
    const message = getApiErrorMessage({
      response: {
        data: {
          message: 'Forbidden.',
        },
      },
    })

    expect(message).toBe('Forbidden.')
  })

  it('returns first validation error when message is missing', () => {
    const message = getApiErrorMessage({
      response: {
        data: {
          errors: {
            email: ['Email is required.'],
          },
        },
      },
    })

    expect(message).toBe('Email is required.')
  })

  it('falls back to explicit fallback text', () => {
    const message = getApiErrorMessage({}, 'Fallback text')

    expect(message).toBe('Fallback text')
  })
})
