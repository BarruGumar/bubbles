export function usePhysics(bubbles, dragging) {
  function update() {
    for (const b of bubbles.value) {
      if (dragging.value?.id === b.id) continue

      b.x += b.vx || 0
      b.y += b.vy || 0

      b.vx *= 0.9
      b.vy *= 0.9
    }
  }

  return { update }
}