<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Activity, Scale, Trophy, Weight } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import LineChart from '@/components/charts/LineChart.vue'
import { api, requestWithRetry } from '@/lib/api'
import { getApiErrorMessage } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useReferenceStore } from '@/stores/reference'
import { useToastStore } from '@/stores/toast'
import LoadingListSkeleton from '@/components/LoadingListSkeleton.vue'

const authStore = useAuthStore()
const referenceStore = useReferenceStore()
const toastStore = useToastStore()

const selectedClientId = ref('')
const loading = ref(false)
const summary = ref({
  heaviest_set: null,
  estimated_one_rep_max: null,
  total_volume: 0,
  completion_rate: 0,
  recent_sessions: [],
  body_weight_trend: [],
})
const roleStats = ref({
  totalUsers: 0,
  totalTrainers: 0,
  totalClients: 0,
  inProgressSessions: 0,
  completedSessions: 0,
})

const canSelectClient = computed(() => ['admin', 'trainer'].includes(authStore.role))
const selectedClient = computed(() =>
  referenceStore.clients.find((client) => String(client.id) === selectedClientId.value),
)

const chartLabels = computed(() =>
  summary.value.body_weight_trend.map((point) =>
    new Date(point.recorded_on).toLocaleDateString(),
  ),
)

const chartDatasets = computed(() => [
  {
    label: 'Body Weight',
    data: summary.value.body_weight_trend.map((point) => Number(point.body_weight)),
    borderColor: '#0f766e',
    backgroundColor: 'rgba(15, 118, 110, 0.2)',
    fill: true,
    tension: 0.3,
  },
])

const metricCards = computed(() => [
  {
    label: 'Heaviest set',
    value: summary.value.heaviest_set ? `${summary.value.heaviest_set} kg` : '-',
    icon: Weight,
  },
  {
    label: 'Best estimated 1RM',
    value: summary.value.estimated_one_rep_max
      ? `${summary.value.estimated_one_rep_max} kg`
      : '-',
    icon: Trophy,
  },
  {
    label: 'Total volume',
    value: `${summary.value.total_volume} kg`,
    icon: Activity,
  },
  {
    label: 'Completion rate',
    value: `${summary.value.completion_rate}%`,
    icon: Scale,
  },
])

const roleHighlights = computed(() => {
  if (authStore.role === 'admin') {
    return [
      { label: 'Total users', value: roleStats.value.totalUsers },
      { label: 'Trainers', value: roleStats.value.totalTrainers },
      { label: 'Clients', value: roleStats.value.totalClients },
    ]
  }

  if (authStore.role === 'trainer') {
    return [
      { label: 'My clients', value: roleStats.value.totalClients },
      { label: 'In progress sessions', value: roleStats.value.inProgressSessions },
      { label: 'Completed sessions', value: roleStats.value.completedSessions },
    ]
  }

  return [
    { label: 'Completed sessions', value: roleStats.value.completedSessions },
    { label: 'In progress sessions', value: roleStats.value.inProgressSessions },
    { label: 'Completion rate', value: `${summary.value.completion_rate}%` },
  ]
})

async function loadRoleStats() {
  try {
    if (authStore.role === 'admin') {
      const [usersResponse, trainersResponse, clientsResponse] = await Promise.all([
        requestWithRetry(() => api.get('/users')),
        requestWithRetry(() => api.get('/trainers')),
        requestWithRetry(() => api.get('/clients')),
      ])

      roleStats.value = {
        ...roleStats.value,
        totalUsers: usersResponse.data.data.length,
        totalTrainers: trainersResponse.data.data.length,
        totalClients: clientsResponse.data.data.length,
      }

      return
    }

    const params = canSelectClient.value && selectedClientId.value
      ? { client_id: Number(selectedClientId.value) }
      : {}

    const [clientsResponse, sessionsResponse] = await Promise.all([
      authStore.role === 'trainer'
        ? requestWithRetry(() => api.get('/clients'))
        : Promise.resolve({ data: { data: [] } }),
      requestWithRetry(() => api.get('/workout-sessions', { params })),
    ])

    const allSessions = sessionsResponse.data.data || []
    roleStats.value = {
      ...roleStats.value,
      totalClients: authStore.role === 'trainer' ? clientsResponse.data.data.length : roleStats.value.totalClients,
      inProgressSessions: allSessions.filter((session) => session.status === 'in_progress').length,
      completedSessions: allSessions.filter((session) => session.status === 'completed').length,
    }
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to load role highlights.'))
  }
}

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
    await loadRoleStats()
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to load dashboard summary.'))
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (canSelectClient.value) {
    await referenceStore.fetchClients()
    selectedClientId.value = referenceStore.clients[0]
      ? String(referenceStore.clients[0].id)
      : ''
  }

  await loadSummary()
})

watch(selectedClientId, loadSummary)
</script>

<template>
  <section class="space-y-6">
    <Card class="border-border/80 bg-card/90">
      <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <CardTitle class="text-2xl">Dashboard</CardTitle>
          <CardDescription>
            Recent performance snapshot with adherence and weight trend.
          </CardDescription>
        </div>

        <div v-if="canSelectClient" class="space-y-1">
          <label class="text-sm text-muted-foreground">Client</label>
          <select
            v-model="selectedClientId"
            class="h-10 min-w-52 rounded-md border border-input bg-background px-3 text-sm"
          >
            <option disabled value="">Select a client</option>
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
      Select a client to load dashboard metrics.
    </p>

    <div v-if="loading" class="text-sm text-muted-foreground">Loading dashboard data...</div>

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <Card v-for="item in roleHighlights" :key="item.label" class="border-border/70 bg-card/85">
          <CardContent class="space-y-2 p-5">
            <p class="text-sm text-muted-foreground">{{ item.label }}</p>
            <p class="text-2xl font-semibold tracking-tight">{{ item.value }}</p>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card v-for="card in metricCards" :key="card.label" class="border-border/70 bg-card/85">
          <CardContent class="space-y-2 p-5">
            <component :is="card.icon" class="size-5 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">{{ card.label }}</p>
            <p class="text-2xl font-semibold tracking-tight">{{ card.value }}</p>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <Card class="border-border/70 bg-card/85">
          <CardHeader>
            <CardTitle>Body Weight Trend</CardTitle>
            <CardDescription>
              Logged body metrics over time.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <p v-if="chartLabels.length === 0" class="text-sm text-muted-foreground">
              No body metric entries yet.
            </p>
            <LineChart
              v-else
              :labels="chartLabels"
              :datasets="chartDatasets"
              y-axis-label="Body weight (kg)"
            />
          </CardContent>
        </Card>

        <Card class="border-border/70 bg-card/85">
          <CardHeader>
            <CardTitle>Recent Sessions</CardTitle>
            <CardDescription>
              Last 5 sessions for {{ selectedClient?.name || authStore.user?.name }}.
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <LoadingListSkeleton v-if="loading" :rows="2" />
            <div
              v-for="session in summary.recent_sessions"
              :key="session.id"
              class="rounded-lg border border-border/70 bg-background/70 p-3"
            >
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium">{{ session.title }}</p>
                <Badge variant="outline">{{ session.status }}</Badge>
              </div>
              <p class="mt-1 text-xs text-muted-foreground">
                {{ new Date(session.started_at).toLocaleString() }}
              </p>
            </div>

            <p v-if="summary.recent_sessions.length === 0" class="text-sm text-muted-foreground">
              No sessions found yet.
            </p>
          </CardContent>
        </Card>
      </div>
    </template>
  </section>
</template>
