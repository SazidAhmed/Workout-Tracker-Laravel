<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
const route = useRoute()
const router = useRouter()

const selectedClientId = ref('')
const loading = ref(false)
const saving = ref(false)
const updating = ref(false)
const completingSessionId = ref(null)
const editingSessionId = ref(null)
const sessions = ref([])
const error = ref('')
const searchTerm = ref('')
const sortBy = ref('started_desc')
const currentPage = ref(1)
const pageSize = 8
const pendingEditSessionId = ref(route.query.edit_session ? Number(route.query.edit_session) : null)

const form = ref({
  client_id: '',
  program_day_id: '',
  title: '',
  notes: '',
  exercises: [
    {
      exercise_id: '',
      exercise_name: '',
      notes: '',
      sets: [
        {
          is_warmup: false,
          planned_reps_min: 8,
          planned_reps_max: 12,
          planned_weight: '',
          actual_reps: '',
          actual_weight: '',
          notes: '',
        },
      ],
    },
  ],
})

const editForm = ref({
  title: '',
  notes: '',
  exercises: [],
})

const canSelectClient = computed(() => ['admin', 'trainer'].includes(authStore.role))

const filteredSessions = computed(() => {
  const needle = searchTerm.value.trim().toLowerCase()
  const list = [...sessions.value]

  if (needle) {
    list.splice(0, list.length, ...list.filter((session) => {
      const haystack = `${session.title || ''} ${session.status || ''} ${session.client?.name || ''}`.toLowerCase()
      return haystack.includes(needle)
    }))
  }

  list.sort((a, b) => {
    if (sortBy.value === 'started_asc') {
      return new Date(a.started_at) - new Date(b.started_at)
    }

    if (sortBy.value === 'status') {
      return String(a.status).localeCompare(String(b.status))
    }

    return new Date(b.started_at) - new Date(a.started_at)
  })

  return list
})

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredSessions.value.length / pageSize)),
)

const paginatedSessions = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredSessions.value.slice(start, start + pageSize)
})

function resetMessages() {
  error.value = ''
}

function validateCreateSession() {
  if (!form.value.program_day_id && normalizeExercisePayload().length === 0) {
    return 'Add at least one exercise or choose a program day ID.'
  }

  for (const exercise of form.value.exercises) {
    if ((exercise.exercise_id || exercise.exercise_name) && (!exercise.sets || exercise.sets.length === 0)) {
      return 'Each exercise must include at least one set.'
    }
  }

  return null
}

function addExercise() {
  form.value.exercises.push({
    exercise_id: '',
    exercise_name: '',
    notes: '',
    sets: [
      {
        is_warmup: false,
        planned_reps_min: 8,
        planned_reps_max: 12,
        planned_weight: '',
        actual_reps: '',
        actual_weight: '',
        notes: '',
      },
    ],
  })
}

function removeExercise(index) {
  form.value.exercises.splice(index, 1)
}

function addSet(exerciseIndex) {
  form.value.exercises[exerciseIndex].sets.push({
    is_warmup: false,
    planned_reps_min: 8,
    planned_reps_max: 12,
    planned_weight: '',
    actual_reps: '',
    actual_weight: '',
    notes: '',
  })
}

function removeSet(exerciseIndex, setIndex) {
  form.value.exercises[exerciseIndex].sets.splice(setIndex, 1)
}

function normalizeExercisePayload() {
  return form.value.exercises
    .filter((exercise) => exercise.exercise_id || exercise.exercise_name)
    .map((exercise) => {
      const option = referenceStore.exercises.find(
        (item) => String(item.id) === String(exercise.exercise_id),
      )

      return {
        exercise_id: exercise.exercise_id ? Number(exercise.exercise_id) : null,
        exercise_name: exercise.exercise_name || option?.name,
        notes: exercise.notes || null,
        sets: exercise.sets.map((set) => ({
          is_warmup: Boolean(set.is_warmup),
          planned_reps_min: set.planned_reps_min ? Number(set.planned_reps_min) : null,
          planned_reps_max: set.planned_reps_max ? Number(set.planned_reps_max) : null,
          planned_weight: set.planned_weight ? Number(set.planned_weight) : null,
          actual_reps: set.actual_reps ? Number(set.actual_reps) : null,
          actual_weight: set.actual_weight ? Number(set.actual_weight) : null,
          notes: set.notes || null,
        })),
      }
    })
}

