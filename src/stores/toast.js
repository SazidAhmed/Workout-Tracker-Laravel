import { ref } from 'vue'
import { defineStore } from 'pinia'

let nextToastId = 1

export const useToastStore = defineStore('toast', () => {
  const items = ref([])

  function push(message, type = 'info', timeoutMs = 3600) {
    const id = nextToastId++
    items.value.push({ id, message, type })

    if (timeoutMs > 0) {
      window.setTimeout(() => {
        dismiss(id)
      }, timeoutMs)
    }

    return id
  }

  function success(message, timeoutMs) {
    return push(message, 'success', timeoutMs)
  }

  function error(message, timeoutMs) {
    return push(message, 'error', timeoutMs)
  }

  function info(message, timeoutMs) {
    return push(message, 'info', timeoutMs)
  }

  function dismiss(id) {
    items.value = items.value.filter((item) => item.id !== id)
  }

  return {
    items,
    push,
    success,
    error,
    info,
    dismiss,
  }
})
