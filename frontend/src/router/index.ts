import { createRouter, createWebHistory } from 'vue-router'
import { watch } from 'vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true, hideNav: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { guest: true, hideNav: true },
    },
    {
      path: '/profile/setup',
      name: 'profile-setup',
      component: () => import('@/views/ProfileSetupView.vue'),
      meta: { auth: true, requiresProfile: false, hideNav: true },
    },
    {
      path: '/day/:date?',
      name: 'day',
      component: () => import('@/views/DayView.vue'),
      meta: { auth: true },
    },
    {
      path: '/dishes',
      name: 'dishes',
      component: () => import('@/views/DishesView.vue'),
      meta: { auth: true },
    },
    {
      path: '/',
      redirect: '/day',
    },
  ],
})

function waitForInit(auth: ReturnType<typeof useAuthStore>): Promise<void> {
  if (auth.isInitialized) return Promise.resolve()
  return new Promise(resolve => {
    const stop = watch(() => auth.isInitialized, init => { if (init) { stop(); resolve() } })
  })
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await waitForInit(auth)

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
