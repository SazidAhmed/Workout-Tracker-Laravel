export function resolveRouteDecision({
  to,
  isAuthenticated,
  isVerified,
  role,
}) {
  const matched = to.matched || []
  const requiresAuth = matched.some((record) => Boolean(record.meta?.requiresAuth))
  const guestOnly = matched.some((record) => Boolean(record.meta?.guestOnly))
  const allowUnverified = matched.some((record) => Boolean(record.meta?.allowUnverified))

  if (guestOnly && isAuthenticated) {
    return isVerified ? '/app' : '/auth/verify-required'
  }

  if (requiresAuth && !isAuthenticated) {
    return {
      path: '/login',
      query: { redirect: to.fullPath },
    }
  }

  if (requiresAuth && isAuthenticated && !isVerified && !allowUnverified) {
    return '/auth/verify-required'
  }

  if (to.name === 'verify-required' && isAuthenticated && isVerified) {
    return '/app'
  }

  const roleRestrictedRecord = [...matched].reverse().find((record) =>
    Array.isArray(record.meta?.roles),
  )
  const allowedRoles = roleRestrictedRecord?.meta?.roles || null

  if (allowedRoles && !allowedRoles.includes(role)) {
    return '/app'
  }

  return true
}
