<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { api, requestWithRetry } from '@/lib/api'
import { getApiErrorMessage } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import LoadingListSkeleton from '@/components/LoadingListSkeleton.vue'

const authStore = useAuthStore()
const toastStore = useToastStore()

const loading = ref(false)
const saving = ref(false)
const exercises = ref([])
const editableExercises = ref({})
const error = ref('')
const searchTerm = ref('')
const sortBy = ref('name_asc')
const currentPage = ref(1)
const pageSize = 10

const createForm = ref({
  name: '',
  muscle_group: '',
  equipment: '',
  movement_type: '',
  default_unit: 'kg',
  visibility: 'shared',
  is_active: true,
})

const isAdmin = computed(() => authStore.role === 'admin')

const filteredExercises = computed(() => {
  const needle = searchTerm.value.trim().toLowerCase()
  const list = [...exercises.value]

  const searched = needle
    ? list.filter((exercise) => {
      const haystack = `${exercise.name || ''} ${exercise.muscle_group || ''} ${exercise.equipment || ''}`.toLowerCase()
      return haystack.includes(needle)
    })
    : list

  searched.sort((a, b) => {
    if (sortBy.value === 'muscle_group') {
      return String(a.muscle_group || '').localeCompare(String(b.muscle_group || ''))
    }

    if (sortBy.value === 'name_desc') {
      return String(b.name || '').localeCompare(String(a.name || ''))
    }

    return String(a.name || '').localeCompare(String(b.name || ''))
  })

  return searched
})

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredExercises.value.length / pageSize)),
)

const paginatedExercises = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredExercises.value.slice(start, start + pageSize)
})

function resetMessages() {
  error.value = ''
}

async function loadExercises() {
  loading.value = true
  resetMessages()

  try {
    const response = await requestWithRetry(() => api.get('/exercises'))
    exercises.value = response.data.data

    editableExercises.value = exercises.value.reduce((acc, exercise) => {
      acc[exercise.id] = {
        name: exercise.name,
        muscle_group: exercise.muscle_group || '',
        equipment: exercise.equipment || '',
        movement_type: exercise.movement_type || '',
        default_unit: exercise.default_unit || 'kg',
        visibility: exercise.visibility,
        is_active: Boolean(exercise.is_active),
      }
      return acc
    }, {})
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to load exercises.')
    toastStore.error(error.value)
  } finally {
    loading.value = false
  }
}

function validateCreateExercise() {
  if (!createForm.value.name.trim()) {
    return 'Exercise name is required.'
  }

  if (!createForm.value.default_unit.trim()) {
    return 'Default unit is required.'
  }

  return null
}

async function createExercise() {
  const validationError = validateCreateExercise()
  if (validationError) {
    error.value = validationError
    toastStore.error(validationError)
    return
  }

  saving.value = true
  resetMessages()

  try {
    const payload = {
      ...createForm.value,
      visibility: isAdmin.value ? createForm.value.visibility : undefined,
    }

    await api.post('/exercises', payload)
    toastStore.success('Exercise created.')
    createForm.value = {
      name: '',
      muscle_group: '',
      equipment: '',
      movement_type: '',
      default_unit: 'kg',
      visibility: 'shared',
      is_active: true,
    }
    await loadExercises()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to create exercise.')
    toastStore.error(error.value)
  } finally {
    saving.value = false
  }
}

async function saveExercise(exerciseId) {
  resetMessages()

  try {
    await api.put(`/exercises/${exerciseId}`, editableExercises.value[exerciseId])
    toastStore.success('Exercise updated.')
    await loadExercises()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to save exercise.')
    toastStore.error(error.value)
  }
}

async function deleteExercise(exerciseId) {
  resetMessages()

  try {
    await api.delete(`/exercises/${exerciseId}`)
    toastStore.success('Exercise deleted.')
    await loadExercises()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to delete exercise.')
    toastStore.error(error.value)
  }
}

