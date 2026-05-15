<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { clImg } from '@/Composables/useCloudinary'
import axios from 'axios'

const props = defineProps({
    conversations:      { type: Array,  default: () => [] },
    activeConversation: { type: Object, default: null },
    messages:           { type: Array,  default: () => [] },
    hasMoreMessages:    { type: Boolean, default: false },
    friends:            { type: Array,  default: () => [] },
})

const page     = usePage()
const authUser = computed(() => page.props.auth?.user)

// ── Refs ──────────────────────────────────────────────────────
const messagesEl   = ref(null)
const msgForm      = useForm({ content: '', image: null })
const imagePreview = ref(null)
const imageInput   = ref(null)
const isMobile     = ref(false)
const showSidebar  = ref(true)

// ── Paginação de mensagens antigas ────────────────────────────
const localMessages  = ref([...props.messages])
const hasMore        = ref(props.hasMoreMessages)
const loadingOlder   = ref(false)

// ── Estado de envio ───────────────────────────────────────────
// 'idle' | 'sending' | 'sent' | 'error'
const sendState = ref('idle')
let sentTimer   = null

// ── Smart scroll ──────────────────────────────────────────────
const isNearBottom = ref(true)
const NEAR_BOTTOM_THRESHOLD = 160

// ── Polling ───────────────────────────────────────────────────
let pollInterval  = null
const lastMsgId   = computed(() =>
    localMessages.value.length
        ? localMessages.value[localMessages.value.length - 1].id
        : 0
)

// ── Helpers ───────────────────────────────────────────────────
function checkMobile() {
    isMobile.value = window.innerWidth < 640
    if (!isMobile.value) showSidebar.value = true
}

function avatarInitial(name) {
    return (name ?? '?')[0].toUpperCase()
}

function formatTime(iso) {
    if (!iso) return ''
    const d    = new Date(iso)
    const now  = new Date()
    const diff = now - d
    if (diff < 60_000)     return 'agora'
    if (diff < 3_600_000)  return Math.floor(diff / 60_000) + 'm'
    if (diff < 86_400_000) return d.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' })
    return d.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit' })
}

function formatDate(iso) {
    if (!iso) return ''
    const d         = new Date(iso)
    const now       = new Date()
    if (d.toDateString() === now.toDateString()) return 'Hoje'
    const yesterday = new Date(now)
    yesterday.setDate(yesterday.getDate() - 1)
    if (d.toDateString() === yesterday.toDateString()) return 'Ontem'
    return d.toLocaleDateString('pt-PT', { weekday: 'long', day: '2-digit', month: 'long' })
}

const groupedMessages = computed(() => {
    const groups = []
    let lastDate = null
    for (const msg of localMessages.value) {
        const dateLabel = formatDate(msg.created_at)
        if (dateLabel !== lastDate) {
            groups.push({ type: 'date', label: dateLabel })
            lastDate = dateLabel
        }
        groups.push({ type: 'msg', ...msg })
    }
    return groups
})

// ── Scroll ────────────────────────────────────────────────────
function checkNearBottom() {
    if (!messagesEl.value) return
    const el   = messagesEl.value
    const dist = el.scrollHeight - el.scrollTop - el.clientHeight
    isNearBottom.value = dist < NEAR_BOTTOM_THRESHOLD
}

function scrollToBottom(smooth = true) {
    nextTick(() => {
        if (messagesEl.value) {
            messagesEl.value.scrollTo({
                top:      messagesEl.value.scrollHeight,
                behavior: smooth ? 'smooth' : 'instant',
            })
            isNearBottom.value = true
        }
    })
}

// ── Load older messages ───────────────────────────────────────
async function loadOlderMessages() {
    if (!props.activeConversation || !hasMore.value || loadingOlder.value) return
    loadingOlder.value = true

    const oldestId     = localMessages.value[0]?.id ?? 0
    const scrollBefore = messagesEl.value?.scrollHeight ?? 0

    router.visit(
        route('conversations.show', props.activeConversation.id) + `?before=${oldestId}`,
        {
            preserveScroll: true,
            preserveState:  true,
            only:           ['messages', 'hasMoreMessages'],
            onSuccess: (page) => {
                const older  = page.props.messages ?? []
                // Prepend older messages without losing current scroll position
                localMessages.value  = [...older, ...localMessages.value]
                hasMore.value        = page.props.hasMoreMessages
                loadingOlder.value   = false

                // Restore scroll so user stays at the same visual position
                nextTick(() => {
                    if (messagesEl.value) {
                        messagesEl.value.scrollTop = messagesEl.value.scrollHeight - scrollBefore
                    }
                })
            },
            onError: () => { loadingOlder.value = false },
        }
    )
}

