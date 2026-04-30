<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useDayStore } from '@/stores/day'
import { stepsKcal } from '@/lib/tdee'
import ASheet from '@/components/ui/ASheet.vue'
import AButton from '@/components/ui/AButton.vue'
import ANumpad from '@/components/ui/ANumpad.vue'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [v: boolean] }>()

const day = useDayStore()
const stepsStr = ref('0')
const loading = ref(false)

const steps = computed(() => {
  const n = parseInt(stepsStr.value, 10)
  return isNaN(n) || n < 0 ? 0 : n
})

const weightKg = computed(() => day.data?.measurements[0]?.weightKg ?? 80)

const kcalEstimate = computed(() => stepsKcal(steps.value, weightKg.value))

const isEditing = computed(() => (day.data?.dayEntry?.steps ?? 0) > 0)

function open() {
  stepsStr.value = String(day.data?.dayEntry?.steps ?? 0)
}

watch(() => props.modelValue, (v) => { if (v) open() })

async function save() {
  loading.value = true
  try {
    await day.updateDayEntry({ steps: steps.value })
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}

async function clear() {
  loading.value = true
  try {
    await day.updateDayEntry({ steps: 0 })
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <ASheet
    :model-value="modelValue"
    :title="isEditing ? 'Шаги за день' : 'Добавить шаги'"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <ANumpad v-model="stepsStr" label="Шаги" unit="" :allow-decimal="false" />

    <div class="mt-4 grid grid-cols-2 gap-2 text-center">
      <div class="py-2.5 rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)">
        <p class="font-mono text-base font-medium" style="color: var(--color-text)">
          {{ steps.toLocaleString('ru-RU') }}
        </p>
        <p class="text-xs mt-0.5" style="color: var(--color-text-3)">шагов</p>
      </div>
      <div class="py-2.5 rounded-[var(--radius-sm)]" style="background: var(--color-accent-soft)">
        <p class="font-mono text-base font-medium" style="color: var(--color-accent)">
          ≈ {{ kcalEstimate }}
        </p>
        <p class="text-xs mt-0.5" style="color: var(--color-text-3)">ккал сжигается</p>
      </div>
    </div>

    <p class="text-xs mt-3" style="color: var(--color-text-3)">
      Шаги × вес × 0.0005 — рекомендуемая оценка.
    </p>

    <div class="flex gap-2 mt-4">
      <AButton
        v-if="isEditing"
        variant="secondary"
        size="md"
        :loading="loading"
        class="flex-1"
        @click="clear"
      >Удалить</AButton>
      <AButton size="md" :loading="loading" class="flex-1" @click="save">
        {{ isEditing ? 'Обновить' : 'Сохранить' }}
      </AButton>
    </div>
  </ASheet>
</template>
