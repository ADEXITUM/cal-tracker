import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { guest: true },
    },
    {
      path: '/profile/setup',
      name: 'profile-setup',
      component: () => import('@/views/ProfileSetupView.vue'),
      meta: { auth: true, requiresProfile: false },
    },
    {
      path: '/day/:date?',
      name: 'day',
      component: () => import('@/views/DayView.vue'),
      meta: { auth: true },
    },
    {
      path: '/',
      redirect: '/day',
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.auth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'day' }
  }

  if (
    to.meta.auth &&
    to.meta.requiresProfile !== false &&
    auth.currentUser &&
    !auth.currentUser.hasProfile
  ) {
    return { name: 'profile-setup' }
  }
})

export default router
