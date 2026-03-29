import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api'
import { clearToken, getToken, setToken } from '@/lib/session'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(getToken())
  const initialized = ref(false)
  const loading = ref(false)

  const isAuthenticated = computed(() => Boolean(user.value && token.value))
  const isVerified = computed(() => Boolean(user.value?.email_verified_at))
  const role = computed(() => user.value?.role ?? null)

  function clearSession() {
    user.value = null
    token.value = null
    clearToken()
  }

  async function initialize() {
    if (initialized.value) {
      return
    }

    if (!token.value) {
      initialized.value = true
      return
    }

    try {
      const response = await api.get('/auth/me')
      user.value = response.data.user
    } catch {
      clearSession()
    } finally {
      initialized.value = true
    }
  }

  async function login(payload) {
    loading.value = true

    try {
      const response = await api.post('/auth/login', payload)
      token.value = response.data.token
      setToken(response.data.token)
      user.value = response.data.user
      return response.data
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      if (token.value) {
        await api.post('/auth/logout')
      }
    } finally {
      clearSession()
    }
  }

  async function forgotPassword(email) {
    const response = await api.post('/auth/forgot-password', { email })
    return response.data
  }

  async function resetPassword(payload) {
    const response = await api.post('/auth/reset-password', payload)
    return response.data
  }

  async function fetchInvitation(tokenValue) {
    const response = await api.get(`/auth/invitations/${tokenValue}`)
    return response.data
  }

  async function activateInvitation(tokenValue, payload) {
    const response = await api.post(`/auth/invitations/${tokenValue}/activate`, payload)
    return response.data
  }

  async function resendVerification() {
    const response = await api.post('/auth/email/verification-notification')
    return response.data
  }

  return {
    user,
    token,
    initialized,
    loading,
    isAuthenticated,
    isVerified,
    role,
    clearSession,
    initialize,
    login,
    logout,
    forgotPassword,
    resetPassword,
    fetchInvitation,
    activateInvitation,
    resendVerification,
  }
})
