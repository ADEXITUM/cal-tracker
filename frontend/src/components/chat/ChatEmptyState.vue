<script setup lang="ts">
defineProps<{
  /** Display names of known household users for example hints. */
  userNames: string[]
}>()
</script>

<template>
  <div class="flex flex-col items-center justify-center text-center px-6 py-12">
    <div
      class="w-12 h-12 rounded-full flex items-center justify-center mb-4"
      style="background: var(--color-accent-tint); color: var(--color-accent)"
    >
      <span class="text-2xl">✦</span>
    </div>
    <h2 class="text-[17px] font-medium mb-1">Записываю еду в дневник</h2>
    <p class="text-[13px] mb-6" style="color: var(--color-text-2)">
      Опиши блюдо — посчитаю КБЖУ и предложу добавить. Подтверждение за тобой.
    </p>
    <div
      class="w-full max-w-sm rounded-[var(--radius-md)] p-3 text-left text-[13px]"
      style="background: var(--color-surface-2); color: var(--color-text-2)"
    >
      <div class="font-medium mb-2" style="color: var(--color-text)">Например:</div>
      <ul class="space-y-1.5">
        <li>"200г грудки + 100г риса варёного"</li>
        <li v-if="userNames.length >= 2">
          "{{ userNames[1] }} съела 130г йогурта Эпика, КБЖУ 130/10/4/16"
        </li>
        <li>"плов: 300г риса сухого + 400г баранины, всего 1.5кг, мне 250г"</li>
      </ul>
    </div>
  </div>
</template>
