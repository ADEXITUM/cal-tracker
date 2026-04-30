<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useGoalsStore } from '@/stores/goals'
import { profileApi } from '@/api/profile'
import { daysApi } from '@/api/days'
import { computeTdee } from '@/lib/tdee'
import { classifyMode } from '@/lib/modes'
import ACard from '@/components/ui/ACard.vue'
import AButton from '@/components/ui/AButton.vue'
import AModeBadge from '@/components/ui/AModeBadge.vue'
import GoalEditSheet from '@/components/goals/GoalEditSheet.vue'
import type { Goal, ActivityLevel, Gender } from '@/types/api'

const goals = useGoalsStore()
const showEdit = ref(false)
const editing = ref<Goal | null>(null)
const tdeeKcal = ref(0)

onMounted(async () => {
  await Promise.all([goals.fetchAll(), bootstrapTdee()])
})

async function bootstrapTdee() {
  try {
    const [profileRes, todayRes] = await Promise.all([
      profileApi.get(),
      daysApi.get(new Date().toISOString().slice(0, 10)),
    ])
    const profile = profileRes.data
    const weight = todayRes.data.measurements[0]?.weightKg ?? 80
    const td = computeTdee({
      gender: profile.gender as Gender,
      birthDate: profile.birthDate,
      heightCm: profile.heightCm,
      activityLevel: profile.activityLevel as ActivityLevel,
      weightKg: weight,
    })
    tdeeKcal.value = td.total
  } catch { /* leave at 0 */ }
}

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

function modeFor(g: Goal) {
  if (!tdeeKcal.value) return null
  return classifyMode(g.kcal, tdeeKcal.value)
}

async function confirmEnd(g: Goal) {
  if (!confirm(`Завершить цель «${g.kcal} ккал» сегодняшним днём?`)) return
  await goals.endGoal(g.uuid)
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
              <AModeBadge
                v-if="modeFor(g)"
                :code="modeFor(g)!.code"
                :label="modeFor(g)!.label"
                :delta-kcal="modeFor(g)!.deltaKcal"
              />
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
            style="color: var(--color-text-2); background: var(--color-surface-2)"
            @click.stop="confirmEnd(g)"
          >Завершить</button>
        </div>
      </ACard>
    </div>

    <GoalEditSheet
      v-model="showEdit"
      :goal="editing"
      @saved="goals.fetchAll(true)"
    />
  </div>
</template>
