<script setup>
import { useToastStore } from '@/stores/toast'

const toastStore = useToastStore()

function toneClass(type) {
  if (type === 'success') {
    return 'border-emerald-300 bg-emerald-50 text-emerald-700'
  }

  if (type === 'error') {
    return 'border-destructive/30 bg-destructive/10 text-destructive'
  }

  return 'border-border/80 bg-card/95 text-foreground'
}
</script>

<template>
  <aside
    class="pointer-events-none fixed right-4 top-4 z-[60] flex w-[min(28rem,calc(100vw-2rem))] flex-col gap-2"
    aria-live="polite"
    aria-atomic="true"
  >
    <div
      v-for="toast in toastStore.items"
      :key="toast.id"
      :class="toneClass(toast.type)"
      class="pointer-events-auto rounded-lg border px-3 py-2 shadow-md"
      role="status"
    >
      <div class="flex items-start justify-between gap-3">
        <p class="text-sm">{{ toast.message }}</p>
        <button
          type="button"
          class="rounded px-1 text-xs opacity-70 hover:opacity-100"
          aria-label="Dismiss notification"
          @click="toastStore.dismiss(toast.id)"
        >
          Close
        </button>
      </div>
    </div>
  </aside>
</template>
