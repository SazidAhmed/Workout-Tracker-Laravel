<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { BellRing, CheckCheck } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import LoadingListSkeleton from '@/components/LoadingListSkeleton.vue'
import { getApiErrorMessage } from '@/lib/http'
import { useNotificationsStore } from '@/stores/notifications'
import { useToastStore } from '@/stores/toast'

const notificationsStore = useNotificationsStore()
const toastStore = useToastStore()
const router = useRouter()

const page = ref(1)
const perPage = 12

const notifications = computed(() => notificationsStore.items)
const meta = computed(() => notificationsStore.meta)
const loading = computed(() => notificationsStore.loading)

async function loadNotifications() {
  try {
    await notificationsStore.fetchNotifications({ page: page.value, per_page: perPage })
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to load notifications.'))
  }
}

async function markAsRead(notification) {
  if (notification.read_at) {
    if (notification.action_url) {
      router.push(notification.action_url)
    }
    return
  }

  try {
    await notificationsStore.markAsRead(notification.id)

    if (notification.action_url) {
      router.push(notification.action_url)
    }
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to mark notification as read.'))
  }
}

async function markAll() {
  try {
    await notificationsStore.markAllAsRead()
    toastStore.success('All notifications marked as read.')
  } catch (e) {
    toastStore.error(getApiErrorMessage(e, 'Unable to mark all notifications as read.'))
  }
}

async function goToPage(targetPage) {
  page.value = targetPage
  await loadNotifications()
}

onMounted(loadNotifications)
</script>

<template>
  <section class="space-y-6">
    <Card class="border-border/80 bg-card/90">
      <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <CardTitle class="inline-flex items-center gap-2 text-2xl">
            <BellRing class="size-5" />
            Notifications
          </CardTitle>
          <CardDescription>
            Workout reminders and follow-ups for your training workflow.
          </CardDescription>
        </div>

        <div class="flex items-center gap-2">
          <Badge variant="outline">Unread: {{ notificationsStore.unreadCount }}</Badge>
          <Button
            size="sm"
            variant="outline"
            :disabled="notificationsStore.unreadCount === 0"
            @click="markAll"
          >
            <CheckCheck class="size-4" />
            Mark all read
          </Button>
        </div>
      </CardHeader>
    </Card>

    <Card class="border-border/80 bg-card/90">
      <CardContent class="space-y-3 pt-6">
        <LoadingListSkeleton v-if="loading" :rows="4" />

        <article
          v-for="notification in notifications"
          :key="notification.id"
          class="rounded-lg border border-border/70 bg-background/70 p-4"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
              <p class="text-sm font-semibold">{{ notification.title }}</p>
              <p class="text-sm text-muted-foreground">{{ notification.message }}</p>
              <p class="text-xs text-muted-foreground">
                {{ new Date(notification.created_at).toLocaleString() }}
              </p>
            </div>

            <div class="flex items-center gap-2">
              <Badge :variant="notification.read_at ? 'secondary' : 'default'">
                {{ notification.read_at ? 'Read' : 'Unread' }}
              </Badge>

              <Button
                size="sm"
                variant="outline"
                @click="markAsRead(notification)"
              >
                {{ notification.read_at ? 'Open' : 'Mark read' }}
              </Button>
            </div>
          </div>
        </article>

        <p v-if="!loading && notifications.length === 0" class="text-sm text-muted-foreground">
          No notifications yet.
        </p>

        <div v-if="!loading && (meta.total || 0) > 0" class="flex items-center justify-between gap-3 text-sm">
          <p class="text-muted-foreground">Page {{ meta.current_page }} of {{ meta.last_page }}</p>
          <div class="flex gap-2">
            <Button
              size="sm"
              variant="outline"
              :disabled="meta.current_page <= 1"
              @click="goToPage(meta.current_page - 1)"
            >
              Previous
            </Button>
            <Button
              size="sm"
              variant="outline"
              :disabled="meta.current_page >= meta.last_page"
              @click="goToPage(meta.current_page + 1)"
            >
              Next
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  </section>
</template>