// ── Polling ───────────────────────────────────────────────────
async function poll() {
    if (!props.activeConversation || document.hidden) return

    try {
        const res  = await axios.get(
            route('conversations.poll', props.activeConversation.id),
            { params: { after: lastMsgId.value } }
        )
        const newMsgs = res.data?.messages ?? []
        if (newMsgs.length) {
            localMessages.value = [...localMessages.value, ...newMsgs]
            if (isNearBottom.value) scrollToBottom(true)
        }
    } catch (_) { /* silent */ }
}

function startPolling() {
    if (pollInterval) return
    pollInterval = setInterval(poll, 12_000)
}

function stopPolling() {
    clearInterval(pollInterval)
    pollInterval = null
}

// ── Image handling ────────────────────────────────────────────
function onImageChange(e) {
    const file = e.target.files[0]
    if (!file) return
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
    msgForm.image      = file
    imagePreview.value = URL.createObjectURL(file)
    e.target.value     = ''
}

function removeImage() {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
    msgForm.image      = null
    imagePreview.value = null
}

// ── Send ──────────────────────────────────────────────────────
function send() {
    const hasText  = msgForm.content.trim().length > 0
    const hasImage = !!msgForm.image
    if ((!hasText && !hasImage) || sendState.value === 'sending' || !props.activeConversation) return

    sendState.value = 'sending'
    clearTimeout(sentTimer)

    msgForm.post(
        route('messages.store', props.activeConversation.id),
        {
            forceFormData:  true,
            preserveScroll: true,
            onSuccess: (page) => {
                msgForm.reset('content', 'image')
                removeImage()
                sendState.value = 'sent'
                // Sync new messages from props after successful send
                localMessages.value = page.props.messages ?? localMessages.value
                sentTimer = setTimeout(() => { sendState.value = 'idle' }, 2000)
                scrollToBottom(true)
            },
            onError: () => {
                sendState.value = 'error'
                sentTimer = setTimeout(() => { sendState.value = 'idle' }, 3000)
            },
        }
    )
}

function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        send()
    }
}

// ── Edit & delete ─────────────────────────────────────────────
const editingId       = ref(null)
const editContent     = ref('')
const confirmDeleteId = ref(null)
const actionLoading   = ref(false)

function startEdit(msg) {
    editingId.value       = msg.id
    editContent.value     = msg.content ?? ''
    confirmDeleteId.value = null
    nextTick(() => document.getElementById(`edit-${msg.id}`)?.focus())
}

function cancelEdit() {
    editingId.value   = null
    editContent.value = ''
}

async function saveEdit(msg) {
    if (!editContent.value.trim() || actionLoading.value) return
    actionLoading.value = true
    try {
        const res = await axios.patch(route('messages.update', msg.id), { content: editContent.value.trim() })
        localMessages.value = localMessages.value.map(m =>
            m.id === msg.id ? { ...m, content: res.data.content, is_edited: res.data.is_edited } : m
        )
        cancelEdit()
    } catch { /* silent */ }
    finally { actionLoading.value = false }
}

function startDelete(id) {
    confirmDeleteId.value = id
    editingId.value       = null
    editContent.value     = ''
}

function cancelDelete() { confirmDeleteId.value = null }

async function confirmDelete(id) {
    if (actionLoading.value) return
    actionLoading.value = true
    try {
        await axios.delete(route('messages.destroy', id))
        localMessages.value   = localMessages.value.filter(m => m.id !== id)
        confirmDeleteId.value = null
    } catch { /* silent */ }
    finally { actionLoading.value = false }
}

// ── Sidebar / compose ─────────────────────────────────────────
const totalUnread = computed(() =>
    props.conversations.reduce((s, c) => s + (c.unread_count ?? 0), 0)
)

function startWith(recipientId) {
    router.post(route('conversations.store'), { recipient_id: recipientId })
}

// ── Lifecycle ─────────────────────────────────────────────────
onMounted(() => {
    checkMobile()
    if (props.activeConversation && isMobile.value) showSidebar.value = false
    window.addEventListener('resize', checkMobile)
    if (messagesEl.value) messagesEl.value.addEventListener('scroll', checkNearBottom)

    scrollToBottom(false)
    if (props.activeConversation) startPolling()
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
    if (messagesEl.value) messagesEl.value.removeEventListener('scroll', checkNearBottom)
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
    stopPolling()
    clearTimeout(sentTimer)
})

// When navigating to a different conversation
watch(() => props.activeConversation?.id, (newId) => {
    stopPolling()
    localMessages.value = [...props.messages]
    hasMore.value       = props.hasMoreMessages
    sendState.value     = 'idle'
    scrollToBottom(false)
    if (newId) startPolling()
}, { flush: 'post' })

