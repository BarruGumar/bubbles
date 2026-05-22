import { ref } from 'vue'

const DRAG_THR = 5
const TOUCH_THR = 14

export function useDrag(onBubbleClick) {
    const dragging = ref(null)
    const dragMoved = ref(false)
    let isTouchDrag = false

    function startDrag(bubble, e) {
        if (e.button !== 0) return
        e.preventDefault()
        e.stopPropagation()
        if (bubble.selected) return
        isTouchDrag = false
        dragging.value = {
            id: bubble.id,
            ox: e.clientX - bubble.x,
            oy: e.clientY - bubble.y,
            sx: e.clientX,
            sy: e.clientY,
        }
        dragMoved.value = false
    }

    function startTouch(bubble, e) {
        e.preventDefault()
        e.stopPropagation()
        if (bubble.selected) return
        isTouchDrag = true
        const t = e.touches[0]
        dragging.value = {
            id: bubble.id,
            ox: t.clientX - bubble.x,
            oy: t.clientY - bubble.y,
            sx: t.clientX,
            sy: t.clientY,
        }
        dragMoved.value = false
    }

    function _move(clientX, clientY, bubbles) {
        if (!dragging.value) return
        const dx = clientX - dragging.value.sx
        const dy = clientY - dragging.value.sy
        const thr = isTouchDrag ? TOUCH_THR : DRAG_THR
        if (!dragMoved.value && Math.hypot(dx, dy) > thr) dragMoved.value = true
        if (!dragMoved.value) return
        const b = bubbles.find((x) => x.id === dragging.value.id)
        if (!b) return
        b.x = Math.max(60, Math.min(clientX - dragging.value.ox, window.innerWidth - b.size - 60))
        b.y = Math.max(60, Math.min(clientY - dragging.value.oy, window.innerHeight - b.size - 60))
        b.vx = 0
        b.vy = 0
    }

    function onMouseMove(e, bubbles) {
        _move(e.clientX, e.clientY, bubbles)
    }

    function onTouchMove(e, bubbles) {
        if (!dragging.value) return
        e.preventDefault()
        _move(e.touches[0].clientX, e.touches[0].clientY, bubbles)
    }

    function stopDrag() {
        if (dragging.value && !dragMoved.value) {
            onBubbleClick?.(dragging.value.id)
        }
        dragging.value = null
        dragMoved.value = false
    }

    return { dragging, dragMoved, startDrag, startTouch, onMouseMove, onTouchMove, stopDrag }
}
