<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'

const defaultBubbles = [
  { id: 1, x: 100, y: 100, label: '#tech', color: '#3b82f6', size: 100, members: [] },
  { id: 2, x: 300, y: 200, label: '#design', color: '#14b8a6', size: 100, members: [] },
  { id: 3, x: 500, y: 150, label: '#music', color: '#8b5cf6', size: 100, members: [] },
]

const bubbles = ref([...defaultBubbles])
const dragging = ref(null)
const apiOnline = ref(true)
const loading = ref(false)
const apiError = ref('')

const nextLocalId = computed(() => {
  const ids = bubbles.value.map(b => Number(b.id)).filter(Number.isFinite)
  return ids.length ? Math.max(...ids) + 1 : 1
})

function normalizeBubble(raw) {
  return {
    id: raw.id,
    label: raw.label ?? '#bubble',
    color: raw.color ?? '#3b82f6',
    x: Number(raw.x ?? 80),
    y: Number(raw.y ?? 80),
    size: Number(raw.size ?? 100),
    members: Array.isArray(raw.members) ? raw.members : [],
  }
}

async function loadBubbles() {
  loading.value = true
  apiError.value = ''

  try {
    const { data } = await axios.get('/api/bubbles')
    const fromApi = Array.isArray(data) ? data.map(normalizeBubble) : []

    bubbles.value = fromApi.length ? fromApi : [...defaultBubbles]
    apiOnline.value = true
  } catch (error) {
    console.error('Falha ao carregar bubbles:', error)
    bubbles.value = bubbles.value.length ? bubbles.value : [...defaultBubbles]
    apiOnline.value = false
    apiError.value = 'API indisponível. A usar modo local temporariamente.'
  } finally {
    loading.value = false
  }
}

async function addBubble() {
  const payload = {
    label: `#bubble-${Date.now().toString().slice(-4)}`,
    color: '#3b82f6',
    x: Math.floor(Math.random() * Math.max(200, window.innerWidth - 200)),
    y: Math.floor(Math.random() * Math.max(200, window.innerHeight - 200)),
    size: 100,
    members: [],
  }

  try {
    const { data } = await axios.post('/api/bubbles', payload)
    bubbles.value.unshift(normalizeBubble(data))
    apiOnline.value = true
    apiError.value = ''
  } catch (error) {
    console.error('Falha ao criar bubble na API:', error)
    bubbles.value.unshift({ ...payload, id: nextLocalId.value })
    apiOnline.value = false
    apiError.value = 'Bubble criada localmente. Sincronização pendente.'
  }
}

function startDrag(bubble, event) {
  dragging.value = {
    id: bubble.id,
    offsetX: event.clientX - bubble.x,
    offsetY: event.clientY - bubble.y,
  }
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(value, max))
}

function onMouseMove(event) {
  if (!dragging.value) return

  const bubble = bubbles.value.find((b) => b.id === dragging.value.id)
  if (!bubble) return

  const bubbleSize = bubble.size ?? 100
  const maxX = window.innerWidth - bubbleSize
  const maxY = window.innerHeight - bubbleSize

  if (maxX <= 0 || maxY <= 0) return

  bubble.x = clamp(event.clientX - dragging.value.offsetX, 0, maxX)
  bubble.y = clamp(event.clientY - dragging.value.offsetY, 0, maxY)
}

async function stopDrag() {
  const dragData = dragging.value
  dragging.value = null

  if (!dragData) return

  const bubble = bubbles.value.find((b) => b.id === dragData.id)
  if (!bubble || !apiOnline.value || typeof bubble.id !== 'number') return

  try {
    await axios.put(`/api/bubbles/${bubble.id}`, { x: bubble.x, y: bubble.y })
  } catch (error) {
    console.error('Falha ao persistir posição:', error)
    apiOnline.value = false
    apiError.value = 'Movimento aplicado localmente. API offline.'
  }
}

onMounted(() => {
  loadBubbles()
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
    <div class="absolute top-4 left-4 z-20 flex gap-2 items-center">
      <button
        class="rounded bg-blue-600 hover:bg-blue-500 text-white text-sm px-4 py-2"
        @click="addBubble"
      >
        + Nova Bubble
      </button>
      <button
        class="rounded bg-gray-700 hover:bg-gray-600 text-white text-sm px-3 py-2"
        @click="loadBubbles"
      >
        Recarregar
      </button>
    </div>

    <p
      v-if="apiError"
      class="absolute top-16 left-4 z-20 text-xs text-yellow-300 bg-gray-800/90 px-3 py-2 rounded"
    >
      {{ apiError }}
    </p>

    <p v-if="loading" class="absolute bottom-4 left-4 z-20 text-xs text-gray-300">A carregar...</p>

    <div
      v-for="bubble in bubbles"
      :key="bubble.id"
      @mousedown="startDrag(bubble, $event)"
      :class="[
        'absolute rounded-full px-4 py-2 text-white select-none shadow-lg',
        dragging?.id === bubble.id ? 'cursor-grabbing' : 'cursor-grab'
      ]"
      :style="{
        left: bubble.x + 'px',
        top: bubble.y + 'px',
        backgroundColor: bubble.color,
        minWidth: (bubble.size || 100) + 'px',
        minHeight: (bubble.size || 100) + 'px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center'
      }"
    >
      {{ bubble.label }}
    </div>
  </div>
</template>
