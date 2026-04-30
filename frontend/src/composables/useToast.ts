import { ref } from 'vue'

export type ToastTone = 'info' | 'success' | 'warning' | 'error'

export interface Toast {
  id: number
  message: string
  tone: ToastTone
}

let nextId = 0
const toasts = ref<Toast[]>([])

export function useToast() {
  function show(message: string, tone: ToastTone = 'info', duration = 3500) {
    const id = ++nextId
    toasts.value.push({ id, message, tone })
    setTimeout(() => dismiss(id), duration)
    return id
  }

  function dismiss(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  return { toasts, show, dismiss }
}
