<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { profileApi } from '@/api/profile'
import { ApiError, ValidationError } from '@/api/client'
import { useToast } from '@/composables/useToast'
import AHeader from '@/components/ui/AHeader.vue'
import AInput from '@/components/ui/AInput.vue'
import AButton from '@/components/ui/AButton.vue'
import type { Gender } from '@/types/api'

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string>>({})

const name = ref('')
const gender = ref<Gender>('male')
const birthDateDisplay = ref('') // dd/mm/yyyy
const heightCm = ref('')

function apiToBirthDateDisplay(iso: string): string {
  // Server returns ISO 'YYYY-MM-DD' (or full datetime — take date part).
  const d = iso.slice(0, 10)
  const [y, m, day] = d.split('-')
  return `${day}/${m}/${y}`
}

function birthDateToApi(): string {
  const [d, m, y] = birthDateDisplay.value.split('/')
  return `${y}-${m}-${d}`
}

function onBirthDateInput(raw: string) {
  const digits = raw.replace(/\D/g, '').slice(0, 8)
  let result = digits
  if (digits.length > 2) result = digits.slice(0, 2) + '/' + digits.slice(2)
  if (digits.length > 4) result = result.slice(0, 5) + '/' + digits.slice(4)
  birthDateDisplay.value = result
}

const initialName = ref('')

const canSave = computed(() =>
  name.value.trim().length > 0 &&
  birthDateDisplay.value.length === 10 &&
  heightCm.value !== '' &&
  !isNaN(parseInt(heightCm.value)),
)

onMounted(async () => {
  if (auth.currentUser) {
    name.value = auth.currentUser.name
    initialName.value = auth.currentUser.name
  }
  try {
    const res = await profileApi.get()
    gender.value = res.data.gender
    birthDateDisplay.value = apiToBirthDateDisplay(res.data.birthDate)
    heightCm.value = String(res.data.heightCm)
  } catch (e) {
    // No profile yet — bounce to setup; this view assumes the user has one.
    if (e instanceof ApiError && e.status === 404) {
      router.replace({ name: 'profile-setup' })
      return
    }
    toast.show('Не удалось загрузить профиль', 'error')
  } finally {
    loading.value = false
  }
})

async function save() {
  if (!canSave.value || saving.value) return
  errors.value = {}
  saving.value = true
  try {
    const tasks: Promise<unknown>[] = [
      profileApi.upsert({
        gender: gender.value,
        birthDate: birthDateToApi(),
        heightCm: parseInt(heightCm.value),
      }),
    ]
    if (name.value.trim() !== initialName.value) {
      tasks.push(auth.updateName(name.value.trim()))
    }
    await Promise.all(tasks)
    initialName.value = name.value.trim()
    toast.show('Профиль обновлён', 'success')
    router.back()
  } catch (e) {
    if (e instanceof ValidationError) {
      Object.entries(e.errors).forEach(([k, v]) => { errors.value[k] = v[0] })
    } else {
      toast.show('Не удалось сохранить', 'error')
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="flex flex-col min-h-svh" style="background: var(--color-bg)">
    <AHeader title="Профиль" back back-to="/settings" />

    <div v-if="loading" class="flex flex-col gap-3 p-4">
      <div class="h-12 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
      <div class="h-12 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
      <div class="h-12 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
    </div>

    <form v-else class="p-4 pb-24 flex flex-col gap-4" @submit.prevent="save">
      <AInput
        v-model="name"
        label="Имя"
        placeholder="Как тебя зовут"
        autocomplete="name"
        :error="errors.name"
      />

      <p v-if="auth.currentUser" class="-mt-2 text-xs" style="color: var(--color-text-3)">
        Email: {{ auth.currentUser.email }} (нельзя изменить)
      </p>

      <div class="flex flex-col gap-1.5">
        <p class="text-sm font-medium" style="color: var(--color-text)">Пол</p>
        <div class="flex gap-2">
          <button
            v-for="opt in [{ value: 'male', label: 'Мужской' }, { value: 'female', label: 'Женский' }]"
            :key="opt.value"
            type="button"
            class="flex-1 rounded-[var(--radius-sm)] border py-2.5 text-base transition-colors"
            :style="{
              background: gender === opt.value ? 'var(--color-accent)' : 'var(--color-surface)',
              color: gender === opt.value ? 'white' : 'var(--color-text)',
              borderColor: gender === opt.value ? 'var(--color-accent)' : 'var(--color-border)',
            }"
            @click="gender = opt.value as Gender"
          >{{ opt.label }}</button>
        </div>
        <p v-if="errors.gender" class="text-sm" style="color: var(--color-red)">{{ errors.gender }}</p>
      </div>

      <AInput
        :model-value="birthDateDisplay"
        label="Дата рождения"
        type="text"
        placeholder="дд/мм/гггг"
        inputmode="numeric"
        :error="errors.birthDate"
        @update:model-value="onBirthDateInput"
      />

      <AInput
        v-model="heightCm"
        label="Рост (см)"
        type="number"
        inputmode="numeric"
        placeholder="180"
        :error="errors.heightCm"
      />

      <p class="text-xs" style="color: var(--color-text-3)">
        Вес записывается ежедневно в дневнике, активность — в текущей цели.
      </p>

      <AButton type="submit" size="lg" :loading="saving" :disabled="!canSave" class="w-full mt-2">
        Сохранить
      </AButton>
    </form>
  </div>
</template>
