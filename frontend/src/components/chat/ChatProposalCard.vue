<script setup lang="ts">
import { ref, computed } from 'vue'
import type { ChatToolUseBlock } from '@/types/api'
import { formatRelativeDateTime } from '@/lib/time'
import AButton from '@/components/ui/AButton.vue'

const props = defineProps<{
  block: ChatToolUseBlock
  /** Stable color for the proposal's target user (derived in parent). */
  accentColor: string
  busy?: boolean
}>()

const emit = defineEmits<{
  (e: 'approve', toolUseId: string): void
  (e: 'reject', toolUseId: string): void
}>()

const showBreakdown = ref(false)

function fmt(n: number, digits = 0): string {
  return n.toFixed(digits)
}

const whenLabel = computed(() =>
  props.block.result ? formatRelativeDateTime(props.block.result.eatenAt) : '',
)
</script>

<template>
  <div
    class="rounded-[var(--radius-md)] overflow-hidden"
    :style="{
      background: 'var(--color-surface)',
      border: '1px solid var(--color-border)',
      borderLeft: `4px solid ${accentColor}`,
    }"
  >
    <!-- Header: target user -->
    <div class="px-3 pt-3 flex items-center justify-between">
      <div class="flex items-center gap-2 min-w-0">
        <span
          class="text-[11px] font-medium uppercase tracking-wide"
          :style="{ color: accentColor }"
        >
          {{ block.result?.targetUser.name ?? '???' }}
        </span>
        <span
          v-if="block.status === 'approved'"
          class="text-[11px] font-medium"
          style="color: var(--color-green)"
        >
          ✓ Добавлено
        </span>
        <span
          v-else-if="block.status === 'rejected'"
          class="text-[11px] font-medium"
          style="color: var(--color-text-3)"
        >
          Отменено
        </span>
        <span
          v-else-if="block.status === 'error'"
          class="text-[11px] font-medium"
          style="color: var(--color-red)"
        >
          Ошибка
        </span>
      </div>
    </div>

    <!-- Body -->
    <div class="px-3 pt-2 pb-3">
      <div v-if="block.result" class="flex items-baseline justify-between gap-2">
        <div class="text-[15px] font-medium truncate">{{ block.result.label }}</div>
        <div class="text-[13px] tabular-nums" style="color: var(--color-text-2)">
          {{ fmt(block.result.eatenGrams) }} г
        </div>
      </div>
      <div v-if="block.result" class="mt-1 text-[13px] tabular-nums" style="color: var(--color-text-2)">
        {{ fmt(block.result.kcal) }} ккал ·
        {{ fmt(block.result.proteinG, 1) }} /
        {{ fmt(block.result.fatG, 1) }} /
        {{ fmt(block.result.carbsG, 1) }}
      </div>
      <div v-if="block.result" class="mt-1 text-[12px]" style="color: var(--color-text-3)">
        {{ whenLabel }} · {{ block.result.slot === 'breakfast' ? 'завтрак' : block.result.slot === 'lunch' ? 'обед' : block.result.slot === 'snack' ? 'перекус' : block.result.slot === 'dinner' ? 'ужин' : 'другое' }}
      </div>

      <div v-else-if="block.error" class="mt-1 text-[13px]" style="color: var(--color-red)">
        {{ block.error }}
      </div>

      <!-- Ingredients breakdown — expandable -->
      <button
        v-if="block.result && block.result.ingredientsBreakdown.length > 1"
        type="button"
        class="mt-2 text-[12px] tabular-nums"
        style="color: var(--color-text-3)"
        @click="showBreakdown = !showBreakdown"
      >
        {{ showBreakdown ? 'Скрыть' : 'Состав' }}
        ({{ block.result.ingredientsBreakdown.length }})
      </button>
      <div
        v-if="showBreakdown && block.result"
        class="mt-2 space-y-1 text-[12px] tabular-nums"
        style="color: var(--color-text-2)"
      >
        <div
          v-for="ing in block.result.ingredientsBreakdown"
          :key="ing.name"
          class="flex justify-between gap-3"
        >
          <span class="truncate">{{ ing.name }} · {{ fmt(ing.grams) }} г</span>
          <span>{{ fmt(ing.kcal) }} ккал</span>
        </div>
        <div
          v-if="block.result.totalYieldGrams !== block.result.eatenGrams"
          class="pt-1 border-t mt-1"
          style="border-color: var(--color-border)"
        >
          Выход блюда: {{ fmt(block.result.totalYieldGrams) }} г,
          съедено: {{ fmt(block.result.eatenGrams) }} г
        </div>
      </div>

      <!-- Actions -->
      <div v-if="block.status === 'pending'" class="mt-3 flex gap-2">
        <AButton
          variant="primary"
          size="sm"
          :loading="busy"
          @click="emit('approve', block.id)"
        >
          Добавить
        </AButton>
        <AButton
          variant="ghost"
          size="sm"
          :disabled="busy"
          @click="emit('reject', block.id)"
        >
          Отменить
        </AButton>
      </div>
    </div>
  </div>
</template>
