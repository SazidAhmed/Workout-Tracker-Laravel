<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ToastViewport from '@/components/ToastViewport.vue'
import { useToastStore } from '@/stores/toast'

const authStore = useAuthStore()
const toastStore = useToastStore()
const router = useRouter()
const isOnline = ref(window.navigator.onLine)

function handleUnauthorized() {
  authStore.clearSession()

  if (router.currentRoute.value.path !== '/login') {
    router.push('/login')
  }
}

function handleForbidden(event) {
  const message = event?.detail?.message || ''
  const needsVerification = /verify|verification|verified email|email/i.test(message)

  if (needsVerification && authStore.isAuthenticated && !authStore.isVerified) {
    if (router.currentRoute.value.path !== '/auth/verify-required') {
      router.push('/auth/verify-required')
    }

    toastStore.error('Please verify your email before continuing.')
    return
  }

  if (message) {
    toastStore.error(message)
  }
}

function handleOnline() {
  isOnline.value = true
  toastStore.success('Connection restored.')
}

function handleOffline() {
  isOnline.value = false
  toastStore.error('You are offline. Actions may fail until connection returns.')
}

onMounted(() => {
  window.addEventListener('fit:unauthorized', handleUnauthorized)
  window.addEventListener('fit:forbidden', handleForbidden)
  window.addEventListener('online', handleOnline)
  window.addEventListener('offline', handleOffline)
})

onBeforeUnmount(() => {
  window.removeEventListener('fit:unauthorized', handleUnauthorized)
  window.removeEventListener('fit:forbidden', handleForbidden)
  window.removeEventListener('online', handleOnline)
  window.removeEventListener('offline', handleOffline)
})
</script>

<template>
  <div>
    <div
      v-if="!isOnline"
      class="sticky top-0 z-[70] border-b border-amber-300 bg-amber-50 px-4 py-2 text-center text-sm text-amber-700"
      role="status"
      aria-live="assertive"
    >
      You are offline. Reconnect to keep syncing workout data.
    </div>
    <RouterView />
    <ToastViewport />
  </div>
</template>
