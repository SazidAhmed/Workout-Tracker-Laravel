<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import LineChart from '@/components/charts/LineChart.vue'
import { api, requestWithRetry } from '@/lib/api'
import { getApiErrorMessage } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useReferenceStore } from '@/stores/reference'
import { useToastStore } from '@/stores/toast'

const authStore = useAuthStore()
const referenceStore = useReferenceStore()
const toastStore = useToastStore()

const selectedClientId = ref('')
const selectedExerciseId = ref('')
const loading = ref(false)
const summary = ref({
  heaviest_set: null,
  estimated_one_rep_max: null,
  total_volume: 0,
  completion_rate: 0,
  body_weight_trend: [],
})
const exerciseProgress = ref([])

const canSelectClient = computed(() => ['admin', 'trainer'].includes(authStore.role))

const summaryCards = computed(() => [
  { label: 'Heaviest set', value: summary.value.heaviest_set ? `${summary.value.heaviest_set} kg` : '-' },
  {
    label: 'Estimated 1RM',
    value: summary.value.estimated_one_rep_max ? `${summary.value.estimated_one_rep_max} kg` : '-',
  },
  { label: 'Total volume', value: `${summary.value.total_volume} kg` },
  { label: 'Completion', value: `${summary.value.completion_rate}%` },
])

const bodyTrendLabels = computed(() =>
  summary.value.body_weight_trend.map((point) => new Date(point.recorded_on).toLocaleDateString()),
)

const bodyTrendDatasets = computed(() => [
  {
    label: 'Body weight',
    data: summary.value.body_weight_trend.map((point) => Number(point.body_weight)),
    borderColor: '#f97316',
    backgroundColor: 'rgba(249, 115, 22, 0.2)',
    fill: true,
    tension: 0.28,
  },
])

const exerciseTrendLabels = computed(() =>
  exerciseProgress.value.map((point) => new Date(point.date).toLocaleDateString()),
)

const exerciseTrendDatasets = computed(() => [
  {
    label: 'Estimated 1RM',
    data: exerciseProgress.value.map((point) => Number(point.estimated_one_rep_max)),
    borderColor: '#0f766e',
    backgroundColor: 'rgba(15, 118, 110, 0.18)',
    fill: true,
    tension: 0.28,
  },
  {
    label: 'Working weight',
    data: exerciseProgress.value.map((point) => Number(point.weight)),
    borderColor: '#334155',
    backgroundColor: 'rgba(51, 65, 85, 0.18)',
    fill: false,
    tension: 0.28,
  },
])

async function loadSummary() {
  if (canSelectClient.value && !selectedClientId.value) {
    return
  }

  loading.value = true

  try {
    const params = canSelectClient.value
      ? { client_id: Number(selectedClientId.value) }
      : {}

    const response = await requestWithRetry(() => api.get('/dashboard/summary', { params }))
    summary.value = response.data.data
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to load summary analytics.'))
  } finally {
    loading.value = false
  }
}

async function loadExerciseProgress() {
  if (!selectedExerciseId.value) {
    exerciseProgress.value = []
    return
  }

  if (canSelectClient.value && !selectedClientId.value) {
    return
  }

  try {
    const params = {
      exercise_id: Number(selectedExerciseId.value),
    }

    if (canSelectClient.value) {
      params.client_id = Number(selectedClientId.value)
    }

    const response = await requestWithRetry(() => api.get('/dashboard/exercise-progress', { params }))
    exerciseProgress.value = response.data.data
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to load exercise progress trend.'))
  }
}

onMounted(async () => {
  if (canSelectClient.value) {
    await referenceStore.fetchClients()
    selectedClientId.value = referenceStore.clients[0]
      ? String(referenceStore.clients[0].id)
      : ''
  }

  await referenceStore.fetchExercises()
  selectedExerciseId.value = referenceStore.exercises[0]
    ? String(referenceStore.exercises[0].id)
    : ''

  await loadSummary()
  await loadExerciseProgress()
})

watch(selectedClientId, async () => {
  await loadSummary()
  await loadExerciseProgress()
})

watch(selectedExerciseId, loadExerciseProgress)
</script>

<template>
  <section class="space-y-6">
    <Card class="border-border/80 bg-card/90">
      <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <CardTitle class="text-2xl">Progress Analytics</CardTitle>
          <CardDescription>
            PRs, 1RM trend, total volume, adherence, and body-weight changes.
          </CardDescription>
        </div>

        <div class="grid gap-2 sm:grid-cols-2">
          <select
            v-if="canSelectClient"
            v-model="selectedClientId"
            class="h-10 min-w-52 rounded-md border border-input bg-background px-3 text-sm"
          >
            <option disabled value="">Select client</option>
            <option v-for="client in referenceStore.clients" :key="client.id" :value="String(client.id)">
              {{ client.name }}
            </option>
          </select>

          <select
            v-model="selectedExerciseId"
            class="h-10 min-w-52 rounded-md border border-input bg-background px-3 text-sm"
          >
            <option disabled value="">Select exercise</option>
            <option v-for="exercise in referenceStore.exercises" :key="exercise.id" :value="String(exercise.id)">
              {{ exercise.name }}
            </option>
          </select>
        </div>
      </CardHeader>
    </Card>

    <p
      v-if="canSelectClient && !selectedClientId"
      class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-700"
    >
      Select a client to load analytics.
    </p>

    <div v-if="loading" class="text-sm text-muted-foreground">Loading analytics...</div>

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card v-for="item in summaryCards" :key="item.label" class="border-border/70 bg-card/85">
          <CardContent class="space-y-2 p-5">
            <p class="text-sm text-muted-foreground">{{ item.label }}</p>
            <p class="text-2xl font-semibold tracking-tight">{{ item.value }}</p>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-6 xl:grid-cols-2">
        <Card class="border-border/70 bg-card/85">
          <CardHeader>
            <CardTitle>Body Weight Trend</CardTitle>
            <CardDescription>
              Progress over tracked measurement dates.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <LineChart
              v-if="bodyTrendLabels.length"
              :labels="bodyTrendLabels"
              :datasets="bodyTrendDatasets"
              y-axis-label="Weight (kg)"
            />
            <p v-else class="text-sm text-muted-foreground">No body weight trend data yet.</p>
          </CardContent>
        </Card>

        <Card class="border-border/70 bg-card/85">
          <CardHeader class="space-y-2">
            <div class="flex items-center justify-between">
              <CardTitle>Exercise Progress</CardTitle>
              <Badge variant="outline">
                {{ exerciseProgress.length }} data points
              </Badge>
            </div>
            <CardDescription>
              Estimated 1RM and working weight by training date.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <LineChart
              v-if="exerciseTrendLabels.length"
              :labels="exerciseTrendLabels"
              :datasets="exerciseTrendDatasets"
              y-axis-label="Weight (kg)"
            />
            <p v-else class="text-sm text-muted-foreground">
              No progress points found for selected exercise.
            </p>
          </CardContent>
        </Card>
      </div>
    </template>
  </section>
</template>
