<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useDayStore } from '@/stores/day'
import { useDishesStore } from '@/stores/dishes'
import ASheet from '@/components/ui/ASheet.vue'
import AButton from '@/components/ui/AButton.vue'
import ANumpad from '@/components/ui/ANumpad.vue'
import type { Dish, Meal, MealSlot } from '@/types/api'

const props = defineProps<{
  modelValue: boolean
  /** When provided, sheet opens in edit mode for this meal. */
  meal?: Meal | null
}>()
const emit = defineEmits<{ 'update:modelValue': [v: boolean] }>()

const day = useDayStore()
const dishStore = useDishesStore()

type Mode = 'pick' | 'grams' | 'adhoc'
const mode = ref<Mode>('pick')
const slot = ref<MealSlot>('lunch')
const slotPickerOpen = ref(false)
const selectedDish = ref<Dish | null>(null)
const amountStr = ref('100')
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

const slotLabel = computed(() => slots.find(s => s.value === slot.value)?.label ?? '')

/** Pre-pick a slot from time of day. Snack is the safe gap-filler. */
function defaultSlot(): MealSlot {
  const h = new Date().getHours()
  if (h < 11) return 'breakfast'
  if (h < 16) return 'lunch'
  if (h < 19) return 'snack'
  return 'dinner'
}

/** Trim search input + ignore single-char queries — they explode the result list. */
const filteredDishes = computed(() => {
  const q = searchQuery.value.trim()
  if (q.length === 0) return []
  return dishStore.search(q).slice(0, 30)
})

/** In piece mode `amountStr` is pieces (e.g. 1.5 banks); in grams mode — grams. */
const grams = computed(() => {
  const n = parseFloat(amountStr.value)
  if (isNaN(n) || n < 0) return 0
  const d = selectedDish.value
  if (d?.isPiece && d.pieceGrams) return Math.round(n * d.pieceGrams * 10) / 10
  return n
})

