<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

let _id = 5

// Cada membro tem ângulo na bolha-mãe, nome e mensagem
const bubbles = ref([
  {
    id: 1, x: 320, y: 180, vx: 0, vy: 0,
    label: '#futebol', color: '#009ac7', size: 100, members: 312, selected: false,
    avatars: [
      { id: 'av1', angle: 30, name: 'João', msg: 'Que golo incrível!' },
      { id: 'av2', angle: 140, name: 'Rita', msg: 'Vamos Portugal!' },
      { id: 'av3', angle: 240, name: 'Pedro', msg: 'Qu jogo...' },
    ],
  },
  {
    id: 2, x: 600, y: 130, vx: 0, vy: 0,
    label: '#ps5', color: '#4ebcff', size: 80, members: 88, selected: false,
    avatars: [
      { id: 'av4', angle: 60, name: 'Tomas', msg: 'Alguém online?' },
      { id: 'av5', angle: 200, name: 'Ana', msg: 'Novo jogo saiu!' },
    ],
  },
  {
    id: 3, x: 750, y: 300, vx: 0, vy: 0,
    label: '#xbox', color: '#2ea87e', size: 80, members: 54, selected: false,
    avatars: [
      { id: 'av6', angle: 100, name: 'Luís', msg: 'Game pass novo?' },
    ],
  },
  {
    id: 4, x: 160, y: 360, vx: 0, vy: 0,
    label: '#música', color: '#e07b4a', size: 90, members: 141, selected: false,
    avatars: [
      { id: 'av7', angle: 320, name: 'Mia', msg: 'Nova playlist!' },
      { id: 'av8', angle: 80, name: 'Rui', msg: 'Concerto amanhã' },
    ],
  },
])

const connections = ref([
  { from: 1, to: 2 },
  { from: 1, to: 4 },
])

const connectSource = ref(null)
const dragging = ref(null)
const dragMoved = ref(false)
const DRAG_THR = 5

function avatarPos(bubble, angle) {
  const rad = (angle * Math.PI) / 180
  const bx = bubble.x + bubble.size / 2
  const by = bubble.y + bubble.size / 2
  const r = bubble.size / 2
  const headR = 18
  const dist = r + headR * 0.6

  return {
    x: bx + Math.cos(rad) * dist,
    y: by + Math.sin(rad) * dist,
  }
}

function balloonPos(bubble, angle) {
  const head = avatarPos(bubble, angle)
  const rad = (angle * Math.PI) / 180
  const offset = 36

  return {
    x: head.x + Math.cos(rad) * offset,
    y: head.y + Math.sin(rad) * offset,
  }
}

function center(id) {
  const b = bubbles.value.find((x) => x.id === id)
  if (!b) return { x: 0, y: 0 }
  return { x: b.x + b.size / 2, y: b.y + b.size / 2 }
}

function startDrag(b, e) {
  if (e.button !== 0) return
  e.preventDefault()
  dragging.value = { id: b.id, ox: e.clientX - b.x, oy: e.clientY - b.y, sx: e.clientX, sy: e.clientY }
  dragMoved.value = false
}

function onMouseMove(e) {
  if (!dragging.value) return

  const dx = e.clientX - dragging.value.sx
  const dy = e.clientY - dragging.value.sy
  if (!dragMoved.value && Math.hypot(dx, dy) > DRAG_THR) dragMoved.value = true
  if (!dragMoved.value) return

  const b = bubbles.value.find((x) => x.id === dragging.value.id)
  if (!b) return

  b.x = Math.max(0, Math.min(e.clientX - dragging.value.ox, window.innerWidth - b.size))
  b.y = Math.max(0, Math.min(e.clientY - dragging.value.oy, window.innerHeight - b.size))
  b.vx = 0
  b.vy = 0
}

function stopDrag() {
  if (dragging.value && !dragMoved.value) {
    const b = bubbles.value.find((x) => x.id === dragging.value.id)
    if (b) {
      bubbles.value.forEach((x) => {
        if (x.id !== b.id) x.selected = false
      })
      b.selected = !b.selected
    }
  }

  dragging.value = null
  dragMoved.value = false
}

function handleContextMenu(b, e) {
  e.preventDefault()
  if (!e.shiftKey) return

  if (!connectSource.value) {
    connectSource.value = b
    return
  }

  if (connectSource.value.id === b.id) {
    connectSource.value = null
    return
  }

  const exists = connections.value.some((c) =>
    (c.from === connectSource.value.id && c.to === b.id)
    || (c.from === b.id && c.to === connectSource.value.id),
  )

  if (!exists) connections.value.push({ from: connectSource.value.id, to: b.id })
  connectSource.value = null
}

const DAMPING = 0.85
const REPULSE = 3.0
const ATTRACT = 0.0005
let animId = null

