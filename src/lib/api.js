import axios from 'axios'
import { clearToken, getToken } from '@/lib/session'

const configuredBase = import.meta.env.VITE_API_BASE_URL
const baseURL = configuredBase || '/api'

export const api = axios.create({
  baseURL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

function wait(ms) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms)
  })
}

export async function requestWithRetry(requestFactory, options = {}) {
  const retries = options.retries ?? 1
  const delayMs = options.delayMs ?? 300

  let lastError = null

  for (let attempt = 0; attempt <= retries; attempt += 1) {
    try {
      return await requestFactory()
    } catch (error) {
      lastError = error
      const status = error?.response?.status
      const retryable = !status || status >= 500

      if (!retryable || attempt === retries) {
        throw error
      }

      await wait(delayMs * (attempt + 1))
    }
  }

  throw lastError
}

api.interceptors.request.use((config) => {
  const token = getToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401) {
      clearToken()
      window.dispatchEvent(new CustomEvent('fit:unauthorized'))
    }

    if (error?.response?.status === 403) {
      window.dispatchEvent(new CustomEvent('fit:forbidden', {
        detail: {
          message: error?.response?.data?.message || '',
        },
      }))
    }

    return Promise.reject(error)
  },
)
