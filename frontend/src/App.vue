<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BottomNav from '@/components/layout/BottomNav.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const showNav = computed(() => auth.isAuthenticated && route.meta.hideNav !== true)
</script>

<template>
  <RouterView />
  <BottomNav v-if="showNav" />
</template>
