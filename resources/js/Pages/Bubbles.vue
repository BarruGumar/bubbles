<script setup>
import { ref, onMounted, onUnmounted } from 'vue'


// Estado das bolhas
const bubbles = ref([
  { id: 1, x: 100, y: 100, label: '#tech' },
  { id: 2, x: 300, y: 200, label: '#design' },
  { id: 3, x: 500, y: 150, label: '#music' },
])

// Estado de drag
const dragging = ref(null)

// Começar drag
function startDrag(bubble, event) {
  dragging.value = {
    id: bubble.id,
    offsetX: event.clientX - bubble.x,
    offsetY: event.clientY - bubble.y
  }
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(value, max))
}

// Movimento do mouse
function onMouseMove(event) {
if (!dragging.value) return

  const bubble = bubbles.value.find(
    b => b.id === dragging.value.id
  )

  if (!bubble) return

  const BUBBLE_SIZE = 100

  let newX = event.clientX - dragging.value.offsetX
  let newY = event.clientY - dragging.value.offsetY

  const maxX = window.innerWidth - BUBBLE_SIZE
  const maxY = window.innerHeight - BUBBLE_SIZE

  // ⚠️ proteção extra (evita travar se max for negativo)
  if (maxX <= 0 || maxY <= 0) return

  bubble.x = clamp(newX, 0, maxX)
  bubble.y = clamp(newY, 0, maxY)
}

// Parar drag
function stopDrag() {
  dragging.value = null
}

// Eventos globais
onMounted(() => {
  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', stopDrag)
})

onUnmounted(() => {
  window.removeEventListener('mousemove', onMouseMove)
  window.removeEventListener('mouseup', stopDrag)
})
</script>

<template>
  <div class="w-screen h-screen bg-gray-900 relative overflow-hidden">
    <div
      v-for="bubble in bubbles"
      :key="bubble.id"
      @mousedown="startDrag(bubble, $event)"
      :class="[
        'absolute rounded-full px-4 py-2 text-white select-none',
        dragging?.id === bubble.id ? 'cursor-grabbing bg-blue-700' : 'cursor-grab bg-blue-500'
      ]"
      :style="{
        left: bubble.x + 'px',
        top: bubble.y + 'px'
      }"
    >
      {{ bubble.label }}
    </div>
  </div>
</template>