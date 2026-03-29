import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api'

export const useReferenceStore = defineStore('reference', () => {
  const clients = ref([])
  const trainers = ref([])
  const exercises = ref([])
  const loading = ref(false)

  async function fetchClients() {
    loading.value = true
    try {
      const response = await api.get('/clients')
      clients.value = response.data.data
      return clients.value
    } finally {
      loading.value = false
    }
  }

  async function fetchTrainers() {
    loading.value = true
    try {
      const response = await api.get('/trainers')
      trainers.value = response.data.data
      return trainers.value
    } finally {
      loading.value = false
    }
  }

  async function fetchExercises() {
    loading.value = true
    try {
      const response = await api.get('/exercises')
      exercises.value = response.data.data
      return exercises.value
    } finally {
      loading.value = false
    }
  }

  return {
    clients,
    trainers,
    exercises,
    loading,
    fetchClients,
    fetchTrainers,
    fetchExercises,
  }
})
