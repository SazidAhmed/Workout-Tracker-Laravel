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

const selectedClientId = ref('')
const loading = ref(false)
const saving = ref(false)
const deletingMetricId = ref(null)
const updatingMetricId = ref(null)
const editingMetricId = ref(null)
const metrics = ref([])
const error = ref('')

const form = ref({
  recorded_on: new Date().toISOString().slice(0, 10),
  body_weight: '',
  notes: '',
})

const editForm = ref({
  recorded_on: '',
  body_weight: '',
  notes: '',
})

const canSelectClient = computed(() => ['admin', 'trainer'].includes(authStore.role))

function resetMessages() {
  error.value = ''
}

function toDateInput(value) {
  if (!value) {
    return ''
  }

  return String(value).includes('T') ? String(value).slice(0, 10) : String(value)
}

async function loadMetrics() {
  if (canSelectClient.value && !selectedClientId.value) {
    metrics.value = []
    return
  }

  loading.value = true
  resetMessages()

  try {
    const params = canSelectClient.value
      ? { client_id: Number(selectedClientId.value) }
      : {}

    const response = await requestWithRetry(() => api.get('/body-metrics', { params }))
    metrics.value = response.data.data
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to load body metrics.')
    toastStore.error(error.value)
  } finally {
    loading.value = false
  }
}

function validateMetricForm() {
  if (!form.value.recorded_on) {
    return 'Recorded date is required.'
  }

  if (!form.value.body_weight || Number(form.value.body_weight) <= 0) {
    return 'Body weight must be greater than zero.'
  }

  return null
}

async function addMetric() {
  const validationError = validateMetricForm()
  if (validationError) {
    error.value = validationError
    toastStore.error(validationError)
    return
  }

  saving.value = true
  resetMessages()

  try {
    const payload = {
      recorded_on: form.value.recorded_on,
      body_weight: Number(form.value.body_weight),
      notes: form.value.notes || null,
    }

    if (canSelectClient.value) {
      payload.client_id = Number(selectedClientId.value)
    }

    await api.post('/body-metrics', payload)
    toastStore.success('Body metric logged.')
    form.value.body_weight = ''
    form.value.notes = ''
    await loadMetrics()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to add metric.')
    toastStore.error(error.value)
  } finally {
    saving.value = false
  }
}

async function deleteMetric(metricId) {
  deletingMetricId.value = metricId
  resetMessages()

  try {
    await api.delete(`/body-metrics/${metricId}`)
    toastStore.success('Metric deleted.')
    await loadMetrics()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to delete metric.')
    toastStore.error(error.value)
  } finally {
    deletingMetricId.value = null
  }
}

function startEditingMetric(metric) {
  editingMetricId.value = metric.id
  editForm.value = {
    recorded_on: toDateInput(metric.recorded_on),
    body_weight: metric.body_weight,
    notes: metric.notes || '',
  }
}

function cancelEditingMetric() {
  editingMetricId.value = null
  editForm.value = {
    recorded_on: '',
    body_weight: '',
    notes: '',
  }
}

async function updateMetric(metricId) {
  updatingMetricId.value = metricId
  resetMessages()

  try {
    await api.put(`/body-metrics/${metricId}`, {
      recorded_on: editForm.value.recorded_on,
      body_weight: Number(editForm.value.body_weight),
      notes: editForm.value.notes || null,
    })

    toastStore.success('Metric updated.')
    cancelEditingMetric()
    await loadMetrics()
  } catch (e) {
    error.value = getApiErrorMessage(e, 'Unable to update metric.')
    toastStore.error(error.value)
  } finally {
    updatingMetricId.value = null
  }
}

onMounted(async () => {
  if (canSelectClient.value) {
    await referenceStore.fetchClients()
    selectedClientId.value = referenceStore.clients[0]
      ? String(referenceStore.clients[0].id)
      : ''
  }

  await loadMetrics()
})

watch(selectedClientId, loadMetrics)
</script>

<template>
  <section class="space-y-6">
    <Card class="border-border/80 bg-card/90">
      <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <CardTitle class="text-2xl">Body Metrics</CardTitle>
          <CardDescription>
            Track body weight history and notes by date.
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
      Select a client before logging body metrics.
    </p>

    <Card class="border-border/80 bg-card/90">
      <CardHeader>
        <CardTitle>Add Metric</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="addMetric">
          <input v-model="form.recorded_on" type="date" required class="form-control">
          <input v-model="form.body_weight" type="number" required min="0" step="0.1" class="form-control" placeholder="Weight (kg)">
          <input v-model="form.notes" class="h-10 rounded-md border border-input bg-background px-3 text-sm md:col-span-2" placeholder="Notes">
          <Button :disabled="saving || (canSelectClient && !selectedClientId)" type="submit" class="md:col-span-2 xl:col-span-4">
            {{ saving ? 'Saving...' : 'Log body metric' }}
          </Button>
        </form>
      </CardContent>
    </Card>

    <Card class="border-border/80 bg-card/90">
      <CardHeader>
        <CardTitle>Weight Log</CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        <LoadingListSkeleton v-if="loading" :rows="3" />

        <article
          v-for="metric in metrics"
          :key="metric.id"
          class="rounded-lg border border-border/70 bg-background/70 p-3"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold">{{ metric.body_weight }} kg</p>
              <p class="text-xs text-muted-foreground">
                {{ new Date(metric.recorded_on).toLocaleDateString() }}
              </p>
              <p v-if="metric.notes" class="mt-1 text-xs text-muted-foreground">{{ metric.notes }}</p>
            </div>

            <div class="flex gap-2">
              <Button
                size="sm"
                variant="outline"
                @click="startEditingMetric(metric)"
              >
                Edit
              </Button>

              <Button
                size="sm"
                variant="destructive"
                :disabled="deletingMetricId === metric.id"
                @click="deleteMetric(metric.id)"
              >
                Delete
              </Button>
            </div>
          </div>

          <div
            v-if="editingMetricId === metric.id"
            class="mt-3 grid gap-2 rounded-md border border-border/70 bg-card/80 p-3 md:grid-cols-3"
          >
            <input
              v-model="editForm.recorded_on"
              type="date"
              class="form-control-sm"
            >
            <input
              v-model="editForm.body_weight"
              type="number"
              min="0"
              step="0.1"
              class="form-control-sm"
              placeholder="Weight (kg)"
            >
            <input
              v-model="editForm.notes"
              class="form-control-sm"
              placeholder="Notes"
            >

            <div class="md:col-span-3 flex flex-wrap gap-2">
              <Button
                size="sm"
                :disabled="updatingMetricId === metric.id"
                @click="updateMetric(metric.id)"
              >
                {{ updatingMetricId === metric.id ? 'Saving...' : 'Save changes' }}
              </Button>
              <Button size="sm" variant="outline" @click="cancelEditingMetric">
                Cancel
              </Button>
            </div>
          </div>
        </article>

        <p v-if="!loading && metrics.length === 0" class="text-sm text-muted-foreground">
          No metrics available.
        </p>

        <Button v-if="!loading && error" size="sm" variant="outline" @click="loadMetrics">
          Retry loading
        </Button>
      </CardContent>
    </Card>
  </section>
</template>
