<script setup>
import { onMounted, onUnmounted, ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import Bubble          from '@/Components/Bubble.vue'
import ConnectionLines from '@/Components/ConnectionLines.vue'
import { useBubbles }     from '@/Composables/useBubbles'
import { useConnections } from '@/Composables/useConnections'
import { usePhysics }     from '@/Composables/usePhysics'
import { useDrag }        from '@/Composables/useDrag'

const { bubbles, hoveredId, connectSource, loading, load, add, toggleSelect } = useBubbles()
const { connections, friendConnections, load: loadConnections, loadFriendConnections, connect } = useConnections()
const { step } = usePhysics()

const authUser        = computed(() => usePage().props.auth?.user)
const pendingFriends  = computed(() => usePage().props.auth?.pending_friends_count ?? 0)
const unreadMessages  = computed(() => usePage().props.auth?.unread_messages_count ?? 0)

const PALETTE  = ['#009ac7','#4ebcff','#2ea87e','#e07b4a','#9b6bdf','#c74a6b','#e0a040','#6b9bdf']
const newLabel = ref('')
const newTitle = ref('')
const newDescription = ref('')
const newTagline = ref('')
const newGuidelines = ref('')
const newColor = ref('#009ac7')
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

function onKeyDown(e) {
  if (e.key === 'Escape') clearSelection()
}

let animId = null
function loop() {
  step(bubbles.value, dragging.value?.id)
  animId = requestAnimationFrame(loop)
}

onMounted(() => {
  load()
  loadConnections()
  loadFriendConnections()
  window.addEventListener('mousemove', onWindowMouseMove)
  window.addEventListener('mouseup',   onWindowMouseUp)
  window.addEventListener('keydown',   onKeyDown)
  animId = requestAnimationFrame(loop)
})

onUnmounted(() => {
  window.removeEventListener('mousemove', onWindowMouseMove)
  window.removeEventListener('mouseup',   onWindowMouseUp)
  window.removeEventListener('keydown',   onKeyDown)
  cancelAnimationFrame(animId)
})

async function createBubble() {
  if (!newLabel.value.trim()) return
  const newBubble = await add(newLabel.value, newColor.value, {
    title:       newTitle.value.trim(),
    description: newDescription.value.trim(),
    tagline:     newTagline.value.trim(),
    coverColor:  newColor.value,
    guidelines:  newGuidelines.value.split('\n').map(g => g.trim()).filter(Boolean).slice(0, 5),
    userId:      authUser.value?.id ?? null,
  })
  newLabel.value       = ''
  newTitle.value       = ''
  newDescription.value = ''
  newTagline.value     = ''
  newGuidelines.value  = ''
  newColor.value       = '#009ac7'
  showAdd.value        = false
  if (newBubble?.persisted) {
    router.visit(route('community.show', newBubble.id))
  }
}

const selectedBubble = computed(() => bubbles.value.find(b => b.selected) ?? null)

const trends = computed(() =>
  [...bubbles.value].sort((a, b) => b.members - a.members).slice(0, 6)
)
</script>

<template>
  <div
    class="w-screen h-screen overflow-hidden relative select-none"
    style="background: transparent; font-family: 'Segoe UI', system-ui, sans-serif;"
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
      class="absolute top-0 left-0 right-0 z-40 flex items-center justify-between px-6"
      style="background: rgba(255,255,255,0.72); backdrop-filter: blur(16px); border-bottom: 1px solid #009ac71a; height: 58px;"
    >
      <span style="font-weight: 900; font-size: 22px; color: #009ac7; letter-spacing: -1px; user-select: none;">bubbles</span>

      <div style="display: flex; align-items: center; gap: 4px;">

        <!-- Hamburger → Nova bolha -->
        <button
          @click.stop="showAdd = !showAdd"
          :style="{
            width: '36px', height: '36px', borderRadius: '10px', border: 'none',
            background: showAdd ? '#009ac714' : 'transparent',
            color: '#5a7a8a', cursor: 'pointer',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            transition: 'background .15s',
          }"
          @mouseenter="$event.currentTarget.style.background='#009ac714'"
          @mouseleave="$event.currentTarget.style.background = showAdd ? '#009ac714' : 'transparent'"
          title="Nova bolha"
        >
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <rect x="8.2" y="2" width="1.6" height="14" rx=".8" fill="currentColor"/>
            <rect x="2" y="8.2" width="14" height="1.6" rx=".8" fill="currentColor"/>
          </svg>
        </button>

        <!-- Mensagens -->
        <Link
          v-if="authUser"
          :href="route('conversations.index')"
          :style="{
            width: '36px', height: '36px', borderRadius: '10px',
            background: 'transparent', color: '#5a7a8a',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            transition: 'background .15s', textDecoration: 'none',
            position: 'relative',
          }"
          @mouseenter="$event.currentTarget.style.background='#009ac714'"
          @mouseleave="$event.currentTarget.style.background='transparent'"
          title="Mensagens"
        >
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M15 2.5H3a1 1 0 00-1 1v7.5a1 1 0 001 1h3.5l2.5 3 2.5-3H15a1 1 0 001-1V3.5a1 1 0 00-1-1z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span
            v-if="unreadMessages > 0"
            style="position: absolute; top: 4px; right: 4px; min-width: 14px; height: 14px; padding: 0 3px; background: #009ac7; color: white; border-radius: 99px; font-size: 9px; font-weight: 800; display: flex; align-items: center; justify-content: center; line-height: 1;"
          >{{ unreadMessages }}</span>
        </Link>

        <!-- Notificações -->
        <button
          :style="{
            width: '36px', height: '36px', borderRadius: '10px', border: 'none',
            background: 'transparent', color: '#5a7a8a', cursor: 'pointer',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            transition: 'background .15s',
          }"
          @mouseenter="$event.currentTarget.style.background='#009ac714'"
          @mouseleave="$event.currentTarget.style.background='transparent'"
          title="Notificações"
        >
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M9 1.5A4.5 4.5 0 004.5 6v3.5L3 11.5h12l-1.5-2V6A4.5 4.5 0 009 1.5z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M7.5 12a1.5 1.5 0 003 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </button>


        <!-- Amigos -->
        <Link
          v-if="authUser"
          :href="route('friends.index')"
          :style="{
            width: '36px', height: '36px', borderRadius: '10px',
            background: 'transparent', color: '#5a7a8a',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            transition: 'background .15s', textDecoration: 'none',
            position: 'relative',
          }"
          @mouseenter="$event.currentTarget.style.background='#009ac714'"
          @mouseleave="$event.currentTarget.style.background='transparent'"
          title="Amigos"
        >
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <circle cx="6.5" cy="5.5" r="2.3" stroke="currentColor" stroke-width="1.4"/>
            <path d="M1.5 14.5c.8-2.5 2.8-3.8 5-3.8s4.2 1.3 5 3.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            <path d="M13 7.5a2 2 0 010 4m2.5 3c-.6-1.8-1.8-2.8-3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
          <span
            v-if="pendingFriends > 0"
            style="position: absolute; top: 4px; right: 4px; min-width: 14px; height: 14px; padding: 0 3px; background: #c74a6b; color: white; border-radius: 99px; font-size: 9px; font-weight: 800; display: flex; align-items: center; justify-content: center; line-height: 1;"
          >{{ pendingFriends }}</span>
        </Link>

        <!-- Perfil -->
        <Link
          v-if="authUser"
          :href="authUser.username ? route('profile.show', authUser.username) : route('profile.edit')"
          :style="{
            width: '36px', height: '36px', borderRadius: '10px',
            background: 'transparent', color: '#5a7a8a',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            transition: 'background .15s', textDecoration: 'none',
          }"
          @mouseenter="$event.currentTarget.style.background='#009ac714'"
          @mouseleave="$event.currentTarget.style.background='transparent'"
          title="O meu perfil"
        >
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <circle cx="9" cy="5.5" r="2.8" stroke="currentColor" stroke-width="1.4"/>
            <path d="M2.5 15c1-3 3.5-4.5 6.5-4.5s5.5 1.5 6.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </Link>

        <!-- Divisor -->
        <div style="width: 1px; height: 20px; background: #009ac71a; margin: 0 6px;" />

        <!-- Avatar do utilizador → perfil -->
        <Link
          v-if="authUser && authUser.username"
          :href="route('profile.show', authUser.username)"
          style="display: flex; align-items: center; justify-content: center; flex-shrink: 0; text-decoration: none; transition: box-shadow .2s; border-radius: 50%;"
        >
          <img
            v-if="authUser.avatar"
            :src="authUser.avatar"
            :style="{
              width: '32px', height: '32px', borderRadius: '50%',
              objectFit: 'cover',
              border: `2px solid ${authUser.avatar_color ?? '#009ac7'}`,
              boxShadow: `0 2px 8px ${authUser.avatar_color ?? '#009ac7'}44`,
            }"
          />
          <div v-else :style="{
            width: '32px', height: '32px', borderRadius: '50%',
            background: authUser.avatar_color ?? '#009ac7',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: '13px', fontWeight: '800', color: 'white',
            boxShadow: `0 2px 8px ${authUser.avatar_color ?? '#009ac7'}44`,
          }">{{ authUser.name?.[0]?.toUpperCase() ?? '?' }}</div>
        </Link>
        <div
          v-else-if="authUser"
          :style="{
            width: '32px', height: '32px', borderRadius: '50%',
            background: authUser.avatar_color ?? '#009ac7',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: '13px', fontWeight: '800', color: 'white',
            boxShadow: `0 2px 8px ${authUser.avatar_color ?? '#009ac7'}44`,
            flexShrink: 0,
          }"
        >{{ authUser.name?.[0]?.toUpperCase() ?? '?' }}</div>

      </div>
    </div>

    <!-- MODAL: Nova bolha -->
    <Transition name="pop">
      <div
        v-if="showAdd"
        class="absolute z-50 rounded-2xl p-5 flex flex-col gap-3"
        style="top: 68px; left: 50%; transform: translateX(-50%); background: white; box-shadow: 0 16px 56px #009ac72a; border: 1px solid #4ebcff33; min-width: 268px;"
        @click.stop
      >
        <p style="font-weight: 700; color: #1a3a4a; font-size: 14px; margin: 0;">Nova bolha</p>

        <!-- Preview + label -->
        <div style="display: flex; align-items: center; gap: 10px;">
          <div :style="{
            width: '36px', height: '36px', borderRadius: '50%', flexShrink: 0,
            background: newColor, transition: 'background .2s, box-shadow .2s',
            boxShadow: `0 3px 10px ${newColor}55`,
          }" />
          <input
            v-model="newLabel"
            placeholder="#hashtag"
            style="flex: 1; background: #f0f8ff; border: 1px solid #4ebcff44; border-radius: 10px; padding: 9px 12px; font-size: 13px; color: #1a3a4a; outline: none; font-family: inherit;"
            @keydown.enter="createBubble"
          />
        </div>

                <input
          v-model="newTitle"
          placeholder="Título da comunidade"
          style="background: #f0f8ff; border: 1px solid #4ebcff44; border-radius: 10px; padding: 9px 12px; font-size: 13px; color: #1a3a4a; outline: none; font-family: inherit;"
        />
        <input
          v-model="newTagline"
          placeholder="Tagline curta"
          style="background: #f0f8ff; border: 1px solid #4ebcff44; border-radius: 10px; padding: 9px 12px; font-size: 13px; color: #1a3a4a; outline: none; font-family: inherit;"
        />
        <textarea
          v-model="newDescription"
          placeholder="Descrição da comunidade"
          rows="2"
          style="background: #f0f8ff; border: 1px solid #4ebcff44; border-radius: 10px; padding: 9px 12px; font-size: 13px; color: #1a3a4a; outline: none; font-family: inherit; resize: vertical;"
        />
        <textarea
          v-model="newGuidelines"
          placeholder="Regras (uma por linha, máx 5)"
          rows="3"
          style="background: #f0f8ff; border: 1px solid #4ebcff44; border-radius: 10px; padding: 9px 12px; font-size: 12px; color: #1a3a4a; outline: none; font-family: inherit; resize: vertical;"
        />

        <!-- Seletor de cor -->
        <div>
          <p style="font-size: 10px; font-weight: 700; color: #8ba0b0; text-transform: uppercase; letter-spacing: .08em; margin: 0 0 8px;">Cor</p>
          <div style="display: flex; align-items: center; gap: 7px; flex-wrap: wrap;">
            <button
              v-for="c in PALETTE"
              :key="c"
              type="button"
              @click="newColor = c"
              :style="{
                width: '22px', height: '22px', borderRadius: '50%', background: c,
                border: 'none', cursor: 'pointer', flexShrink: 0,
                boxShadow: newColor === c ? `0 0 0 2px white, 0 0 0 4px ${c}` : 'none',
                transition: 'box-shadow .15s',
              }"
            />
            <!-- Picker de cor livre -->
            <label
              :style="{
                width: '22px', height: '22px', borderRadius: '50%', cursor: 'pointer',
                border: '2px dashed #b0c8d8', display: 'flex', alignItems: 'center',
                justifyContent: 'center', flexShrink: 0, position: 'relative', overflow: 'hidden',
                boxShadow: !PALETTE.includes(newColor) ? `0 0 0 2px white, 0 0 0 4px ${newColor}` : 'none',
                background: !PALETTE.includes(newColor) ? newColor : 'transparent',
              }"
              title="Cor personalizada"
            >
              <input type="color" v-model="newColor" style="position: absolute; width: 200%; height: 200%; opacity: 0; cursor: pointer; border: none; padding: 0;" />
              <span v-if="PALETTE.includes(newColor)" style="font-size: 12px; color: #8ba0b0; position: relative; pointer-events: none;">+</span>
            </label>
          </div>
        </div>

        <div style="display: flex; gap: 8px; margin-top: 2px;">
          <button
            style="flex: 1; padding: 9px; border-radius: 10px; background: #f0f8ff; border: 1px solid #e0eef8; color: #8b8b8b; font-size: 12px; cursor: pointer;"
            @click="showAdd = false"
          >Cancelar</button>
          <button
            :style="{
              flex: 1, padding: '9px', borderRadius: '10px', border: 'none',
              background: newColor, color: 'white', fontSize: '12px', fontWeight: '700',
              cursor: 'pointer', transition: 'background .2s',
            }"
            @click="createBubble"
          >Criar</button>
        </div>
      </div>
    </Transition>

    <!-- LOADING -->
    <div
      v-if="loading"
      style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 10;"
    >
      <div style="display: flex; flex-direction: column; align-items: center; gap: 14px; opacity: 0.5;">
        <div style="width: 38px; height: 38px; border-radius: 50%; border: 3px solid #009ac733; border-top-color: #009ac7; animation: spin .7s linear infinite;" />
        <span style="font-size: 12px; font-weight: 600; color: #009ac7; letter-spacing: .04em;">A carregar bolhas...</span>
      </div>
    </div>

    <!-- SVG LAYER: connections + avatars -->
    <ConnectionLines v-if="!loading" :connections="connections" :friend-connections="friendConnections" :bubbles="bubbles" />

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

    <!-- EXPANDED BUBBLE PANEL -->
    <Transition name="bubble-expand">
      <div
        v-if="selectedBubble"
        :style="{
          position:       'absolute',
          zIndex:         36,
          left:           `${selectedBubble.x + selectedBubble.size / 2 - 150}px`,
          top:            `${selectedBubble.y + selectedBubble.size / 2 - 150}px`,
          width:          '300px',
          height:         '300px',
          borderRadius:   '50%',
          backgroundImage:    selectedBubble.image
            ? `radial-gradient(circle at 38% 32%, ${selectedBubble.color}55 0%, ${selectedBubble.color}99 100%), url(${selectedBubble.image})`
            : `radial-gradient(circle at 38% 32%, ${selectedBubble.color}ee 0%, ${selectedBubble.color} 60%)`,
          backgroundSize:     'cover',
          backgroundPosition: 'center',
          boxShadow:          `0 0 0 5px white, 0 0 0 9px ${selectedBubble.color}88, 0 24px 64px ${selectedBubble.color}55`,
          overflow:           'hidden',
          display:            'flex',
          flexDirection:      'column',
          alignItems:         'center',
          justifyContent:     'center',
          cursor:             'default',
        }"
        @click.stop
        @mousedown.stop
      >
        <!-- Glass highlight -->
        <div style="position: absolute; top: 18px; left: 16%; width: 68%; height: 28%; border-radius: 50%; background: rgba(255,255,255,0.16); pointer-events: none; transform: rotate(-10deg);" />

        <!-- Close button -->
        <button
          @click.stop="clearSelection"
          :style="{
            position: 'absolute', top: '54px', right: '54px',
            background: 'rgba(255,255,255,0.22)', border: 'none', borderRadius: '50%',
            width: '26px', height: '26px', cursor: 'pointer',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: '16px', lineHeight: 1, color: 'white', fontWeight: '400', zIndex: 2,
            transition: 'background .15s',
          }"
          @mouseenter="$event.currentTarget.style.background='rgba(255,255,255,0.38)'"
          @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.22)'"
        >×</button>

        <!-- Label -->
        <span :style="{
          fontSize: '18px', fontWeight: '900', color: 'white',
          letterSpacing: '-.02em', textShadow: '0 2px 10px rgba(0,0,0,.28)',
          textAlign: 'center', position: 'relative', zIndex: 1,
        }">{{ selectedBubble.label }}</span>

        <!-- Members -->
        <span :style="{
          fontSize: '10px', color: 'rgba(255,255,255,.72)', fontWeight: '600',
          position: 'relative', zIndex: 1, marginBottom: '10px',
        }">{{ selectedBubble.members }} membros</span>

        <!-- Avatar row -->
        <div :style="{
          display: 'flex', gap: '5px', marginBottom: '10px',
          position: 'relative', zIndex: 1,
        }">
          <div
            v-for="av in (selectedBubble.avatars || []).slice(0, 4)"
            :key="av.id"
            :title="av.name"
            :style="{
              width: '30px', height: '30px', borderRadius: '50%',
              background: 'rgba(255,255,255,0.28)', border: '2px solid rgba(255,255,255,0.55)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: '12px', fontWeight: '800', color: 'white',
            }"
          >{{ av.name[0] }}</div>
        </div>

        <!-- Mini posts -->
        <div :style="{
          display: 'flex', flexDirection: 'column', gap: '5px',
          width: '72%', position: 'relative', zIndex: 1,
        }">
          <div
            v-for="p in (selectedBubble.posts || []).slice(0, 3)"
            :key="p.id"
            :style="{
              background: 'rgba(255,255,255,0.18)', borderRadius: '8px',
              padding: '5px 9px',
            }"
          >
            <span :style="{ fontSize: '9px', fontWeight: '800', color: 'rgba(255,255,255,.95)' }">{{ p.author }}</span>
            <span :style="{ fontSize: '9px', color: 'rgba(255,255,255,.72)', marginLeft: '5px' }">{{ p.text }}</span>
          </div>
        </div>

        <!-- Enter button → community page (só para bolhas persistidas) -->
        <Link
          v-if="selectedBubble.persisted"
          :href="route('community.show', selectedBubble.id)"
          :style="{
            marginTop: '14px', padding: '8px 22px', borderRadius: '99px',
            background: 'rgba(255,255,255,0.20)', border: '1.5px solid rgba(255,255,255,0.52)',
            color: 'white', fontSize: '11px', fontWeight: '800', cursor: 'pointer',
            position: 'relative', zIndex: 1, letterSpacing: '.04em',
            transition: 'background .2s', textDecoration: 'none',
            display: 'inline-block',
          }"
          @mouseenter="$event.currentTarget.style.background='rgba(255,255,255,0.34)'"
          @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.20)'"
          @click.stop
        >Entrar na comunidade →</Link>
        <span
          v-else
          :style="{
            marginTop: '12px', fontSize: '9px', color: 'rgba(255,255,255,.45)',
            position: 'relative', zIndex: 1, textAlign: 'center', padding: '0 24px',
            lineHeight: 1.4,
          }"
        >Demo · Cria uma comunidade com ≡</span>

      </div>
    </Transition>

    <!-- GLOBAL TRENDS SIDEBAR -->
    <div
      style="position: absolute; right: 16px; top: 70px; z-index: 38; width: 192px; background: rgba(255,255,255,0.82); backdrop-filter: blur(16px); border-radius: 18px; border: 1px solid #4ebcff22; box-shadow: 0 4px 20px #009ac70c; padding: 14px 12px; display: flex; flex-direction: column; gap: 2px;"
      @click.stop
    >
      <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 8px; padding: 0 6px;">Global Trends</p>

      <div
        v-for="(b, i) in trends"
        :key="b.id"
        @click.stop="toggleSelect(b.id)"
        style="display: flex; align-items: center; gap: 9px; padding: 7px 8px; border-radius: 10px; cursor: pointer; transition: background .15s;"
        @mouseenter="$event.currentTarget.style.background='#f0f8ff'"
        @mouseleave="$event.currentTarget.style.background='transparent'"
      >
        <span style="font-size: 10px; font-weight: 700; color: #c0ccd4; width: 12px; flex-shrink: 0; text-align: right;">{{ i + 1 }}</span>
        <div :style="{ width: '8px', height: '8px', borderRadius: '50%', background: b.color, flexShrink: 0, boxShadow: `0 0 5px ${b.color}88` }" />
        <div style="flex: 1; min-width: 0;">
          <p style="font-size: 12px; font-weight: 700; color: #1a3a4a; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ b.label }}</p>
          <p style="font-size: 9px; color: #8ba0b0; margin: 0;">{{ b.members }} membros</p>
        </div>
      </div>
    </div>

    <!-- FLOOR GRADIENT -->
    <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 100px; background: linear-gradient(to top, #9dcee8 0%, transparent 100%); pointer-events: none; z-index: 1;" />

    <!-- HINT -->
    <div style="position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); z-index: 10; pointer-events: none;">
      <span style="font-size: 10px; color: #009ac7aa; background: rgba(255,255,255,.6); padding: 5px 16px; border-radius: 99px; backdrop-filter: blur(8px);">
        Arrasta · Clica para expandir · Esc para fechar
      </span>
    </div>

    <!-- Connect source hint -->
    <Transition name="fade">
      <div
        v-if="connectSource"
        style="position: absolute; top: 68px; left: 50%; transform: translateX(-50%); z-index: 40; pointer-events: none;"
      >
        <span style="font-size: 11px; color: #009ac7; background: #4ebcff18; padding: 5px 14px; border-radius: 99px; border: 1px solid #4ebcff44; backdrop-filter: blur(8px);">
          {{ connectSource.label }} → Shift+RMB em outra
        </span>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s }
.fade-enter-from,  .fade-leave-to      { opacity: 0 }
@keyframes spin { to { transform: rotate(360deg) } }

.pop-enter-active, .pop-leave-active   { transition: opacity .35s ease, transform .45s cubic-bezier(.2,.82,.2,1) }
.pop-enter-from,   .pop-leave-to       { opacity: 0; transform: translateX(-50%) scale(.93) }

.bubble-expand-enter-active { transition: opacity .3s ease, transform .45s cubic-bezier(.22,.78,.26,1); }
.bubble-expand-leave-active { transition: opacity .22s ease, transform .32s cubic-bezier(.6,0,.4,1); }
.bubble-expand-enter-from,
.bubble-expand-leave-to     { opacity: 0; transform: scale(0.32); }

input::placeholder { color: #4ebcff77 }
input:focus        { border-color: #4ebcff !important }
</style>
