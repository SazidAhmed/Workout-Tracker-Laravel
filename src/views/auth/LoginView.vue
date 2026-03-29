<script setup>
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { LockKeyhole, Mail } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/lib/http'
import { useToastStore } from '@/stores/toast'

const authStore = useAuthStore()
const toastStore = useToastStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const submitting = ref(false)

async function submit() {
  submitting.value = true

  try {
    await authStore.login({
      email: email.value,
      password: password.value,
    })

    const redirect = route.query.redirect || '/app'
    router.push(String(redirect))
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to sign in right now.'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto grid min-h-[78vh] w-full max-w-5xl items-center gap-8 lg:grid-cols-[1fr_0.9fr]">
    <section>
      <p class="mb-3 inline-flex rounded-full border border-border/70 bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground">
        Workout Tracking Platform
      </p>
      <h1 class="max-w-xl text-4xl font-bold tracking-tight sm:text-5xl">
        Train smarter with programs, live sessions, and progress analytics.
      </h1>
      <p class="mt-4 max-w-lg text-base leading-7 text-muted-foreground">
        Sign in with your invited account to manage clients, assign training blocks,
        and log workouts from your phone in real time.
      </p>
    </section>

    <Card class="border-border/80 bg-card/92 shadow-xl">
      <CardHeader>
        <CardTitle>Sign in</CardTitle>
        <CardDescription>Use your invited account credentials.</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
          <label class="form-label">
            <span class="form-label-text">Email</span>
            <div class="relative">
              <Mail class="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground" />
              <input
                v-model="email"
                type="email"
                required
                class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm"
                placeholder="coach@example.com"
              >
            </div>
          </label>

          <label class="form-label">
            <span class="form-label-text">Password</span>
            <div class="relative">
              <LockKeyhole class="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground" />
              <input
                v-model="password"
                type="password"
                required
                class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm"
                placeholder="Your password"
              >
            </div>
          </label>

          <Button :disabled="submitting" class="w-full" type="submit">
            {{ submitting ? 'Signing in...' : 'Sign in' }}
          </Button>

          <div class="flex items-center justify-between gap-3 text-sm">
            <RouterLink to="/forgot-password" class="text-primary hover:underline">Forgot password?</RouterLink>
            <RouterLink to="/auth/verified" class="text-muted-foreground hover:text-foreground">
              Email verified page
            </RouterLink>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
