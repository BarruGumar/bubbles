<script setup>
import { onMounted, onUnmounted, ref, computed } from 'vue'
import Bubble         from '@/Components/Bubble.vue'
import ConnectionLines from '@/Components/ConnectionLines.vue'
import { useBubbles }     from '@/Composables/useBubbles'
import { useConnections } from '@/Composables/useConnections'
import { usePhysics }     from '@/Composables/usePhysics'
import { useDrag }        from '@/Composables/useDrag'

const { bubbles, hoveredId, connectSource, load, add, toggleSelect } = useBubbles()
const { connections, connect } = useConnections()
const { step } = usePhysics()

const newLabel = ref('')
const showAdd  = ref(false)

const { dragging, startDrag, onMouseMove: moveDrag, stopDrag } = useDrag(
  (id) => toggleSelect(id)
)

function onWindowMouseMove(e) { moveDrag(e, bubbles.value) }
function onWindowMouseUp()    { stopDrag() }

function handleContextMenu(bubble, e) {
  e.preventDefault()
  if (!e.shiftKey) return
  if (!connectSource.value) { connectSource.value = bubble; return }
  if (connectSource.value.id === bubble.id) { connectSource.value = null; return }
  connect(connectSource.value.id, bubble.id)
  connectSource.value = null
}

function onBubbleLeave(id) {
  if (hoveredId.value === id) hoveredId.value = null
}

function clearSelection() {
  bubbles.value.forEach(b => { b.selected = false })
  connectSource.value = null
}

let animId = null
function loop() {
  step(bubbles.value, dragging.value?.id)
  animId = requestAnimationFrame(loop)
}

onMounted(() => {
  load()
  window.addEventListener('mousemove', onWindowMouseMove)
  window.addEventListener('mouseup',   onWindowMouseUp)
  animId = requestAnimationFrame(loop)
})

onUnmounted(() => {
  window.removeEventListener('mousemove', onWindowMouseMove)
  window.removeEventListener('mouseup',   onWindowMouseUp)
  cancelAnimationFrame(animId)
})

async function createBubble() {
  if (!newLabel.value.trim()) return
  await add(newLabel.value)
  newLabel.value = ''
  showAdd.value  = false
}

const selectedBubble = computed(() => bubbles.value.find(b => b.selected) ?? null)

const connectedTo = computed(() => {
  if (!selectedBubble.value) return []
  const sid = selectedBubble.value.id
  return connections.value
    .filter(c => c.from === sid || c.to === sid)
    .map(c => bubbles.value.find(b => b.id === (c.from === sid ? c.to : c.from)))
    .filter(Boolean)
})
</script>

