<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/lib/http'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const authStore = useAuthStore()
const toastStore = useToastStore()

const token = ref(String(route.query.token || ''))
const email = ref(String(route.query.email || ''))
const password = ref('')
const passwordConfirmation = ref('')
const submitting = ref(false)

const isReady = computed(() => Boolean(token.value && email.value))

async function submit() {
  submitting.value = true

  try {
    const response = await authStore.resetPassword({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })

    toastStore.success(response.message)
    password.value = ''
    passwordConfirmation.value = ''
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to reset password.'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto grid min-h-[72vh] w-full max-w-xl place-items-center">
    <Card class="w-full border-border/80 bg-card/92 shadow-xl">
      <CardHeader>
        <CardTitle>Reset Password</CardTitle>
        <CardDescription>
          Set a new password for your account.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
          <p v-if="!isReady" class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-700">
            The reset link is missing token/email query values.
          </p>

          <label class="form-label">
            <span class="form-label-text">Email</span>
            <input
              v-model="email"
              type="email"
              required
              class="form-control w-full"
            >
          </label>

          <label class="form-label">
            <span class="form-label-text">Token</span>
            <input
              v-model="token"
              type="text"
              required
              class="form-control w-full"
            >
          </label>

          <label class="form-label">
            <span class="form-label-text">New password</span>
            <input
              v-model="password"
              type="password"
              required
              minlength="8"
              class="form-control w-full"
            >
          </label>

          <label class="form-label">
            <span class="form-label-text">Confirm password</span>
            <input
              v-model="passwordConfirmation"
              type="password"
              required
              minlength="8"
              class="form-control w-full"
            >
          </label>

          <Button :disabled="submitting || !isReady" class="w-full" type="submit">
            {{ submitting ? 'Saving...' : 'Reset password' }}
          </Button>

          <RouterLink to="/login" class="inline-block text-sm text-primary hover:underline">
            Back to login
          </RouterLink>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
