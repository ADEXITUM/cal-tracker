<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Insight, InsightTone } from '@/types/api'

const props = defineProps<{
  insights: Insight[]
  date: string
}>()

const dismissedKey = computed(() => `dismissed_insights_${props.date}`)
const dismissed = ref<string[]>(loadDismissed())

function loadDismissed(): string[] {
  try {
    const raw = localStorage.getItem(`dismissed_insights_${props.date}`)
    return raw ? JSON.parse(raw) : []
  } catch { return [] }
}

function dismiss(code: string) {
  dismissed.value = [...dismissed.value, code]
  try { localStorage.setItem(dismissedKey.value, JSON.stringify(dismissed.value)) } catch { /* noop */ }
}

const visible = computed(() => props.insights.filter(i => !dismissed.value.includes(i.code)))

const TONE_STYLES: Record<InsightTone, { border: string; icon: string; iconColor: string }> = {
  neutral: { border: 'var(--color-border)',     icon: 'ⓘ', iconColor: 'var(--color-text-3)' },
  good:    { border: 'var(--color-green)',      icon: '✓', iconColor: 'var(--color-green)' },
  warm:    { border: 'var(--color-accent)',     icon: '◑', iconColor: 'var(--color-accent)' },
  warn:    { border: 'var(--color-yellow)',     icon: '!', iconColor: 'var(--color-yellow)' },
  alert:   { border: 'var(--color-red)',        icon: '!', iconColor: 'var(--color-red)' },
}
</script>

<template>
  <div v-if="visible.length" class="flex flex-col gap-2">
    <div
      v-for="i in visible"
      :key="i.code"
      class="px-3 py-2.5 rounded-[var(--radius-sm)] flex items-start gap-2.5"
      :style="{
        background: 'var(--color-surface)',
        borderLeft: `3px solid ${TONE_STYLES[i.tone].border}`,
      }"
    >
      <span
        class="font-semibold mt-0.5 flex-shrink-0"
        :style="{ color: TONE_STYLES[i.tone].iconColor }"
      >
        {{ TONE_STYLES[i.tone].icon }}
      </span>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold" style="color: var(--color-text)">{{ i.title }}</p>
        <p class="text-xs mt-0.5 leading-relaxed" style="color: var(--color-text-2)">{{ i.body }}</p>
      </div>
      <button
        type="button"
        class="text-xs px-1 -mr-1"
        style="color: var(--color-text-3)"
        aria-label="Скрыть"
        @click="dismiss(i.code)"
      >✕</button>
    </div>
  </div>
</template>
