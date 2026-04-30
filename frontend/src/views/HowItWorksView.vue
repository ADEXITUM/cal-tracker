<script setup lang="ts">
import { useRouter } from 'vue-router'
import ACard from '@/components/ui/ACard.vue'

const router = useRouter()
</script>

<template>
  <div class="flex flex-col min-h-svh" style="background: var(--color-bg)">
    <header
      class="sticky top-0 z-10 flex items-center px-4 py-3 gap-3"
      style="background: var(--color-bg); border-bottom: 1px solid var(--color-border)"
    >
      <button class="p-1 -ml-1" style="color: var(--color-text-2)" @click="router.back()">←</button>
      <h1 class="text-base font-semibold" style="color: var(--color-text)">Как это работает</h1>
    </header>

    <div class="p-4 pb-24 flex flex-col gap-4">

      <ACard>
        <div class="p-4 flex flex-col gap-2">
          <p class="text-sm font-semibold" style="color: var(--color-text)">Главный принцип</p>
          <p class="text-sm leading-relaxed" style="color: var(--color-text-2)">
            Цель — это <strong>сколько калорий тебе есть в день</strong>. Ты сам решаешь это число при создании цели.
            Каждый день мы сравниваем съеденное с целью — это и есть выполнение плана.
          </p>
          <p class="text-sm leading-relaxed" style="color: var(--color-text-2)">
            Шаги и тренировки <strong>не меняют цель</strong>. Они показывают реальный расход энергии в отдельной вкладке.
          </p>
        </div>
      </ACard>

      <ACard>
        <div class="p-4 flex flex-col gap-2">
          <p class="text-sm font-semibold" style="color: var(--color-text)">Тип цели</p>
          <p class="text-sm leading-relaxed" style="color: var(--color-text-2)">
            При создании цели ты выбираешь:
          </p>
          <ul class="text-sm leading-relaxed list-disc pl-5 space-y-1" style="color: var(--color-text-2)">
            <li><strong>Сушка</strong> — дефицит, цель ниже расхода</li>
            <li><strong>Поддержка</strong> — калории около расхода</li>
            <li><strong>Набор</strong> — профицит, цель выше расхода</li>
          </ul>
          <p class="text-sm leading-relaxed" style="color: var(--color-text-2)">
            Этот ярлык висит на цели весь её срок. Не путать с дневным бейджем «На цели/Перебор/Недобор» —
            он про то, попал ли ты в число <em>сегодня</em>.
          </p>
        </div>
      </ACard>

      <ACard>
        <div class="p-4 flex flex-col gap-2">
          <p class="text-sm font-semibold" style="color: var(--color-text)">Как считается расход (TDEE)</p>
          <p class="text-sm leading-relaxed" style="color: var(--color-text-2)">
            На вкладке «Реальный баланс» — три части:
          </p>
          <ul class="text-sm leading-relaxed list-disc pl-5 space-y-1" style="color: var(--color-text-2)">
            <li><strong>База</strong> = BMR × 1.2 — минимальный расход в покое + быт. BMR считается формулой Mifflin-St Jeor по полу, росту, весу, возрасту.</li>
            <li><strong>Шаги</strong> = шаги × вес × 0.0005 — рекомендуемая оценка расхода при ходьбе.</li>
            <li><strong>Тренировки</strong> = ккал, которые ты сам указал в записи.</li>
          </ul>
          <p class="text-sm leading-relaxed" style="color: var(--color-text-2)">
            <strong>Сжёг = База + Шаги + Тренировки</strong>. Если шаги/тренировки не записаны — расход = только База.
          </p>
        </div>
      </ACard>

      <ACard>
        <div class="p-4 flex flex-col gap-2">
          <p class="text-sm font-semibold" style="color: var(--color-text)">Бейдж выполнения дня</p>
          <p class="text-sm leading-relaxed" style="color: var(--color-text-2)">
            Сравнение съеденного с целью:
          </p>
          <ul class="text-sm leading-relaxed list-disc pl-5 space-y-1" style="color: var(--color-text-2)">
            <li><strong>На цели</strong> — отклонение в пределах ±5%</li>
            <li><strong>Перебор / Недобор</strong> — 5–15%</li>
            <li><strong>Сильный перебор / недобор</strong> — больше 15%</li>
          </ul>
        </div>
      </ACard>

      <ACard>
        <div class="p-4 flex flex-col gap-2">
          <p class="text-sm font-semibold" style="color: var(--color-text)">Календарь в Прогрессе</p>
          <p class="text-sm leading-relaxed" style="color: var(--color-text-2)">
            Цвет каждого дня — это попадание в цель:
          </p>
          <ul class="text-sm leading-relaxed list-disc pl-5 space-y-1" style="color: var(--color-text-2)">
            <li><span style="color: var(--color-green)">●</span> зелёный — на цели (±5%)</li>
            <li><span style="color: var(--color-yellow)">●</span> жёлтый — отклонение 5–15%</li>
            <li><span style="color: var(--color-red)">●</span> красный — отклонение больше 15%</li>
            <li><span style="color: var(--color-text-3)">●</span> серый — нет данных</li>
          </ul>
        </div>
      </ACard>

      <ACard>
        <div class="p-4 flex flex-col gap-2">
          <p class="text-sm font-semibold" style="color: var(--color-text)">А что если я перестал записывать шаги?</p>
          <p class="text-sm leading-relaxed" style="color: var(--color-text-2)">
            Бейдж «На цели» от этого не зависит. Он сравнивает только съеденное с целью.
            Только вкладка «Реальный баланс» покажет меньший расход — это нормально, она информативная.
          </p>
        </div>
      </ACard>

    </div>
  </div>
</template>
