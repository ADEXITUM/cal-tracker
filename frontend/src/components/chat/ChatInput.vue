<script setup lang="ts">
import { ref, nextTick, watch } from 'vue'

const props = defineProps<{
  sending: boolean
}>()

const emit = defineEmits<{
  (e: 'send', text: string): void
}>()

const text = ref('')
const textareaRef = ref<HTMLTextAreaElement | null>(null)

function resize() {
  const el = textareaRef.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = Math.min(el.scrollHeight, 160) + 'px'
}

watch(text, () => nextTick(resize))

function submit() {
  if (props.sending) return
  const t = text.value.trim()
  if (!t) return
  emit('send', t)
  text.value = ''
  nextTick(resize)
}

function onKey(e: KeyboardEvent) {
  // Enter sends, Shift+Enter inserts newline.
  if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
    e.preventDefault()
    submit()
  }
}
</script>

<template>
  <div
    class="flex items-end gap-2 p-2"
    style="background: var(--color-surface); border-top: 1px solid var(--color-border)"
  >
    <textarea
      ref="textareaRef"
      v-model="text"
      rows="1"
      placeholder="Что съели?"
      class="flex-1 resize-none rounded-[var(--radius-sm)] px-3 py-2 text-[14px] focus:outline-none focus:ring-2"
      style="background: var(--color-surface-2); color: var(--color-text); border: 1px solid var(--color-border); --tw-ring-color: var(--color-accent-soft)"
      :disabled="sending"
      @keydown="onKey"
    />
    <button
      type="button"
      class="rounded-[var(--radius-sm)] w-10 h-10 flex items-center justify-center transition-transform active:scale-[0.95] disabled:opacity-40"
      style="background: var(--color-accent); color: white"
      :disabled="sending || !text.trim()"
      @click="submit"
    >
      <span class="text-[20px] leading-none">↑</span>
    </button>
  </div>
</template>
