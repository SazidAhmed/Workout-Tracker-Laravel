export function getApiErrorMessage(error, fallback = 'Something went wrong. Please try again.') {
  if (error?.response?.data?.message) {
    return error.response.data.message
  }

  const firstErrorBag = error?.response?.data?.errors
    ? Object.values(error.response.data.errors)[0]
    : null

  if (Array.isArray(firstErrorBag) && firstErrorBag.length > 0) {
    return firstErrorBag[0]
  }

  if (error?.message) {
    return error.message
  }

  return fallback
}
