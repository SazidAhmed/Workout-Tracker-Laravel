import { describe, expect, it } from 'vitest'
import { resolveRouteDecision } from '@/router/navigation'

function makeTo({
  name = 'dashboard',
  fullPath = '/app',
  matched = [],
} = {}) {
  return {
    name,
    fullPath,
    matched,
  }
}

describe('resolveRouteDecision', () => {
  it('redirects unauthenticated users to login for protected routes', () => {
    const to = makeTo({
      fullPath: '/app/programs',
      matched: [{ meta: { requiresAuth: true } }],
    })

    const decision = resolveRouteDecision({
      to,
      isAuthenticated: false,
      isVerified: false,
      role: null,
    })

    expect(decision).toEqual({
      path: '/login',
      query: { redirect: '/app/programs' },
    })
  })

  it('redirects authenticated unverified users to verify-required page', () => {
    const to = makeTo({
      matched: [{ meta: { requiresAuth: true } }],
    })

    const decision = resolveRouteDecision({
      to,
      isAuthenticated: true,
      isVerified: false,
      role: 'trainer',
    })

    expect(decision).toBe('/auth/verify-required')
  })

  it('blocks route when role is not allowed', () => {
    const to = makeTo({
      matched: [{ meta: { requiresAuth: true, roles: ['admin'] } }],
    })

    const decision = resolveRouteDecision({
      to,
      isAuthenticated: true,
      isVerified: true,
      role: 'trainer',
    })

    expect(decision).toBe('/app')
  })

  it('allows valid verified route access', () => {
    const to = makeTo({
      matched: [{ meta: { requiresAuth: true, roles: ['trainer'] } }],
    })

    const decision = resolveRouteDecision({
      to,
      isAuthenticated: true,
      isVerified: true,
      role: 'trainer',
    })

    expect(decision).toBe(true)
  })
})
