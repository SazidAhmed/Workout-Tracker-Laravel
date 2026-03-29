<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { api, requestWithRetry } from '@/lib/api'
import { getApiErrorMessage } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useReferenceStore } from '@/stores/reference'
import { useToastStore } from '@/stores/toast'
import LoadingListSkeleton from '@/components/LoadingListSkeleton.vue'

const authStore = useAuthStore()
const referenceStore = useReferenceStore()
const toastStore = useToastStore()

const loading = ref(false)
const submitting = ref(false)
const users = ref([])
const filterRole = ref('')
const error = ref('')
const editableUsers = ref({})
const searchTerm = ref('')
const sortBy = ref('name_asc')
const currentPage = ref(1)
const pageSize = 8

const inviteForm = ref({
  name: '',
  email: '',
  role: authStore.role === 'trainer' ? 'client' : 'client',
  trainer_id: '',
  is_active: true,
})

const isAdmin = computed(() => authStore.role === 'admin')
const pageTitle = computed(() => (isAdmin.value ? 'User Management' : 'Client Management'))

const filteredUsers = computed(() => {
  const needle = searchTerm.value.trim().toLowerCase()
  const list = [...users.value]

  const searched = needle
    ? list.filter((user) => {
      const haystack = `${user.name || ''} ${user.email || ''} ${user.role || ''}`.toLowerCase()
      return haystack.includes(needle)
    })
    : list

  searched.sort((a, b) => {
    if (sortBy.value === 'name_desc') {
      return String(b.name || '').localeCompare(String(a.name || ''))
    }

    if (sortBy.value === 'role') {
      return String(a.role || '').localeCompare(String(b.role || ''))
    }

    return String(a.name || '').localeCompare(String(b.name || ''))
  })

  return searched
})

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredUsers.value.length / pageSize)),
)

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredUsers.value.slice(start, start + pageSize)
})

function resetMessages() {
  error.value = ''
}

async function loadUsers() {
  loading.value = true
  resetMessages()

  try {
    const endpoint = authStore.role === 'trainer' ? '/clients' : '/users'
    const params = authStore.role === 'admin' && filterRole.value
      ? { role: filterRole.value }
      : {}

    const response = await requestWithRetry(() => api.get(endpoint, { params }))
    users.value = response.data.data

    editableUsers.value = users.value.reduce((acc, user) => {
      acc[user.id] = {
        name: user.name,
        phone: user.phone || '',
        address: user.address || '',
        role: user.role,
        trainer_id: user.trainer?.id ? String(user.trainer.id) : '',
        is_active: Boolean(user.is_active),
      }
      return acc
    }, {})
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to load users.')
    toastStore.error(error.value)
  } finally {
    loading.value = false
  }
}

function validateInviteForm() {
  if (!inviteForm.value.name.trim()) {
    return 'Name is required.'
  }

  const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(inviteForm.value.email)
  if (!emailValid) {
    return 'Enter a valid email address.'
  }

  if (isAdmin.value && inviteForm.value.role === 'client' && !inviteForm.value.trainer_id) {
    return 'Assign a trainer for client invitations.'
  }

  return null
}

async function loadReferenceData() {
  if (isAdmin.value) {
    await Promise.all([referenceStore.fetchTrainers(), referenceStore.fetchClients()])
  } else if (authStore.role === 'trainer') {
    await referenceStore.fetchClients()
  }
}

async function inviteUser() {
  const validationError = validateInviteForm()
  if (validationError) {
    error.value = validationError
    toastStore.error(validationError)
    return
  }

  submitting.value = true
  resetMessages()

  try {
    const payload = {
      name: inviteForm.value.name,
      email: inviteForm.value.email,
      role: isAdmin.value ? inviteForm.value.role : 'client',
      is_active: inviteForm.value.is_active,
    }

    if (payload.role === 'client') {
      payload.trainer_id = isAdmin.value
        ? Number(inviteForm.value.trainer_id || 0)
        : undefined
    }

    await api.post('/users', payload)
    toastStore.success('Invitation sent successfully.')

    inviteForm.value = {
      name: '',
      email: '',
      role: isAdmin.value ? 'client' : 'client',
      trainer_id: '',
      is_active: true,
    }

    await loadUsers()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to invite user.')
    toastStore.error(error.value)
  } finally {
    submitting.value = false
  }
}

async function saveUser(userId) {
  resetMessages()

  try {
    const edit = editableUsers.value[userId]
    const payload = {
      name: edit.name,
      phone: edit.phone,
      address: edit.address,
      is_active: edit.is_active,
    }

    if (isAdmin.value) {
      payload.role = edit.role
      payload.trainer_id = edit.role === 'client' && edit.trainer_id
        ? Number(edit.trainer_id)
        : null
    }

    await api.put(`/users/${userId}`, payload)
    toastStore.success('User updated successfully.')
    await loadUsers()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to save user changes.')
    toastStore.error(error.value)
  }
}

onMounted(async () => {
  await loadReferenceData()
  await loadUsers()
})

watch([searchTerm, sortBy], () => {
  currentPage.value = 1
})