function resetForm() {
  form.value = {
    client_id: selectedClientId.value,
    program_day_id: '',
    title: '',
    notes: '',
    exercises: [
      {
        exercise_id: '',
        exercise_name: '',
        notes: '',
        sets: [
          {
            is_warmup: false,
            planned_reps_min: 8,
            planned_reps_max: 12,
            planned_weight: '',
            actual_reps: '',
            actual_weight: '',
            notes: '',
          },
        ],
      },
    ],
  }
}

function startEditingSession(session) {
  editingSessionId.value = session.id
  editForm.value = {
    title: session.title || '',
    notes: session.notes || '',
    exercises: (session.exercises || []).map((exercise) => ({
      id: exercise.id,
      exercise_name: exercise.exercise_name,
      notes: exercise.notes || '',
      sets: (exercise.sets || []).map((set) => ({
        id: set.id,
        actual_reps: set.actual_reps || '',
        actual_weight: set.actual_weight || '',
        notes: set.notes || '',
      })),
    })),
  }
}

function cancelEditingSession() {
  editingSessionId.value = null
  editForm.value = {
    title: '',
    notes: '',
    exercises: [],
  }
}

function normalizeSessionUpdatePayload() {
  return {
    title: editForm.value.title,
    notes: editForm.value.notes || null,
    exercises: editForm.value.exercises.map((exercise) => ({
      id: exercise.id,
      notes: exercise.notes || null,
      sets: exercise.sets.map((set) => ({
        id: set.id,
        actual_reps: set.actual_reps ? Number(set.actual_reps) : null,
        actual_weight: set.actual_weight ? Number(set.actual_weight) : null,
        notes: set.notes || null,
      })),
    })),
  }
}

async function updateSession() {
  if (!editingSessionId.value) {
    return
  }

  updating.value = true
  resetMessages()

  try {
    await api.put(`/workout-sessions/${editingSessionId.value}`, normalizeSessionUpdatePayload())
    toastStore.success('Session updated successfully.')
    cancelEditingSession()
    await loadSessions()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to update session.')
    toastStore.error(error.value)
  } finally {
    updating.value = false
  }
}

async function loadSessions() {
  if (canSelectClient.value && !selectedClientId.value) {
    sessions.value = []
    return
  }

  loading.value = true
  resetMessages()

  try {
    const params = canSelectClient.value
      ? { client_id: Number(selectedClientId.value) }
      : {}

    const response = await requestWithRetry(() => api.get('/workout-sessions', { params }))
    sessions.value = response.data.data

    if (pendingEditSessionId.value) {
      const target = sessions.value.find((session) => session.id === pendingEditSessionId.value)

      if (target) {
        startEditingSession(target)
        toastStore.success('Opened the newly started session for editing.')
      }

      pendingEditSessionId.value = null
      const nextQuery = { ...route.query }
      delete nextQuery.edit_session
      router.replace({ query: nextQuery })
    }
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to load sessions.')
    toastStore.error(error.value)
  } finally {
    loading.value = false
  }
}

async function createSession() {
  const validationError = validateCreateSession()
  if (validationError) {
    error.value = validationError
    toastStore.error(validationError)
    return
  }

  saving.value = true
  resetMessages()

  try {
    const payload = {
      title: form.value.title || null,
      notes: form.value.notes || null,
      program_day_id: form.value.program_day_id ? Number(form.value.program_day_id) : null,
      exercises: normalizeExercisePayload(),
    }

    if (canSelectClient.value) {
      payload.client_id = Number(selectedClientId.value)
    }

    await api.post('/workout-sessions', payload)
    toastStore.success('Workout session started.')
    resetForm()
    await loadSessions()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to create session.')
    toastStore.error(error.value)
  } finally {
    saving.value = false
  }
}

async function completeSession(sessionId) {
  completingSessionId.value = sessionId
  resetMessages()

  try {
    await api.post(`/workout-sessions/${sessionId}/complete`)
    toastStore.success('Session marked as completed.')
    await loadSessions()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to complete session.')
    toastStore.error(error.value)
  } finally {
    completingSessionId.value = null
  }
}

