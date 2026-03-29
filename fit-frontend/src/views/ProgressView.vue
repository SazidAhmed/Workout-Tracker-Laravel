<script setup>
import { computed } from 'vue'
import { BarChart3, RotateCcw, Sparkles } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { useWorkoutStore } from '@/stores/workout'

const workoutStore = useWorkoutStore()

const statusTone = computed(() =>
  workoutStore.completionRate >= 100 ? 'secondary' : 'outline',
)
</script>

<template>
  <section class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
    <Card class="border-border/70 bg-card shadow-sm">
      <CardHeader class="space-y-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <CardTitle>Weekly progress</CardTitle>
            <CardDescription>
              This view reads the same Pinia store as the overview page.
            </CardDescription>
          </div>
          <Badge :variant="statusTone">
            {{ workoutStore.completionRate }}% complete
          </Badge>
        </div>
      </CardHeader>
      <CardContent class="space-y-5">
        <div class="space-y-2">
          <div class="flex items-center justify-between text-sm">
            <span class="text-muted-foreground">Goal completion</span>
            <span class="font-medium">{{ workoutStore.completedSessions }} of {{ workoutStore.weeklyGoal }}</span>
          </div>
          <div class="h-3 overflow-hidden rounded-full bg-muted">
            <div
              class="h-full rounded-full bg-primary transition-[width]"
              :style="{ width: `${Math.min(workoutStore.completionRate, 100)}%` }"
            />
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <article class="rounded-2xl border border-border/70 bg-muted/30 p-4">
            <BarChart3 class="mb-3 size-5 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">Sessions remaining</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight">{{ workoutStore.sessionsLeft }}</p>
          </article>
          <article class="rounded-2xl border border-border/70 bg-muted/30 p-4">
            <Sparkles class="mb-3 size-5 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">Recovery score</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight">{{ workoutStore.energyScore }}%</p>
          </article>
        </div>
      </CardContent>
      <CardFooter class="flex flex-wrap gap-3">
        <Button @click="workoutStore.logWorkout()">Add another session</Button>
        <Button variant="outline" @click="workoutStore.resetWeek()">
          <RotateCcw class="size-4" />
          Reset week
        </Button>
      </CardFooter>
    </Card>

    <Card class="border-border/70 bg-card shadow-sm">
      <CardHeader>
        <CardTitle>Store snapshot</CardTitle>
        <CardDescription>
          Useful as a starting point while you replace the demo state with real data.
        </CardDescription>
      </CardHeader>
      <CardContent class="grid gap-3">
        <div class="flex items-center justify-between rounded-2xl border border-border/70 p-4">
          <span class="text-sm text-muted-foreground">Weekly goal</span>
          <span class="font-medium">{{ workoutStore.weeklyGoal }}</span>
        </div>
        <div class="flex items-center justify-between rounded-2xl border border-border/70 p-4">
          <span class="text-sm text-muted-foreground">Completed sessions</span>
          <span class="font-medium">{{ workoutStore.completedSessions }}</span>
        </div>
        <div class="flex items-center justify-between rounded-2xl border border-border/70 p-4">
          <span class="text-sm text-muted-foreground">Current streak</span>
          <span class="font-medium">{{ workoutStore.streakDays }} days</span>
        </div>
        <div class="flex items-center justify-between rounded-2xl border border-border/70 p-4">
          <span class="text-sm text-muted-foreground">Energy score</span>
          <span class="font-medium">{{ workoutStore.energyScore }}%</span>
        </div>
      </CardContent>
    </Card>
  </section>
</template>