function updatePhysics() {
  const cx = window.innerWidth / 2
  const cy = window.innerHeight / 2 - 40

  for (let i = 0; i < bubbles.value.length; i++) {
    const b1 = bubbles.value[i]
    if (dragging.value?.id === b1.id && dragMoved.value) continue

    let fx = 0
    let fy = 0

    for (let j = 0; j < bubbles.value.length; j++) {
      if (i === j) continue

      const b2 = bubbles.value[j]
      const dx = b1.x - b2.x
      const dy = b1.y - b2.y
      const d = Math.hypot(dx, dy) || 0.01
      const minD = (b1.size + b2.size) / 2 + 80

      if (d < minD) {
        const s = (minD - d) / minD
        fx += (dx / d) * s * REPULSE
        fy += (dy / d) * s * REPULSE
      }
    }

    fx += (cx - b1.x - b1.size / 2) * ATTRACT
    fy += (cy - b1.y - b1.size / 2) * ATTRACT

    b1.vx = (b1.vx + fx) * DAMPING
    b1.vy = (b1.vy + fy) * DAMPING
    b1.x = Math.max(60, Math.min(b1.x + b1.vx, window.innerWidth - b1.size - 60))
    b1.y = Math.max(60, Math.min(b1.y + b1.vy, window.innerHeight - b1.size - 60))
  }

  animId = requestAnimationFrame(updatePhysics)
}

const newLabel = ref('')
const showAdd = ref(false)
const COLORS = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b']