// Sync messages when props update (e.g. after send)
watch(() => props.messages, (msgs) => {
    if (!loadingOlder.value) {
        localMessages.value = [...msgs]
        hasMore.value       = props.hasMoreMessages
    }
}, { deep: true })
</script>

<template>
    <Head title="Mensagens · bubbles" />

    <AuthenticatedLayout>
        <!-- Full-height chat shell -->
        <div style="display: flex; height: calc(100vh - 58px); overflow: hidden; background: transparent;">

            <!-- ═══ SIDEBAR ═══════════════════════════════════ -->
            <aside
                v-show="!isMobile || showSidebar"
                :style="{
                    width: isMobile ? '100%' : '300px',
                    minWidth: isMobile ? 'unset' : '300px',
                    display: 'flex', flexDirection: 'column',
                    borderRight: '1px solid #009ac712',
                    background: 'rgba(255,255,255,0.60)',
                    backdropFilter: 'blur(24px)',
                }"
            >
                <!-- Sidebar header -->
                <div style="padding: 20px 20px 14px; border-bottom: 1px solid #009ac710; flex-shrink: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h2 style="font-size: 17px; font-weight: 900; color: #1a3a4a; margin: 0; letter-spacing: -.02em;">Mensagens</h2>
                            <p v-if="totalUnread > 0" style="font-size: 11px; color: #009ac7; margin: 2px 0 0; font-weight: 700;">
                                {{ totalUnread }} não {{ totalUnread === 1 ? 'lida' : 'lidas' }}
                            </p>
                        </div>
                        <Link
                            :href="route('friends.index')"
                            title="Nova conversa"
                            style="width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg,#009ac7,#4ebcff); display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 12px #009ac730; text-decoration: none; transition: transform .2s, box-shadow .2s;"
                            @mouseenter="$event.currentTarget.style.transform='scale(1.08)'; $event.currentTarget.style.boxShadow='0 6px 20px #009ac740'"
                            @mouseleave="$event.currentTarget.style.transform='scale(1)'; $event.currentTarget.style.boxShadow='0 3px 12px #009ac730'"
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                        </Link>
                    </div>
                </div>

                <!-- Conversation list -->
                <div style="flex: 1; overflow-y: auto; padding: 8px;">
                    <!-- Empty: show friends -->
                    <div v-if="conversations.length === 0 && friends.length > 0">
                        <p style="font-size: 10px; font-weight: 800; color: #b0c0cc; text-transform: uppercase; letter-spacing: .08em; margin: 8px 12px 10px;">Os teus amigos</p>
                        <button
                            v-for="f in friends" :key="f.id"
                            @click="startWith(f.id)"
                            style="width: 100%; display: flex; align-items: center; gap: 11px; padding: 10px 12px; border-radius: 14px; border: none; background: transparent; cursor: pointer; text-align: left; transition: background .15s; margin-bottom: 2px;"
                            @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.06)'"
                            @mouseleave="$event.currentTarget.style.background='transparent'"
                        >
                            <img v-if="f.avatar" :src="clImg(f.avatar, 80, 80, 'fill', 'face')" loading="lazy" :style="{ width:'38px', height:'38px', borderRadius:'50%', objectFit:'cover', flexShrink:'0', border:`2px solid ${f.avatar_color}` }" />
                            <div v-else :style="{ width:'38px', height:'38px', borderRadius:'50%', flexShrink:'0', background: f.avatar_color, display:'flex', alignItems:'center', justifyContent:'center', fontSize:'14px', fontWeight:'800', color:'white' }">{{ avatarInitial(f.name) }}</div>
                            <div style="flex: 1; min-width: 0;">
                                <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ f.name }}</p>
                                <p style="font-size: 11px; color: #009ac7; margin: 1px 0 0;">Iniciar conversa</p>
                            </div>
                        </button>
                    </div>

                    <!-- Empty: no friends -->
                    <div v-else-if="conversations.length === 0" style="text-align: center; padding: 60px 20px;">
                        <p style="font-size: 28px; margin: 0 0 10px;">💬</p>
                        <p style="font-size: 13px; color: #8ba0b0; margin: 0;">Nenhuma conversa ainda.</p>
                        <p style="font-size: 11px; color: #b0c0cc; margin: 6px 0 0;">Vai aos <Link :href="route('friends.index')" style="color:#009ac7;text-decoration:none;font-weight:700;">amigos</Link> e inicia uma conversa.</p>
                    </div>

                    <!-- Conversation items -->
                    <Link
                        v-for="conv in conversations" :key="conv.id"
                        :href="route('conversations.show', conv.id)"
                        @click="isMobile && (showSidebar = false)"
                        style="display: flex; align-items: center; gap: 11px; padding: 11px 12px; border-radius: 14px; text-decoration: none; transition: background .15s; margin-bottom: 2px; position: relative;"
                        :style="{
                            background: activeConversation?.id === conv.id ? 'rgba(0,154,199,0.10)' : 'transparent',
                            border: activeConversation?.id === conv.id ? '1px solid #009ac722' : '1px solid transparent',
                        }"
                        @mouseenter="e => { if (activeConversation?.id !== conv.id) e.currentTarget.style.background = 'rgba(0,154,199,0.05)' }"
                        @mouseleave="e => { if (activeConversation?.id !== conv.id) e.currentTarget.style.background = 'transparent' }"
                    >
                        <div style="position: relative; flex-shrink: 0;">
                            <img v-if="conv.other_user?.avatar" :src="clImg(conv.other_user.avatar, 88, 88, 'fill', 'face')" loading="lazy" :style="{ width:'42px', height:'42px', borderRadius:'50%', objectFit:'cover', border:`2px solid ${conv.other_user.avatar_color}`, boxShadow:`0 2px 8px ${conv.other_user.avatar_color}33`, display:'block' }" />
                            <div v-else :style="{ width:'42px', height:'42px', borderRadius:'50%', background: conv.other_user?.avatar_color ?? '#009ac7', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'16px', fontWeight:'800', color:'white', flexShrink:'0' }">{{ avatarInitial(conv.other_user?.name) }}</div>
                            <span v-if="conv.unread_count > 0" style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; border-radius: 50%; background: #009ac7; border: 2px solid white;" />
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 6px;">
                                <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ conv.other_user?.name ?? '—' }}</p>
                                <span style="font-size: 10px; color: #b0c0cc; white-space: nowrap; flex-shrink: 0;">{{ formatTime(conv.last_message?.created_at) }}</span>
                            </div>
                            <p style="font-size: 11px; margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" :style="{ color: conv.unread_count > 0 ? '#009ac7' : '#8ba0b0', fontWeight: conv.unread_count > 0 ? '600' : '400' }">
                                <span v-if="conv.last_message?.is_own" style="color: #b0c0cc;">Eu: </span>
                                {{ conv.last_message?.content ?? 'Sem mensagens ainda.' }}
                            </p>
                        </div>
                        <span v-if="conv.unread_count > 0" style="flex-shrink: 0; min-width: 20px; height: 20px; padding: 0 6px; background: #009ac7; color: white; border-radius: 99px; font-size: 10px; font-weight: 800; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px #009ac740;">{{ conv.unread_count }}</span>
                    </Link>
                </div>
            </aside>

            <!-- ═══ MAIN ═══════════════════════════════════════ -->
            <main v-show="!isMobile || !showSidebar" style="flex: 1; display: flex; flex-direction: column; min-width: 0; background: transparent;">

                <!-- No conversation selected -->
                <div v-if="!activeConversation" style="flex: 1; overflow-y: auto; display: flex; flex-direction: column;">
                    <div v-if="friends.length > 0" style="max-width: 480px; width: 100%; margin: 0 auto; padding: 40px 28px 60px;">
                        <div style="text-align: center; margin-bottom: 32px;">
                            <div style="width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg,#009ac714,#4ebcff0e); border: 2px solid #009ac722; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 14px;">💬</div>
                            <p style="font-size: 16px; font-weight: 800; color: #1a3a4a; margin: 0 0 6px;">As tuas mensagens</p>
                            <p style="font-size: 13px; color: #8ba0b0; margin: 0; line-height: 1.5;">Escolhe um amigo para começar a conversar.</p>
                        </div>
                        <div style="background: rgba(255,255,255,0.80); backdrop-filter: blur(20px); border: 1px solid #009ac714; border-radius: 20px; box-shadow: 0 4px 20px #009ac70a; overflow: hidden;">
                            <button
                                v-for="(f, i) in friends" :key="f.id"
                                @click="startWith(f.id)"
                                style="width: 100%; display: flex; align-items: center; gap: 14px; padding: 14px 20px; border: none; background: transparent; cursor: pointer; text-align: left; transition: background .15s;"
                                :style="{ borderTop: i > 0 ? '1px solid #009ac70c' : 'none' }"
                                @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.05)'"
                                @mouseleave="$event.currentTarget.style.background='transparent'"
                            >
                                <img v-if="f.avatar" :src="clImg(f.avatar, 96, 96, 'fill', 'face')" :style="{ width:'46px', height:'46px', borderRadius:'50%', objectFit:'cover', flexShrink:'0', border:`2.5px solid ${f.avatar_color}`, boxShadow:`0 2px 10px ${f.avatar_color}33` }" />
                                <div v-else :style="{ width:'46px', height:'46px', borderRadius:'50%', flexShrink:'0', background: f.avatar_color, display:'flex', alignItems:'center', justifyContent:'center', fontSize:'17px', fontWeight:'800', color:'white' }">{{ avatarInitial(f.name) }}</div>
                                <div style="flex: 1; min-width: 0;">
                                    <p style="font-size: 14px; font-weight: 700; color: #1a3a4a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ f.name }}</p>
                                    <p v-if="f.username" style="font-size: 12px; color: #009ac7; margin: 2px 0 0;">@{{ f.username }}</p>
                                </div>
                                <span style="flex-shrink: 0; padding: 7px 16px; border-radius: 99px; background: linear-gradient(135deg,#009ac7,#4ebcff); color: white; font-size: 12px; font-weight: 700; box-shadow: 0 3px 10px #009ac730;">Enviar mensagem</span>
                            </button>
                        </div>
                    </div>
                    <div v-else style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; padding: 40px;">
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg,#009ac714,#4ebcff0e); border: 2px solid #009ac722; display: flex; align-items: center; justify-content: center; font-size: 36px;">💬</div>
                        <p style="font-size: 15px; font-weight: 700; color: #1a3a4a; margin: 0;">As tuas mensagens</p>
                        <p style="font-size: 13px; color: #8ba0b0; margin: 0; text-align: center; max-width: 260px; line-height: 1.5;">Adiciona amigos para começar a conversar com eles.</p>
                        <Link :href="route('friends.index')" style="margin-top: 4px; padding: 10px 22px; background: linear-gradient(135deg,#009ac7,#4ebcff); color: white; font-size: 13px; font-weight: 700; border-radius: 99px; text-decoration: none; box-shadow: 0 4px 16px #009ac730; transition: transform .2s, box-shadow .2s;" @mouseenter="$event.currentTarget.style.transform='translateY(-2px)'; $event.currentTarget.style.boxShadow='0 8px 24px #009ac740'" @mouseleave="$event.currentTarget.style.transform='translateY(0)'; $event.currentTarget.style.boxShadow='0 4px 16px #009ac730'">Ver amigos</Link>
                    </div>
                </div>

                <!-- Active conversation -->
                <template v-else>

                    <!-- Header -->
                    <div style="height: 64px; flex-shrink: 0; display: flex; align-items: center; gap: 14px; padding: 0 24px; background: rgba(255,255,255,0.65); backdrop-filter: blur(20px); border-bottom: 1px solid #009ac712;">
                        <button v-if="isMobile" @click="showSidebar = true" style="flex-shrink: 0; width: 34px; height: 34px; border-radius: 50%; border: 1.5px solid #009ac733; background: rgba(0,154,199,0.06); color: #009ac7; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s;" @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.14)'" @mouseleave="$event.currentTarget.style.background='rgba(0,154,199,0.06)'">←</button>
                        <img v-if="activeConversation.other_user?.avatar" :src="clImg(activeConversation.other_user.avatar, 80, 80, 'fill', 'face')" :style="{ width:'38px', height:'38px', borderRadius:'50%', objectFit:'cover', border:`2px solid ${activeConversation.other_user.avatar_color}`, boxShadow:`0 2px 8px ${activeConversation.other_user.avatar_color}44`, flexShrink:'0' }" />
                        <div v-else :style="{ width:'38px', height:'38px', borderRadius:'50%', flexShrink:'0', background: activeConversation.other_user?.avatar_color ?? '#009ac7', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'14px', fontWeight:'800', color:'white' }">{{ avatarInitial(activeConversation.other_user?.name) }}</div>
                        <div style="flex: 1; min-width: 0;">
                            <p style="font-size: 14px; font-weight: 800; color: #1a3a4a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ activeConversation.other_user?.name }}</p>
                            <p v-if="activeConversation.other_user?.username" style="font-size: 11px; color: #009ac7; margin: 1px 0 0;">@{{ activeConversation.other_user.username }}</p>
                        </div>
                        <Link v-if="activeConversation.other_user?.username" :href="route('profile.show', activeConversation.other_user.username)" style="padding: 7px 16px; border-radius: 99px; border: 1.5px solid #009ac733; color: #009ac7; font-size: 12px; font-weight: 700; text-decoration: none; background: rgba(0,154,199,0.06); transition: all .2s; white-space: nowrap;" @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.14)'; $event.currentTarget.style.borderColor='#009ac7'" @mouseleave="$event.currentTarget.style.background='rgba(0,154,199,0.06)'; $event.currentTarget.style.borderColor='#009ac733'">Ver perfil</Link>
                    </div>

                    <!-- Messages area -->
                    <div
                        ref="messagesEl"
                        style="flex: 1; overflow-y: auto; padding: 24px 28px; display: flex; flex-direction: column; gap: 4px;"
                    >
                        <!-- Load older button -->
                        <div v-if="hasMore" style="text-align: center; margin-bottom: 12px;">
                            <button
                                @click="loadOlderMessages"
                                :disabled="loadingOlder"
                                style="padding: 6px 20px; border-radius: 99px; border: 1.5px solid #009ac733; background: rgba(255,255,255,0.7); color: #009ac7; font-size: 11px; font-weight: 700; cursor: pointer; backdrop-filter: blur(8px); transition: all .2s;"
                                @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.08)'"
                                @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.7)'"
                            >
                                {{ loadingOlder ? 'A carregar…' : '↑ Carregar mensagens antigas' }}
                            </button>
                        </div>

                        <!-- Empty state -->
                        <div v-if="localMessages.length === 0" style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; opacity: .6;">
                            <p style="font-size: 22px; margin: 0;">👋</p>
                            <p style="font-size: 13px; color: #8ba0b0; margin: 0;">Sem mensagens ainda. Diz olá!</p>
                        </div>

                        <!-- Message groups -->
                        <template v-for="(item, i) in groupedMessages" :key="i">
                            <div v-if="item.type === 'date'" style="display: flex; align-items: center; gap: 12px; margin: 18px 0 10px;">
                                <div style="flex: 1; height: 1px; background: linear-gradient(to right, transparent, #009ac714);"></div>
                                <span style="font-size: 10px; font-weight: 700; color: #b0c0cc; letter-spacing: .06em; white-space: nowrap;">{{ item.label }}</span>
                                <div style="flex: 1; height: 1px; background: linear-gradient(to left, transparent, #009ac714);"></div>
                            </div>

                            <div
                                v-else
                                class="msg-group"
                                style="display: flex; flex-direction: column;"
                                :style="{ alignItems: item.is_own ? 'flex-end' : 'flex-start' }"
                            >
                                <!-- Action buttons (own messages, shown on hover) -->
                                <div
                                    v-if="item.is_own && confirmDeleteId !== item.id && editingId !== item.id"
                                    class="msg-actions"
                                    style="display: flex; gap: 4px; margin-bottom: 3px;"
                                >
                                    <button
                                        v-if="item.content"
                                        @click.stop="startEdit(item)"
                                        style="width: 24px; height: 24px; border-radius: 6px; border: none; background: rgba(255,255,255,0.85); color: #8ba0b0; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .15s; backdrop-filter: blur(8px); box-shadow: 0 1px 4px #0002;"
                                        @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.12)'; $event.currentTarget.style.color='#009ac7'"
                                        @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.85)'; $event.currentTarget.style.color='#8ba0b0'"
                                        title="Editar"
                                    >
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button
                                        @click.stop="startDelete(item.id)"
                                        style="width: 24px; height: 24px; border-radius: 6px; border: none; background: rgba(255,255,255,0.85); color: #8ba0b0; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .15s; backdrop-filter: blur(8px); box-shadow: 0 1px 4px #0002;"
                                        @mouseenter="$event.currentTarget.style.background='rgba(224,85,85,0.10)'; $event.currentTarget.style.color='#e05555'"
                                        @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.85)'; $event.currentTarget.style.color='#8ba0b0'"
                                        title="Eliminar"
                                    >
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                    </button>
                                </div>

                                <!-- Delete confirmation -->
                                <div
                                    v-if="confirmDeleteId === item.id"
                                    style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px; background: rgba(255,255,255,0.95); border: 1px solid #e0555533; border-radius: 10px; padding: 6px 10px; backdrop-filter: blur(8px); box-shadow: 0 2px 10px #e055550a;"
                                >
                                    <span style="font-size: 11px; color: #5a7a8a; font-weight: 600;">Eliminar mensagem?</span>
                                    <button @click="confirmDelete(item.id)" :disabled="actionLoading" style="padding: 3px 10px; border-radius: 99px; border: none; background: #e05555; color: white; font-size: 11px; font-weight: 700; cursor: pointer;" :style="{ opacity: actionLoading ? 0.6 : 1 }">Sim</button>
                                    <button @click="cancelDelete" style="padding: 3px 10px; border-radius: 99px; border: 1.5px solid #009ac744; background: transparent; color: #009ac7; font-size: 11px; font-weight: 700; cursor: pointer;">Não</button>
                                </div>

                                <!-- Message bubble -->
                                <div
                                    style="max-width: 68%; border-radius: 18px; font-size: 13.5px; line-height: 1.5; word-break: break-word;"
                                    :style="editingId === item.id
                                        ? { background: 'rgba(255,255,255,0.97)', border: '1.5px solid #009ac744', backdropFilter: 'blur(12px)', boxShadow: '0 4px 20px #009ac71a', padding: '2px', minWidth: '220px' }
                                        : item.is_own
                                            ? {
                                                background: item.image_url && !item.content ? 'transparent' : `linear-gradient(135deg, ${authUser?.avatar_color ?? '#009ac7'}, ${authUser?.avatar_color ?? '#009ac7'}cc)`,
                                                color: 'white',
                                                borderBottomRightRadius: '5px',
                                                boxShadow: item.image_url && !item.content ? 'none' : `0 4px 16px ${authUser?.avatar_color ?? '#009ac7'}44`,
                                                padding: item.image_url ? '0' : '10px 14px',
                                              }
                                            : {
                                                background: item.image_url && !item.content ? 'transparent' : 'rgba(255,255,255,0.78)',
                                                backdropFilter: item.image_url && !item.content ? 'none' : 'blur(12px)',
                                                color: '#1a3a4a',
                                                borderBottomLeftRadius: '5px',
                                                border: item.image_url && !item.content ? 'none' : '1px solid #009ac714',
                                                boxShadow: item.image_url && !item.content ? 'none' : '0 2px 8px #009ac70a',
                                                padding: item.image_url ? '0' : '10px 14px',
                                              }
                                    "
                                >
                                    <!-- Edit mode -->
                                    <template v-if="editingId === item.id">
                                        <textarea
                                            :id="`edit-${item.id}`"
                                            v-model="editContent"
                                            rows="1"
                                            @keydown.enter.exact.prevent="saveEdit(item)"
                                            @keydown.esc="cancelEdit"
                                            @input="$event.target.style.height='auto'; $event.target.style.height=Math.min($event.target.scrollHeight, 120)+'px'"
                                            style="width: 100%; resize: none; border: none; border-radius: 14px; padding: 10px 14px; font-size: 13.5px; font-family: inherit; background: transparent; color: #1a3a4a; outline: none; line-height: 1.5; box-sizing: border-box; display: block;"
                                        />
                                        <div style="display: flex; gap: 6px; justify-content: flex-end; padding: 0 6px 6px;">
                                            <button @click="cancelEdit" style="padding: 4px 12px; border-radius: 99px; border: 1.5px solid #009ac744; background: transparent; color: #009ac7; font-size: 11px; font-weight: 700; cursor: pointer;">Cancelar</button>
                                            <button @click="saveEdit(item)" :disabled="!editContent.trim() || actionLoading" style="padding: 4px 12px; border-radius: 99px; border: none; background: #009ac7; color: white; font-size: 11px; font-weight: 700; cursor: pointer;" :style="{ opacity: (!editContent.trim() || actionLoading) ? 0.6 : 1 }">Guardar</button>
                                        </div>
                                    </template>
                                    <!-- Normal display -->
                                    <template v-else>
                                        <img v-if="item.image_url" :src="clImg(item.image_url, 520, 0, 'limit')" loading="lazy" style="display: block; max-width: 260px; max-height: 320px; border-radius: 14px; width: 100%;" />
                                        <span v-if="item.content" :style="item.image_url ? { display: 'block', padding: '8px 14px 10px' } : {}">{{ item.content }}</span>
                                    </template>
                                </div>

                                <span style="font-size: 10px; color: #b0c0cc; margin-top: 3px; padding: 0 3px;">
                                    {{ formatTime(item.created_at) }}
                                    <span v-if="item.is_edited" style="font-style: italic; opacity: .7;">· editado</span>
                                </span>
                            </div>
                        </template>
                    </div>

                    <!-- "Scroll to bottom" button when reading old messages -->
                    <Transition name="fade">
                        <div v-if="!isNearBottom" style="position: absolute; bottom: 90px; right: 28px; z-index: 10;">
                            <button
                                @click="scrollToBottom(true)"
                                style="width: 38px; height: 38px; border-radius: 50%; border: none; background: #009ac7; color: white; font-size: 16px; cursor: pointer; box-shadow: 0 4px 16px #009ac740; display: flex; align-items: center; justify-content: center; transition: transform .2s;"
                                @mouseenter="$event.currentTarget.style.transform='scale(1.1)'"
                                @mouseleave="$event.currentTarget.style.transform='scale(1)'"
                            >↓</button>
                        </div>
                    </Transition>

                    <!-- Input bar -->
                    <div style="flex-shrink: 0; padding: 14px 20px; background: rgba(255,255,255,0.65); backdrop-filter: blur(20px); border-top: 1px solid #009ac712; display: flex; flex-direction: column; gap: 8px;">
                        <!-- Image preview -->
                        <div v-if="imagePreview" style="display: flex; align-items: flex-start; gap: 8px;">
                            <div style="position: relative; display: inline-block;">
                                <img :src="imagePreview" style="max-height: 80px; max-width: 140px; border-radius: 10px; display: block; border: 1.5px solid #009ac730;" />
                                <button @click="removeImage" style="position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; border-radius: 50%; border: none; background: #e05555; color: white; font-size: 11px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1;">×</button>
                            </div>
                        </div>

                        <!-- Buttons + textarea row -->
                        <div style="display: flex; align-items: flex-end; gap: 10px;">
                            <!-- Image button -->
                            <label :style="{ flexShrink: 0, width: '40px', height: '40px', borderRadius: '50%', border: imagePreview ? '1.5px solid #009ac7' : '1.5px solid #c8d8e066', background: imagePreview ? '#009ac714' : 'transparent', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', color: imagePreview ? '#009ac7' : '#b0c0cc', transition: 'all .2s' }">
                                <input ref="imageInput" type="file" accept="image/*" style="display: none;" @change="onImageChange" />
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </label>

                            <!-- Textarea -->
                            <textarea
                                v-model="msgForm.content"
                                placeholder="Escreve uma mensagem…"
                                rows="1"
                                @keydown="handleKeydown"
                                @input="$event.target.style.height = 'auto'; $event.target.style.height = Math.min($event.target.scrollHeight, 120) + 'px'"
                                style="flex: 1; resize: none; border: 1.5px solid #009ac722; border-radius: 20px; padding: 10px 16px; font-size: 13.5px; font-family: inherit; color: #1a3a4a; background: rgba(255,255,255,0.85); outline: none; line-height: 1.5; max-height: 120px; overflow-y: auto; transition: border-color .2s, box-shadow .2s;"
                                @focus="$event.target.style.borderColor='#009ac7'; $event.target.style.boxShadow='0 0 0 3px #009ac714'"
                                @blur="$event.target.style.borderColor='#009ac722'; $event.target.style.boxShadow='none'"
                            />

                            <!-- Send button with state feedback -->
                            <button
                                @click="send"
                                :disabled="(!msgForm.content.trim() && !msgForm.image) || sendState === 'sending'"
                                :style="{
                                    flexShrink: 0, width: '40px', height: '40px', borderRadius: '50%', border: 'none',
                                    background: sendState === 'error' ? '#e05555' : sendState === 'sent' ? '#2ea87e' : 'linear-gradient(135deg,#009ac7,#4ebcff)',
                                    color: 'white', display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    cursor: 'pointer', boxShadow: '0 3px 12px #009ac740',
                                    transition: 'transform .2s, box-shadow .2s, opacity .2s, background .3s',
                                    opacity: ((!msgForm.content.trim() && !msgForm.image) || sendState === 'sending') ? '0.45' : '1',
                                }"
                                @mouseenter="e => { if (sendState === 'idle') { e.currentTarget.style.transform='scale(1.1)'; e.currentTarget.style.boxShadow='0 6px 20px #009ac750' } }"
                                @mouseleave="e => { e.currentTarget.style.transform='scale(1)'; e.currentTarget.style.boxShadow='0 3px 12px #009ac740' }"
                            >
                                <!-- Sending spinner -->
                                <svg v-if="sendState === 'sending'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round">
                                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" style="animation: spin 1s linear infinite; transform-origin: center;" />
                                </svg>
                                <!-- Sent check -->
                                <svg v-else-if="sendState === 'sent'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <!-- Error x -->
                                <svg v-else-if="sendState === 'error'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                <!-- Default send icon -->
                                <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            </button>
                        </div>

                        <!-- Status hint below input -->
                        <p v-if="sendState === 'error'" style="font-size: 10px; color: #e05555; margin: 0; text-align: center; font-weight: 600;">Erro ao enviar. Tenta novamente.</p>
                    </div>

                </template>
            </main>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
aside > div::-webkit-scrollbar,
div[style*="overflow-y: auto"]::-webkit-scrollbar { width: 4px; }
aside > div::-webkit-scrollbar-track,
div[style*="overflow-y: auto"]::-webkit-scrollbar-track { background: transparent; }
aside > div::-webkit-scrollbar-thumb,
div[style*="overflow-y: auto"]::-webkit-scrollbar-thumb { background: #009ac722; border-radius: 99px; }

.fade-enter-active, .fade-leave-active { transition: opacity .2s }
.fade-enter-from, .fade-leave-to       { opacity: 0 }

@keyframes spin { to { transform: rotate(360deg) } }

.msg-group .msg-actions { opacity: 0; transition: opacity .15s; }
.msg-group:hover .msg-actions { opacity: 1; }
</style>
