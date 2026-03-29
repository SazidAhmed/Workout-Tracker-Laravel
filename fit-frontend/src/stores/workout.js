import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

export const useWorkoutStore = defineStore('workout', () => {
  const weeklyGoal = ref(4)
  const completedSessions = ref(2)
  const streakDays = ref(6)
  const energyScore = ref(78)

  const sessionsLeft = computed(() =>
    Math.max(weeklyGoal.value - completedSessions.value, 0),
  )
  const completionRate = computed(() =>
    Math.round((completedSessions.value / weeklyGoal.value) * 100),
  )

  function logWorkout() {
    completedSessions.value += 1
    streakDays.value += 1
    energyScore.value = Math.min(100, energyScore.value + 4)
  }

  function resetWeek() {
    completedSessions.value = 0
    streakDays.value = 0
    energyScore.value = 72
  }

  return {
    weeklyGoal,
    completedSessions,
    streakDays,
    energyScore,
    sessionsLeft,
    completionRate,
    logWorkout,
    resetWeek,
  }
})