function addBubble() {
  const lbl = (newLabel.value.trim() || 'novo').replace(/^#*/, '#')

  bubbles.value.push({
    id: _id++,
    x: window.innerWidth / 2 - 50,
    y: window.innerHeight / 2 - 50,
    vx: 0,
    vy: 0,
    label: lbl,
    color: COLORS[Math.floor(Math.random() * COLORS.length)],
    size: 85,
    members: 0,
    selected: false,
    avatars: [],
  })

  newLabel.value = ''
  showAdd.value = false
}

onMounted(() => {
  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', stopDrag)
  animId = requestAnimationFrame(updatePhysics)
})

onUnmounted(() => {
  window.removeEventListener('mousemove', onMouseMove)
  window.removeEventListener('mouseup', stopDrag)
  cancelAnimationFrame(animId)
})
</script>

<template>
  <div
    class="w-screen h-screen overflow-hidden relative select-none"
    style="background: linear-gradient(160deg, #f0f8ff 0%, #daeef9 50%, #c5e5f5 100%); font-family: 'Segoe UI', system-ui, sans-serif;"
    @click.self="bubbles.forEach((b) => b.selected = false)"
  >
    <svg class="absolute inset-0 w-full h-full pointer-events-none" style="opacity:.04;">
      <defs>
        <pattern id="g" width="44" height="44" patternUnits="userSpaceOnUse">
          <path d="M 44 0 L 0 0 0 44" fill="none" stroke="#009ac7" stroke-width="1" />
        </pattern>
      </defs>
      <rect width="100%" height="100%" fill="url(#g)" />
    </svg>

    <div
      class="absolute top-0 left-0 right-0 z-40 flex items-center justify-between px-6 py-3"
      style="background:rgba(255,255,255,0.72);backdrop-filter:blur(16px);border-bottom:1px solid #009ac71a;"
    >
      <span style="font-weight:900;font-size:22px;color:#009ac7;letter-spacing:-1px;">bubbles</span>
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
          onmouseover="this.style.background='#4ebcff'"
          onmouseout="this.style.background='#009ac7'"
        >
          + Bolha
        </button>
      </div>
    </div>

    <Transition name="pop">
      <div
        v-if="showAdd"
        class="absolute z-50 rounded-2xl p-5 flex flex-col gap-3"
        style="top:68px;left:50%;transform:translateX(-50%);background:white;box-shadow:0 16px 56px #009ac72a;border:1px solid #4ebcff33;min-width:240px;"
      >
        <p style="font-weight:700;color:#009ac7;font-size:14px;">Nova bolha</p>
        <input
          v-model="newLabel"
          placeholder="#hashtag"
          style="background:#f0f8ff;border:1px solid #4ebcff44;border-radius:10px;padding:9px 12px;font-size:13px;color:#009ac7;outline:none;font-family:inherit;"
          @keydown.enter="addBubble"
        >
        <div class="flex gap-2">
          <button
            style="flex:1;padding:9px;border-radius:10px;background:#f0f8ff;border:1px solid #e0eef8;color:#8b8b8b;font-size:12px;cursor:pointer;"
            @click="showAdd = false"
          >
            Cancelar
          </button>
          <button
            style="flex:1;padding:9px;border-radius:10px;background:#009ac7;border:none;color:white;font-size:12px;font-weight:700;cursor:pointer;"
            @click="addBubble"
          >
            Criar
          </button>
        </div>
      </div>
    </Transition>

    <svg class="absolute inset-0 w-full h-full" style="z-index:2;pointer-events:none;">
      <defs>
        <clipPath id="headClip" clipPathUnits="userSpaceOnUse">
          <circle r="18" />
        </clipPath>
      </defs>

      <g>
        <line
          v-for="(c, i) in connections"
          :key="'c' + i"
          :x1="center(c.from).x"
          :y1="center(c.from).y"
          :x2="center(c.to).x"
          :y2="center(c.to).y"
          :stroke="bubbles.find((b) => b.id === c.from)?.color || '#009ac7'"
          stroke-width="2"
          stroke-dasharray="6 5"
          opacity="0.28"
        />
      </g>

      <g v-for="bubble in bubbles" :key="'svg' + bubble.id">
        <g v-for="av in bubble.avatars" :key="av.id">
          <circle
            :cx="avatarPos(bubble, av.angle).x"
            :cy="avatarPos(bubble, av.angle).y + 2"
            r="19"
            fill="rgba(0,0,0,0.12)"
          />
          <circle
            :cx="avatarPos(bubble, av.angle).x"
            :cy="avatarPos(bubble, av.angle).y"
            r="19"
            :fill="bubble.color"
          />
          <circle
            :cx="avatarPos(bubble, av.angle).x"
            :cy="avatarPos(bubble, av.angle).y"
            r="16"
            fill="white"
            opacity="0.9"
          />
          <text
            :x="avatarPos(bubble, av.angle).x"
            :y="avatarPos(bubble, av.angle).y + 5"
            text-anchor="middle"
            font-size="13"
            font-weight="700"
            font-family="Segoe UI, system-ui, sans-serif"
            :fill="bubble.color"
          >{{ av.name[0] }}</text>

          <g :transform="`translate(${balloonPos(bubble, av.angle).x}, ${balloonPos(bubble, av.angle).y})`">
            <rect
              :x="av.angle > 90 && av.angle < 270 ? -(av.msg.length * 5.5 + 16) : 0"
              y="-14"
              :width="av.msg.length * 5.5 + 16"
              height="24"
              rx="8"
              fill="white"
              :stroke="bubble.color"
              stroke-width="1.2"
              opacity="0.95"
            />
            <text
              :x="av.angle > 90 && av.angle < 270 ? -(av.msg.length * 5.5 / 2 + 8) + (av.msg.length * 5.5 + 16) / 2 - (av.msg.length * 5.5 + 16) / 2 : (av.msg.length * 5.5 + 16) / 2"
              y="2"
              text-anchor="middle"
              font-size="10"
              font-family="Segoe UI, system-ui, sans-serif"
              fill="#2a4a5a"
            >{{ av.msg }}</text>
          </g>
        </g>
      </g>
    </svg>

    <div
      v-for="b in bubbles"
      :key="b.id"
      :style="{
        position: 'absolute',
        zIndex: b.selected ? 10 : 3,
        left: `${b.x}px`,
        top: `${b.y}px`,
        width: `${b.size}px`,
        height: `${b.size}px`,
        borderRadius: '50%',
        background: b.color,
        cursor: dragging?.id === b.id ? 'grabbing' : 'grab',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '3px',
        boxShadow: b.selected
          ? `0 0 0 4px white, 0 0 0 7px ${b.color}, 0 12px 40px ${b.color}55`
          : connectSource?.id === b.id
            ? '0 0 0 4px white, 0 0 0 7px #009ac7'
            : `0 8px 32px ${b.color}44, 0 2px 8px ${b.color}22`,
        transform: b.selected ? 'scale(1.07)' : 'scale(1)',
        transition: dragging?.id === b.id ? 'none' : 'box-shadow .25s, transform .25s',
      }"
      @mousedown="startDrag(b, $event)"
      @contextmenu="handleContextMenu(b, $event)"
    >
      <span
        :style="{
          fontSize: '11px',
          fontWeight: '800',
          color: 'white',
          letterSpacing: '.02em',
          textShadow: '0 1px 4px rgba(0,0,0,.25)',
          textAlign: 'center',
          padding: '0 10px',
          lineHeight: 1.2,
        }"
      >{{ b.label }}</span>

      <span style="font-size:9px;color:rgba(255,255,255,.7);font-weight:500;">
        {{ b.members }} membros
      </span>

      <div
        v-if="connectSource?.id === b.id"
        style="position:absolute;top:-8px;right:-8px;background:#009ac7;color:white;border-radius:99px;font-size:8px;padding:2px 7px;border:2px solid white;font-weight:700;z-index:11;"
      >
        origem
      </div>
    </div>

    <div style="position:absolute;bottom:0;left:0;right:0;height:100px;background:linear-gradient(to top,#9dcee8 0%,transparent 100%);pointer-events:none;z-index:1;" />

    <div style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);z-index:10;pointer-events:none;">
      <span style="font-size:10px;color:#009ac7aa;background:rgba(255,255,255,.6);padding:5px 16px;border-radius:99px;backdrop-filter:blur(8px);">
        Arrasta · Shift+RMB para conectar · Clica para selecionar
      </span>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,.fade-leave-active{transition:opacity .2s}
.fade-enter-from,.fade-leave-to{opacity:0}
.pop-enter-active,.pop-leave-active{transition:opacity .2s,transform .2s}
.pop-enter-from,.pop-leave-to{opacity:0;transform:translateX(-50%) scale(.93)}
input::placeholder{color:#4ebcff77}
input:focus{border-color:#4ebcff !important}
</style>
