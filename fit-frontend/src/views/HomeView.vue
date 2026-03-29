<script setup>
import { computed } from 'vue'
import { Activity, ArrowRight, Flame, Target } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
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

const quickStats = computed(() => [
  {
    label: 'Weekly goal',
    value: `${workoutStore.completedSessions}/${workoutStore.weeklyGoal}`,
    hint: `${workoutStore.sessionsLeft} sessions remaining`,
    icon: Target,
  },
  {
    label: 'Current streak',
    value: `${workoutStore.streakDays} days`,
    hint: 'Consistency compounds quickly',
    icon: Flame,
  },
  {
    label: 'Energy score',
    value: `${workoutStore.energyScore}%`,
    hint: 'Recovered and ready',
    icon: Activity,
  },
])
</script>

<template>
  <section class="grid gap-6 lg:grid-cols-[1.35fr_0.95fr]">
    <Card class="overflow-hidden border-border/70 bg-card shadow-sm">
      <CardHeader class="space-y-6 border-b border-border/70 bg-gradient-to-br from-background via-background to-muted/40">
        <div class="flex flex-wrap items-center gap-3">
          <Badge variant="secondary">Starter dashboard</Badge>
          <Badge variant="outline">Pinia-backed state</Badge>
          <Badge variant="outline">shadcn-vue components</Badge>
        </div>
        <div class="max-w-2xl space-y-3">
          <CardTitle class="text-4xl tracking-tight sm:text-5xl">
            Build your app on a real Vue stack, not the default placeholder.
          </CardTitle>
          <CardDescription class="max-w-xl text-base leading-7 text-muted-foreground">
            This starter now includes routed views, shared Pinia state, and a
            shadcn-vue component setup ready for additional UI primitives.
          </CardDescription>
        </div>
      </CardHeader>
      <CardContent class="grid gap-4 p-6 sm:grid-cols-3">
        <article
          v-for="stat in quickStats"
          :key="stat.label"
          class="rounded-2xl border border-border/70 bg-muted/30 p-4"
        >
          <component :is="stat.icon" class="mb-4 size-5 text-muted-foreground" />
          <p class="text-sm text-muted-foreground">{{ stat.label }}</p>
          <p class="mt-2 text-2xl font-semibold tracking-tight">{{ stat.value }}</p>
          <p class="mt-2 text-sm text-muted-foreground">{{ stat.hint }}</p>
        </article>
      </CardContent>
      <CardFooter class="flex flex-col items-start gap-3 border-t border-border/70 px-6 py-5 sm:flex-row sm:items-center">
        <Button @click="workoutStore.logWorkout()">Log workout</Button>
        <RouterLink
          to="/progress"
          class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
        >
          Open progress view
          <ArrowRight class="size-4" />
        </RouterLink>
      </CardFooter>
    </Card>

    <Card class="border-border/70 bg-card shadow-sm">
      <CardHeader>
        <CardTitle>What was added</CardTitle>
        <CardDescription>
          The project is now wired for common Vue application patterns.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="rounded-2xl border border-border/70 p-4">
          <p class="text-sm font-medium">Vue Router</p>
          <p class="mt-2 text-sm leading-6 text-muted-foreground">
            Route records live under <code class="rounded bg-muted px-1.5 py-1 text-xs">src/router</code>
            and the app shell renders them through <code class="rounded bg-muted px-1.5 py-1 text-xs">RouterView</code>.
          </p>
        </div>
        <div class="rounded-2xl border border-border/70 p-4">
          <p class="text-sm font-medium">Pinia</p>
          <p class="mt-2 text-sm leading-6 text-muted-foreground">
            Shared workout metrics come from a single setup store, so both pages
            stay in sync.
          </p>
        </div>
        <div class="rounded-2xl border border-border/70 p-4">
          <p class="text-sm font-medium">shadcn-vue</p>
          <p class="mt-2 text-sm leading-6 text-muted-foreground">
            Neutral theme tokens, path aliases, and starter UI components are in
            place for rapid component additions via the CLI.
          </p>
        </div>
      </CardContent>
    </Card>
  </section>
</template>