onMounted(loadExercises)

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
      <CardHeader>
        <CardTitle class="text-2xl">Exercise Catalog</CardTitle>
        <CardDescription>
          Manage shared and trainer-specific movements for program creation.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" @submit.prevent="createExercise">
          <input
            v-model="createForm.name"
            required
            class="form-control"
            placeholder="Exercise name"
          >
          <input
            v-model="createForm.muscle_group"
            class="form-control"
            placeholder="Muscle group"
          >
          <input
            v-model="createForm.equipment"
            class="form-control"
            placeholder="Equipment"
          >
          <input
            v-model="createForm.movement_type"
            class="form-control"
            placeholder="Movement type"
          >
          <input
            v-model="createForm.default_unit"
            class="form-control"
            placeholder="Default unit"
          >

          <select
            v-if="isAdmin"
            v-model="createForm.visibility"
            class="form-control"
          >
            <option value="shared">Shared</option>
            <option value="trainer">Trainer owned</option>
          </select>

          <label class="inline-flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm">
            <input v-model="createForm.is_active" type="checkbox">
            Active
          </label>

          <Button :disabled="saving" type="submit" class="md:col-span-2 xl:col-span-3">
            {{ saving ? 'Creating...' : 'Create exercise' }}
          </Button>
        </form>
      </CardContent>
    </Card>

    <Card class="border-border/80 bg-card/90">
      <CardHeader>
        <CardTitle>Exercises</CardTitle>
        <CardDescription>Inline edits for library cleanup and maintenance.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="grid gap-3 sm:grid-cols-2">
          <input
            v-model="searchTerm"
            class="form-control"
            placeholder="Search exercises"
            aria-label="Search exercises"
          >
          <select v-model="sortBy" class="form-control" aria-label="Sort exercises">
            <option value="name_asc">Name A-Z</option>
            <option value="name_desc">Name Z-A</option>
            <option value="muscle_group">Muscle group</option>
          </select>
        </div>

        <LoadingListSkeleton v-if="loading" :rows="3" />

        <article
          v-for="exercise in paginatedExercises"
          :key="exercise.id"
          class="space-y-3 rounded-lg border border-border/70 bg-background/70 p-4"
        >
          <div class="flex flex-wrap items-center gap-2">
            <Badge variant="outline">{{ exercise.visibility }}</Badge>
            <Badge variant="secondary">{{ exercise.default_unit || 'kg' }}</Badge>
          </div>

          <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
            <input v-model="editableExercises[exercise.id].name" class="form-control-sm">
            <input v-model="editableExercises[exercise.id].muscle_group" class="form-control-sm" placeholder="Muscle group">
            <input v-model="editableExercises[exercise.id].equipment" class="form-control-sm" placeholder="Equipment">
            <input v-model="editableExercises[exercise.id].movement_type" class="form-control-sm" placeholder="Movement type">
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm">
              <input v-model="editableExercises[exercise.id].is_active" type="checkbox">
              Active
            </label>

            <Button size="sm" @click="saveExercise(exercise.id)">Save</Button>
            <Button size="sm" variant="destructive" @click="deleteExercise(exercise.id)">Delete</Button>
          </div>
        </article>

        <div v-if="!loading && filteredExercises.length > 0" class="flex items-center justify-between gap-3 text-sm">
          <p class="text-muted-foreground">Page {{ currentPage }} of {{ totalPages }}</p>
          <div class="flex gap-2">
            <Button size="sm" variant="outline" :disabled="currentPage === 1" @click="currentPage -= 1">Previous</Button>
            <Button size="sm" variant="outline" :disabled="currentPage >= totalPages" @click="currentPage += 1">Next</Button>
          </div>
        </div>

        <p v-if="!loading && filteredExercises.length === 0" class="text-sm text-muted-foreground">
          No exercises in catalog.
        </p>

        <Button v-if="!loading && error" size="sm" variant="outline" @click="loadExercises">
          Retry loading
        </Button>
      </CardContent>
    </Card>
  </section>
</template>
