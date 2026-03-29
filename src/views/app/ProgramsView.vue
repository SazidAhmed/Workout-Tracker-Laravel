<script setup>
import { computed, onMounted, ref, watch } from 'vue'
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

const loading = ref(false)
const saving = ref(false)
const deletingProgramId = ref(null)
const deletingProgramDayId = ref(null)
const editingProgramId = ref(null)
const programs = ref([])
const error = ref('')
const searchTerm = ref('')
const sortBy = ref('updated_desc')
const currentPage = ref(1)
const pageSize = 6

const selectedClientId = ref('')

const form = ref({
  name: '',
  goal: '',
  status: 'draft',
  start_date: '',
  end_date: '',
  notes: '',
  days: [],
})

const canManage = computed(() => ['admin', 'trainer'].includes(authStore.role))
const canSelectClient = computed(() => ['admin', 'trainer'].includes(authStore.role))

const filteredPrograms = computed(() => {
  const needle = searchTerm.value.trim().toLowerCase()
  const list = [...programs.value]

  const searched = needle
    ? list.filter((program) => {
      const haystack = `${program.name || ''} ${program.goal || ''} ${program.client?.name || ''}`.toLowerCase()
      return haystack.includes(needle)
    })
    : list

  searched.sort((a, b) => {
    if (sortBy.value === 'name_asc') {
      return String(a.name || '').localeCompare(String(b.name || ''))
    }

    if (sortBy.value === 'name_desc') {
      return String(b.name || '').localeCompare(String(a.name || ''))
    }

    return new Date(b.updated_at || b.created_at) - new Date(a.updated_at || a.created_at)
  })

  return searched
})

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredPrograms.value.length / pageSize)),
)

const paginatedPrograms = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredPrograms.value.slice(start, start + pageSize)
})

function resetMessages() {
  error.value = ''
}

function toDateInput(value) {
  if (!value) {
    return ''
  }

  return String(value).includes('T') ? String(value).slice(0, 10) : String(value)
}

function emptyExercise() {
  return {
    exercise_id: '',
    exercise_name: '',
    target_sets: 3,
    target_reps_min: 8,
    target_reps_max: 12,
    target_weight: '',
    rest_seconds: 90,
    notes: '',
  }
}

function addDay() {
  form.value.days.push({
    title: '',
    scheduled_on: '',
    notes: '',
    exercises: [emptyExercise()],
  })
}

function addExercise(dayIndex) {
  form.value.days[dayIndex].exercises.push(emptyExercise())
}

function removeExercise(dayIndex, exerciseIndex) {
  form.value.days[dayIndex].exercises.splice(exerciseIndex, 1)
}

function removeDay(dayIndex) {
  form.value.days.splice(dayIndex, 1)
}

async function loadPrograms() {
  if (canSelectClient.value && !selectedClientId.value) {
    programs.value = []
    return
  }

  loading.value = true
  resetMessages()

  try {
    const params = canSelectClient.value
      ? { client_id: Number(selectedClientId.value) }
      : {}

    const response = await requestWithRetry(() => api.get('/programs', { params }))
    programs.value = response.data.data
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to load programs.')
    toastStore.error(error.value)
  } finally {
    loading.value = false
  }
}

function validateProgramForm() {
  if (!form.value.name.trim()) {
    return 'Program name is required.'
  }

  const days = normalizeDays()
  if (days.length === 0) {
    return 'Add at least one scheduled day with exercises.'
  }

  return null
}

function normalizeDays() {
  return form.value.days
    .filter((day) => day.title && day.scheduled_on)
    .map((day) => ({
      title: day.title,
      scheduled_on: day.scheduled_on,
      notes: day.notes || null,
      exercises: day.exercises
        .filter((exercise) => exercise.exercise_id || exercise.exercise_name)
        .map((exercise) => {
          const selectedExercise = referenceStore.exercises.find(
            (item) => String(item.id) === String(exercise.exercise_id),
          )

          return {
            exercise_id: exercise.exercise_id ? Number(exercise.exercise_id) : null,
            exercise_name: exercise.exercise_name || selectedExercise?.name,
            target_sets: exercise.target_sets ? Number(exercise.target_sets) : null,
            target_reps_min: exercise.target_reps_min ? Number(exercise.target_reps_min) : null,
            target_reps_max: exercise.target_reps_max ? Number(exercise.target_reps_max) : null,
            target_weight: exercise.target_weight ? Number(exercise.target_weight) : null,
            rest_seconds: exercise.rest_seconds ? Number(exercise.rest_seconds) : null,
            notes: exercise.notes || null,
          }
        }),
    }))
    .filter((day) => day.exercises.length > 0)
}

function resetForm() {
  form.value = {
    name: '',
    goal: '',
    status: 'draft',
    start_date: '',
    end_date: '',
    notes: '',
    days: [],
  }

  editingProgramId.value = null
}

