<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useDishesStore } from '@/stores/dishes'
import ACard from '@/components/ui/ACard.vue'
import AButton from '@/components/ui/AButton.vue'
import ASheet from '@/components/ui/ASheet.vue'
import AInput from '@/components/ui/AInput.vue'
import AHeader from '@/components/ui/AHeader.vue'
import AConfirm from '@/components/ui/AConfirm.vue'
import type { Dish } from '@/types/api'

const store = useDishesStore()
const search = ref('')
const showForm = ref(false)
const saving = ref(false)
const editing = ref<Dish | null>(null)
const dishToDelete = ref<Dish | null>(null)

const empty = () => ({
  name: '',
  kcalPer100g: '',
  proteinPer100g: '',
  fatPer100g: '',
  carbsPer100g: '',
  isPiece: false,
  pieceGrams: '',
  pieceLabel: '',
})
const form = ref(empty())

const filtered = computed(() => store.search(search.value))

const canSave = computed(() => {
  const f = form.value
  const ok =
    f.name.trim().length > 0 &&
    f.kcalPer100g !== '' && !isNaN(parseFloat(f.kcalPer100g)) &&
    f.proteinPer100g !== '' && !isNaN(parseFloat(f.proteinPer100g)) &&
    f.fatPer100g !== '' && !isNaN(parseFloat(f.fatPer100g)) &&
    f.carbsPer100g !== '' && !isNaN(parseFloat(f.carbsPer100g))
  if (!ok) return false
  if (f.isPiece) {
    return (
      f.pieceLabel.trim().length > 0 &&
      f.pieceGrams !== '' && !isNaN(parseFloat(f.pieceGrams)) && parseFloat(f.pieceGrams) > 0
    )
  }
  return true
})

onMounted(() => store.fetchAll(true))

function openCreate() {
  editing.value = null
  form.value = empty()
  showForm.value = true
}

function openEdit(d: Dish) {
  editing.value = d
  form.value = {
    name: d.name,
    kcalPer100g: String(d.kcalPer100g),
    proteinPer100g: String(d.proteinPer100g),
    fatPer100g: String(d.fatPer100g),
    carbsPer100g: String(d.carbsPer100g),
    isPiece: d.isPiece,
    pieceGrams: d.pieceGrams != null ? String(d.pieceGrams) : '',
    pieceLabel: d.pieceLabel ?? '',
  }
  showForm.value = true
}

async function saveDish() {
  if (!canSave.value) return
  saving.value = true
  try {
    const f = form.value
    const payload = {
      name: f.name.trim(),
      kcalPer100g: parseFloat(f.kcalPer100g),
      proteinPer100g: parseFloat(f.proteinPer100g),
      fatPer100g: parseFloat(f.fatPer100g),
      carbsPer100g: parseFloat(f.carbsPer100g),
      isPiece: f.isPiece,
      pieceGrams: f.isPiece ? parseFloat(f.pieceGrams) : null,
      pieceLabel: f.isPiece ? f.pieceLabel.trim() : null,
    }
    if (editing.value) await store.update(editing.value.uuid, payload)
    else await store.create(payload)
    showForm.value = false
    form.value = empty()
    editing.value = null
  } finally {
    saving.value = false
  }
}

function confirmDeleteDish() {
  const d = dishToDelete.value
  if (d) void store.remove(d.uuid)
  dishToDelete.value = null
}
</script>

<template>
  <div class="flex flex-col min-h-svh" style="background: var(--color-bg)">
    <AHeader title="Мои блюда" back back-to="/settings">
      <template #right>
        <AButton size="sm" @click="openCreate">+ Новое</AButton>
      </template>
    </AHeader>

    <div class="p-4 pb-24 flex flex-col gap-3">
      <input v-model="search" type="text" placeholder="Поиск..."
        class="w-full rounded-[var(--radius-sm)] border px-3 py-2.5 text-base outline-none"
        style="background: var(--color-surface); border-color: var(--color-border); color: var(--color-text)" />

      <div v-if="store.loading" class="text-sm text-center py-8" style="color: var(--color-text-3)">Загрузка...</div>

      <ACard v-for="dish in filtered" :key="dish.uuid">
        <div class="flex items-center justify-between px-4 py-3">
          <button type="button" class="flex-1 text-left min-w-0 active:opacity-70" @click="openEdit(dish)">
            <p class="text-sm font-medium" style="color: var(--color-text)">
              {{ dish.name }}
              <span v-if="dish.isPiece" class="text-xs font-normal" style="color: var(--color-text-3)">
                · {{ dish.pieceLabel || 'шт' }} {{ dish.pieceGrams }}г
              </span>
            </p>
            <p class="text-xs mt-0.5" style="color: var(--color-text-3)">
              {{ dish.kcalPer100g }} ккал · Б{{ dish.proteinPer100g }} Ж{{ dish.fatPer100g }} У{{ dish.carbsPer100g }} /100г
            </p>
          </button>
          <button class="text-xs p-2" style="color: var(--color-text-3)" aria-label="Удалить" @click="dishToDelete = dish">✕</button>
        </div>
      </ACard>

      <p v-if="!store.loading && filtered.length === 0" class="text-sm text-center py-8" style="color: var(--color-text-3)">
        {{ search ? 'Ничего не найдено' : 'Добавьте первое блюдо' }}
      </p>
    </div>

    <AConfirm
      :model-value="dishToDelete !== null"
      title="Удалить блюдо?"
      :message="dishToDelete ? `«${dishToDelete.name}» будет удалено.` : ''"
      confirm-label="Удалить"
      @update:model-value="(v) => { if (!v) dishToDelete = null }"
      @confirm="confirmDeleteDish"
    />

    <ASheet v-model="showForm" :title="editing ? 'Изменить блюдо' : 'Новое блюдо'">
      <div class="flex flex-col gap-4">
        <AInput v-model="form.name" label="Название" placeholder="Куриная грудка" />

        <p class="text-xs" style="color: var(--color-text-3)">КБЖУ на 100 г</p>
        <div class="grid grid-cols-2 gap-3">
          <AInput v-model="form.kcalPer100g" label="Калории" type="number" placeholder="165" />
          <AInput v-model="form.proteinPer100g" label="Белки (г)" type="number" placeholder="31" />
          <AInput v-model="form.fatPer100g" label="Жиры (г)" type="number" placeholder="3.6" />
          <AInput v-model="form.carbsPer100g" label="Углеводы (г)" type="number" placeholder="0" />
        </div>

        <label class="flex items-center gap-2.5 px-3 py-2.5 rounded-[var(--radius-md)] cursor-pointer"
          style="background: var(--color-surface-2); border: 1px solid var(--color-border)">
          <input v-model="form.isPiece" type="checkbox" class="w-4 h-4" />
          <div class="flex-1">
            <p class="text-sm font-medium" style="color: var(--color-text)">Считать по штукам</p>
            <p class="text-xs" style="color: var(--color-text-3)">Например: банка йогурта, ложка масла</p>
          </div>
        </label>

        <div v-if="form.isPiece" class="grid grid-cols-2 gap-3">
          <AInput v-model="form.pieceLabel" label="Единица" placeholder="банка" />
          <AInput v-model="form.pieceGrams" label="Граммов в 1 шт" type="number" placeholder="125" />
        </div>

        <AButton size="lg" :loading="saving" :disabled="!canSave" class="w-full" @click="saveDish">
          {{ editing ? 'Сохранить' : 'Добавить' }}
        </AButton>
      </div>
    </ASheet>
  </div>
</template>
