<script setup lang="ts">
import { computed } from 'vue'
import type { ChatMessage, ChatTextBlock, ChatToolUseBlock } from '@/types/api'
import ChatProposalCard from './ChatProposalCard.vue'

const props = defineProps<{
  message: ChatMessage
  /** Currently-authenticated admin uuid — used to align "my" messages right. */
  selfUuid: string | null
  /** Stable color per target user uuid (proposals can target either admin). */
  userColor: (uuid: string) => string
  busyToolId: string | null
}>()

const emit = defineEmits<{
  (e: 'approve', messageUuid: string, toolUseId: string): void
  (e: 'reject', messageUuid: string, toolUseId: string): void
  (e: 'approveAll', messageUuid: string): void
}>()

const isUser = computed(() => props.message.role === 'user')
const isMine = computed(
  () => isUser.value && props.message.sender?.uuid === props.selfUuid,
)
const isPartner = computed(
  () =>
    isUser.value &&
    props.message.sender !== null &&
    props.message.sender.uuid !== props.selfUuid,
)
const senderColor = computed(() =>
  props.message.sender ? props.userColor(props.message.sender.uuid) : 'var(--color-text-3)',
)

const textBlocks = computed(() =>
  props.message.content.filter((b): b is ChatTextBlock => b.type === 'text'),
)
const toolBlocks = computed(() =>
  props.message.content.filter((b): b is ChatToolUseBlock => b.type === 'tool_use'),
)
const pendingCount = computed(() => toolBlocks.value.filter((b) => b.status === 'pending').length)

function formatTime(iso: string): string {
  const d = new Date(iso)
  return d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div :class="['flex flex-col gap-1', isMine ? 'items-end' : 'items-start']">
    <!-- Partner sender label (shared chat: shows whose message it is) -->
    <div
      v-if="isPartner && message.sender"
      class="text-[11px] px-1 font-medium flex items-center gap-1.5"
      :style="{ color: senderColor }"
    >
      <span>{{ message.sender.name }}</span>
      <span style="color: var(--color-text-3)">·</span>
      <span style="color: var(--color-text-3)">{{ formatTime(message.createdAt) }}</span>
    </div>

    <!-- Text blocks -->
    <div
      v-for="(block, i) in textBlocks"
      :key="`t-${i}`"
      class="max-w-[85%] rounded-[var(--radius-md)] px-3 py-2 text-[14px] leading-snug whitespace-pre-wrap"
      :style="
        isMine
          ? 'background: var(--color-accent); color: white;'
          : isPartner
            ? `background: var(--color-surface); color: var(--color-text); border-left: 3px solid ${senderColor};`
            : 'background: var(--color-surface-2); color: var(--color-text);'
      "
    >{{ block.text }}</div>

    <!-- Tool blocks (proposals) — only on assistant messages -->
    <template v-if="!isUser && toolBlocks.length > 0">
      <div class="w-full max-w-[85%] flex flex-col gap-2">
        <ChatProposalCard
          v-for="block in toolBlocks"
          :key="block.id"
          :block="block"
          :accent-color="block.result ? userColor(block.result.targetUser.uuid) : 'var(--color-text-3)'"
          :busy="busyToolId === block.id"
          @approve="(id) => emit('approve', message.uuid, id)"
          @reject="(id) => emit('reject', message.uuid, id)"
        />
        <button
          v-if="pendingCount > 1"
          type="button"
          class="self-stretch rounded-[var(--radius-sm)] px-3 py-2 text-[13px] font-medium transition-colors"
          style="background: var(--color-surface-2); color: var(--color-text); border: 1px solid var(--color-border)"
          :disabled="busyToolId !== null"
          @click="emit('approveAll', message.uuid)"
        >
          Добавить всё ({{ pendingCount }})
        </button>
      </div>
    </template>
  </div>
</template>
