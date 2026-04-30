<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useGoalsStore } from '@/stores/goals'
import { profileApi } from '@/api/profile'
import { daysApi } from '@/api/days'
import ASheet from '@/components/ui/ASheet.vue'
import AButton from '@/components/ui/AButton.vue'
import AInput from '@/components/ui/AInput.vue'
import GoalPresets from '@/components/goals/GoalPresets.vue'
import { GOAL_TYPE_LABEL, GOAL_TYPE_DESCRIPTION } from '@/lib/modes'
import type { Goal, GoalType, ActivityLevel, Gender } from '@/types/api'

const props = defineProps<{
  modelValue: boolean
  goal?: Goal | null
}>()

const emit = defineEmits<{
  'update:modelValue': [v: boolean]
  'saved': []
}>()

const goals = useGoalsStore()

const startDate = ref('')
const endDate = ref<string | null>(null)
const noEndDate = ref(true)
const goalType = ref<GoalType>('maintenance')
const goalDraft = ref({ kcal: 2000, proteinG: 150, fatG: 60, carbsG: 200 })
const saving = ref(false)
const error = ref('')

// Profile inputs for the local TDEE-helper used by GoalPresets.
const profileData = ref<{ gender: Gender; birthDate: string; heightCm: number } | null>(null)
const weightKg = ref(80)

// Activity level lives only here — used to estimate average TDEE for the kcal calculator.
// Persisted in localStorage so the user doesn't re-pick it every time.
const ACTIVITY_KEY = 'goal_calc_activity_level'
const activityLevel = ref<ActivityLevel>(
  (typeof localStorage !== 'undefined' && (localStorage.getItem(ACTIVITY_KEY) as ActivityLevel)) || 'light',
)
watch(activityLevel, (v) => {
  if (typeof localStorage !== 'undefined') localStorage.setItem(ACTIVITY_KEY, v)
})

async function bootstrap() {
  try {
    const [profileRes, todayRes] = await Promise.all([
      profileApi.get(),
      daysApi.get(new Date().toISOString().slice(0, 10)),
    ])
    profileData.value = {
      gender: profileRes.data.gender as Gender,
      birthDate: profileRes.data.birthDate,
      heightCm: profileRes.data.heightCm,
    }
    const latestWeight = todayRes.data.measurements[0]?.weightKg
    if (latestWeight) weightKg.value = latestWeight
  } catch {
    // fallback values stay
  }
}

function reset() {
  if (props.goal) {
    startDate.value = props.goal.startDate
    endDate.value = props.goal.endDate
    noEndDate.value = props.goal.endDate === null
    goalType.value = props.goal.type
    goalDraft.value = {
      kcal: props.goal.kcal,
      proteinG: props.goal.proteinG,
      fatG: props.goal.fatG,
      carbsG: props.goal.carbsG,
    }
  } else {
    startDate.value = new Date().toISOString().slice(0, 10)
    endDate.value = null
    noEndDate.value = true
    goalType.value = 'maintenance'
    goalDraft.value = { kcal: 2000, proteinG: 150, fatG: 60, carbsG: 200 }
  }
  error.value = ''
}

watch(() => props.modelValue, async (v) => {
  if (v) {
    await bootstrap()
    reset()
  }
})

const conflictingGoals = computed(() => {
  if (!startDate.value) return []
  const newEnd = noEndDate.value ? null : endDate.value
  return goals.items.filter(g => {
    if (props.goal && g.uuid === props.goal.uuid) return false
    const gEnd = g.endDate
    const gEndsBeforeUs = gEnd !== null && gEnd < startDate.value
    const gStartsAfterUs = newEnd !== null && g.startDate > newEnd
    return !(gEndsBeforeUs || gStartsAfterUs)
  })
})

const hasConflict = computed(() => conflictingGoals.value.length > 0)

