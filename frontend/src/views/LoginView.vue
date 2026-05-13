<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { NetworkError, ValidationError } from '@/api/client'
import { isNavigationFailure } from 'vue-router'
import AButton from '@/components/ui/AButton.vue'
import AInput from '@/components/ui/AInput.vue'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const errors = ref<Record<string, string>>({})
const globalError = ref('')

async function submit() {
  loading.value = true
  errors.value = {}
  globalError.value = ''
  try {
    await auth.login(email.value, password.value, navigator.userAgent.slice(0, 100))
    await router.push({ name: 'day' })
  } catch (e) {
    if (isNavigationFailure(e)) return
    if (e instanceof ValidationError) {
      Object.entries(e.errors).forEach(([k, v]) => { errors.value[k] = v[0] })
    } else if (e instanceof NetworkError || !navigator.onLine) {
      globalError.value = 'Нет соединения. Проверьте интернет и попробуйте ещё раз.'
    } else {
      globalError.value = 'Не удалось войти. Попробуйте ещё раз.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-svh flex-col items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
      <h1 class="mb-8 text-center font-mono text-3xl font-light" style="color: var(--color-accent)">
        Кал Трекер
      </h1>

      <form class="flex flex-col gap-4" @submit.prevent="submit">
        <AInput
          v-model="email"
          label="Email"
          type="email"
          autocomplete="email"
          placeholder="you@example.com"
          :error="errors.email"
        />
        <AInput
          v-model="password"
          label="Пароль"
          type="password"
          autocomplete="current-password"
          placeholder="••••••••"
          :error="errors.password"
        />

        <p v-if="globalError" class="text-sm" style="color: var(--color-red)">{{ globalError }}</p>

        <AButton type="submit" size="lg" :loading="loading" class="w-full mt-2">
          Войти
        </AButton>
      </form>
    </div>
  </div>
</template>
