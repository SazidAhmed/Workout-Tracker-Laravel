<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/lib/http'
import { useToastStore } from '@/stores/toast'

const authStore = useAuthStore()
const router = useRouter()
const toastStore = useToastStore()

const sending = ref(false)

async function resendVerification() {
  sending.value = true

  try {
    const response = await authStore.resendVerification()
    toastStore.success(response.message)
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to resend verification email.'))
  } finally {
    sending.value = false
  }
}

async function signOut() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="mx-auto grid min-h-[72vh] w-full max-w-xl place-items-center">
    <Card class="w-full border-border/80 bg-card/92 shadow-xl">
      <CardHeader>
        <CardTitle>Verify Your Email</CardTitle>
        <CardDescription>
          Your account is active, but API access is locked until email verification is complete.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <p class="text-sm text-muted-foreground">
          Check your inbox for the verification email, then return and continue.
        </p>

        <div class="flex flex-wrap gap-3">
          <Button :disabled="sending" type="button" @click="resendVerification">
            {{ sending ? 'Sending...' : 'Resend verification email' }}
          </Button>
          <Button variant="outline" type="button" @click="signOut">
            Sign out
          </Button>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
