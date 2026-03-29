<script setup>
defineProps({
  rows: {
    type: Number,
    default: 3,
  },
})
</script>

<template>
  <div class="space-y-3" role="status" aria-live="polite">
    <div
      v-for="index in rows"
      :key="index"
      class="animate-pulse rounded-lg border border-border/70 bg-card/70 p-4"
    >
      <div class="mb-3 h-4 w-1/3 rounded bg-muted" />
      <div class="h-3 w-2/3 rounded bg-muted" />
    </div>
  </div>
</template>