function loadProgramIntoForm(program) {
  editingProgramId.value = program.id

  if (canSelectClient.value && program.client?.id) {
    selectedClientId.value = String(program.client.id)
  }

  form.value = {
    name: program.name || '',
    goal: program.goal || '',
    status: program.status || 'draft',
    start_date: toDateInput(program.start_date),
    end_date: toDateInput(program.end_date),
    notes: program.notes || '',
    days: (program.days || []).map((day) => ({
      title: day.title || '',
      scheduled_on: toDateInput(day.scheduled_on),
      notes: day.notes || '',
      exercises: (day.exercises || []).map((exercise) => ({
        exercise_id: exercise.exercise_id ? String(exercise.exercise_id) : '',
        exercise_name: exercise.exercise_name || '',
        target_sets: exercise.target_sets || '',
        target_reps_min: exercise.target_reps_min || '',
        target_reps_max: exercise.target_reps_max || '',
        target_weight: exercise.target_weight || '',
        rest_seconds: exercise.rest_seconds || '',
        notes: exercise.notes || '',
      })),
    })),
  }
}

async function submitProgram() {
  if (!canManage.value) {
    return
  }

  const validationError = validateProgramForm()
  if (validationError) {
    error.value = validationError
    toastStore.error(validationError)
    return
  }

  saving.value = true
  resetMessages()

  try {
    const payload = {
      client_id: canSelectClient.value ? Number(selectedClientId.value) : authStore.user.id,
      name: form.value.name,
      goal: form.value.goal || null,
      status: form.value.status,
      start_date: form.value.start_date || null,
      end_date: form.value.end_date || null,
      notes: form.value.notes || null,
      days: normalizeDays(),
    }

    if (editingProgramId.value) {
      await api.put(`/programs/${editingProgramId.value}`, payload)
      toastStore.success('Program updated.')
    } else {
      await api.post('/programs', payload)
      toastStore.success('Program created.')
    }

    resetForm()
    await loadPrograms()
  } catch (e) {
    error.value = getApiErrorMessage(e, editingProgramId.value ? 'Unable to update program.' : 'Unable to create program.')
    toastStore.error(error.value)
  } finally {
    saving.value = false
  }
}

async function deleteProgram(programId) {
  deletingProgramId.value = programId
  resetMessages()

  try {
    await api.delete(`/programs/${programId}`)
    toastStore.success('Program deleted.')
    await loadPrograms()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to delete program.')
    toastStore.error(error.value)
  } finally {
    deletingProgramId.value = null
  }
}

async function deleteProgramDay(programDayId) {
  deletingProgramDayId.value = programDayId
  resetMessages()

  try {
    await api.delete(`/program-days/${programDayId}`)
    toastStore.success('Scheduled day deleted.')
    await loadPrograms()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to delete scheduled day.')
    toastStore.error(error.value)
  } finally {
    deletingProgramDayId.value = null
  }
}

onMounted(async () => {
  await referenceStore.fetchExercises()

  if (canSelectClient.value) {
    await referenceStore.fetchClients()
    selectedClientId.value = referenceStore.clients[0]
      ? String(referenceStore.clients[0].id)
      : ''
  }

  await loadPrograms()
})

