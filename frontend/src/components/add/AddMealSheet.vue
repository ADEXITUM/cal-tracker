<script setup lang="ts">
import { ref, computed } from 'vue'
import { useDayStore } from '@/stores/day'
import { useDishesStore } from '@/stores/dishes'
import ASheet from '@/components/ui/ASheet.vue'
import AButton from '@/components/ui/AButton.vue'
import ANumpad from '@/components/ui/ANumpad.vue'
import type { Dish, MealSlot } from '@/types/api'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [v: boolean] }>()

const day = useDayStore()
const dishStore = useDishesStore()

// mode: 'pick' = dish picker, 'grams' = enter grams, 'adhoc' = manual entry
const mode = ref<'pick' | 'grams' | 'adhoc'>('pick')
const slot = ref<MealSlot>('lunch')
const selectedDish = ref<Dish | null>(null)
const gramsStr = ref('100')
const adhoc = ref({ name: '', kcal: '', protein: '', fat: '', carbs: '' })
const loading = ref(false)
const searchQuery = ref('')

const slots: { value: MealSlot; label: string }[] = [
  { value: 'breakfast', label: 'Завтрак' },
  { value: 'lunch', label: 'Обед' },
  { value: 'snack', label: 'Перекус' },
  { value: 'dinner', label: 'Ужин' },
  { value: 'other', label: 'Другое' },
]

const filteredDishes = computed(() => dishStore.search(searchQuery.value))

function open() {
  dishStore.fetchAll()
  mode.value = 'pick'
  selectedDish.value = null
  gramsStr.value = '100'
  searchQuery.value = ''
}

function selectDish(dish: Dish) {
  selectedDish.value = dish
  mode.value = 'grams'
}

async function save() {
  loading.value = true
  try {
    if (mode.value === 'grams' && selectedDish.value) {
      await day.addMeal({
        slot: slot.value,
        eatenAt: new Date().toISOString(),
        dishUuid: selectedDish.value.uuid,
        grams: parseFloat(gramsStr.value),
      })
    } else {
      await day.addMeal({
        slot: slot.value,
        eatenAt: new Date().toISOString(),
        name: adhoc.value.name,
        kcal: parseFloat(adhoc.value.kcal) || 0,
        proteinG: parseFloat(adhoc.value.protein) || 0,
        fatG: parseFloat(adhoc.value.fat) || 0,
        carbsG: parseFloat(adhoc.value.carbs) || 0,
      })
    }
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}

// Reset when opened
import { watch } from 'vue'
watch(() => props.modelValue, (v) => { if (v) open() })
</script>

<template>
  <ASheet :model-value="modelValue" title="Добавить приём" @update:model-value="$emit('update:modelValue', $event)">

    <!-- Slot selector -->
    <div class="flex gap-2 flex-wrap mb-4">
      <button
        v-for="s in slots" :key="s.value"
        class="px-3 py-1.5 rounded-full text-sm font-medium border transition-colors"
        :style="{
          background: slot === s.value ? 'var(--color-accent)' : 'var(--color-surface-2)',
          color: slot === s.value ? 'white' : 'var(--color-text-2)',
          borderColor: slot === s.value ? 'var(--color-accent)' : 'transparent',
        }"
        @click="slot = s.value"
      >{{ s.label }}</button>
    </div>

    <!-- Dish picker -->
    <div v-if="mode === 'pick'">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Поиск блюда..."
        class="w-full rounded-[var(--radius-sm)] border px-3 py-2.5 text-base mb-3 outline-none"
        style="background: var(--color-surface-2); border-color: var(--color-border); color: var(--color-text)"
      />
      <div class="flex flex-col gap-1 max-h-48 overflow-y-auto mb-3">
        <button
          v-for="dish in filteredDishes" :key="dish.uuid"
          class="flex items-center justify-between px-3 py-2.5 rounded-[var(--radius-sm)] text-left"
          style="background: var(--color-surface-2)"
          @click="selectDish(dish)"
        >
          <span class="text-sm font-medium" style="color: var(--color-text)">{{ dish.name }}</span>
          <span class="text-xs" style="color: var(--color-text-3)">{{ dish.kcalPer100g }} ккал/100г</span>
        </button>
        <p v-if="filteredDishes.length === 0" class="text-sm text-center py-4" style="color: var(--color-text-3)">
          Нет блюд
        </p>
      </div>
      <AButton variant="secondary" size="md" class="w-full" @click="mode = 'adhoc'">
        Ввести вручную
      </AButton>
    </div>

    <!-- Grams input -->
    <div v-else-if="mode === 'grams'">
      <p class="text-sm font-medium mb-3" style="color: var(--color-text)">{{ selectedDish!.name }}</p>
      <ANumpad v-model="gramsStr" label="Граммы" unit="г" />
      <div class="mt-4 p-3 rounded-[var(--radius-sm)] text-sm" style="background: var(--color-surface-2); color: var(--color-text-2)">
        {{ Math.round(selectedDish!.kcalPer100g * parseFloat(gramsStr || '0') / 100) }} ккал ·
        Б {{ Math.round(selectedDish!.proteinPer100g * parseFloat(gramsStr || '0') / 100) }}г ·
        Ж {{ Math.round(selectedDish!.fatPer100g * parseFloat(gramsStr || '0') / 100) }}г ·
        У {{ Math.round(selectedDish!.carbsPer100g * parseFloat(gramsStr || '0') / 100) }}г
      </div>
      <div class="flex gap-2 mt-4">
        <AButton variant="secondary" size="md" class="flex-1" @click="mode = 'pick'">Назад</AButton>
        <AButton size="md" :loading="loading" class="flex-1" @click="save">Добавить</AButton>
      </div>
    </div>

    <!-- Ad-hoc -->
    <div v-else class="flex flex-col gap-3">
      <input v-model="adhoc.name" type="text" placeholder="Название блюда"
        class="w-full rounded-[var(--radius-sm)] border px-3 py-2.5 text-base outline-none"
        style="background: var(--color-surface-2); border-color: var(--color-border); color: var(--color-text)" />
      <div class="grid grid-cols-2 gap-2">
        <div v-for="field in [
          { key: 'kcal', label: 'Калории' },
          { key: 'protein', label: 'Белки (г)' },
          { key: 'fat', label: 'Жиры (г)' },
          { key: 'carbs', label: 'Углеводы (г)' },
        ]" :key="field.key">
          <label class="text-xs mb-1 block" style="color: var(--color-text-2)">{{ field.label }}</label>
          <input v-model="(adhoc as any)[field.key]" type="number" inputmode="decimal"
            class="w-full rounded-[var(--radius-sm)] border px-3 py-2 text-base outline-none"
            style="background: var(--color-surface-2); border-color: var(--color-border); color: var(--color-text)" />
        </div>
      </div>
      <div class="flex gap-2">
        <AButton variant="secondary" size="md" class="flex-1" @click="mode = 'pick'">Назад</AButton>
        <AButton size="md" :loading="loading" class="flex-1" @click="save">Добавить</AButton>
      </div>
    </div>

  </ASheet>
</template>
