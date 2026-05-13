import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useChatStore } from '@/stores/chat'
import type { ChatMessage } from '@/types/api'

vi.mock('@/api/chat', () => ({
  chatApi: {
    list: vi.fn(),
    send: vi.fn(),
    apply: vi.fn(),
  },
}))

import { chatApi } from '@/api/chat'

const sample = (overrides: Partial<ChatMessage> = {}): ChatMessage => ({
  uuid: 'm1',
  role: 'user',
  content: [{ type: 'text', text: 'hi' }],
  createdAt: '2026-05-13T12:00:00+00:00',
  sender: null,
  ...overrides,
})

describe('chat store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('starts empty', () => {
    const chat = useChatStore()
    expect(chat.messages).toEqual([])
    expect(chat.hasMessages).toBe(false)
    expect(chat.initialized).toBe(false)
  })

  it('load() populates messages and marks initialized', async () => {
    vi.mocked(chatApi.list).mockResolvedValue({ data: [sample()] })
    const chat = useChatStore()
    await chat.load()
    expect(chat.messages).toHaveLength(1)
    expect(chat.initialized).toBe(true)
    expect(chat.error).toBeNull()
  })

  it('load() captures error without blowing up', async () => {
    vi.mocked(chatApi.list).mockRejectedValue(new Error('boom'))
    const chat = useChatStore()
    await chat.load()
    expect(chat.error).toBe('boom')
    expect(chat.initialized).toBe(true)
  })

  it('send() appends both user and assistant messages', async () => {
    vi.mocked(chatApi.send).mockResolvedValue({
      data: {
        user: sample({ uuid: 'u', role: 'user' }),
        assistant: sample({ uuid: 'a', role: 'assistant', content: [{ type: 'text', text: 'ok' }] }),
      },
    })
    const chat = useChatStore()
    await chat.send('  hello  ')
    expect(chat.messages.map((m) => m.uuid)).toEqual(['u', 'a'])
    expect(chatApi.send).toHaveBeenCalledWith('hello')
  })

  it('send() ignores empty input', async () => {
    const chat = useChatStore()
    await chat.send('   ')
    expect(chatApi.send).not.toHaveBeenCalled()
  })

  it('apply() replaces message by uuid', async () => {
    const original = sample({
      uuid: 'a',
      role: 'assistant',
      content: [
        {
          type: 'tool_use',
          id: 'tu1',
          name: 'propose_meal',
          input: {},
          status: 'pending',
        },
      ],
    })
    const updated = sample({
      uuid: 'a',
      role: 'assistant',
      content: [
        {
          type: 'tool_use',
          id: 'tu1',
          name: 'propose_meal',
          input: {},
          status: 'approved',
          mealUuid: 'meal-1',
        },
      ],
    })
    vi.mocked(chatApi.apply).mockResolvedValue({ data: updated })

    const chat = useChatStore()
    chat.messages.push(original)
    await chat.apply('a', [{ toolUseId: 'tu1', action: 'approve' }])

    expect(chat.messages[0].content[0]).toMatchObject({ status: 'approved', mealUuid: 'meal-1' })
  })

  it('reset() clears everything', () => {
    const chat = useChatStore()
    chat.messages.push(sample())
    chat.reset()
    expect(chat.messages).toEqual([])
    expect(chat.initialized).toBe(false)
  })
})