watch(selectedClientId, loadPrograms)

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
          <CardTitle class="text-2xl">Programs</CardTitle>
          <CardDescription>
            Build and schedule training plans with structured workout days.
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
      Select a client before creating or viewing programs.
    </p>

    <Card v-if="canManage" class="border-border/80 bg-card/90">
      <CardHeader>
        <div class="flex items-center justify-between gap-3">
          <CardTitle>{{ editingProgramId ? 'Edit Program' : 'Create Program' }}</CardTitle>
          <Button
            v-if="editingProgramId"
            type="button"
            size="sm"
            variant="outline"
            @click="resetForm"
          >
            Cancel edit
          </Button>
        </div>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          <input
            v-model="form.name"
            required
            class="form-control"
            placeholder="Program name"
          >
          <input
            v-model="form.goal"
            class="form-control"
            placeholder="Goal"
          >
          <select v-model="form.status" class="form-control">
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="archived">Archived</option>
          </select>
          <input v-model="form.start_date" type="date" class="form-control">
          <input v-model="form.end_date" type="date" class="form-control">
          <input
            v-model="form.notes"
            class="form-control"
            placeholder="Notes"
          >
        </div>

        <div class="space-y-4 rounded-lg border border-border/70 bg-background/70 p-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Workout Days</h3>
            <Button type="button" size="sm" variant="outline" @click="addDay">Add day</Button>
          </div>

          <article
            v-for="(day, dayIndex) in form.days"
            :key="dayIndex"
            class="space-y-3 rounded-lg border border-border/70 bg-card/80 p-3"
          >
            <div class="grid gap-3 md:grid-cols-3">
              <input v-model="day.title" class="form-control-sm" placeholder="Day title">
              <input v-model="day.scheduled_on" type="date" class="form-control-sm">
              <input v-model="day.notes" class="form-control-sm" placeholder="Day notes">
            </div>

            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Exercises</h4>
                <Button type="button" size="sm" variant="outline" @click="addExercise(dayIndex)">Add exercise</Button>
              </div>

              <div
                v-for="(exercise, exerciseIndex) in day.exercises"
                :key="exerciseIndex"
                class="grid gap-2 rounded-md border border-border/70 p-2 md:grid-cols-3 lg:grid-cols-4"
              >
                <select v-model="exercise.exercise_id" class="form-control-xs">
                  <option value="">Custom exercise</option>
                  <option v-for="option in referenceStore.exercises" :key="option.id" :value="String(option.id)">
                    {{ option.name }}
                  </option>
                </select>
                <input v-model="exercise.exercise_name" class="form-control-xs" placeholder="Exercise name">
                <input v-model="exercise.target_sets" type="number" min="1" class="form-control-xs" placeholder="Sets">
                <input v-model="exercise.target_reps_min" type="number" min="1" class="form-control-xs" placeholder="Reps min">
                <input v-model="exercise.target_reps_max" type="number" min="1" class="form-control-xs" placeholder="Reps max">
                <input v-model="exercise.target_weight" type="number" min="0" step="0.5" class="form-control-xs" placeholder="Weight">
                <input v-model="exercise.rest_seconds" type="number" min="0" class="form-control-xs" placeholder="Rest sec">
                <Button
                  type="button"
                  size="sm"
                  variant="destructive"
                  @click="removeExercise(dayIndex, exerciseIndex)"
                >
                  Remove
                </Button>
              </div>
            </div>

            <Button type="button" size="sm" variant="ghost" @click="removeDay(dayIndex)">Remove day</Button>
          </article>
        </div>

        <Button :disabled="saving || (canSelectClient && !selectedClientId)" @click="submitProgram">
          {{
            saving
              ? (editingProgramId ? 'Updating program...' : 'Creating program...')
              : (editingProgramId ? 'Update program' : 'Create program')
          }}
        </Button>
      </CardContent>
    </Card>

    <Card class="border-border/80 bg-card/90">
      <CardHeader>
        <CardTitle>Program List</CardTitle>
        <CardDescription>
          Structured plans assigned to each client.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="grid gap-3 sm:grid-cols-2">
          <input
            v-model="searchTerm"
            class="form-control"
            placeholder="Search programs"
            aria-label="Search programs"
          >
          <select v-model="sortBy" class="form-control" aria-label="Sort programs">
            <option value="updated_desc">Recently updated</option>
            <option value="name_asc">Name A-Z</option>
            <option value="name_desc">Name Z-A</option>
          </select>
        </div>

        <LoadingListSkeleton v-if="loading" :rows="3" />

        <article
          v-for="program in paginatedPrograms"
          :key="program.id"
          class="rounded-lg border border-border/70 bg-background/70 p-4"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold">{{ program.name }}</p>
              <p class="text-xs text-muted-foreground">
                {{ program.client?.name }} • {{ program.trainer?.name }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <Badge variant="outline">{{ program.status }}</Badge>
              <Badge variant="secondary">{{ program.days.length }} days</Badge>
              <Button
                v-if="canManage"
                size="sm"
                variant="outline"
                @click="loadProgramIntoForm(program)"
              >
                Edit
              </Button>
              <Button
                v-if="canManage"
                size="sm"
                variant="destructive"
                :disabled="deletingProgramId === program.id"
                @click="deleteProgram(program.id)"
              >
                Delete
              </Button>
            </div>
          </div>

          <p v-if="program.goal" class="mt-2 text-sm text-muted-foreground">Goal: {{ program.goal }}</p>

          <ul class="mt-3 space-y-1 text-sm text-muted-foreground">
            <li
              v-for="day in program.days"
              :key="day.id"
              class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/60 px-2 py-1.5"
            >
              <span>
                {{ new Date(day.scheduled_on).toLocaleDateString() }} - {{ day.title }} ({{ day.exercises.length }} exercises)
              </span>
              <Button
                v-if="canManage"
                size="sm"
                variant="outline"
                :disabled="deletingProgramDayId === day.id"
                @click="deleteProgramDay(day.id)"
              >
                {{ deletingProgramDayId === day.id ? 'Deleting...' : 'Delete day' }}
              </Button>
            </li>
          </ul>
        </article>

        <div v-if="!loading && filteredPrograms.length > 0" class="flex items-center justify-between gap-3 text-sm">
          <p class="text-muted-foreground">Page {{ currentPage }} of {{ totalPages }}</p>
          <div class="flex gap-2">
            <Button size="sm" variant="outline" :disabled="currentPage === 1" @click="currentPage -= 1">Previous</Button>
            <Button size="sm" variant="outline" :disabled="currentPage >= totalPages" @click="currentPage += 1">Next</Button>
          </div>
        </div>

        <p v-if="!loading && filteredPrograms.length === 0" class="text-sm text-muted-foreground">
          No programs found.
        </p>

        <Button v-if="!loading && error" size="sm" variant="outline" @click="loadPrograms">
          Retry loading
        </Button>
      </CardContent>
    </Card>
  </section>
</template>