watch(totalPages, () => {
  if (currentPage.value > totalPages.value) {
    currentPage.value = totalPages.value
  }
})
</script>

<template>
  <section class="space-y-6">
    <Card class="border-border/80 bg-card/90">
      <CardHeader class="space-y-2">
        <CardTitle class="text-2xl">{{ pageTitle }}</CardTitle>
        <CardDescription>
          Invite new accounts and maintain role/activation assignments.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" @submit.prevent="inviteUser">
          <input
            v-model="inviteForm.name"
            type="text"
            required
            class="form-control"
            placeholder="Full name"
          >
          <input
            v-model="inviteForm.email"
            type="email"
            required
            class="form-control"
            placeholder="Email"
          >

          <select
            v-if="isAdmin"
            v-model="inviteForm.role"
            class="form-control"
          >
            <option value="client">Client</option>
            <option value="trainer">Trainer</option>
            <option value="admin">Admin</option>
          </select>

          <select
            v-if="isAdmin && inviteForm.role === 'client'"
            v-model="inviteForm.trainer_id"
            class="form-control"
            required
          >
            <option disabled value="">Assign trainer</option>
            <option v-for="trainer in referenceStore.trainers" :key="trainer.id" :value="String(trainer.id)">
              {{ trainer.name }}
            </option>
          </select>

          <label class="inline-flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm">
            <input v-model="inviteForm.is_active" type="checkbox">
            Active
          </label>

          <Button :disabled="submitting" type="submit" class="md:col-span-2 xl:col-span-3">
            {{ submitting ? 'Sending invite...' : 'Invite user' }}
          </Button>
        </form>
      </CardContent>
    </Card>

    <Card class="border-border/80 bg-card/90">
      <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <CardTitle>Directory</CardTitle>
          <CardDescription>Inline updates for role and account state.</CardDescription>
        </div>

        <select
          v-if="isAdmin"
          v-model="filterRole"
          class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
          @change="loadUsers"
        >
          <option value="">All roles</option>
          <option value="admin">Admin</option>
          <option value="trainer">Trainer</option>
          <option value="client">Client</option>
        </select>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="grid gap-3 sm:grid-cols-2">
          <input
            v-model="searchTerm"
            class="form-control"
            placeholder="Search name, email, or role"
            aria-label="Search users"
          >
          <select v-model="sortBy" class="form-control" aria-label="Sort users">
            <option value="name_asc">Name A-Z</option>
            <option value="name_desc">Name Z-A</option>
            <option value="role">Role</option>
          </select>
        </div>

        <LoadingListSkeleton v-if="loading" :rows="3" />

        <article
          v-for="user in paginatedUsers"
          :key="user.id"
          class="grid gap-3 rounded-lg border border-border/70 bg-background/70 p-4 lg:grid-cols-[1.2fr_1fr_1fr_auto]"
        >
          <div class="space-y-2">
            <input
              v-model="editableUsers[user.id].name"
              class="form-control-sm w-full"
            >
            <input
              v-model="editableUsers[user.id].phone"
              class="form-control-sm w-full"
              placeholder="Phone"
            >
            <input
              v-model="editableUsers[user.id].address"
              class="form-control-sm w-full"
              placeholder="Address"
            >
            <p class="text-xs text-muted-foreground">{{ user.email }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-xs text-muted-foreground">Role</label>
            <select
              v-model="editableUsers[user.id].role"
              :disabled="!isAdmin"
              class="form-control-sm w-full"
            >
              <option value="admin">Admin</option>
              <option value="trainer">Trainer</option>
              <option value="client">Client</option>
            </select>

            <select
              v-if="isAdmin && editableUsers[user.id].role === 'client'"
              v-model="editableUsers[user.id].trainer_id"
              class="form-control-sm w-full"
            >
              <option value="">Unassigned</option>
              <option v-for="trainer in referenceStore.trainers" :key="trainer.id" :value="String(trainer.id)">
                {{ trainer.name }}
              </option>
            </select>
          </div>

          <div class="flex items-center">
            <label class="inline-flex items-center gap-2 text-sm">
              <input v-model="editableUsers[user.id].is_active" type="checkbox">
              Active account
            </label>
          </div>

          <div class="flex items-start justify-end">
            <Button size="sm" @click="saveUser(user.id)">Save</Button>
          </div>
        </article>

        <div v-if="!loading && filteredUsers.length > 0" class="flex items-center justify-between gap-3 text-sm">
          <p class="text-muted-foreground">Page {{ currentPage }} of {{ totalPages }}</p>
          <div class="flex gap-2">
            <Button size="sm" variant="outline" :disabled="currentPage === 1" @click="currentPage -= 1">Previous</Button>
            <Button size="sm" variant="outline" :disabled="currentPage >= totalPages" @click="currentPage += 1">Next</Button>
          </div>
        </div>

        <p v-if="!loading && filteredUsers.length === 0" class="text-sm text-muted-foreground">
          No users available.
        </p>

        <Button v-if="!loading && error" size="sm" variant="outline" @click="loadUsers">
          Retry loading
        </Button>
      </CardContent>
    </Card>
  </section>
</template>
