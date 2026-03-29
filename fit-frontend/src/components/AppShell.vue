<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { Bell, CalendarDays, ChartSpline, ClipboardList, Dumbbell, LogOut, Ruler, UserCog, Users } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { Button } from '@/components/ui/button'

const authStore = useAuthStore()
const notificationsStore = useNotificationsStore()
const router = useRouter()
const menuOpen = ref(false)
const pollerId = ref(null)

const unreadCount = computed(() => notificationsStore.unreadCount)

const navItems = computed(() => {
  const common = [
    { label: 'Dashboard', to: '/app', icon: Dumbbell },
    { label: 'Notifications', to: '/app/notifications', icon: Bell },
    { label: 'Exercises', to: '/app/exercises', icon: Dumbbell },
    { label: 'Programs', to: '/app/programs', icon: ClipboardList },
    { label: 'Upcoming', to: '/app/upcoming', icon: CalendarDays },
    { label: 'Sessions', to: '/app/sessions', icon: Dumbbell },
    { label: 'Metrics', to: '/app/metrics', icon: Ruler },
    { label: 'Progress', to: '/app/progress', icon: ChartSpline },
  ]

  if (authStore.role === 'admin') {
    return [{ label: 'Users', to: '/app/users', icon: UserCog }, ...common]
  }

  if (authStore.role === 'trainer') {
    return [{ label: 'Clients', to: '/app/users', icon: Users }, ...common]
  }

  return common
})

async function logout() {
  await authStore.logout()
  notificationsStore.clear()
  router.push('/login')
}

async function refreshUnreadCount() {
  try {
    await notificationsStore.refreshUnreadCount()
  } catch {
    // Keep navigation responsive even if notification refresh fails.
  }
}

onMounted(async () => {
  await refreshUnreadCount()
  pollerId.value = window.setInterval(refreshUnreadCount, 60_000)
})

onBeforeUnmount(() => {
  if (pollerId.value) {
    window.clearInterval(pollerId.value)
    pollerId.value = null
  }
})
</script>

<template>
  <div class="relative min-h-screen overflow-hidden">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-[80] focus:rounded-md focus:bg-card focus:px-3 focus:py-2 focus:text-sm">
      Skip to main content
    </a>
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(237,137,54,0.16),_transparent_42%),radial-gradient(circle_at_bottom_right,_rgba(20,184,166,0.14),_transparent_44%)]" />

    <header class="sticky top-0 z-30 border-b border-border/70 bg-background/88 backdrop-blur">
      <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <RouterLink to="/app" class="flex items-center gap-3">
          <div class="grid size-10 place-items-center rounded-xl bg-primary text-primary-foreground shadow-lg shadow-primary/20">
            <Dumbbell class="size-5" />
          </div>
          <div>
            <p class="text-sm font-bold tracking-tight">Fit Coach</p>
            <p class="text-xs text-muted-foreground">{{ authStore.user?.role }} portal</p>
          </div>
        </RouterLink>

        <button
          class="inline-flex h-9 items-center rounded-md border border-border/70 px-3 text-sm md:hidden"
          type="button"
          aria-label="Toggle navigation menu"
          @click="menuOpen = !menuOpen"
        >
          Menu
        </button>

        <nav class="hidden items-center gap-1 md:flex" aria-label="Primary navigation">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-muted-foreground transition hover:text-foreground"
            active-class="bg-primary text-primary-foreground hover:text-primary-foreground"
          >
            {{ item.label }}
            <span
              v-if="item.to === '/app/notifications' && unreadCount > 0"
              class="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold leading-none text-primary-foreground"
            >
              {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
          </RouterLink>
        </nav>

        <div class="hidden items-center gap-3 md:flex">
          <div class="text-right">
            <p class="text-sm font-medium">{{ authStore.user?.name }}</p>
            <p class="text-xs text-muted-foreground">{{ authStore.user?.email }}</p>
          </div>
          <Button variant="outline" size="sm" @click="logout">
            <LogOut class="size-4" />
            Sign out
          </Button>
        </div>
      </div>

      <div v-if="menuOpen" class="border-t border-border/70 px-4 py-3 md:hidden">
        <div class="mb-3 flex flex-wrap gap-2">
          <RouterLink
            v-for="item in navItems"
            :key="item.label"
            :to="item.to"
            class="inline-flex items-center gap-1 rounded-full border border-border/70 px-3 py-1.5 text-sm"
            active-class="bg-primary text-primary-foreground"
            @click="menuOpen = false"
          >
            {{ item.label }}
            <span
              v-if="item.to === '/app/notifications' && unreadCount > 0"
              class="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold leading-none text-primary-foreground"
            >
              {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
          </RouterLink>
        </div>

        <Button variant="outline" size="sm" class="w-full" @click="logout">
          <LogOut class="size-4" />
          Sign out
        </Button>
      </div>
    </header>

    <main id="main-content" class="relative z-10 mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8" tabindex="-1">
      <RouterView />
    </main>
  </div>
</template>
