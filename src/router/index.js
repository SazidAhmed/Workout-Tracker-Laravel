import { createRouter, createWebHistory } from 'vue-router'
import AppShell from '@/components/AppShell.vue'
import LoginView from '@/views/auth/LoginView.vue'
import ForgotPasswordView from '@/views/auth/ForgotPasswordView.vue'
import ResetPasswordView from '@/views/auth/ResetPasswordView.vue'
import ActivateInvitationView from '@/views/auth/ActivateInvitationView.vue'
import VerifiedView from '@/views/auth/VerifiedView.vue'
import VerifyRequiredView from '@/views/auth/VerifyRequiredView.vue'
import DashboardSummaryView from '@/views/app/DashboardSummaryView.vue'
import UsersView from '@/views/app/UsersView.vue'
import ExercisesView from '@/views/app/ExercisesView.vue'
import ProgramsView from '@/views/app/ProgramsView.vue'
import SessionsView from '@/views/app/SessionsView.vue'
import UpcomingWorkoutsView from '@/views/app/UpcomingWorkoutsView.vue'
import BodyMetricsView from '@/views/app/BodyMetricsView.vue'
import ProgressView from '@/views/app/ProgressView.vue'
import NotificationsView from '@/views/app/NotificationsView.vue'
import { useAuthStore } from '@/stores/auth'
import { pinia } from '@/stores'
import { resolveRouteDecision } from '@/router/navigation'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guestOnly: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: ForgotPasswordView,
      meta: { guestOnly: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: ResetPasswordView,
      meta: { guestOnly: true },
    },
    {
      path: '/activate/:token',
      name: 'activate-invitation',
      component: ActivateInvitationView,
      meta: { guestOnly: true },
    },
    {
      path: '/auth/verified',
      name: 'verified',
      component: VerifiedView,
    },
    {
      path: '/auth/verify-required',
      name: 'verify-required',
      component: VerifyRequiredView,
      meta: { requiresAuth: true, allowUnverified: true },
    },
    {
      path: '/app',
      component: AppShell,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: DashboardSummaryView,
        },
        {
          path: 'users',
          name: 'users',
          component: UsersView,
          meta: { roles: ['admin', 'trainer'] },
        },
        {
          path: 'exercises',
          name: 'exercises',
          component: ExercisesView,
        },
        {
          path: 'programs',
          name: 'programs',
          component: ProgramsView,
        },
        {
          path: 'sessions',
          name: 'sessions',
          component: SessionsView,
        },
        {
          path: 'upcoming',
          name: 'upcoming-workouts',
          component: UpcomingWorkoutsView,
        },
        {
          path: 'metrics',
          name: 'body-metrics',
          component: BodyMetricsView,
        },
        {
          path: 'progress',
          name: 'progress',
          component: ProgressView,
        },
        {
          path: 'notifications',
          name: 'notifications',
          component: NotificationsView,
        },
      ],
    },
    {
      path: '/',
      redirect: '/app',
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/app',
    },
  ],
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore(pinia)

  await authStore.initialize()

  return resolveRouteDecision({
    to,
    isAuthenticated: authStore.isAuthenticated,
    isVerified: authStore.isVerified,
    role: authStore.role,
  })
})

export default router
