<script setup lang="ts">
import { ref, computed, nextTick, onMounted, watch } from 'vue'
import { useChatStore } from '@/stores/chat'
import { useAuthStore } from '@/stores/auth'
import { useDayStore } from '@/stores/day'
import ChatMessageBubble from '@/components/chat/ChatMessageBubble.vue'
import ChatInput from '@/components/chat/ChatInput.vue'
import ChatEmptyState from '@/components/chat/ChatEmptyState.vue'

const chat = useChatStore()
const auth = useAuthStore()
const dayStore = useDayStore()

const scrollerRef = ref<HTMLElement | null>(null)
const busyToolId = ref<string | null>(null)

/**
 * Stable color per user uuid. We seed from saved accounts (where avatar
 * colors live in client-side storage) and fall back to a hash-based hue
 * so that proposals for users we don't have a token for still render
 * with a consistent marker.
 */
function userColor(uuid: string): string {
  const acc = auth.savedAccounts.find((a) => a.uuid === uuid)
  if (acc?.avatarColor) return acc.avatarColor
  if (auth.currentUser?.uuid === uuid && auth.currentUser.avatarColor) {
    return auth.currentUser.avatarColor
  }
  // Stable hash → hue
  let h = 0
  for (let i = 0; i < uuid.length; i++) h = (h * 31 + uuid.charCodeAt(i)) & 0xffffffff
  const hue = Math.abs(h) % 360
  return `hsl(${hue} 65% 45%)`
}

const knownUserNames = computed(() => {
  const names = new Set<string>()
  if (auth.currentUser) names.add(auth.currentUser.name)
  for (const a of auth.savedAccounts) names.add(a.name)
  return Array.from(names)
})

async function scrollToBottom(smooth = false) {
  await nextTick()
  const el = scrollerRef.value
  if (!el) return
  el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' })
}

onMounted(async () => {
  if (!chat.initialized) {
    await chat.load()
  }
  await scrollToBottom(false)
})

watch(
  [() => chat.messages.length, () => chat.sending],
  () => { void scrollToBottom(true) },
)

async function handleSend(text: string) {
  try {
    await chat.send(text)
    // Refresh day in background — meal may have been added for the
    // current user via approve flow earlier; for new turns the day
    // doesn't change yet, but we still want fresh insights/totals.
  } catch {
    // Error already exposed via chat.error
  }
}

async function handleApprove(messageUuid: string, toolUseId: string) {
  if (busyToolId.value) return
  busyToolId.value = toolUseId
  try {
    await chat.apply(messageUuid, [{ toolUseId, action: 'approve' }])
    void dayStore.fetch({ skipCache: true })
  } finally {
    busyToolId.value = null
  }
}

async function handleReject(messageUuid: string, toolUseId: string) {
  if (busyToolId.value) return
  busyToolId.value = toolUseId
  try {
    await chat.apply(messageUuid, [{ toolUseId, action: 'reject' }])
  } finally {
    busyToolId.value = null
  }
}

async function handleApproveAll(messageUuid: string) {
  if (busyToolId.value) return
  const msg = chat.messages.find((m) => m.uuid === messageUuid)
  if (!msg) return
  const pendingIds = msg.content
    .filter((b) => b.type === 'tool_use' && b.status === 'pending')
    .map((b) => (b as { id: string }).id)
  if (pendingIds.length === 0) return

  busyToolId.value = pendingIds[0] ?? null
  try {
    await chat.apply(
      messageUuid,
      pendingIds.map((id) => ({ toolUseId: id, action: 'approve' as const })),
    )
    void dayStore.fetch({ skipCache: true })
  } finally {
    busyToolId.value = null
  }
}
</script>

<template>
  <div class="flex flex-col h-svh">
    <!-- Top bar -->
    <header
      class="flex-shrink-0 flex items-center px-4 py-3"
      style="background: var(--color-surface); border-bottom: 1px solid var(--color-border)"
    >
      <h1 class="text-[17px] font-medium">Чат</h1>
    </header>

    <!-- Messages -->
    <div
      ref="scrollerRef"
      class="flex-1 overflow-y-auto px-3 py-4 flex flex-col gap-3"
      style="background: var(--color-bg)"
    >
      <ChatEmptyState
        v-if="chat.initialized && chat.messages.length === 0"
        :user-names="knownUserNames"
      />

      <ChatMessageBubble
        v-for="msg in chat.messages"
        :key="msg.uuid"
        :message="msg"
        :self-uuid="auth.currentUser?.uuid ?? null"
        :user-color="userColor"
        :busy-tool-id="busyToolId"
        @approve="handleApprove"
        @reject="handleReject"
        @approve-all="handleApproveAll"
      />

      <!-- Typing indicator — visible while waiting for the model. -->
      <div
        v-if="chat.sending"
        class="self-start max-w-[85%] rounded-[var(--radius-md)] px-3 py-2.5 flex items-center gap-1.5"
        style="background: var(--color-surface-2)"
      >
        <span class="typing-dot" />
        <span class="typing-dot" style="animation-delay: 150ms" />
        <span class="typing-dot" style="animation-delay: 300ms" />
      </div>

      <div
        v-if="chat.error"
        class="self-center text-[12px] px-3 py-2 rounded-[var(--radius-sm)]"
        style="background: var(--color-red-soft); color: var(--color-red)"
      >
        {{ chat.error }}
      </div>
    </div>

    <!-- Input area sits above the global bottom nav. -->
    <div class="flex-shrink-0" style="padding-bottom: calc(env(safe-area-inset-bottom) + 56px)">
      <ChatInput :sending="chat.sending" @send="handleSend" />
    </div>
  </div>
</template>

<style scoped>
.typing-dot {
  width: 6px;
  height: 6px;
  border-radius: 9999px;
  background: var(--color-text-3);
  animation: typing-bounce 1.2s ease-in-out infinite;
}

@keyframes typing-bounce {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 0.4;
  }
  30% {
    transform: translateY(-3px);
    opacity: 1;
  }
}
</style>
