<script setup>
import { onMounted, onUnmounted, ref, computed, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import axios from 'axios'
import Bubble                from '@/Components/Bubble.vue'
import ConnectionLines       from '@/Components/ConnectionLines.vue'
import CreateCommunityModal  from '@/Components/CreateCommunityModal.vue'
import PostCard              from '@/Components/PostCard.vue'
import ToastContainer        from '@/Components/ToastContainer.vue'
import { clImg }          from '@/Composables/useCloudinary'
import { useBubbles }     from '@/Composables/useBubbles'
import { useConnections } from '@/Composables/useConnections'
import { usePhysics }     from '@/Composables/usePhysics'
import { useDrag }        from '@/Composables/useDrag'
import { useToast }       from '@/Composables/useToast'

const props = defineProps({
  feed:           { type: Array,   default: () => [] },
  hasFriends:     { type: Boolean, default: false },
  hasCommunities: { type: Boolean, default: false },
})

const { bubbles, hoveredId, connectSource, loading, load, add, toggleSelect } = useBubbles()
const { connections, friendConnections, load: loadConnections, loadFriendConnections, connect } = useConnections()
const { step } = usePhysics()
const { show: toast } = useToast()

const page           = usePage()
const authUser       = computed(() => page.props.auth?.user)
const pendingFriends      = computed(() => page.props.auth?.pending_friends_count      ?? 0)
const unreadMessages      = computed(() => page.props.auth?.unread_messages_count      ?? 0)
const unreadNotifications = computed(() => page.props.auth?.unread_notifications_count ?? 0)

const feedOpen = ref(false)

watch(() => page.props.flash?.status, (msg) => { if (msg) toast(msg, 'success') })
watch(() => page.props.flash?.error,  (msg) => { if (msg) toast(msg, 'error')   })

const showAdd      = ref(false)
const searchOpen   = ref(false)
const searchQuery  = ref('')
const searchResults = ref(null)
const searchLoading = ref(false)
let searchTimer = null

const hasResults = computed(() =>
  searchResults.value &&
  (searchResults.value.users?.length || searchResults.value.communities?.length || searchResults.value.posts?.length)
)

watch(searchQuery, (q) => {
  clearTimeout(searchTimer)
  if (!q.trim()) { searchResults.value = null; searchLoading.value = false; return }
  searchLoading.value = true
  searchTimer = setTimeout(() => {
    axios.get(route('search.api'), { params: { q } })
      .then(r => { searchResults.value = r.data; searchLoading.value = false })
      .catch(() => { searchLoading.value = false })
  }, 320)
})

function openSearch() {
  searchOpen.value = true
  setTimeout(() => document.getElementById('bubble-search-input')?.focus(), 50)
}

function closeSearch() {
  searchOpen.value   = false
  searchQuery.value  = ''
  searchResults.value = null
  searchLoading.value = false
}

function goToResult(url) {
  closeSearch()
  router.visit(url)
}

function submitSearch(e) {
  e.preventDefault()
  const q = searchQuery.value.trim()
  if (!q) return
  closeSearch()
  router.visit(route('search.index', { q }))
}

const { dragging, startDrag, startTouch, onMouseMove: moveDrag, onTouchMove: moveDragTouch, stopDrag } = useDrag(
  (id) => toggleSelect(id)
)

function onWindowMouseMove(e) { moveDrag(e, bubbles.value) }
function onWindowMouseUp()    { stopDrag() }
function onWindowTouchMove(e) { moveDragTouch(e, bubbles.value) }
function onWindowTouchEnd()   { stopDrag() }

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
  if (e.key === 'Escape') {
    if (searchOpen.value) { searchOpen.value = false; return }
    clearSelection()
  }
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    openSearch()
  }
}

let animId   = null
let lastTime = 0

function loop(timestamp) {
  const dt = timestamp - lastTime
  lastTime = timestamp
  // Skip physics after a large gap (tab was hidden or page froze).
  // Normal 60fps ≈ 16ms; anything beyond 100ms means we missed frames.
  if (dt < 100) step(bubbles.value, dragging.value?.id)
  animId = requestAnimationFrame(loop)
}

function startLoop() {
  if (animId !== null) return
  lastTime = performance.now()
  animId = requestAnimationFrame(loop)
}

