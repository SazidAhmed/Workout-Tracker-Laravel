<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/lib/http'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toastStore = useToastStore()

const token = String(route.params.token || '')
const loadingInvitation = ref(true)
const invitation = ref(null)
const invitationUnavailable = ref(false)
const form = ref({
  name: '',
  password: '',
  password_confirmation: '',
})
const submitting = ref(false)

onMounted(async () => {
  try {
    const response = await authStore.fetchInvitation(token)
    invitation.value = response.user
    form.value.name = response.user.name
  } catch (e) {
    invitationUnavailable.value = true
    toastStore.error(getApiErrorMessage(e, 'Invitation is invalid or expired.'))
  } finally {
    loadingInvitation.value = false
  }
})

async function submit() {
  submitting.value = true

  try {
    await authStore.activateInvitation(token, form.value)
    toastStore.success('Invitation activated. Please sign in.')
    router.push('/login')
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Could not activate invitation.'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto grid min-h-[72vh] w-full max-w-xl place-items-center">
    <Card class="w-full border-border/80 bg-card/92 shadow-xl">
      <CardHeader>
        <CardTitle>Activate Your Account</CardTitle>
        <CardDescription>
          Finish account setup and verify your email afterward.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div v-if="loadingInvitation" class="text-sm text-muted-foreground">Loading invitation...</div>

        <template v-else>
          <div v-if="invitation" class="mb-4 space-y-2 rounded-lg border border-border/70 bg-muted/30 p-3 text-sm">
            <p><span class="form-label-text">Email:</span> {{ invitation.email }}</p>
            <p class="flex items-center gap-2">
              <span class="form-label-text">Role:</span>
              <Badge variant="outline">{{ invitation.role }}</Badge>
            </p>
          </div>

          <form v-if="invitation" class="space-y-4" @submit.prevent="submit">
            <label class="form-label">
              <span class="form-label-text">Name</span>
              <input
                v-model="form.name"
                type="text"
                required
                class="form-control w-full"
              >
            </label>

            <label class="form-label">
              <span class="form-label-text">Password</span>
              <input
                v-model="form.password"
                type="password"
                minlength="8"
                required
                class="form-control w-full"
              >
            </label>

            <label class="form-label">
              <span class="form-label-text">Confirm password</span>
              <input
                v-model="form.password_confirmation"
                type="password"
                minlength="8"
                required
                class="form-control w-full"
              >
            </label>

            <Button :disabled="submitting" class="w-full" type="submit">
              {{ submitting ? 'Activating...' : 'Activate account' }}
            </Button>
          </form>

          <p v-else-if="invitationUnavailable" class="text-sm text-muted-foreground">
            This invitation is no longer available.
          </p>
        </template>

        <RouterLink to="/login" class="mt-4 inline-block text-sm text-primary hover:underline">
          Back to login
        </RouterLink>
      </CardContent>
    </Card>
  </div>
</template>
