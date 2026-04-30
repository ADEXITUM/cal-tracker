<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useDayStore } from '@/stores/day'
import ASheet from '@/components/ui/ASheet.vue'
import AButton from '@/components/ui/AButton.vue'
import ANumpad from '@/components/ui/ANumpad.vue'
import ASegmented from '@/components/ui/ASegmented.vue'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [v: boolean] }>()

const day = useDayStore()

type FormTab = 'weight' | 'girth'
const formTab = ref<FormTab>('weight')

const weightStr = ref('80')
const bodyFatStr = ref('')

const waistStr = ref('')
const hipsStr = ref('')
const chestStr = ref('')
const bicepsStr = ref('')

const loading = ref(false)

const tabOptions: { value: FormTab; label: string }[] = [
  { value: 'weight', label: 'Вес' },
  { value: 'girth',  label: 'Замеры тела' },
]

const canSave = computed(() => {
  const w = parseFloat(weightStr.value)
  return !isNaN(w) && w >= 30 && w <= 300
})

const isEditing = computed(() => (day.data?.measurements.length ?? 0) > 0)

function preFill() {
  const m = day.data?.measurements[0]
  if (m) {
    weightStr.value  = String(m.weightKg)
    bodyFatStr.value = m.bodyFatPct != null ? String(m.bodyFatPct) : ''
    waistStr.value   = m.waistCm    != null ? String(m.waistCm)    : ''
    hipsStr.value    = m.hipsCm     != null ? String(m.hipsCm)     : ''
    chestStr.value   = m.chestCm    != null ? String(m.chestCm)    : ''
    bicepsStr.value  = m.bicepsCm   != null ? String(m.bicepsCm)   : ''
  } else {
    weightStr.value = '80'
    bodyFatStr.value = ''
    waistStr.value = ''
    hipsStr.value = ''
    chestStr.value = ''
    bicepsStr.value = ''
  }
  formTab.value = 'weight'
}

watch(() => props.modelValue, (v) => { if (v) preFill() })

async function save() {
  loading.value = true
  try {
    const num = (s: string) => s ? parseFloat(s) : null
    await day.addMeasurement({
      measuredAt: new Date().toISOString(),
      weightKg:   parseFloat(weightStr.value),
      bodyFatPct: num(bodyFatStr.value),
      waistCm:    num(waistStr.value),
      hipsCm:     num(hipsStr.value),
      chestCm:    num(chestStr.value),
      bicepsCm:   num(bicepsStr.value),
    })
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}

const girthFields = [
  { key: 'waist',  ref: waistStr,  label: 'Талия',  placeholder: '85' },
  { key: 'chest',  ref: chestStr,  label: 'Грудь',  placeholder: '100' },
  { key: 'hips',   ref: hipsStr,   label: 'Бёдра',  placeholder: '95' },
  { key: 'biceps', ref: bicepsStr, label: 'Бицепс', placeholder: '35' },
] as const
</script>

<template>
  <ASheet
    :model-value="modelValue"
    :title="isEditing ? 'Изменить замер' : 'Замер дня'"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <ASegmented v-model="formTab" :options="tabOptions" class="mb-4" />

    <!-- Weight tab -->
    <div v-if="formTab === 'weight'" class="flex flex-col gap-4">
      <ANumpad v-model="weightStr" label="Вес" unit="кг" />

      <div>
        <label class="text-xs mb-1 block" style="color: var(--color-text-3)">% жира (опционально)</label>
        <input
          v-model="bodyFatStr"
          type="number"
          inputmode="decimal"
          placeholder="напр. 18"
          class="w-full rounded-[var(--radius-sm)] px-3 py-2.5 text-base outline-none"
          style="background: var(--color-surface-2); border: 1px solid var(--color-border); color: var(--color-text)"
        />
      </div>
    </div>

    <!-- Girth tab -->
    <div v-else class="flex flex-col gap-3">
      <p class="text-xs" style="color: var(--color-text-3)">
        Замеры в сантиметрах. Оставь пустым то, что не меришь.
      </p>
      <div class="grid grid-cols-2 gap-3">
        <div v-for="f in girthFields" :key="f.key">
          <label class="text-xs mb-1 block" style="color: var(--color-text-3)">{{ f.label }} (см)</label>
          <input
            :value="f.ref.value"
            type="number"
            inputmode="decimal"
            :placeholder="f.placeholder"
            class="w-full rounded-[var(--radius-sm)] px-3 py-2.5 text-base outline-none"
            style="background: var(--color-surface-2); border: 1px solid var(--color-border); color: var(--color-text)"
            @input="f.ref.value = ($event.target as HTMLInputElement).value"
          />
        </div>
      </div>
    </div>

    <AButton size="lg" :loading="loading" :disabled="!canSave" class="w-full mt-5" @click="save">
      {{ isEditing ? 'Обновить' : 'Сохранить' }}
    </AButton>
  </ASheet>
</template>