function formatShortDate(iso: string): string {
  return new Date(iso + 'T12:00:00').toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      startDate: startDate.value,
      endDate: noEndDate.value ? null : endDate.value,
      type: goalType.value,
      kcal: goalDraft.value.kcal,
      proteinG: goalDraft.value.proteinG,
      fatG: goalDraft.value.fatG,
      carbsG: goalDraft.value.carbsG,
      note: null,
    }
    if (props.goal) {
      await goals.update(props.goal.uuid, payload)
    } else {
      await goals.create(payload)
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    error.value = (e as Error).message ?? 'Не удалось сохранить'
  } finally {
    saving.value = false
  }
}

async function endNow() {
  if (!props.goal) return
  saving.value = true
  try {
    await goals.endGoal(props.goal.uuid)
    emit('saved')
    emit('update:modelValue', false)
  } finally {
    saving.value = false
  }
}

const goalTypeOptions: GoalType[] = ['cut', 'maintenance', 'bulk']
</script>

<template>
  <ASheet
    :model-value="modelValue"
    :title="goal ? 'Изменить цель' : 'Новая цель'"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="flex flex-col gap-4">
      <!-- Goal type — explicit user choice -->
      <div class="flex flex-col gap-1.5">
        <p class="text-sm font-medium" style="color: var(--color-text)">Тип цели</p>
        <div class="grid grid-cols-3 gap-1.5">
          <button
            v-for="t in goalTypeOptions"
            :key="t"
            type="button"
            class="px-3 py-2 rounded-[var(--radius-sm)] text-sm transition-colors"
            :style="{
              background: goalType === t ? 'var(--color-accent)' : 'var(--color-surface-2)',
              color: goalType === t ? 'white' : 'var(--color-text-2)',
              fontWeight: goalType === t ? '600' : '400',
            }"
            @click="goalType = t"
          >{{ GOAL_TYPE_LABEL[t] }}</button>
        </div>
        <p class="text-xs" style="color: var(--color-text-3)">
          {{ GOAL_TYPE_DESCRIPTION[goalType] }}
        </p>
      </div>

      <GoalPresets
        v-model="goalDraft"
        v-model:activity-level="activityLevel"
        :goal-type="goalType"
        :profile="profileData"
        :weight-kg="weightKg"
      />

      <div class="grid grid-cols-2 gap-3">
        <AInput v-model="startDate" label="Начало" type="date" />
        <AInput
          :model-value="endDate ?? ''"
          label="Конец"
          type="date"
          :disabled="noEndDate"
          @update:model-value="endDate = $event || null"
        />
      </div>

      <label class="flex items-center gap-2 text-sm" style="color: var(--color-text-2)">
        <input v-model="noEndDate" type="checkbox" class="cursor-pointer" />
        Без срока (открытая цель)
      </label>

      <div
        v-if="hasConflict"
        class="text-xs p-3 rounded-[var(--radius-sm)] flex flex-col gap-1"
        style="background: var(--color-red-soft); color: var(--color-text-2); border: 1px solid var(--color-red)"
      >
        <p style="color: var(--color-red); font-weight: 500">Цель перекликается с другими</p>
        <p class="mt-0.5" style="color: var(--color-text-2)">
          На каждую дату может быть только одна цель. Сначала завершите или измените даты:
        </p>
        <ul class="flex flex-col gap-0.5 mt-1">
          <li v-for="g in conflictingGoals" :key="g.uuid">
            • {{ g.kcal }} ккал, {{ formatShortDate(g.startDate) }}—{{ g.endDate ? formatShortDate(g.endDate) : 'без срока' }}
          </li>
        </ul>
      </div>

      <p v-if="error" class="text-sm" style="color: var(--color-red)">{{ error }}</p>

      <div class="flex gap-2">
        <AButton
          v-if="goal && !goal.endDate"
          variant="secondary"
          size="md"
          :loading="saving"
          @click="endNow"
        >
          Закончить
        </AButton>
        <AButton size="md" :loading="saving" :disabled="hasConflict" class="flex-1" @click="save">
          {{ goal ? 'Сохранить' : 'Создать' }}
        </AButton>
      </div>
    </div>
  </ASheet>
</template>
