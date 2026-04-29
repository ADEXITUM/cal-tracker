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
const loading = ref(false)

const canSave = computed(() => {
  const w = parseFloat(weightStr.value)
  return !isNaN(w) && w >= 30 && w <= 300
})

watch(() => props.modelValue, (v) => { if (v) { weightStr.value = '80'; bodyFatStr.value = '' } })

async function save() {
  loading.value = true
  try {
    await day.addMeasurement({
      measuredAt: new Date().toISOString(),
      weightKg: parseFloat(weightStr.value),
      bodyFatPct: bodyFatStr.value ? parseFloat(bodyFatStr.value) : null,
    })
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <ASheet :model-value="modelValue" title="Замер веса" @update:model-value="$emit('update:modelValue', $event)">
    <ANumpad v-model="weightStr" label="Вес" unit="кг" />
    <div class="mt-4">
      <label class="text-sm mb-2 block" style="color: var(--color-text-2)">% жира (опционально)</label>
      <input v-model="bodyFatStr" type="number" inputmode="decimal" placeholder="—"
        class="w-full rounded-[var(--radius-sm)] border px-3 py-2.5 text-base outline-none"
        style="background: var(--color-surface-2); border-color: var(--color-border); color: var(--color-text)" />
    </div>
    <AButton size="lg" :loading="loading" :disabled="!canSave" class="w-full mt-4" @click="save">Сохранить</AButton>
  </ASheet>
</template>