<template>
  <div
    class="w-screen h-screen overflow-hidden relative select-none"
    style="background: linear-gradient(160deg, #f0f8ff 0%, #daeef9 50%, #c5e5f5 100%); font-family: 'Segoe UI', system-ui, sans-serif;"
    @click.self="clearSelection"
  >

    <!-- BACKGROUND GRID -->
    <svg class="absolute inset-0 w-full h-full pointer-events-none" style="opacity: .04;">
      <defs>
        <pattern id="bg-grid" width="44" height="44" patternUnits="userSpaceOnUse">
          <path d="M 44 0 L 0 0 0 44" fill="none" stroke="#009ac7" stroke-width="1" />
        </pattern>
      </defs>
      <rect width="100%" height="100%" fill="url(#bg-grid)" />
    </svg>

    <!-- AMBIENT ORBS -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
      <div style="position:absolute;top:-80px;left:-80px;width:320px;height:320px;border-radius:50%;background:radial-gradient(circle,#009ac718 0%,transparent 70%);" />
      <div style="position:absolute;bottom:-60px;right:-60px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,#4ebcff14 0%,transparent 70%);" />
      <div style="position:absolute;top:40%;left:65%;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,#9b6bdf0c 0%,transparent 70%);" />
    </div>

    <!-- TOP BAR -->
    <div
      class="absolute top-0 left-0 right-0 z-40 flex items-center justify-between px-6 py-3"
      style="background: rgba(255,255,255,0.72); backdrop-filter: blur(16px); border-bottom: 1px solid #009ac71a;"
    >
      <span style="font-weight: 900; font-size: 22px; color: #009ac7; letter-spacing: -1px;">bubbles</span>

      <div class="flex items-center gap-3">
        <Transition name="fade">
          <span
            v-if="connectSource"
            style="font-size:11px;color:#009ac7;background:#4ebcff18;padding:5px 14px;border-radius:99px;border:1px solid #4ebcff44;"
          >
            {{ connectSource.label }} → Shift+RMB em outra
          </span>
        </Transition>

        <button
          style="background:#009ac7;color:white;border:none;border-radius:99px;padding:8px 20px;font-size:12px;font-weight:700;cursor:pointer;box-shadow:0 4px 18px #009ac740;"
          @click.stop="showAdd = !showAdd"
        >+ Bolha</button>
      </div>
    </div>

    <!-- ADD BUBBLE MODAL -->
    <Transition name="pop">
      <div
        v-if="showAdd"
        class="absolute z-50 rounded-2xl p-5 flex flex-col gap-3"
        style="top:68px;left:50%;transform:translateX(-50%);background:white;box-shadow:0 16px 56px #009ac72a;border:1px solid #4ebcff33;min-width:240px;"
        @click.stop
      >
        <p style="font-weight:700;color:#009ac7;font-size:14px;">Nova bolha</p>
        <input
          v-model="newLabel"
          placeholder="#hashtag"
          style="background:#f0f8ff;border:1px solid #4ebcff44;border-radius:10px;padding:9px 12px;font-size:13px;color:#009ac7;outline:none;font-family:inherit;"
          @keydown.enter="createBubble"
        />
        <div class="flex gap-2">
          <button
            style="flex:1;padding:9px;border-radius:10px;background:#f0f8ff;border:1px solid #e0eef8;color:#8b8b8b;font-size:12px;cursor:pointer;"
            @click="showAdd = false"
          >Cancelar</button>
          <button
            style="flex:1;padding:9px;border-radius:10px;background:#009ac7;border:none;color:white;font-size:12px;font-weight:700;cursor:pointer;"
            @click="createBubble"
          >Criar</button>
        </div>
      </div>
    </Transition>

    <!-- SVG LAYER: connections + avatars -->
    <ConnectionLines :connections="connections" :bubbles="bubbles" />

    <!-- BUBBLES -->
    <Bubble
      v-for="b in bubbles"
      :key="b.id"
      :bubble="b"
      :isDragging="dragging?.id === b.id"
      :isHovered="hoveredId === b.id"
      :anyHovered="hoveredId !== null"
      :isConnectSource="connectSource?.id === b.id"
      @mousedown="startDrag(b, $event)"
      @mouseenter="hoveredId = b.id"
      @mouseleave="onBubbleLeave(b.id)"
      @contextmenu="handleContextMenu(b, $event)"
    />

    <!-- INFO PANEL -->
    <Transition name="slide-right">
      <div
        v-if="selectedBubble"
        class="absolute right-4 z-40 rounded-2xl p-5 flex flex-col gap-4"
        style="top:72px;width:220px;background:rgba(255,255,255,0.88);backdrop-filter:blur(20px);box-shadow:0 8px 32px #009ac71a;border:1px solid #4ebcff22;"
        @click.stop
      >
        <div class="flex items-center gap-3">
          <div :style="{
            width:'14px',height:'14px',borderRadius:'50%',
            background: selectedBubble.color,
            boxShadow: `0 0 8px ${selectedBubble.color}66`,
            flexShrink: 0,
          }" />
          <span style="font-weight:800;font-size:15px;color:#1a3a4a;word-break:break-all;">{{ selectedBubble.label }}</span>
        </div>

        <div class="flex flex-col gap-3">
          <div>
            <p style="font-size:10px;color:#8ba0b0;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Membros</p>
            <p :style="{ fontSize:'22px', fontWeight:'800', color: selectedBubble.color }">{{ selectedBubble.members }}</p>
          </div>
          <div>
            <p style="font-size:10px;color:#8ba0b0;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Atividade</p>
            <div style="height:5px;background:#e8f4fb;border-radius:99px;overflow:hidden;">
              <div :style="{
                height:'100%',
                width: `${(selectedBubble.activity * 100).toFixed(0)}%`,
                background: selectedBubble.color,
                borderRadius:'99px',
                transition:'width .5s ease',
              }" />
            </div>
            <p style="font-size:9px;color:#b0c0cc;margin-top:3px;">{{ (selectedBubble.activity * 100).toFixed(0) }}%</p>
          </div>
        </div>

        <div v-if="connectedTo.length">
          <p style="font-size:10px;color:#8ba0b0;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Conectado a</p>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="cb in connectedTo"
              :key="cb.id"
              :style="{
                fontSize:'10px',fontWeight:'700',padding:'3px 9px',borderRadius:'99px',
                background:`${cb.color}18`,color:cb.color,border:`1px solid ${cb.color}44`,
              }"
            >{{ cb.label }}</span>
          </div>
        </div>

        <button
          style="padding:8px;border-radius:10px;background:#f0f8ff;border:1px solid #e0eef8;color:#8b8b8b;font-size:11px;cursor:pointer;margin-top:auto;"
          @click="clearSelection"
        >Fechar</button>
      </div>
    </Transition>

    <!-- FLOOR GRADIENT -->
    <div style="position:absolute;bottom:0;left:0;right:0;height:100px;background:linear-gradient(to top,#9dcee8 0%,transparent 100%);pointer-events:none;z-index:1;" />

    <!-- HINT -->
    <div style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);z-index:10;pointer-events:none;">
      <span style="font-size:10px;color:#009ac7aa;background:rgba(255,255,255,.6);padding:5px 16px;border-radius:99px;backdrop-filter:blur(8px);">
        Arrasta · Shift+RMB para conectar · Clica para selecionar
      </span>
    </div>

  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s }
.fade-enter-from,  .fade-leave-to      { opacity: 0 }

.pop-enter-active, .pop-leave-active   { transition: opacity .35s ease, transform .45s cubic-bezier(.2,.82,.2,1) }
.pop-enter-from,   .pop-leave-to       { opacity: 0; transform: translateX(-50%) scale(.93) }

.slide-right-enter-active, .slide-right-leave-active { transition: opacity .3s ease, transform .4s cubic-bezier(.2,.82,.2,1) }
.slide-right-enter-from,   .slide-right-leave-to     { opacity: 0; transform: translateX(20px) }

input::placeholder { color: #4ebcff77 }
input:focus        { border-color: #4ebcff !important }
</style>
