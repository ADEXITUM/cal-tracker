import { api } from './client'
import type { ChatMessage } from '@/types/api'

interface ListResponse {
  data: ChatMessage[]
}

interface SendResponse {
  data: { user: ChatMessage; assistant: ChatMessage }
}

interface ApplyResponse {
  data: ChatMessage
}

export type ProposalAction = 'approve' | 'reject'

export const chatApi = {
  list: () => api.get<ListResponse>('/chat/messages'),

  send: (text: string) =>
    api.post<SendResponse>('/chat/messages', { text }),

  apply: (
    messageUuid: string,
    items: Array<{ toolUseId: string; action: ProposalAction }>,
  ) =>
    api.post<ApplyResponse>(`/chat/messages/${messageUuid}/apply`, { items }),
}
