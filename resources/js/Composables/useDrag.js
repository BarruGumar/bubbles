import { ref } from 'vue'

const DRAG_THR = 5

export function useDrag(onBubbleClick) {
  const dragging  = ref(null)
  const dragMoved = ref(false)

  function startDrag(bubble, e) {
    if (e.button !== 0) return
    e.preventDefault()
    e.stopPropagation()
    if (bubble.selected) return
    dragging.value  = { id: bubble.id, ox: e.clientX - bubble.x, oy: e.clientY - bubble.y, sx: e.clientX, sy: e.clientY }
    dragMoved.value = false
  }

  function onMouseMove(e, bubbles) {
    if (!dragging.value) return
    const dx = e.clientX - dragging.value.sx
    const dy = e.clientY - dragging.value.sy
    if (!dragMoved.value && Math.hypot(dx, dy) > DRAG_THR) dragMoved.value = true
    if (!dragMoved.value) return

    const b = bubbles.find(x => x.id === dragging.value.id)
    if (!b) return
    b.x  = Math.max(60, Math.min(e.clientX - dragging.value.ox, window.innerWidth  - b.size - 60))
    b.y  = Math.max(60, Math.min(e.clientY - dragging.value.oy, window.innerHeight - b.size - 60))
    b.vx = 0
    b.vy = 0
  }

  function stopDrag() {
    if (dragging.value && !dragMoved.value) {
      onBubbleClick?.(dragging.value.id)
    }
    dragging.value  = null
    dragMoved.value = false
  }

  return { dragging, dragMoved, startDrag, onMouseMove, stopDrag }
}