function stopLoop() {
  if (animId === null) return
  cancelAnimationFrame(animId)
  animId = null
}

function onVisibilityChange() {
  document.hidden ? stopLoop() : startLoop()
}

let pollTimer = null

onMounted(() => {
  load()
  loadConnections()
  loadFriendConnections()
  window.addEventListener('mousemove',    onWindowMouseMove)
  window.addEventListener('mouseup',      onWindowMouseUp)
  window.addEventListener('keydown',      onKeyDown)
  window.addEventListener('touchmove',    onWindowTouchMove, { passive: false })
  window.addEventListener('touchend',     onWindowTouchEnd)
  document.addEventListener('visibilitychange', onVisibilityChange)
  startLoop()
  if (authUser.value) {
    pollTimer = setInterval(() => {
      router.reload({ only: ['auth'], preserveScroll: true, preserveState: true })
    }, 30000)
  }
})

onUnmounted(() => {
  window.removeEventListener('mousemove',    onWindowMouseMove)
  window.removeEventListener('mouseup',      onWindowMouseUp)
  window.removeEventListener('keydown',      onKeyDown)
  window.removeEventListener('touchmove',    onWindowTouchMove)
  window.removeEventListener('touchend',     onWindowTouchEnd)
  document.removeEventListener('visibilitychange', onVisibilityChange)
  stopLoop()
  clearInterval(pollTimer)
})

async function handleCreate(data) {
  const newBubble = await add(data.label, data.color, {
    title:       data.title,
    description: data.description,
    tagline:     data.tagline,
    coverColor:  data.color,
    guidelines:  data.guidelines,
  })
  showAdd.value = false
  if (newBubble?.persisted) {
    router.visit(route('community.show', newBubble.id))
  }
}

const selectedBubble = computed(() => bubbles.value.find(b => b.selected) ?? null)

const trends = computed(() =>
  [...bubbles.value].sort((a, b) => b.members - a.members).slice(0, 6)
)

const trendsOpen = ref(window.innerWidth >= 640)
</script>

