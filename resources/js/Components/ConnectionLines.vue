<script setup>
const props = defineProps({
  connections:       { type: Array, required: true },
  friendConnections: { type: Array, default: () => [] },
  bubbles:           { type: Array, required: true },
})

function getBubble(id) {
  return props.bubbles.find(b => b.id === id)
}

function center(id) {
  const b = getBubble(id)
  return b ? { x: b.x + b.size / 2, y: b.y + b.size / 2 } : { x: 0, y: 0 }
}

function midpoint(fromId, toId) {
  const a = center(fromId)
  const b = center(toId)
  return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }
}

function badgeLabel(friendNames) {
  if (!friendNames?.length) return '?'
  return friendNames.length === 1 ? friendNames[0][0].toUpperCase() : friendNames.length
}

function avatarPos(bubble, angle) {
  const rad  = (angle * Math.PI) / 180
  const dist = bubble.size / 2 + 10
  return {
    x: bubble.x + bubble.size / 2 + Math.cos(rad) * dist,
    y: bubble.y + bubble.size / 2 + Math.sin(rad) * dist,
  }
}

function balloonPos(bubble, angle) {
  const head = avatarPos(bubble, angle)
  const rad  = (angle * Math.PI) / 180
  return { x: head.x + Math.cos(rad) * 38, y: head.y + Math.sin(rad) * 38 }
}

function balloonWidth(msg) {
  return msg.length * 5.5 + 16
}

function balloonLeft(angle, msg) {
  return angle > 90 && angle < 270 ? -balloonWidth(msg) : 0
}

function balloonTextX(angle, msg) {
  const w = balloonWidth(msg)
  return angle > 90 && angle < 270 ? -w / 2 : w / 2
}
</script>

<template>
  <svg
    class="absolute inset-0 w-full h-full pointer-events-none"
    style="z-index: 2;"
  >
    <!-- Friend connection lines (amigos em comum) -->
    <g v-for="(c, i) in friendConnections" :key="'fc-' + i">
      <!-- Glow -->
      <line
        :x1="center(c.from).x" :y1="center(c.from).y"
        :x2="center(c.to).x"   :y2="center(c.to).y"
        stroke="#9b6bdf"
        stroke-width="10"
        stroke-linecap="round"
        opacity="0.07"
      />
      <!-- Main line -->
      <line
        :x1="center(c.from).x" :y1="center(c.from).y"
        :x2="center(c.to).x"   :y2="center(c.to).y"
        stroke="#9b6bdf"
        stroke-width="1.8"
        stroke-dasharray="5 4"
        opacity="0.4"
        class="friend-line"
      />
      <!-- Midpoint badge -->
      <g :transform="`translate(${midpoint(c.from, c.to).x}, ${midpoint(c.from, c.to).y})`">
        <circle r="12" fill="white" stroke="#9b6bdf" stroke-width="1.4" opacity="0.95" />
        <text
          text-anchor="middle" dominant-baseline="central"
          font-size="9" font-weight="800"
          font-family="Segoe UI, system-ui, sans-serif"
          fill="#9b6bdf"
        >{{ badgeLabel(c.friendNames) }}</text>
      </g>
    </g>

    <!-- Manual connection lines -->
    <line
      v-for="(c, i) in connections"
      :key="'conn-' + i"
      :x1="center(c.from).x" :y1="center(c.from).y"
      :x2="center(c.to).x"   :y2="center(c.to).y"
      :stroke="getBubble(c.from)?.color ?? '#009ac7'"
      stroke-width="2"
      stroke-dasharray="6 5"
      opacity="0.3"
      class="conn-line"
    />

    <!-- Avatars + speech bubbles per bubble -->
    <g v-for="bubble in bubbles" :key="'av-' + bubble.id">
      <g v-for="av in bubble.avatars" :key="av.id">

        <!-- Shadow -->
        <circle
          :cx="avatarPos(bubble, av.angle).x"
          :cy="avatarPos(bubble, av.angle).y + 2"
          r="19" fill="rgba(0,0,0,0.1)"
        />
        <!-- Colour ring -->
        <circle
          :cx="avatarPos(bubble, av.angle).x"
          :cy="avatarPos(bubble, av.angle).y"
          r="19" :fill="bubble.color"
        />
        <!-- Face -->
        <circle
          :cx="avatarPos(bubble, av.angle).x"
          :cy="avatarPos(bubble, av.angle).y"
          r="16" fill="white" opacity="0.92"
        />
        <!-- Initial -->
        <text
          :x="avatarPos(bubble, av.angle).x"
          :y="avatarPos(bubble, av.angle).y + 5"
          text-anchor="middle"
          font-size="13"
          font-weight="700"
          font-family="Segoe UI, system-ui, sans-serif"
          :fill="bubble.color"
        >{{ av.name[0] }}</text>

        <!-- Speech balloon -->
        <g :transform="`translate(${balloonPos(bubble, av.angle).x}, ${balloonPos(bubble, av.angle).y})`">
          <rect
            :x="balloonLeft(av.angle, av.msg)"
            y="-14"
            :width="balloonWidth(av.msg)"
            height="24"
            rx="8"
            fill="white"
            :stroke="bubble.color"
            stroke-width="1.2"
            opacity="0.96"
          />
          <text
            :x="balloonTextX(av.angle, av.msg)"
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
</template>

<style scoped>
.conn-line {
  animation: dashFlow 2.4s linear infinite;
}

.friend-line {
  animation: dashFlow 3.2s linear infinite reverse;
}

@keyframes dashFlow {
  to { stroke-dashoffset: -11; }
}
</style>
