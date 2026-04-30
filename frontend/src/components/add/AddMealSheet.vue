<script setup lang="ts">
import { ref, computed, watch } from 'vue'
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

const grams = computed(() => {
  const g = parseFloat(gramsStr.value)
  return isNaN(g) || g < 0 ? 0 : g
})

const gramsPreview = computed(() => {
  if (!selectedDish.value) return { kcal: 0, protein: 0, fat: 0, carbs: 0 }
  const d = selectedDish.value
  const g = grams.value
  return {
    kcal:    Math.round(d.kcalPer100g    * g / 100),
    protein: Math.round(d.proteinPer100g * g / 100),
    fat:     Math.round(d.fatPer100g     * g / 100),
    carbs:   Math.round(d.carbsPer100g   * g / 100),
  }
})

const canSaveGrams = computed(() => selectedDish.value !== null && grams.value > 0)

const canSaveAdhoc = computed(() => adhoc.value.name.trim().length > 0)

function open() {
  dishStore.fetchAll(true)
  mode.value = 'pick'
  selectedDish.value = null
  gramsStr.value = '100'
  searchQuery.value = ''
  adhoc.value = { name: '', kcal: '', protein: '', fat: '', carbs: '' }
}

function selectDish(dish: Dish) {
  selectedDish.value = dish
  gramsStr.value = '100'
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
        grams: grams.value,
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

watch(() => props.modelValue, (v) => { if (v) open() })
</script>

<template>
  <ASheet :model-value="modelValue" title="Добавить приём" @update:model-value="$emit('update:modelValue', $event)">

    <!-- Slot selector -->
    <div class="flex gap-1.5 flex-wrap mb-5">
      <button
        v-for="s in slots" :key="s.value"
        class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
        :style="slot === s.value
          ? 'background: var(--color-accent); color: #fff'
          : 'background: var(--color-surface-2); color: var(--color-text-2)'"
        @click="slot = s.value"
      >{{ s.label }}</button>
    </div>

    <!-- ── Dish picker ── -->
    <div v-if="mode === 'pick'">

      <!-- Search -->
      <div class="relative mb-3">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-base pointer-events-none" style="color: var(--color-text-3)">🔍</span>
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Поиск по блюдам..."
          class="w-full rounded-[var(--radius-md)] px-3 py-2.5 pl-9 text-sm outline-none"
          style="background: var(--color-surface-2); color: var(--color-text); border: 1px solid var(--color-border)"
        />
      </div>

      <!-- Dish list -->
      <div class="flex flex-col gap-1.5 max-h-52 overflow-y-auto mb-4">
        <button
          v-for="dish in filteredDishes" :key="dish.uuid"
          class="flex items-center justify-between px-4 py-3 rounded-[var(--radius-md)] text-left transition-colors active:scale-[0.98]"
          style="background: var(--color-surface-2); border: 1px solid var(--color-border)"
          @click="selectDish(dish)"
        >
          <div class="min-w-0">
            <p class="text-sm font-medium truncate" style="color: var(--color-text)">{{ dish.name }}</p>
            <p class="text-xs mt-0.5" style="color: var(--color-text-3)">
              {{ dish.kcalPer100g }} ккал · Б{{ dish.proteinPer100g }} Ж{{ dish.fatPer100g }} У{{ dish.carbsPer100g }} /100г
            </p>
          </div>
          <span class="ml-3 text-lg flex-shrink-0" style="color: var(--color-text-3)">›</span>
        </button>

        <div v-if="filteredDishes.length === 0" class="py-6 text-center">
          <p class="text-sm" style="color: var(--color-text-3)">Блюда не найдены</p>
          <p class="text-xs mt-1" style="color: var(--color-text-3)">Добавьте блюда в разделе Настройки → Блюда</p>
        </div>
      </div>

      <!-- Divider -->
      <div class="flex items-center gap-3 mb-4">
        <div class="flex-1 h-px" style="background: var(--color-border)"></div>
        <span class="text-xs" style="color: var(--color-text-3)">или введите вручную</span>
        <div class="flex-1 h-px" style="background: var(--color-border)"></div>
      </div>

      <AButton variant="secondary" size="md" class="w-full" @click="mode = 'adhoc'">
        Ввести КБЖУ вручную
      </AButton>
    </div>

    <!-- ── Grams input ── -->
    <div v-else-if="mode === 'grams'">
      <!-- Selected dish header -->
      <div class="flex items-center gap-2 px-3 py-2.5 rounded-[var(--radius-md)] mb-4" style="background: var(--color-surface-2)">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold truncate" style="color: var(--color-text)">{{ selectedDish!.name }}</p>
          <p class="text-xs" style="color: var(--color-text-3)">{{ selectedDish!.kcalPer100g }} ккал / 100г</p>
        </div>
        <button
          class="text-xs px-2 py-1 rounded-[var(--radius-sm)]"
          style="color: var(--color-accent); background: var(--color-accent-soft)"
          @click="mode = 'pick'"
        >Изменить</button>
      </div>

      <ANumpad v-model="gramsStr" label="Граммы" unit="г" />

      <!-- Macro preview -->
      <div class="mt-4 grid grid-cols-4 gap-2 text-center">
        <div v-for="item in [
          { label: 'Ккал', val: gramsPreview.kcal },
          { label: 'Белки', val: gramsPreview.protein },
          { label: 'Жиры', val: gramsPreview.fat },
          { label: 'Углеводы', val: gramsPreview.carbs },
        ]" :key="item.label"
          class="py-2 rounded-[var(--radius-sm)]"
          style="background: var(--color-surface-2)"
        >
          <p class="font-mono text-base font-medium" style="color: var(--color-text)">{{ item.val }}</p>
          <p class="text-xs mt-0.5" style="color: var(--color-text-3)">{{ item.label }}</p>
        </div>
      </div>

      <AButton size="md" :loading="loading" :disabled="!canSaveGrams" class="w-full mt-4" @click="save">
        Добавить
      </AButton>
    </div>

    <!-- ── Ad-hoc manual entry ── -->
    <div v-else class="flex flex-col gap-3">
      <div>
        <p class="text-xs mb-1.5 font-medium" style="color: var(--color-text-2)">Название</p>
        <input
          v-model="adhoc.name"
          type="text"
          placeholder="Например: Борщ со сметаной"
          class="w-full rounded-[var(--radius-md)] px-3 py-2.5 text-sm outline-none"
          style="background: var(--color-surface-2); border: 1px solid var(--color-border); color: var(--color-text)"
        />
      </div>

      <div>
        <p class="text-xs mb-1.5 font-medium" style="color: var(--color-text-2)">КБЖУ на порцию</p>
        <div class="grid grid-cols-2 gap-2">
          <div v-for="field in [
            { key: 'kcal', label: 'Калории (ккал)', placeholder: '350' },
            { key: 'protein', label: 'Белки (г)', placeholder: '20' },
            { key: 'fat', label: 'Жиры (г)', placeholder: '15' },
            { key: 'carbs', label: 'Углеводы (г)', placeholder: '30' },
          ]" :key="field.key" class="flex flex-col gap-1">
            <label class="text-xs" style="color: var(--color-text-3)">{{ field.label }}</label>
            <input
              v-model="(adhoc as any)[field.key]"
              type="number"
              inputmode="decimal"
              :placeholder="field.placeholder"
              class="w-full rounded-[var(--radius-sm)] px-3 py-2 text-base outline-none"
              style="background: var(--color-surface-2); border: 1px solid var(--color-border); color: var(--color-text)"
            />
          </div>
        </div>
      </div>

      <div class="flex gap-2 pt-1">
        <AButton variant="secondary" size="md" class="flex-1" @click="mode = 'pick'">← Назад</AButton>
        <AButton size="md" :loading="loading" :disabled="!canSaveAdhoc" class="flex-1" @click="save">Добавить</AButton>
      </div>
    </div>

  </ASheet>
</template>
