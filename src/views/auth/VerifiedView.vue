<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/lib/http'
import { useToastStore } from '@/stores/toast'

const authStore = useAuthStore()
const toastStore = useToastStore()
const sending = ref(false)
const message = ref('Your email is verified. You can continue to sign in.')

async function resend() {
  sending.value = true

  try {
    const response = await authStore.resendVerification()
    toastStore.success(response.message)
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Could not resend verification email.'))
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="mx-auto grid min-h-[72vh] w-full max-w-xl place-items-center">
    <Card class="w-full border-border/80 bg-card/92 shadow-xl">
      <CardHeader>
        <CardTitle>Email Status</CardTitle>
        <CardDescription>
          Confirmation and verification handoff screen.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <p class="text-sm text-muted-foreground">
          {{ message }}
        </p>

        <div class="flex flex-wrap gap-3">
          <RouterLink to="/login">
            <Button type="button">Go to login</Button>
          </RouterLink>
          <Button
            type="button"
            variant="outline"
            :disabled="sending || !authStore.isAuthenticated"
            @click="resend"
          >
            {{ sending ? 'Sending...' : 'Resend verification email' }}
          </Button>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
