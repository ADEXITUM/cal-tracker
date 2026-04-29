<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useDayStore } from '@/stores/day'
import ASheet from '@/components/ui/ASheet.vue'
import AButton from '@/components/ui/AButton.vue'
import AInput from '@/components/ui/AInput.vue'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [v: boolean] }>()

const day = useDayStore()
const name = ref('')
const duration = ref('')
const kcalBurned = ref('')
const loading = ref(false)

const canSave = computed(() => name.value.trim().length > 0)

watch(() => props.modelValue, (v) => { if (v) { name.value = ''; duration.value = ''; kcalBurned.value = '' } })

async function save() {
  if (!name.value.trim()) return
  loading.value = true
  try {
    await day.addWorkout({
      name: name.value.trim(),
      durationMin: duration.value ? parseInt(duration.value) : null,
      kcalBurned: kcalBurned.value ? parseInt(kcalBurned.value) : null,
    })
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <ASheet :model-value="modelValue" title="Добавить тренировку" @update:model-value="$emit('update:modelValue', $event)">
    <div class="flex flex-col gap-4">
      <AInput v-model="name" label="Название" placeholder="Бег, силовая, плавание..." />
      <div class="grid grid-cols-2 gap-3">
        <AInput v-model="duration" label="Длительность (мин)" type="number" placeholder="45" />
        <AInput v-model="kcalBurned" label="Сожжено ккал" type="number" placeholder="300" />
      </div>
      <AButton size="lg" :loading="loading" :disabled="!canSave" class="w-full" @click="save">Сохранить</AButton>
    </div>
  </ASheet>
</template>
