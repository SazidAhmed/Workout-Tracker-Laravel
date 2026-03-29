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
const email = ref('')
const submitting = ref(false)

async function submit() {
  submitting.value = true

  try {
    const response = await authStore.forgotPassword(email.value)
    toastStore.success(response.message)
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to send reset link.'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto grid min-h-[72vh] w-full max-w-xl place-items-center">
    <Card class="w-full border-border/80 bg-card/92 shadow-xl">
      <CardHeader>
        <CardTitle>Forgot Password</CardTitle>
        <CardDescription>
          We will send a reset link to your email.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
          <label class="form-label">
            <span class="form-label-text">Email</span>
            <input
              v-model="email"
              type="email"
              required
              class="form-control w-full"
              placeholder="you@example.com"
            >
          </label>

          <Button :disabled="submitting" class="w-full" type="submit">
            {{ submitting ? 'Sending...' : 'Send reset link' }}
          </Button>

          <RouterLink to="/login" class="inline-block text-sm text-primary hover:underline">
            Back to login
          </RouterLink>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