onMounted(async () => {
  await referenceStore.fetchExercises()

  if (canSelectClient.value) {
    await referenceStore.fetchClients()
    const queryClientId = route.query.client_id ? String(route.query.client_id) : ''
    const hasQueryClient = queryClientId && referenceStore.clients.some((client) => String(client.id) === queryClientId)

    selectedClientId.value = hasQueryClient
      ? queryClientId
      : (referenceStore.clients[0] ? String(referenceStore.clients[0].id) : '')

    form.value.client_id = selectedClientId.value
  }

  await loadSessions()
})

watch(selectedClientId, async (value) => {
  form.value.client_id = value
  currentPage.value = 1
  await loadSessions()
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
      <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <CardTitle class="text-2xl">Workout Sessions</CardTitle>
          <CardDescription>
            Start ad hoc sessions, log sets, and complete workouts.
          </CardDescription>
        </div>

        <div v-if="canSelectClient" class="space-y-1">
          <label class="text-sm text-muted-foreground">Client</label>
          <select
            v-model="selectedClientId"
            class="h-10 min-w-52 rounded-md border border-input bg-background px-3 text-sm"
          >
            <option disabled value="">Select client</option>
            <option v-for="client in referenceStore.clients" :key="client.id" :value="String(client.id)">
              {{ client.name }}
            </option>
          </select>
        </div>
      </CardHeader>
    </Card>

    <p
      v-if="canSelectClient && !selectedClientId"
      class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-700"
    >
      Select a client before creating or viewing sessions.
    </p>

    <Card class="border-border/80 bg-card/90">
      <CardHeader>
        <CardTitle>Start Session</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <input
            v-model="form.title"
            class="form-control"
            placeholder="Session title"
          >
          <input
            v-model="form.program_day_id"
            type="number"
            min="1"
            class="form-control"
            placeholder="Program day ID (optional)"
          >
          <input
            v-model="form.notes"
            class="h-10 rounded-md border border-input bg-background px-3 text-sm md:col-span-2"
            placeholder="Session notes"
          >
        </div>

        <div class="space-y-3 rounded-lg border border-border/70 bg-background/70 p-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Exercises</h3>
            <Button type="button" size="sm" variant="outline" @click="addExercise">Add exercise</Button>
          </div>

          <article
            v-for="(exercise, exerciseIndex) in form.exercises"
            :key="exerciseIndex"
            class="space-y-3 rounded-lg border border-border/70 bg-card/80 p-3"
          >
            <div class="grid gap-2 md:grid-cols-3">
              <select v-model="exercise.exercise_id" class="form-control-sm">
                <option value="">Custom exercise</option>
                <option v-for="item in referenceStore.exercises" :key="item.id" :value="String(item.id)">
                  {{ item.name }}
                </option>
              </select>
              <input v-model="exercise.exercise_name" class="form-control-sm" placeholder="Exercise name">
              <input v-model="exercise.notes" class="form-control-sm" placeholder="Notes">
            </div>

            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Sets</h4>
                <Button type="button" size="sm" variant="outline" @click="addSet(exerciseIndex)">Add set</Button>
              </div>

              <div
                v-for="(set, setIndex) in exercise.sets"
                :key="setIndex"
                class="grid gap-2 rounded-md border border-border/70 p-2 md:grid-cols-4 lg:grid-cols-7"
              >
                <label class="inline-flex items-center gap-2 text-xs">
                  <input v-model="set.is_warmup" type="checkbox">
                  Warmup
                </label>
                <input v-model="set.planned_reps_min" type="number" min="1" class="form-control-xs" placeholder="Reps min">
                <input v-model="set.planned_reps_max" type="number" min="1" class="form-control-xs" placeholder="Reps max">
                <input v-model="set.planned_weight" type="number" min="0" step="0.5" class="form-control-xs" placeholder="Planned kg">
                <input v-model="set.actual_reps" type="number" min="1" class="form-control-xs" placeholder="Actual reps">
                <input v-model="set.actual_weight" type="number" min="0" step="0.5" class="form-control-xs" placeholder="Actual kg">
                <Button type="button" size="sm" variant="destructive" @click="removeSet(exerciseIndex, setIndex)">Remove set</Button>
              </div>
            </div>

            <Button type="button" size="sm" variant="ghost" @click="removeExercise(exerciseIndex)">Remove exercise</Button>
          </article>
        </div>

        <Button :disabled="saving || (canSelectClient && !selectedClientId)" @click="createSession">
          {{ saving ? 'Starting session...' : 'Start session' }}
        </Button>
      </CardContent>
    </Card>

    <Card class="border-border/80 bg-card/90">
      <CardHeader>
        <CardTitle>Session History</CardTitle>
        <CardDescription>Recent workout logs and completion state.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="grid gap-3 sm:grid-cols-2">
          <input
            v-model="searchTerm"
            class="form-control"
            placeholder="Search title, status, or client"
            aria-label="Search sessions"
          >
          <select v-model="sortBy" class="form-control" aria-label="Sort sessions">
            <option value="started_desc">Newest first</option>
            <option value="started_asc">Oldest first</option>
            <option value="status">Status</option>
          </select>
        </div>

        <LoadingListSkeleton v-if="loading" :rows="3" />

        <article
          v-for="session in paginatedSessions"
          :key="session.id"
          class="rounded-lg border border-border/70 bg-background/70 p-4"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold">{{ session.title }}</p>
              <p class="text-xs text-muted-foreground">
                {{ session.client?.name }} • {{ new Date(session.started_at).toLocaleString() }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <Badge variant="outline">{{ session.status }}</Badge>
              <Button
                size="sm"
                variant="outline"
                @click="startEditingSession(session)"
              >
                Edit
              </Button>
              <Button
                v-if="session.status === 'in_progress'"
                size="sm"
                :disabled="completingSessionId === session.id"
                @click="completeSession(session.id)"
              >
                Complete
              </Button>
            </div>
          </div>

          <ul class="mt-3 space-y-1 text-sm text-muted-foreground">
            <li v-for="exercise in session.exercises" :key="exercise.id">
              {{ exercise.exercise_name }} • {{ exercise.sets.length }} sets
            </li>
          </ul>

          <div
            v-if="editingSessionId === session.id"
            class="mt-4 space-y-3 rounded-lg border border-border/70 bg-card/80 p-3"
          >
            <div class="grid gap-2 md:grid-cols-2">
              <input
                v-model="editForm.title"
                class="form-control-sm"
                placeholder="Session title"
              >
              <input
                v-model="editForm.notes"
                class="form-control-sm"
                placeholder="Session notes"
              >
            </div>

            <article
              v-for="exercise in editForm.exercises"
              :key="exercise.id"
              class="space-y-2 rounded-md border border-border/70 p-3"
            >
              <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-medium">{{ exercise.exercise_name }}</p>
                <input
                  v-model="exercise.notes"
                  class="form-control-xs"
                  placeholder="Exercise notes"
                >
              </div>

              <div
                v-for="set in exercise.sets"
                :key="set.id"
                class="grid gap-2 rounded-md border border-border/70 p-2 md:grid-cols-3"
              >
                <input
                  v-model="set.actual_reps"
                  type="number"
                  min="1"
                  class="form-control-xs"
                  placeholder="Actual reps"
                >
                <input
                  v-model="set.actual_weight"
                  type="number"
                  min="0"
                  step="0.5"
                  class="form-control-xs"
                  placeholder="Actual kg"
                >
                <input
                  v-model="set.notes"
                  class="form-control-xs"
                  placeholder="Set notes"
                >
              </div>
            </article>

            <div class="flex flex-wrap gap-2">
              <Button size="sm" :disabled="updating" @click="updateSession">
                {{ updating ? 'Saving...' : 'Save session changes' }}
              </Button>
              <Button size="sm" variant="outline" @click="cancelEditingSession">
                Cancel
              </Button>
            </div>
          </div>
        </article>

        <div v-if="!loading && filteredSessions.length > 0" class="flex items-center justify-between gap-3 text-sm">
          <p class="text-muted-foreground">Page {{ currentPage }} of {{ totalPages }}</p>
          <div class="flex gap-2">
            <Button size="sm" variant="outline" :disabled="currentPage === 1" @click="currentPage -= 1">Previous</Button>
            <Button size="sm" variant="outline" :disabled="currentPage >= totalPages" @click="currentPage += 1">Next</Button>
          </div>
        </div>

        <p v-if="!loading && filteredSessions.length === 0" class="text-sm text-muted-foreground">
          No sessions logged yet.
        </p>

        <Button v-if="!loading && error" size="sm" variant="outline" @click="loadSessions">
          Retry loading
        </Button>
      </CardContent>
    </Card>
  </section>
</template>
