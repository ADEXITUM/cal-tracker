<script setup lang="ts">
import { computed, ref } from 'vue'
import { defaultMacroSplit, GOAL_TYPE_DELTA, type MacroSplit } from '@/lib/modes'
import { ACTIVITY_LABEL, estimateAverageTdee } from '@/lib/tdee'
import ACard from '@/components/ui/ACard.vue'
import type { ActivityLevel, Gender, GoalType } from '@/types/api'

const props = defineProps<{
  modelValue: { kcal: number; proteinG: number; fatG: number; carbsG: number }
  goalType: GoalType
  weightKg: number
  profile: { gender: Gender; birthDate: string; heightCm: number } | null
  activityLevel: ActivityLevel
}>()

const emit = defineEmits<{
  'update:modelValue': [v: { kcal: number; proteinG: number; fatG: number; carbsG: number }]
  'update:activityLevel': [v: ActivityLevel]
}>()

const showFormula = ref(false)
const customMode = ref(false)

const ACTIVITY_OPTIONS: ActivityLevel[] = ['sedentary', 'light', 'moderate', 'active']

/** Estimated average TDEE for this user based on the locally chosen activity level. */
const estimatedTdee = computed(() => {
  if (!props.profile) return 0
  return estimateAverageTdee({
    gender: props.profile.gender,
    birthDate: props.profile.birthDate,
    heightCm: props.profile.heightCm,
    weightKg: props.weightKg,
    activityLevel: props.activityLevel,
  })
})

/** Suggested kcal for the current goal type, given the estimated TDEE. */
const suggestedKcal = computed(() => {
  if (!estimatedTdee.value) return 0
  return Math.max(800, estimatedTdee.value + GOAL_TYPE_DELTA[props.goalType])
})

const suggestedSplit = computed<MacroSplit>(() => defaultMacroSplit(suggestedKcal.value, props.weightKg))

function applySuggested() {
  emit('update:modelValue', suggestedSplit.value)
  customMode.value = false
}

function updateCustom(field: 'kcal' | 'proteinG' | 'fatG' | 'carbsG', val: string) {
  const num = parseInt(val) || 0
  customMode.value = true
  emit('update:modelValue', { ...props.modelValue, [field]: num })
}

const isUsingSuggested = computed(() =>
  !customMode.value
    && props.modelValue.kcal === suggestedSplit.value.kcal
    && props.modelValue.proteinG === suggestedSplit.value.proteinG
    && props.modelValue.fatG === suggestedSplit.value.fatG
    && props.modelValue.carbsG === suggestedSplit.value.carbsG,
)
</script>

<template>
  <div class="flex flex-col gap-3">
    <!-- Activity level selector — local only, doesn't go to profile -->
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center justify-between">
        <p class="text-sm font-medium" style="color: var(--color-text)">Активность во время цели</p>
        <button
          type="button"
          class="text-xs underline"
          style="color: var(--color-text-3)"
          @click="showFormula = !showFormula"
        >как считается?</button>
      </div>
      <select
        :value="activityLevel"
        class="w-full rounded-[var(--radius-sm)] border px-3 py-2 text-sm outline-none"
        style="background: var(--color-surface); border-color: var(--color-border); color: var(--color-text)"
        @change="$emit('update:activityLevel', ($event.target as HTMLSelectElement).value as ActivityLevel)"
      >
        <option v-for="a in ACTIVITY_OPTIONS" :key="a" :value="a">{{ ACTIVITY_LABEL[a] }}</option>
      </select>
      <p class="text-xs" style="color: var(--color-text-3)">
        Используется только чтобы предложить число. После сохранения активность не хранится — двигайся как хочешь.
      </p>
    </div>

    <div
      v-if="showFormula"
      class="text-xs p-3 rounded-[var(--radius-sm)]"
      style="background: var(--color-surface-2); color: var(--color-text-2)"
    >
      <p class="mb-1">
        <strong>Средний TDEE</strong> ≈ BMR × коэф. активности = <strong>{{ estimatedTdee }}</strong> ккал
      </p>
      <p class="mb-1"><strong>Предложение по типу:</strong></p>
      <ul class="list-disc pl-5 space-y-0.5">
        <li>Сушка: TDEE − 400</li>
        <li>Поддержка: TDEE</li>
        <li>Набор: TDEE + 300</li>
      </ul>
      <p class="mt-2 mb-1"><strong>БЖУ-сплит:</strong></p>
      <ul class="list-disc pl-5 space-y-0.5">
        <li>Белки = 1.8 × вес ({{ Math.round(weightKg * 1.8) }} г)</li>
        <li>Жиры = 25% ккал ÷ 9</li>
        <li>Углеводы = остаток ÷ 4</li>
      </ul>
    </div>

    <!-- Suggested preset card -->
    <ACard
      v-if="suggestedKcal > 0"
      :class="['cursor-pointer transition-all', isUsingSuggested ? 'ring-2' : '']"
      :style="{
        '--tw-ring-color': isUsingSuggested ? 'var(--color-accent)' : 'transparent',
        borderColor: isUsingSuggested ? 'var(--color-accent)' : undefined,
      }"
      @click="applySuggested"
    >
      <div class="px-3 py-2.5 flex items-center justify-between">
        <div>
          <p class="text-sm font-semibold" style="color: var(--color-text)">Предложение</p>
          <p class="text-xs mt-0.5" style="color: var(--color-text-3)">
            {{ suggestedSplit.kcal }} ккал · Б{{ suggestedSplit.proteinG }} Ж{{ suggestedSplit.fatG }} У{{ suggestedSplit.carbsG }}
          </p>
        </div>
        <span
          v-if="estimatedTdee"
          class="text-xs px-2 py-0.5 rounded-full"
          style="background: var(--color-surface-2); color: var(--color-text-2)"
        >
          {{ GOAL_TYPE_DELTA[goalType] === 0 ? '= TDEE' : (GOAL_TYPE_DELTA[goalType] > 0 ? '+' : '') + GOAL_TYPE_DELTA[goalType] }}
        </span>
      </div>
    </ACard>

    <!-- Custom inputs always visible -->
    <div class="flex flex-col gap-2">
      <p class="text-sm font-medium" style="color: var(--color-text)">Точные числа</p>
      <div class="grid grid-cols-2 gap-2">
        <div v-for="f in [
          { key: 'kcal', label: 'Ккал' },
          { key: 'proteinG', label: 'Белки (г)' },
          { key: 'fatG', label: 'Жиры (г)' },
          { key: 'carbsG', label: 'Углеводы (г)' },
        ]" :key="f.key">
          <label class="text-xs mb-1 block" style="color: var(--color-text-3)">{{ f.label }}</label>
          <input
            type="number"
            :value="(modelValue as Record<string, number>)[f.key]"
            class="w-full rounded-[var(--radius-sm)] border px-2 py-1.5 text-sm outline-none"
            style="background: var(--color-bg); border-color: var(--color-border); color: var(--color-text)"
            @input="updateCustom(f.key as 'kcal' | 'proteinG' | 'fatG' | 'carbsG', ($event.target as HTMLInputElement).value)"
          />
        </div>
      </div>
    </div>
  </div>
</template>
