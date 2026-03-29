<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Badge } from '@/components/ui/badge'
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
const router = useRouter()

const selectedClientId = ref('')
const loading = ref(false)
const startingProgramDayId = ref(null)
const error = ref('')
const upcomingDays = ref([])
const existingSessionsByDay = ref({})

const canSelectClient = computed(() => ['admin', 'trainer'].includes(authStore.role))

function resetMessages() {
  error.value = ''
}

function normalizeDate(value) {
  if (!value) {
    return null
  }

  return String(value).includes('T')
    ? new Date(value)
    : new Date(`${value}T00:00:00`)
}

function toDayKey(value) {
  return new Date(value).toISOString().slice(0, 10)
}

async function loadUpcomingWorkouts() {
  if (canSelectClient.value && !selectedClientId.value) {
    upcomingDays.value = []
    existingSessionsByDay.value = {}
    return
  }

  loading.value = true
  resetMessages()

  try {
    const params = canSelectClient.value
      ? { client_id: Number(selectedClientId.value) }
      : {}

    const [programsResponse, sessionsResponse] = await Promise.all([
      requestWithRetry(() => api.get('/programs', { params })),
      requestWithRetry(() => api.get('/workout-sessions', {
        params: {
          ...params,
          scope: 'upcoming',
        },
      })),
    ])

    const todayKey = toDayKey(new Date())

    const days = programsResponse.data.data
      .flatMap((program) =>
        (program.days || []).map((day) => ({
          id: day.id,
          title: day.title,
          scheduled_on: day.scheduled_on,
          notes: day.notes,
          exercises: day.exercises || [],
          program: {
            id: program.id,
            name: program.name,
            goal: program.goal,
            status: program.status,
          },
          client: program.client,
          trainer: program.trainer,
        })),
      )
      .filter((day) => {
        const key = toDayKey(normalizeDate(day.scheduled_on))
        return key >= todayKey
      })
      .sort((a, b) => normalizeDate(a.scheduled_on) - normalizeDate(b.scheduled_on))

    upcomingDays.value = days

    const map = {}
    for (const session of sessionsResponse.data.data || []) {
      if (session.program_day_id) {
        map[session.program_day_id] = session
      }
    }

    existingSessionsByDay.value = map
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to load upcoming workouts.')
    toastStore.error(error.value)
  } finally {
    loading.value = false
  }
}

async function startScheduledSession(day) {
  startingProgramDayId.value = day.id
  resetMessages()

  try {
    const payload = {
      program_day_id: day.id,
      title: day.title,
    }

    if (canSelectClient.value) {
      payload.client_id = Number(selectedClientId.value)
    }

    const response = await api.post('/workout-sessions', payload)
    const sessionId = response?.data?.data?.id

    toastStore.success('Scheduled session started.')

    if (sessionId) {
      const query = { edit_session: String(sessionId) }

      if (canSelectClient.value) {
        query.client_id = selectedClientId.value
      }

      await router.push({ path: '/app/sessions', query })
      return
    }

    await loadUpcomingWorkouts()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to start session from scheduled workout.')
    toastStore.error(error.value)
  } finally {
    startingProgramDayId.value = null
  }
}

onMounted(async () => {
  if (canSelectClient.value) {
    await referenceStore.fetchClients()
    selectedClientId.value = referenceStore.clients[0]
      ? String(referenceStore.clients[0].id)
      : ''
  }

  await loadUpcomingWorkouts()
})

watch(selectedClientId, loadUpcomingWorkouts)
</script>

<template>
  <section class="space-y-6">
    <Card class="border-border/80 bg-card/90">
      <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <CardTitle class="text-2xl">Upcoming Workouts</CardTitle>
          <CardDescription>
            Date-assigned workout days ready to start in the gym.
          </CardDescription>
        </div>

        <div v-if="canSelectClient" class="space-y-1">
          <label class="text-sm text-muted-foreground">Client</label>
          <select
            v-model="selectedClientId"
            class="h-10 min-w-52 rounded-md border border-input bg-background px-3 text-sm"
          >
            <option disabled value="">Select client</option>
            <option
              v-for="client in referenceStore.clients"
              :key="client.id"
              :value="String(client.id)"
            >
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
      Select a client to view upcoming assignments.
    </p>

    <Card class="border-border/80 bg-card/90">
      <CardHeader>
        <CardTitle>Scheduled Days</CardTitle>
        <CardDescription>
          Start directly from a planned day to preserve workout snapshot history.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <LoadingListSkeleton v-if="loading" :rows="3" />

        <article
          v-for="day in upcomingDays"
          :key="day.id"
          class="rounded-lg border border-border/70 bg-background/70 p-4"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold">{{ day.title }}</p>
              <p class="text-xs text-muted-foreground">
                {{ new Date(day.scheduled_on).toLocaleDateString() }} • Program: {{ day.program.name }}
              </p>
              <p class="mt-1 text-xs text-muted-foreground">
                {{ day.client?.name }} • Trainer: {{ day.trainer?.name }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <Badge variant="outline">{{ day.exercises.length }} exercises</Badge>
              <Badge v-if="existingSessionsByDay[day.id]" variant="secondary">
                Session {{ existingSessionsByDay[day.id].status }}
              </Badge>
              <Button
                :disabled="Boolean(existingSessionsByDay[day.id]) || startingProgramDayId === day.id"
                size="sm"
                @click="startScheduledSession(day)"
              >
                {{ startingProgramDayId === day.id ? 'Starting...' : 'Start session' }}
              </Button>
            </div>
          </div>

          <ul class="mt-3 space-y-1 text-sm text-muted-foreground">
            <li v-for="exercise in day.exercises" :key="exercise.id">
              {{ exercise.exercise_name }} • {{ exercise.target_sets || '-' }} sets
            </li>
          </ul>
        </article>

        <p v-if="!loading && upcomingDays.length === 0" class="text-sm text-muted-foreground">
          No upcoming assigned workout days found.
        </p>

        <Button v-if="!loading && error" size="sm" variant="outline" @click="loadUpcomingWorkouts">
          Retry loading
        </Button>
      </CardContent>
    </Card>
  </section>
</template>
