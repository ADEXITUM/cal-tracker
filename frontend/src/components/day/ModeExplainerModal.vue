<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import ASheet from '@/components/ui/ASheet.vue'
import AButton from '@/components/ui/AButton.vue'
import AModeBadge from '@/components/ui/AModeBadge.vue'
import { MODE_DESCRIPTIONS } from '@/lib/modes'
import type { DayMode, DayGoal, Totals } from '@/types/api'

const props = defineProps<{
  modelValue: boolean
  mode: DayMode | null
  goal: DayGoal | null
  totals: Totals | null
}>()

defineEmits<{ 'update:modelValue': [v: boolean] }>()

const router = useRouter()

const description = computed(() => (props.mode ? MODE_DESCRIPTIONS[props.mode.code] : ''))

const deltaPct = computed(() => {
  if (!props.goal || !props.mode || props.goal.kcal === 0) return 0
  return Math.round((props.mode.deltaKcal / props.goal.kcal) * 100)
})

function goToGoals() {
  router.push({ name: 'goals' })
}
</script>

<template>
  <ASheet :model-value="modelValue" title="Выполнение цели" @update:model-value="$emit('update:modelValue', $event)">
    <div v-if="mode" class="flex flex-col gap-4">
      <div class="flex justify-center">
        <AModeBadge :code="mode.code" :label="mode.label" :delta-kcal="mode.deltaKcal" />
      </div>

      <div class="grid grid-cols-3 gap-2 text-center">
        <div class="px-2 py-3 rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)">
          <p class="text-xs mb-1" style="color: var(--color-text-3)">Съел</p>
          <p class="font-mono text-base" style="color: var(--color-text)">{{ totals ? Math.round(totals.kcal) : '—' }}</p>
        </div>
        <div class="px-2 py-3 rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)">
          <p class="text-xs mb-1" style="color: var(--color-text-3)">Цель</p>
          <p class="font-mono text-base" style="color: var(--color-text)">{{ goal?.kcal ?? '—' }}</p>
        </div>
        <div class="px-2 py-3 rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)">
          <p class="text-xs mb-1" style="color: var(--color-text-3)">Разница</p>
          <p class="font-mono text-base" style="color: var(--color-text)">
            {{ mode.deltaKcal > 0 ? '+' : '' }}{{ mode.deltaKcal }}
          </p>
          <p class="text-[10px] mt-0.5" style="color: var(--color-text-3)">{{ deltaPct > 0 ? '+' : '' }}{{ deltaPct }}%</p>
        </div>
      </div>

      <div class="text-sm leading-relaxed" style="color: var(--color-text-2)">
        {{ description }}
      </div>

      <AButton size="md" class="w-full" @click="goToGoals">Изменить цель</AButton>
    </div>
  </ASheet>
</template>
