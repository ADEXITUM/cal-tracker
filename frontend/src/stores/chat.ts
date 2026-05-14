import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { chatApi, type ProposalAction } from '@/api/chat'
import { useAuthStore } from '@/stores/auth'
import type { ChatMessage } from '@/types/api'

export const useChatStore = defineStore('chat', () => {
  const messages = ref<ChatMessage[]>([])
  const loading = ref(false)
  const sending = ref(false)
  const error = ref<string | null>(null)
  /** Set once we have successfully loaded from the server so empty-state UI
   *  doesn't flash before the first request completes. */
  const initialized = ref(false)

  const hasMessages = computed(() => messages.value.length > 0)

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const res = await chatApi.list()
      messages.value = res.data
    } catch (e) {
      error.value = (e as Error).message ?? 'Не удалось загрузить чат'
    } finally {
      loading.value = false
      initialized.value = true
    }
  }

  async function send(text: string): Promise<void> {
    const trimmed = text.trim()
    if (!trimmed) return

    // Optimistic push: user sees their own bubble immediately while the
    // model still chews on the request. On success we swap the temp row
    // with the server-issued one (real uuid + canonical timestamp).
    //
    // On failure we KEEP the temp row in place: the backend persists the
    // user message before calling the LLM, so even on 500 the message is
    // safely in the DB and will reappear on reload. Removing it from the
    // UI made it look as if the message vanished after an error, which
    // confused users — they retyped, then saw a duplicate after refresh.
    const auth = useAuthStore()
    const tempUuid = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
    const tempMsg: ChatMessage = {
      uuid: tempUuid,
      role: 'user',
      content: [{ type: 'text', text: trimmed }],
      createdAt: new Date().toISOString(),
      sender: auth.currentUser
        ? {
            uuid: auth.currentUser.uuid,
            name: auth.currentUser.name,
            avatarColor: auth.currentUser.avatarColor,
          }
        : null,
    }
    messages.value.push(tempMsg)

    sending.value = true
    error.value = null
    try {
      const res = await chatApi.send(trimmed)
      const idx = messages.value.findIndex((m) => m.uuid === tempUuid)
      if (idx >= 0) {
        messages.value.splice(idx, 1, res.data.user)
      } else {
        messages.value.push(res.data.user)
      }
      messages.value.push(res.data.assistant)
    } catch (e) {
      // "Failed to fetch" — это network error браузера (мобильник засыпал,
      // proxy оборвал, и т.п.). Бэкенд при этом мог запрос обработать; user
      // message в БД точно есть. Даём понятный текст вместо сырого fetch error.
      const raw = (e as Error).message ?? ''
      error.value = raw.toLowerCase().includes('failed to fetch') || raw === 'Network unavailable'
        ? 'Связь оборвалась. Сообщение могло сохраниться — обнови чат через минуту.'
        : raw || 'Не удалось отправить сообщение'
      throw e
    } finally {
      sending.value = false
    }
  }

  async function apply(
    messageUuid: string,
    items: Array<{ toolUseId: string; action: ProposalAction }>,
  ): Promise<ChatMessage> {
    error.value = null
    const res = await chatApi.apply(messageUuid, items)
    // Replace the message in-place to reflect updated proposal statuses.
    const idx = messages.value.findIndex((m) => m.uuid === messageUuid)
    if (idx >= 0) {
      messages.value.splice(idx, 1, res.data)
    }
    return res.data
  }

  function reset(): void {
    messages.value = []
    loading.value = false
    sending.value = false
    error.value = null
    initialized.value = false
  }

  return {
    messages,
    loading,
    sending,
    error,
    initialized,
    hasMessages,
    load,
    send,
    apply,
    reset,
  }
})