<template>
  <div
    class="w-screen h-screen overflow-hidden relative select-none"
    style="background: transparent; font-family: 'Segoe UI', system-ui, sans-serif; touch-action: none;"
    @click.self="clearSelection"
    @touchstart.self="clearSelection"
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

        <!-- Pesquisa -->
        <button
          @click.stop="openSearch"
          :style="{
            width: '36px', height: '36px', borderRadius: '10px', border: 'none',
            background: 'transparent', color: '#5a7a8a', cursor: 'pointer',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            transition: 'background .15s',
          }"
          @mouseenter="$event.currentTarget.style.background='#009ac714'"
          @mouseleave="$event.currentTarget.style.background='transparent'"
          title="Pesquisar (Ctrl+K)"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>

        <!-- Feed toggle -->
        <button
          @click.stop="feedOpen = !feedOpen"
          :style="{
            width: '36px', height: '36px', borderRadius: '10px', border: 'none',
            background: feedOpen ? '#009ac714' : 'transparent',
            color: feedOpen ? '#009ac7' : '#5a7a8a', cursor: 'pointer',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            transition: 'background .15s, color .15s',
          }"
          @mouseenter="$event.currentTarget.style.background='#009ac714'"
          @mouseleave="$event.currentTarget.style.background = feedOpen ? '#009ac714' : 'transparent'"
          title="Feed"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6h16M4 10h16M4 14h12M4 18h8"/>
          </svg>
        </button>

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
        <Link
          v-if="authUser"
          :href="route('notifications.index')"
          :style="{
            width: '36px', height: '36px', borderRadius: '10px',
            background: 'transparent', color: '#5a7a8a',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            transition: 'background .15s', textDecoration: 'none',
            position: 'relative',
          }"
          @mouseenter="$event.currentTarget.style.background='#009ac714'"
          @mouseleave="$event.currentTarget.style.background='transparent'"
          title="Notificações"
        >
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M9 1.5A4.5 4.5 0 004.5 6v3.5L3 11.5h12l-1.5-2V6A4.5 4.5 0 009 1.5z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M7.5 12a1.5 1.5 0 003 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
          <span
            v-if="unreadNotifications > 0"
            style="position: absolute; top: 4px; right: 4px; min-width: 14px; height: 14px; padding: 0 3px; background: #c74a6b; color: white; border-radius: 99px; font-size: 9px; font-weight: 800; display: flex; align-items: center; justify-content: center; line-height: 1;"
          >{{ unreadNotifications > 9 ? '9+' : unreadNotifications }}</span>
        </Link>


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

    <!-- SEARCH OVERLAY -->
    <Transition name="overlay">
      <div
        v-if="searchOpen"
        style="position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,0.28); backdrop-filter: blur(4px); display: flex; align-items: flex-start; justify-content: center; padding-top: 80px;"
        @click="closeSearch"
      >
        <div @click.stop style="width: 100%; max-width: 560px; margin: 0 20px;">

          <!-- Input -->
          <form @submit.prevent="submitSearch" style="position: relative;">
            <svg style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #8ba0b0; pointer-events: none;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input
              id="bubble-search-input"
              v-model="searchQuery"
              type="text"
              placeholder="Pesquisa pessoas, comunidades ou posts…"
              style="width: 100%; box-sizing: border-box; background: rgba(255,255,255,0.97); border: none; border-radius: 16px; padding: 16px 50px 16px 50px; font-size: 15px; color: #1a3a4a; outline: none; font-family: inherit; box-shadow: 0 16px 48px rgba(0,0,0,0.2);"
            />
            <!-- Loading spinner ou Esc hint -->
            <div style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%);">
              <svg v-if="searchLoading" style="color: #009ac7; animation: spin 1s linear infinite;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
              <kbd v-else style="font-size: 11px; color: #b0c0cc; background: #f0f4f8; border-radius: 6px; padding: 2px 7px; font-family: inherit;">Esc</kbd>
            </div>
          </form>

          <!-- Resultados inline -->
          <div
            v-if="searchQuery.trim() && (hasResults || searchResults)"
            style="margin-top: 8px; background: rgba(255,255,255,0.97); border-radius: 16px; box-shadow: 0 16px 48px rgba(0,0,0,0.18); overflow: hidden; max-height: 420px; overflow-y: auto;"
          >
            <!-- Sem resultados -->
            <div v-if="searchResults && !hasResults && !searchLoading" style="padding: 24px; text-align: center; color: #8ba0b0; font-size: 13px;">
              Sem resultados para "{{ searchQuery }}"
            </div>

            <template v-if="hasResults">
              <!-- Pessoas -->
              <template v-if="searchResults.users?.length">
                <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0; padding: 12px 16px 6px;">Pessoas</p>
                <div
                  v-for="u in searchResults.users.slice(0, 4)" :key="'u'+u.id"
                  @click="goToResult(u.username ? route('profile.show', u.username) : '#')"
                  style="display: flex; align-items: center; gap: 12px; padding: 10px 16px; cursor: pointer; transition: background .12s;"
                  @mouseenter="$event.currentTarget.style.background='#f0f8ff'"
                  @mouseleave="$event.currentTarget.style.background='transparent'"
                >
                  <img v-if="u.avatar" :src="clImg(u.avatar, 72, 72, 'fill', 'face')" :style="{ width:'36px', height:'36px', borderRadius:'50%', objectFit:'cover', border:`2px solid ${u.avatar_color}`, flexShrink:'0' }" />
                  <div v-else :style="{ width:'36px', height:'36px', borderRadius:'50%', background: u.avatar_color, flexShrink:'0', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'13px', fontWeight:'800', color:'white' }">{{ (u.name ?? '?')[0].toUpperCase() }}</div>
                  <div style="flex:1; min-width:0;">
                    <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ u.name }}</p>
                    <p v-if="u.username" style="font-size: 11px; color: #009ac7; margin: 0;">@{{ u.username }}</p>
                  </div>
                </div>
              </template>

              <!-- Comunidades -->
              <template v-if="searchResults.communities?.length">
                <div style="height: 1px; background: #f0f4f8; margin: 4px 0;" />
                <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0; padding: 8px 16px 6px;">Comunidades</p>
                <div
                  v-for="c in searchResults.communities.slice(0, 4)" :key="'c'+c.id"
                  @click="goToResult(route('community.show', c.id))"
                  style="display: flex; align-items: center; gap: 12px; padding: 10px 16px; cursor: pointer; transition: background .12s;"
                  @mouseenter="$event.currentTarget.style.background='#f0f8ff'"
                  @mouseleave="$event.currentTarget.style.background='transparent'"
                >
                  <div :style="{ width:'36px', height:'36px', borderRadius:'50%', flexShrink:'0', overflow:'hidden', boxShadow:`0 3px 10px ${c.color}44`, background:`radial-gradient(circle at 38% 32%, ${c.color}ee 0%, ${c.color} 60%)`, display:'flex', alignItems:'center', justifyContent:'center' }">
                    <img v-if="c.image" :src="c.image" style="width:100%; height:100%; object-fit:cover; display:block;" />
                    <span v-else style="font-size: 9px; font-weight: 800; color: white;">{{ c.label?.slice(0,3) }}</span>
                  </div>
                  <div style="flex:1; min-width:0;">
                    <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ c.title }}</p>
                    <p style="font-size: 11px; color: #8ba0b0; margin: 0;">{{ c.members }} membros</p>
                  </div>
                </div>
              </template>

              <!-- Posts -->
              <template v-if="searchResults.posts?.length">
                <div style="height: 1px; background: #f0f4f8; margin: 4px 0;" />
                <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0; padding: 8px 16px 6px;">Posts</p>
                <div
                  v-for="p in searchResults.posts.slice(0, 3)" :key="'p'+p.id"
                  @click="goToResult(p.author.username ? route('profile.show', p.author.username) : '#')"
                  style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 16px; cursor: pointer; transition: background .12s;"
                  @mouseenter="$event.currentTarget.style.background='#f0f8ff'"
                  @mouseleave="$event.currentTarget.style.background='transparent'"
                >
                  <img v-if="p.author.avatar" :src="clImg(p.author.avatar, 48, 48, 'fill', 'face')" :style="{ width:'28px', height:'28px', borderRadius:'50%', objectFit:'cover', border:`1.5px solid ${p.author.avatar_color}`, flexShrink:'0', marginTop:'1px' }" />
                  <div v-else :style="{ width:'28px', height:'28px', borderRadius:'50%', background: p.author.avatar_color, flexShrink:'0', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'10px', fontWeight:'800', color:'white', marginTop:'1px' }">{{ (p.author.name ?? '?')[0].toUpperCase() }}</div>
                  <div style="flex:1; min-width:0;">
                    <p style="font-size: 12px; font-weight: 700; color: #1a3a4a; margin: 0 0 2px;">{{ p.author.name }}</p>
                    <p style="font-size: 11px; color: #5a7a8a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ p.content }}</p>
                  </div>
                </div>
              </template>

              <!-- Ver todos -->
              <div
                @click="submitSearch({ preventDefault: () => {} })"
                style="padding: 12px 16px; text-align: center; font-size: 12px; font-weight: 700; color: #009ac7; cursor: pointer; border-top: 1px solid #f0f4f8; transition: background .12s;"
                @mouseenter="$event.currentTarget.style.background='#f0f8ff'"
                @mouseleave="$event.currentTarget.style.background='transparent'"
              >Ver todos os resultados →</div>
            </template>
          </div>

          <p v-if="!searchQuery.trim()" style="font-size: 11px; color: rgba(255,255,255,0.7); margin: 10px 0 0; text-align: center;">Enter para pesquisar · Esc para fechar</p>
        </div>
      </div>
    </Transition>

    <!-- MODAL: Nova bolha -->
    <CreateCommunityModal
      v-if="showAdd"
      @create="handleCreate"
      @cancel="showAdd = false"
    />

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
      @touchstart="startTouch(b, $event)"
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
      <!-- Header — clicável em mobile para abrir/fechar -->
      <div
        style="display: flex; align-items: center; justify-content: space-between; padding: 0 6px; margin-bottom: 8px; cursor: pointer; user-select: none;"
        @click="trendsOpen = !trendsOpen"
        @touchend.prevent="trendsOpen = !trendsOpen"
      >
        <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0;">Global Trends</p>
        <svg
          width="12" height="12" viewBox="0 0 12 12" fill="none"
          :style="{ transform: trendsOpen ? 'rotate(0deg)' : 'rotate(180deg)', transition: 'transform .25s', flexShrink: 0 }"
        >
          <path d="M2 8L6 4l4 4" stroke="#8ba0b0" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>

      <!-- Lista colapsável -->
      <div
        :style="{
          overflow: 'hidden',
          maxHeight: trendsOpen ? '400px' : '0px',
          transition: 'max-height .3s cubic-bezier(.4,0,.2,1)',
          display: 'flex', flexDirection: 'column', gap: '2px',
        }"
      >
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
    </div>

    <!-- FEED PANEL -->
    <Transition name="slide-left">
      <div
        v-if="feedOpen"
        style="position: absolute; left: 16px; top: 70px; z-index: 38; width: 330px; height: calc(100vh - 86px); background: rgba(255,255,255,0.88); backdrop-filter: blur(16px); border-radius: 18px; border: 1px solid #4ebcff22; box-shadow: 0 4px 20px #009ac70c; display: flex; flex-direction: column;"
        @click.stop
        @mousedown.stop
      >
        <!-- Header -->
        <div style="padding: 14px 16px 10px; border-bottom: 1px solid #f0f4f8; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
          <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0;">Feed</p>
          <Link :href="route('feed.index')" style="font-size: 11px; font-weight: 700; color: #009ac7; text-decoration: none;">Ver tudo →</Link>
        </div>

        <!-- Content -->
        <div style="padding: 10px 10px 20px; flex: 1; overflow-y: auto;">
          <div v-if="props.feed.length === 0" style="text-align: center; padding: 40px 16px;">
            <p style="font-size: 28px; margin: 0 0 10px;">🫧</p>
            <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0 0 6px;">Feed vazio</p>
            <p style="font-size: 11px; color: #8ba0b0; margin: 0; line-height: 1.5;">Junta-te a comunidades e adiciona amigos para ver posts aqui.</p>
          </div>
          <div v-else style="display: flex; flex-direction: column; gap: 8px;">
            <PostCard
              v-for="item in props.feed"
              :key="`${item._type}-${item.id}`"
              :post="item"
              :author="item.author"
              :auth-user="authUser"
              :can-edit="item.can_edit"
              :can-delete="item.can_delete"
              :like-route="item.like_route"
              :comment-route="item.comment_route"
              :delete-route="item.delete_route"
              :edit-route="item.can_edit ? item.edit_route : null"
              :report-route="!item.can_edit && authUser ? (item._type === 'post' ? route('posts.report', item.id) : route('community-posts.report', item.id)) : null"
              :community="item.community ?? null"
            />
          </div>
        </div>
      </div>
    </Transition>

    <ToastContainer />

    <!-- FLOOR GRADIENT -->
    <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 100px; background: linear-gradient(to top, #9dcee8 0%, transparent 100%); pointer-events: none; z-index: 1;" />

    <!-- HINT -->
    <div style="position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); z-index: 10; pointer-events: none;">
      <span style="font-size: 10px; color: #009ac7aa; background: rgba(255,255,255,.6); padding: 5px 16px; border-radius: 99px; backdrop-filter: blur(8px);">
        Segura para arrastar · Clica para expandir · Esc para fechar
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
.fade-enter-active, .fade-leave-active    { transition: opacity .2s }
.fade-enter-from,  .fade-leave-to        { opacity: 0 }

.slide-left-enter-active { transition: opacity .28s ease, transform .32s cubic-bezier(.2,.8,.2,1) }
.slide-left-leave-active { transition: opacity .2s ease, transform .24s ease }
.slide-left-enter-from   { opacity: 0; transform: translateX(-20px) }
.slide-left-leave-to     { opacity: 0; transform: translateX(-20px) }

.overlay-enter-active, .overlay-leave-active { transition: opacity .2s ease }
.overlay-enter-from,   .overlay-leave-to     { opacity: 0 }
@keyframes spin { to { transform: rotate(360deg) } }


.bubble-expand-enter-active { transition: opacity .3s ease, transform .45s cubic-bezier(.22,.78,.26,1); }
.bubble-expand-leave-active { transition: opacity .22s ease, transform .32s cubic-bezier(.6,0,.4,1); }
.bubble-expand-enter-from,
.bubble-expand-leave-to     { opacity: 0; transform: scale(0.32); }

input::placeholder { color: #4ebcff77 }
input:focus        { border-color: #4ebcff !important }
</style>
