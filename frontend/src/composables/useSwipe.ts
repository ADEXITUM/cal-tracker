import { onMounted, onUnmounted } from 'vue'

export function useSwipe(opts: {
  onLeft?: () => void
  onRight?: () => void
  threshold?: number
  target?: () => HTMLElement | null
}) {
  const threshold = opts.threshold ?? 60
  let startX = 0
  let startY = 0

  function onTouchStart(e: TouchEvent) {
    startX = e.touches[0].clientX
    startY = e.touches[0].clientY
  }

  function onTouchEnd(e: TouchEvent) {
    const dx = e.changedTouches[0].clientX - startX
    const dy = e.changedTouches[0].clientY - startY
    if (Math.abs(dy) > Math.abs(dx)) return // vertical scroll, ignore
    if (dx < -threshold) opts.onLeft?.()
    else if (dx > threshold) opts.onRight?.()
  }

  onMounted(() => {
    const el = opts.target?.() ?? document.body
    el.addEventListener('touchstart', onTouchStart, { passive: true })
    el.addEventListener('touchend', onTouchEnd, { passive: true })
  })

  onUnmounted(() => {
    const el = opts.target?.() ?? document.body
    el.removeEventListener('touchstart', onTouchStart)
    el.removeEventListener('touchend', onTouchEnd)
  })
}