const preview = computed(() => {
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

const isEdit = computed(() => !!props.meal)

const numpadUnit = computed(() => {
  const d = selectedDish.value
  return d?.isPiece ? (d.pieceLabel || 'шт') : 'г'
})
const numpadLabel = computed(() => selectedDish.value?.isPiece ? 'Количество' : 'Граммы')

function open() {
  void dishStore.fetchAll()
  searchQuery.value = ''
  slotPickerOpen.value = false
  if (props.meal) {
    // Edit existing meal
    slot.value = props.meal.slot
    if (props.meal.dishUuid) {
      const dish = dishStore.items.find(d => d.uuid === props.meal!.dishUuid) ?? null
      selectedDish.value = dish
      // For piece dishes, convert stored grams back to pieces for the numpad.
      const g = props.meal.grams ?? 0
      if (dish?.isPiece && dish.pieceGrams) {
        const pieces = Math.round((g / dish.pieceGrams) * 10) / 10
        amountStr.value = String(pieces || 1)
      } else {
        amountStr.value = String(g || 100)
      }
      mode.value = dish ? 'grams' : 'adhoc'
    } else {
      selectedDish.value = null
      adhoc.value = {
        name: props.meal.name ?? '',
        kcal: String(props.meal.kcal),
        protein: String(props.meal.proteinG),
        fat: String(props.meal.fatG),
        carbs: String(props.meal.carbsG),
      }
      mode.value = 'adhoc'
    }
  } else {
    slot.value = defaultSlot()
    selectedDish.value = null
    amountStr.value = '100'
    adhoc.value = { name: '', kcal: '', protein: '', fat: '', carbs: '' }
    mode.value = 'pick'
  }
}

function selectDish(dish: Dish) {
  selectedDish.value = dish
  amountStr.value = dish.isPiece ? '1' : '100'
  mode.value = 'grams'
}

async function save() {
  loading.value = true
  try {
    const eatenAt = props.meal?.eatenAt ?? new Date().toISOString()
    if (mode.value === 'grams' && selectedDish.value) {
      const payload = {
        slot: slot.value,
        eatenAt,
        dishUuid: selectedDish.value.uuid,
        grams: grams.value,
      }
      if (isEdit.value && props.meal) await day.updateMeal(props.meal.uuid, payload)
      else await day.addMeal(payload)
    } else {
      const payload = {
        slot: slot.value,
        eatenAt,
        name: adhoc.value.name,
        kcal: parseFloat(adhoc.value.kcal) || 0,
        proteinG: parseFloat(adhoc.value.protein) || 0,
        fatG: parseFloat(adhoc.value.fat) || 0,
        carbsG: parseFloat(adhoc.value.carbs) || 0,
      }
      if (isEdit.value && props.meal) await day.updateMeal(props.meal.uuid, payload)
      else await day.addMeal(payload)
    }
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}

watch(() => props.modelValue, (v) => { if (v) open() })
</script>

<template>
  <ASheet :model-value="modelValue" :title="isEdit ? 'Изменить приём' : 'Добавить приём'" @update:model-value="$emit('update:modelValue', $event)">

    <!-- Slot selector: collapsed chip with current value, tap to expand -->
    <div class="mb-5">
      <button
        v-if="!slotPickerOpen"
        type="button"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm transition-colors"
        style="background: var(--color-surface-2); color: var(--color-text-2)"
        @click="slotPickerOpen = true"
      >
        <span style="color: var(--color-text-3)">Приём:</span>
        <span style="color: var(--color-text); font-weight: 500">{{ slotLabel }}</span>
        <span style="color: var(--color-text-3)">▾</span>
      </button>
      <div v-else class="flex gap-1.5 flex-wrap">
        <button
          v-for="s in slots" :key="s.value"
          type="button"
          class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
          :style="slot === s.value
            ? 'background: var(--color-accent); color: #fff'
            : 'background: var(--color-surface-2); color: var(--color-text-2)'"
          @click="slot = s.value; slotPickerOpen = false"
        >{{ s.label }}</button>
      </div>
    </div>

    <!-- ── Dish picker (search-on-demand) ── -->
    <div v-if="mode === 'pick'">
      <div class="relative mb-3">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-base pointer-events-none" style="color: var(--color-text-3)">🔍</span>
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Найти блюдо..."
          autofocus
          class="w-full rounded-[var(--radius-md)] px-3 py-2.5 pl-9 text-sm outline-none"
          style="background: var(--color-surface-2); color: var(--color-text); border: 1px solid var(--color-border)"
        />
      </div>

      <!-- Empty state: hint + manual entry button. No long list dump. -->
      <div v-if="searchQuery.trim().length === 0" class="py-8 text-center">
        <p class="text-sm mb-1" style="color: var(--color-text-2)">Начните вводить название</p>
        <p class="text-xs mb-5" style="color: var(--color-text-3)">или введите КБЖУ вручную</p>
        <AButton variant="secondary" size="md" @click="mode = 'adhoc'">
          Ввести вручную
        </AButton>
      </div>

      <!-- Filtered results -->
      <div v-else class="flex flex-col gap-1.5 max-h-72 overflow-y-auto">
        <button
          v-for="dish in filteredDishes" :key="dish.uuid"
          type="button"
          class="flex items-center justify-between px-4 py-3 rounded-[var(--radius-md)] text-left transition-colors active:scale-[0.98]"
          style="background: var(--color-surface-2); border: 1px solid var(--color-border)"
          @click="selectDish(dish)"
        >
          <div class="min-w-0">
            <p class="text-sm font-medium truncate" style="color: var(--color-text)">
              {{ dish.name }}
              <span v-if="dish.isPiece" class="text-xs font-normal" style="color: var(--color-text-3)">· {{ dish.pieceLabel || 'шт' }}</span>
            </p>
            <p class="text-xs mt-0.5" style="color: var(--color-text-3)">
              {{ dish.kcalPer100g }} ккал · Б{{ dish.proteinPer100g }} Ж{{ dish.fatPer100g }} У{{ dish.carbsPer100g }} /100г
            </p>
          </div>
          <span class="ml-3 text-lg flex-shrink-0" style="color: var(--color-text-3)">›</span>
        </button>

        <p v-if="filteredDishes.length === 0" class="py-6 text-sm text-center" style="color: var(--color-text-3)">
          Ничего не найдено
        </p>
      </div>
    </div>

    <!-- ── Grams / pieces input ── -->
    <div v-else-if="mode === 'grams'">
      <div class="flex items-center gap-2 px-3 py-2.5 rounded-[var(--radius-md)] mb-4" style="background: var(--color-surface-2)">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold truncate" style="color: var(--color-text)">{{ selectedDish!.name }}</p>
          <p class="text-xs" style="color: var(--color-text-3)">
            {{ selectedDish!.kcalPer100g }} ккал / 100г
            <template v-if="selectedDish!.isPiece && selectedDish!.pieceGrams">
              · 1 {{ selectedDish!.pieceLabel || 'шт' }} = {{ selectedDish!.pieceGrams }} г
            </template>
          </p>
        </div>
        <button
          type="button"
          v-if="!isEdit"
          class="text-xs px-2 py-1 rounded-[var(--radius-sm)]"
          style="color: var(--color-accent); background: var(--color-accent-soft)"
          @click="mode = 'pick'; selectedDish = null"
        >Изменить</button>
      </div>

      <ANumpad v-model="amountStr" :label="numpadLabel" :unit="numpadUnit" />

      <p v-if="selectedDish!.isPiece" class="text-xs text-center mt-2" style="color: var(--color-text-3)">
        ≈ {{ grams }} г
      </p>

      <div class="mt-4 grid grid-cols-4 gap-2 text-center">
        <div v-for="item in [
          { label: 'Ккал', val: preview.kcal },
          { label: 'Белки', val: preview.protein },
          { label: 'Жиры', val: preview.fat },
          { label: 'Углеводы', val: preview.carbs },
        ]" :key="item.label"
          class="py-2 rounded-[var(--radius-sm)]"
          style="background: var(--color-surface-2)"
        >
          <p class="font-mono text-base font-medium" style="color: var(--color-text)">{{ item.val }}</p>
          <p class="text-xs mt-0.5" style="color: var(--color-text-3)">{{ item.label }}</p>
        </div>
      </div>

      <AButton size="md" :loading="loading" :disabled="!canSaveGrams" class="w-full mt-4" @click="save">
        {{ isEdit ? 'Сохранить' : 'Добавить' }}
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
        <AButton v-if="!isEdit" variant="secondary" size="md" class="flex-1" @click="mode = 'pick'">← Назад</AButton>
        <AButton size="md" :loading="loading" :disabled="!canSaveAdhoc" class="flex-1" @click="save">
          {{ isEdit ? 'Сохранить' : 'Добавить' }}
        </AButton>
      </div>
    </div>

  </ASheet>
</template>
