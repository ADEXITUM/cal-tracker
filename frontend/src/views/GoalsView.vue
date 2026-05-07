<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useGoalsStore } from '@/stores/goals'
import { GOAL_TYPE_LABEL } from '@/lib/modes'
import ACard from '@/components/ui/ACard.vue'
import AButton from '@/components/ui/AButton.vue'
import AConfirm from '@/components/ui/AConfirm.vue'
import GoalEditSheet from '@/components/goals/GoalEditSheet.vue'
import type { Goal, GoalType } from '@/types/api'

const goals = useGoalsStore()
const showEdit = ref(false)
const editing = ref<Goal | null>(null)
const goalToEnd = ref<Goal | null>(null)

onMounted(async () => {
  await goals.fetchAll()
})

function openCreate() {
  editing.value = null
  showEdit.value = true
}

function openEdit(g: Goal) {
  editing.value = g
  showEdit.value = true
}

function formatDate(iso: string): string {
  return new Date(iso + 'T12:00:00').toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' })
}

const today = new Date().toISOString().slice(0, 10)

function isActive(g: Goal): boolean {
  return g.startDate <= today && (g.endDate === null || g.endDate >= today)
}

const TYPE_STYLE: Record<GoalType, { bg: string; text: string; icon: string }> = {
  cut:         { bg: 'var(--color-red-soft)',   text: 'var(--color-red)',    icon: '↘' },
  maintenance: { bg: 'var(--color-surface-2)',  text: 'var(--color-text-2)', icon: '=' },
  bulk:        { bg: 'var(--color-green-soft)', text: 'var(--color-green)',  icon: '↗' },
}

function askEnd(g: Goal) {
  goalToEnd.value = g
}

async function confirmEnd() {
  const g = goalToEnd.value
  goalToEnd.value = null
  if (g) await goals.endGoal(g.uuid)
}

const sortedGoals = computed(() => goals.sorted)
</script>

<template>
  <div class="flex flex-col min-h-svh" style="background: var(--color-bg)">
    <header
      class="sticky top-0 z-10 flex items-center justify-between px-4 py-3"
      style="background: var(--color-bg); border-bottom: 1px solid var(--color-border)"
    >
      <h1 class="text-base font-semibold" style="color: var(--color-text)">Цели</h1>
      <AButton size="sm" @click="openCreate">+ Новая</AButton>
    </header>

    <div class="p-4 pb-24 flex flex-col gap-3">
      <div v-if="goals.loading" class="text-sm text-center py-8" style="color: var(--color-text-3)">
        Загрузка...
      </div>

      <p v-else-if="sortedGoals.length === 0" class="text-sm text-center py-8" style="color: var(--color-text-3)">
        Нет целей. Создайте первую.
      </p>

      <ACard
        v-for="g in sortedGoals"
        :key="g.uuid"
        class="cursor-pointer"
        @click="openEdit(g)"
      >
        <div class="px-4 py-3 flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span
                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                :style="{ background: TYPE_STYLE[g.type].bg, color: TYPE_STYLE[g.type].text }"
              >
                <span>{{ TYPE_STYLE[g.type].icon }}</span>
                <span>{{ GOAL_TYPE_LABEL[g.type] }}</span>
              </span>
              <span
                v-if="isActive(g)"
                class="text-[10px] px-1.5 py-0.5 rounded-full font-medium"
                style="background: var(--color-accent); color: white"
              >
                Активна
              </span>
            </div>
            <p class="font-mono text-base" style="color: var(--color-text)">
              {{ g.kcal }} ккал
            </p>
            <p class="text-xs mt-0.5" style="color: var(--color-text-3)">
              Б{{ g.proteinG }} · Ж{{ g.fatG }} · У{{ g.carbsG }}
            </p>
            <p class="text-xs mt-1" style="color: var(--color-text-3)">
              {{ formatDate(g.startDate) }} → {{ g.endDate ? formatDate(g.endDate) : 'без срока' }}
            </p>
          </div>
          <button
            v-if="isActive(g) && !g.endDate"
            class="text-xs px-2 py-1 rounded-[var(--radius-sm)] flex-shrink-0"
            style="color: var(--color-accent); background: var(--color-surface-2)"
            @click.stop="askEnd(g)"
          >Завершить</button>
        </div>
      </ACard>
    </div>

    <GoalEditSheet
      v-model="showEdit"
      :goal="editing"
      @saved="goals.fetchAll(true)"
    />

    <AConfirm
      :model-value="goalToEnd !== null"
      title="Завершить цель?"
      :message="goalToEnd ? `Цель «${goalToEnd.kcal} ккал» будет завершена сегодняшним днём.` : ''"
      confirm-label="Завершить"
      variant="primary"
      @update:model-value="(v) => { if (!v) goalToEnd = null }"
      @confirm="confirmEnd"
    />
  </div>
</template>
