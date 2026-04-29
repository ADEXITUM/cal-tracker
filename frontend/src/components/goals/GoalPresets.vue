<script setup lang="ts">
import { computed, ref } from 'vue'
import { PRESET_DEFINITIONS, defaultMacroSplit, type PresetKey, type MacroSplit } from '@/lib/modes'
import ACard from '@/components/ui/ACard.vue'

const props = defineProps<{
  tdeeKcal: number
  weightKg: number
  modelValue: { kcal: number; proteinG: number; fatG: number; carbsG: number }
  initialPreset?: PresetKey
}>()

const emit = defineEmits<{
  'update:modelValue': [v: { kcal: number; proteinG: number; fatG: number; carbsG: number }]
}>()

const selectedKey = ref<PresetKey | 'custom'>(props.initialPreset ?? 'maintenance')
const showFormula = ref(false)
const customExpanded = ref(false)

const presets = computed(() =>
  PRESET_DEFINITIONS.map(p => {
    const kcal = props.tdeeKcal + p.deltaFromTdee
    return { ...p, ...defaultMacroSplit(kcal, props.weightKg) }
  }),
)

function pick(key: PresetKey, split: MacroSplit) {
  selectedKey.value = key
  customExpanded.value = false
  emit('update:modelValue', split)
}

function toggleCustom() {
  selectedKey.value = 'custom'
  customExpanded.value = true
}

function updateCustom(field: 'kcal' | 'proteinG' | 'fatG' | 'carbsG', val: string) {
  const num = parseInt(val) || 0
  emit('update:modelValue', { ...props.modelValue, [field]: num })
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div class="flex items-center justify-between">
      <p class="text-sm font-medium" style="color: var(--color-text)">Пресет цели</p>
      <button
        type="button"
        class="text-xs underline"
        style="color: var(--color-text-3)"
        @click="showFormula = !showFormula"
      >
        как считается?
      </button>
    </div>

    <div
      v-if="showFormula"
      class="text-xs p-3 rounded-[var(--radius-sm)]"
      style="background: var(--color-surface-2); color: var(--color-text-2)"
    >
      <p class="mb-1"><strong>TDEE</strong> = ваш дневной расход (BMR + активность). Сейчас: {{ tdeeKcal }} ккал</p>
      <p class="mb-1"><strong>Макро-сплит:</strong></p>
      <ul class="list-disc pl-5 space-y-0.5">
        <li>Белки = 1.8 г × вес ({{ Math.round(weightKg * 1.8) }} г при {{ weightKg }} кг)</li>
        <li>Жиры = 25% от ккал ÷ 9</li>
        <li>Углеводы = остаток ÷ 4</li>
      </ul>
    </div>

    <div class="grid grid-cols-1 gap-2">
      <ACard
        v-for="p in presets"
        :key="p.key"
        :class="['cursor-pointer transition-all', selectedKey === p.key ? 'ring-2' : '']"
        :style="{
          '--tw-ring-color': selectedKey === p.key ? 'var(--color-accent)' : 'transparent',
          borderColor: selectedKey === p.key ? 'var(--color-accent)' : undefined,
        }"
        @click="pick(p.key, p)"
      >
        <div class="px-3 py-2.5 flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold" style="color: var(--color-text)">{{ p.label }}</p>
            <p class="text-xs mt-0.5" style="color: var(--color-text-3)">
              {{ p.kcal }} ккал · Б{{ p.proteinG }} Ж{{ p.fatG }} У{{ p.carbsG }}
            </p>
          </div>
          <span
            class="text-xs px-2 py-0.5 rounded-full"
            :style="{
              background: p.deltaFromTdee === 0 ? 'var(--color-surface-2)' : 'var(--color-surface-2)',
              color: 'var(--color-text-2)',
            }"
          >
            {{ p.deltaFromTdee > 0 ? '+' : '' }}{{ p.deltaFromTdee }}
          </span>
        </div>
      </ACard>

      <ACard
        :class="['cursor-pointer', selectedKey === 'custom' ? 'ring-2' : '']"
        :style="{
          '--tw-ring-color': selectedKey === 'custom' ? 'var(--color-accent)' : 'transparent',
          borderColor: selectedKey === 'custom' ? 'var(--color-accent)' : undefined,
        }"
        @click="toggleCustom"
      >
        <div class="px-3 py-2.5">
          <p class="text-sm font-semibold" style="color: var(--color-text)">Свои числа</p>
          <p class="text-xs mt-0.5" style="color: var(--color-text-3)">
            Задать ккал и БЖУ вручную
          </p>
        </div>

        <div v-if="customExpanded" class="px-3 pb-3 grid grid-cols-2 gap-2" @click.stop>
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
      </ACard>
    </div>
  </div>
</template>
