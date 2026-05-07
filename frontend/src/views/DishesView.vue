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
const showCreate = ref(false)
const saving = ref(false)
const form = ref({ name: '', kcalPer100g: '', proteinPer100g: '', fatPer100g: '', carbsPer100g: '' })
const dishToDelete = ref<Dish | null>(null)

function confirmDeleteDish() {
  const d = dishToDelete.value
  dishToDelete.value = null
  if (d) void store.remove(d.uuid)
}

const filtered = computed(() => store.search(search.value))

const canCreate = computed(() => {
  const f = form.value
  return (
    f.name.trim().length > 0 &&
    f.kcalPer100g !== '' && !isNaN(parseFloat(f.kcalPer100g)) &&
    f.proteinPer100g !== '' && !isNaN(parseFloat(f.proteinPer100g)) &&
    f.fatPer100g !== '' && !isNaN(parseFloat(f.fatPer100g)) &&
    f.carbsPer100g !== '' && !isNaN(parseFloat(f.carbsPer100g))
  )
})

onMounted(() => store.fetchAll(true))

async function createDish() {
  if (!canCreate.value) return
  saving.value = true
  try {
    await store.create({
      name: form.value.name.trim(),
      kcalPer100g: parseFloat(form.value.kcalPer100g),
      proteinPer100g: parseFloat(form.value.proteinPer100g),
      fatPer100g: parseFloat(form.value.fatPer100g),
      carbsPer100g: parseFloat(form.value.carbsPer100g),
    })
    showCreate.value = false
    form.value = { name: '', kcalPer100g: '', proteinPer100g: '', fatPer100g: '', carbsPer100g: '' }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="flex flex-col min-h-svh" style="background: var(--color-bg)">
    <AHeader title="Мои блюда" back back-to="/settings">
      <template #right>
        <AButton size="sm" @click="showCreate = true">+ Новое</AButton>
      </template>
    </AHeader>

    <div class="p-4 pb-24 flex flex-col gap-3">
      <input v-model="search" type="text" placeholder="Поиск..."
        class="w-full rounded-[var(--radius-sm)] border px-3 py-2.5 text-base outline-none"
        style="background: var(--color-surface); border-color: var(--color-border); color: var(--color-text)" />

      <div v-if="store.loading" class="text-sm text-center py-8" style="color: var(--color-text-3)">Загрузка...</div>

      <ACard v-for="dish in filtered" :key="dish.uuid">
        <div class="flex items-center justify-between px-4 py-3">
          <div>
            <p class="text-sm font-medium" style="color: var(--color-text)">{{ dish.name }}</p>
            <p class="text-xs mt-0.5" style="color: var(--color-text-3)">
              {{ dish.kcalPer100g }} ккал · Б{{ dish.proteinPer100g }} Ж{{ dish.fatPer100g }} У{{ dish.carbsPer100g }} /100г
            </p>
          </div>
          <button class="text-xs p-1" style="color: var(--color-text-3)" @click="dishToDelete = dish">✕</button>
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

    <ASheet v-model="showCreate" title="Новое блюдо">
      <div class="flex flex-col gap-4">
        <AInput v-model="form.name" label="Название" placeholder="Куриная грудка" />
        <p class="text-xs" style="color: var(--color-text-3)">КБЖУ на 100 г</p>
        <div class="grid grid-cols-2 gap-3">
          <AInput v-model="form.kcalPer100g" label="Калории" type="number" placeholder="165" />
          <AInput v-model="form.proteinPer100g" label="Белки (г)" type="number" placeholder="31" />
          <AInput v-model="form.fatPer100g" label="Жиры (г)" type="number" placeholder="3.6" />
          <AInput v-model="form.carbsPer100g" label="Углеводы (г)" type="number" placeholder="0" />
        </div>
        <AButton size="lg" :loading="saving" :disabled="!canCreate" class="w-full" @click="createDish">Сохранить</AButton>
      </div>
    </ASheet>
  </div>
</template>
