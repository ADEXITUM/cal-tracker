<script setup lang="ts">

const props = withDefaults(defineProps<{
  modelValue: string
  label?: string
  unit?: string
  allowDecimal?: boolean
}>(), { allowDecimal: true, unit: '' })

const emit = defineEmits<{ 'update:modelValue': [v: string] }>()

const keys = ['7','8','9','4','5','6','1','2','3','.','0','⌫']

function tap(k: string) {
  let v = props.modelValue
  if (k === '⌫') { v = v.slice(0, -1) || '' }
  else if (k === '.') { if (!props.allowDecimal || v.includes('.')) return; v = v ? v + '.' : '0.' }
  else if (v === '0') { v = k }
  else { v = v + k }
  // Max 6 digits
  if (v.replace('.','').length > 6) return
  emit('update:modelValue', v)
  navigator.vibrate?.(5)
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <div class="text-center py-3 rounded-[var(--radius-md)]" style="background: var(--color-surface-2)">
      <span v-if="label" class="text-sm block mb-1" style="color: var(--color-text-3)">{{ label }}</span>
      <span class="font-mono text-4xl font-light" style="color: var(--color-text)">
        {{ modelValue || '0' }}<span v-if="unit" class="text-xl ml-1" style="color: var(--color-text-3)">{{ unit }}</span>
      </span>
    </div>
    <div class="grid grid-cols-3 gap-2">
      <button
        v-for="k in keys"
        :key="k"
        type="button"
        class="h-14 rounded-[var(--radius-md)] text-xl font-medium transition-transform active:scale-95"
        :style="{
          background: k === '⌫' ? 'var(--color-surface-2)' : 'var(--color-surface)',
          color: 'var(--color-text)',
          border: '1px solid var(--color-border)',
          fontFamily: k === '⌫' ? 'inherit' : 'JetBrains Mono Variable, monospace',
        }"
        @click="tap(k)"
      >
        {{ k }}
      </button>
    </div>
  </div>
</template>
