<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useDayStore } from '@/stores/day'
import ASheet from '@/components/ui/ASheet.vue'
import AButton from '@/components/ui/AButton.vue'
import ANumpad from '@/components/ui/ANumpad.vue'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [v: boolean] }>()

const day = useDayStore()
const weightStr = ref('80')
const bodyFatStr = ref('')
const muscleStr = ref('')
const stepsStr = ref('')
const loading = ref(false)
const showOptional = ref(false)

const canSave = computed(() => {
  const w = parseFloat(weightStr.value)
  return !isNaN(w) && w >= 30 && w <= 300
})

const isEditing = computed(() => (day.data?.measurements.length ?? 0) > 0)

function preFill() {
  const m = day.data?.measurements[0]
  if (m) {
    weightStr.value = String(m.weightKg)
    bodyFatStr.value = m.bodyFatPct != null ? String(m.bodyFatPct) : ''
    muscleStr.value = m.muscleMassKg != null ? String(m.muscleMassKg) : ''
    if (m.bodyFatPct != null || m.muscleMassKg != null) showOptional.value = true
  } else {
    weightStr.value = '80'
    bodyFatStr.value = ''
    muscleStr.value = ''
    showOptional.value = false
  }
  const s = day.data?.dayEntry?.steps
  stepsStr.value = s != null ? String(s) : ''
}

watch(() => props.modelValue, (v) => { if (v) preFill() })

async function save() {
  loading.value = true
  try {
    await day.addMeasurement({
      measuredAt: new Date().toISOString(),
      weightKg: parseFloat(weightStr.value),
      bodyFatPct: bodyFatStr.value ? parseFloat(bodyFatStr.value) : null,
      muscleMassKg: muscleStr.value ? parseFloat(muscleStr.value) : null,
    })
    const stepsVal = stepsStr.value ? parseInt(stepsStr.value, 10) : null
    if (stepsVal !== (day.data?.dayEntry?.steps ?? null)) {
      await day.updateDayEntry({ steps: stepsVal ?? 0 })
    }
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <ASheet
    :model-value="modelValue"
    :title="isEditing ? 'Изменить замер' : 'Замер дня'"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <ANumpad v-model="weightStr" label="Вес" unit="кг" />

    <!-- Optional fields toggle -->
    <button
      type="button"
      class="mt-4 w-full text-xs py-2 rounded-[var(--radius-sm)]"
      :style="showOptional
        ? 'color: var(--color-text-2); background: transparent'
        : 'color: var(--color-text-2); background: var(--color-surface-2)'"
      @click="showOptional = !showOptional"
    >
      {{ showOptional ? 'Скрыть дополнительные поля' : '+ % жира · мышцы · шаги' }}
    </button>

    <div v-if="showOptional" class="mt-3 flex flex-col gap-3">
      <div>
        <label class="text-xs mb-1 block" style="color: var(--color-text-3)">% жира</label>
        <input
          v-model="bodyFatStr"
          type="number"
          inputmode="decimal"
          placeholder="напр. 18"
          class="w-full rounded-[var(--radius-sm)] px-3 py-2 text-base outline-none"
          style="background: var(--color-surface-2); border: 1px solid var(--color-border); color: var(--color-text)"
        />
      </div>
      <div>
        <label class="text-xs mb-1 block" style="color: var(--color-text-3)">Мышечная масса (кг)</label>
        <input
          v-model="muscleStr"
          type="number"
          inputmode="decimal"
          placeholder="напр. 35"
          class="w-full rounded-[var(--radius-sm)] px-3 py-2 text-base outline-none"
          style="background: var(--color-surface-2); border: 1px solid var(--color-border); color: var(--color-text)"
        />
      </div>
      <div>
        <label class="text-xs mb-1 block" style="color: var(--color-text-3)">Шаги за день</label>
        <input
          v-model="stepsStr"
          type="number"
          inputmode="numeric"
          placeholder="напр. 8000"
          class="w-full rounded-[var(--radius-sm)] px-3 py-2 text-base outline-none"
          style="background: var(--color-surface-2); border: 1px solid var(--color-border); color: var(--color-text)"
        />
        <p class="text-xs mt-1" style="color: var(--color-text-3)">
          Шаги учитываются в дневном расходе калорий.
        </p>
      </div>
    </div>

    <AButton size="lg" :loading="loading" :disabled="!canSave" class="w-full mt-4" @click="save">
      {{ isEditing ? 'Обновить' : 'Сохранить' }}
    </AButton>
  </ASheet>
</template>
