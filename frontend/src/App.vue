<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BottomNav from '@/components/layout/BottomNav.vue'
import SyncIndicator from '@/components/layout/SyncIndicator.vue'
import UpdatePrompt from '@/components/layout/UpdatePrompt.vue'
import ToastContainer from '@/components/ui/ToastContainer.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const showNav = computed(() => auth.isAuthenticated && route.meta.hideNav !== true)
</script>

<template>
  <RouterView v-slot="{ Component, route: r }">
    <Transition name="page" mode="out-in">
      <!-- key by route name (not path) so navigating between dates within
           /day/:date doesn't remount DayView and re-fetch from scratch -->
      <component :is="Component" :key="r.name as string" />
    </Transition>
  </RouterView>
  <SyncIndicator v-if="auth.isAuthenticated" />
  <BottomNav v-if="showNav" />
  <UpdatePrompt />
  <ToastContainer />
</template>
