<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import { clImg } from '@/Composables/useCloudinary'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
    post:         { type: Object, required: true },
    author:       { type: Object, required: true },
    authUser:     { type: Object, default: null },
    canEdit:      { type: Boolean, default: false },
    canDelete:    { type: Boolean, default: false },
    isCreator:    { type: Boolean, default: false },
    likeRoute:    { type: String, required: true },
    commentRoute: { type: String, required: true },
    deleteRoute:  { type: String, required: true },
    editRoute:    { type: String, default: null },
    reportRoute:  { type: String, default: null },
    accentColor:  { type: String, default: '#009ac7' },
    community:    { type: Object, default: null },
})

const { show: toast } = useToast()

// ── Reactions ────────────────────────────────────────────────────
const REACTIONS = [
    { type: 'like',  emoji: '👍', label: 'Curtir' },
    { type: 'love',  emoji: '❤️',  label: 'Adorar' },
    { type: 'laugh', emoji: '😂', label: 'Haha'   },
    { type: 'wow',   emoji: '😮', label: 'Uau'    },
    { type: 'sad',   emoji: '😢', label: 'Triste' },
]

const localUserReaction = ref(props.post.user_reaction ?? null)
const localLikeCount    = ref(props.post.likes_count ?? 0)
const likeLoading       = ref(false)
const localIsLiked      = computed(() => localUserReaction.value !== null)

const showReactionPicker = ref(false)
let hoverTimer = null
let hideTimer  = null

watch(() => props.post.user_reaction, (v) => {
    if (!likeLoading.value) localUserReaction.value = v ?? null
})
watch(() => props.post.likes_count, (v) => {
    if (!likeLoading.value) localLikeCount.value = v ?? 0
})

function currentEmoji() {
    return REACTIONS.find(r => r.type === localUserReaction.value)?.emoji ?? null
}

function onLikeHoverStart() {
    if (!props.authUser) return
    clearTimeout(hideTimer)
    if (!showReactionPicker.value) {
        hoverTimer = setTimeout(() => { showReactionPicker.value = true }, 450)
    }
}

function onLikeHoverEnd() {
    clearTimeout(hoverTimer)
}

function scheduleHide() {
    clearTimeout(hideTimer)
    hideTimer = setTimeout(() => { showReactionPicker.value = false }, 220)
}

function cancelHide() {
    clearTimeout(hideTimer)
}

function setReaction(type) {
    if (!props.authUser || likeLoading.value) return
    showReactionPicker.value = false

    const prevReaction = localUserReaction.value
    const isSame      = prevReaction === type
    const hadReaction  = prevReaction !== null

    // Optimistic update
    if (isSame) {
        localUserReaction.value = null
        localLikeCount.value = Math.max(0, localLikeCount.value - 1)
    } else if (!hadReaction) {
        localUserReaction.value = type
        localLikeCount.value += 1
    } else {
        localUserReaction.value = type
    }

    likeLoading.value = true
    router.post(props.likeRoute, { type }, {
        preserveScroll: true,
        preserveState:  true,
        onFinish: () => { likeLoading.value = false },
        onError:  () => {
            localUserReaction.value = prevReaction
            localLikeCount.value    = props.post.likes_count
        },
    })
}

function toggleLike() {
    if (localUserReaction.value) {
        setReaction(localUserReaction.value)
    } else {
        setReaction('like')
    }
}

// ── Edit state ────────────────────────────────────────────────────
const localContent = ref(props.post.content)
const editMode     = ref(false)
const editContent  = ref('')
const editLoading  = ref(false)

watch(() => props.post.content, (v) => {
    if (!editMode.value) localContent.value = v
})

function startEdit() {
    editContent.value = localContent.value
    editMode.value    = true
}

function cancelEdit() {
    editMode.value = false
}

async function saveEdit() {
    const trimmed = editContent.value.trim()
    if (!trimmed || editLoading.value) return
    if (trimmed === localContent.value) { cancelEdit(); return }
    editLoading.value = true
    try {
        await axios.patch(props.editRoute, { content: trimmed })
        localContent.value = trimmed
        editMode.value     = false
        toast('Post atualizado.', 'success')
    } catch {
        toast('Erro ao atualizar. Tenta novamente.', 'error')
    } finally {
        editLoading.value = false
    }
}

// ── Delete state ──────────────────────────────────────────────────
const confirmDelete = ref(false)

function doDelete() {
    router.delete(props.deleteRoute, { preserveScroll: true })
}

// ── Comments state ────────────────────────────────────────────────
const expandedComments = ref(false)
const commentText      = ref('')

function submitComment() {
    const text = commentText.value.trim()
    if (!text) return
    router.post(props.commentRoute, { content: text }, {
        preserveScroll: true,
        preserveState:  true,
        onSuccess: () => { commentText.value = '' },
    })
}

function deleteComment(commentId) {
    router.delete(route('comments.destroy', commentId), {
        preserveScroll: true,
        preserveState:  true,
    })
}

// ── Report state ──────────────────────────────────────────────────
const showReport    = ref(false)
const reportText    = ref('')
const reportSending = ref(false)

async function submitReport() {
    const text = reportText.value.trim()
    if (!text || reportSending.value) return
    reportSending.value = true
    try {
        await axios.post(props.reportRoute, { reason: text })
        showReport.value  = false
        reportText.value  = ''
        toast('Denúncia enviada.')
    } catch {
        toast('Erro ao enviar denúncia.', 'error')
    } finally {
        reportSending.value = false
    }
}

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase()
}

// ── Lightbox ──────────────────────────────────────────────────────
const lightboxOpen = ref(false)
const zoom         = ref(1)
const panX         = ref(0)
const panY         = ref(0)
const isDragging   = ref(false)
const lightboxEl   = ref(null)

function openLightbox() {
    lightboxOpen.value = true
    zoom.value = 1; panX.value = 0; panY.value = 0
    nextTick(() => lightboxEl.value?.focus())
}

function closeLightbox() {
    lightboxOpen.value = false
    zoom.value = 1; panX.value = 0; panY.value = 0
}

function onWheel(e) {
    const delta = e.deltaY < 0 ? 0.15 : -0.15
    const newZoom = Math.min(5, Math.max(0.5, zoom.value + delta))
    const cx = window.innerWidth / 2
    const cy = window.innerHeight / 2
    const dx = e.clientX - cx
    const dy = e.clientY - cy
    const ratio = newZoom / zoom.value
    panX.value = dx * (1 - ratio) + panX.value * ratio
    panY.value = dy * (1 - ratio) + panY.value * ratio
    zoom.value = newZoom
    if (zoom.value <= 1) { panX.value = 0; panY.value = 0 }
}

function zoomAtPoint(e) {
    const newZoom = zoom.value >= 2 ? 1 : 2.5
    if (newZoom <= 1) { zoom.value = 1; panX.value = 0; panY.value = 0; return }
    const cx = window.innerWidth / 2
    const cy = window.innerHeight / 2
    const ratio = newZoom / zoom.value
    panX.value = (e.clientX - cx) * (1 - ratio) + panX.value * ratio
    panY.value = (e.clientY - cy) * (1 - ratio) + panY.value * ratio
    zoom.value = newZoom
}

function startDrag(e) {
    const startX = e.clientX - panX.value
    const startY = e.clientY - panY.value
    let moved = false
    const onMove = (ev) => {
        if (!moved && (Math.abs(ev.clientX - e.clientX) > 3 || Math.abs(ev.clientY - e.clientY) > 3)) moved = true
        if (!moved) return
        isDragging.value = true
        panX.value = ev.clientX - startX
        panY.value = ev.clientY - startY
    }
    const onUp = () => { isDragging.value = false; document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp) }
    document.addEventListener('mousemove', onMove)
    document.addEventListener('mouseup', onUp)
}

async function downloadImage() {
    try {
        const url  = clImg(props.post.image, 2400, 0, 'limit')
        const resp = await fetch(url)
        const blob = await resp.blob()
        const blobUrl = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = blobUrl; a.download = 'post-image.jpg'
        document.body.appendChild(a); a.click()
        document.body.removeChild(a); URL.revokeObjectURL(blobUrl)
    } catch {
        toast('Erro ao baixar imagem.', 'error')
    }
}

function handleKeydown(e) {
    if (e.key === 'Escape') closeLightbox()
    if (e.key === '+' || e.key === '=') zoom.value = Math.min(5, zoom.value + 0.25)
    if (e.key === '-') { zoom.value = Math.max(0.5, zoom.value - 0.25); if (zoom.value <= 1) { panX.value = 0; panY.value = 0 } }
}

watch(lightboxOpen, (open) => {
    if (open) document.addEventListener('keydown', handleKeydown)
    else      document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <div style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid #4ebcff1a; box-shadow: 0 2px 12px #009ac708; padding: 20px;">

        <!-- Community badge (for feed) -->
        <Link
            v-if="community"
            :href="route('community.show', community.id)"
            style="display: inline-flex; align-items: center; gap: 6px; margin-bottom: 10px; text-decoration: none;"
        >
            <div :style="{ width: '18px', height: '18px', borderRadius: '50%', background: community.color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '8px', fontWeight: '800', color: 'white', flexShrink: '0' }">
                {{ (community.label ?? '?')[0].toUpperCase() }}
            </div>
            <span :style="{ fontSize: '11px', fontWeight: '700', color: community.color }">{{ community.title }}</span>
        </Link>

        <div style="display: flex; gap: 14px; align-items: flex-start;">
            <!-- Avatar -->
            <component
                :is="author.username ? Link : 'div'"
                :href="author.username ? route('profile.show', author.username) : undefined"
                style="flex-shrink: 0; display: block; text-decoration: none;"
            >
                <img
                    v-if="author.avatar"
                    :src="clImg(author.avatar, 80, 80, 'fill', 'face')"
                    loading="lazy"
                    :style="{
                        width: '38px', height: '38px', borderRadius: '50%', objectFit: 'cover', display: 'block',
                        border: isCreator ? `2px solid ${accentColor}` : `2px solid ${author.avatar_color}`,
                        boxShadow: isCreator ? `0 0 0 3px ${accentColor}30, 0 2px 10px ${accentColor}50` : 'none',
                    }"
                />
                <div v-else :style="{
                    width: '38px', height: '38px', borderRadius: '50%', flexShrink: 0,
                    background: author.avatar_color ?? '#009ac7',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: '15px', fontWeight: '800', color: 'white',
                    border: isCreator ? `2px solid ${accentColor}` : 'none',
                }">{{ formatInitial(author.name) }}</div>
            </component>

            <div style="flex: 1; min-width: 0;">
                <!-- Header row -->
                <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 8px; gap: 8px;">
                    <div style="display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px;">
                        <component
                            :is="author.username ? Link : 'span'"
                            :href="author.username ? route('profile.show', author.username) : undefined"
                            :style="{
                                fontSize: '13px', fontWeight: '800', textDecoration: 'none',
                                color: isCreator ? accentColor : '#1a3a4a',
                            }"
                        >
                            <span v-if="isCreator" style="margin-right: 2px;">✦</span>{{ author.name }}
                        </component>
                        <span
                            v-if="isCreator"
                            :style="{
                                display: 'inline-flex', alignItems: 'center',
                                padding: '1px 7px', borderRadius: '99px',
                                border: `1px solid ${accentColor}55`,
                                background: accentColor + '14',
                                color: accentColor,
                                fontSize: '9px', fontWeight: '800',
                                textTransform: 'uppercase', letterSpacing: '.04em',
                            }"
                        >Criador</span>
                        <span v-if="author.username" :style="{ fontSize: '11px', color: isCreator ? accentColor + 'bb' : '#009ac7' }">@{{ author.username }}</span>
                        <span style="font-size: 11px; color: #8ba0b0;">{{ post.created_at }}</span>
                        <span v-if="localContent !== post.content" style="font-size: 10px; color: #b0c0cc; font-style: italic;">(editado)</span>
                    </div>

                    <!-- Actions menu -->
                    <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
                        <button
                            v-if="canEdit && !editMode && !confirmDelete"
                            @click="startEdit"
                            style="background: none; border: none; cursor: pointer; color: #b0c0cc; font-size: 12px; padding: 3px 7px; border-radius: 6px; transition: all .2s; font-weight: 600;"
                            @mouseenter="$event.currentTarget.style.color='#009ac7'; $event.currentTarget.style.background='#f0f8ff'"
                            @mouseleave="$event.currentTarget.style.color='#b0c0cc'; $event.currentTarget.style.background='transparent'"
                            title="Editar post"
                        >✎</button>

                        <button
                            v-if="!canEdit && authUser && reportRoute && !confirmDelete && !showReport"
                            @click="showReport = !showReport"
                            style="background: none; border: none; cursor: pointer; color: #c0c8d0; font-size: 12px; padding: 3px 7px; border-radius: 6px; transition: all .2s;"
                            @mouseenter="$event.currentTarget.style.color='#e05555'; $event.currentTarget.style.background='#fff0f0'"
                            @mouseleave="$event.currentTarget.style.color='#c0c8d0'; $event.currentTarget.style.background='transparent'"
                            title="Denunciar"
                        >⚑</button>

                        <template v-if="canDelete && confirmDelete">
                            <span style="font-size: 11px; color: #e05555; font-weight: 600; white-space: nowrap;">Tens a certeza?</span>
                            <button
                                @click="confirmDelete = false"
                                style="padding: 3px 9px; border-radius: 6px; border: 1px solid #dde8f0; background: #f0f8ff; color: #5a7a8a; font-size: 11px; font-weight: 600; cursor: pointer;"
                            >Não</button>
                            <button
                                @click="doDelete"
                                style="padding: 3px 9px; border-radius: 6px; border: none; background: #e05555; color: white; font-size: 11px; font-weight: 700; cursor: pointer;"
                            >Apagar</button>
                        </template>

                        <button
                            v-else-if="canDelete && !editMode"
                            @click="confirmDelete = true"
                            style="background: none; border: none; cursor: pointer; color: #c0c8d0; font-size: 16px; padding: 2px 4px; border-radius: 6px; line-height: 1; transition: color .2s;"
                            @mouseenter="$event.target.style.color='#e05555'"
                            @mouseleave="$event.target.style.color='#c0c8d0'"
                            title="Apagar post"
                        >×</button>
                    </div>
                </div>

                <!-- Edit mode textarea -->
                <div v-if="editMode" style="margin-bottom: 10px;">
                    <textarea
                        v-model="editContent"
                        maxlength="1000"
                        rows="4"
                        style="width: 100%; background: #f0f8ff; border: 1.5px solid #009ac744; border-radius: 10px; padding: 10px 12px; font-size: 14px; color: #1a3a4a; outline: none; font-family: inherit; resize: vertical; box-sizing: border-box; transition: border-color .2s;"
                        @focus="$event.target.style.borderColor='#009ac7'"
                        @blur="$event.target.style.borderColor='#009ac744'"
                        @keydown.ctrl.enter="saveEdit"
                        @keydown.esc="cancelEdit"
                    />
                    <div style="display: flex; gap: 8px; margin-top: 8px; justify-content: flex-end;">
                        <button
                            @click="cancelEdit"
                            style="padding: 6px 14px; border-radius: 99px; border: 1.5px solid #e0eef8; background: #f0f8ff; color: #8ba0b0; font-size: 12px; font-weight: 600; cursor: pointer;"
                        >Cancelar</button>
                        <button
                            @click="saveEdit"
                            :disabled="editLoading || !editContent.trim()"
                            :style="{ padding: '6px 16px', borderRadius: '99px', border: 'none', background: '#009ac7', color: 'white', fontSize: '12px', fontWeight: '700', cursor: editLoading ? 'not-allowed' : 'pointer', opacity: editLoading ? 0.6 : 1 }"
                        >{{ editLoading ? 'A guardar...' : 'Guardar' }}</button>
                    </div>
                </div>

                <!-- Post content -->
                <p v-else style="font-size: 14px; color: #2a4a5a; line-height: 1.65; margin: 0; white-space: pre-wrap;">{{ localContent }}</p>

                <!-- Image -->
                <img
                    v-if="post.image"
                    :src="clImg(post.image, 800, 0, 'limit')"
                    loading="lazy"
                    style="margin-top: 12px; max-width: 100%; border-radius: 12px; object-fit: cover; max-height: 400px; display: block; border: 1px solid #4ebcff1a; cursor: zoom-in;"
                    @click.stop="openLightbox"
                    title="Clica para ampliar"
                />

                <!-- Video -->
                <video
                    v-if="post.video"
                    :src="post.video"
                    controls
                    preload="metadata"
                    style="margin-top: 12px; max-width: 100%; border-radius: 12px; display: block; border: 1px solid #4ebcff1a; max-height: 480px; background: #000; outline: none;"
                />

                <!-- Report form -->
                <div v-if="showReport" style="margin-top: 12px; background: #fff8f8; border: 1px solid #e0555522; border-radius: 10px; padding: 12px;">
                    <p style="font-size: 12px; font-weight: 700; color: #e05555; margin: 0 0 8px;">Denunciar post</p>
                    <textarea
                        v-model="reportText"
                        placeholder="Descreve o motivo da denúncia..."
                        rows="2"
                        maxlength="500"
                        style="width: 100%; background: white; border: 1px solid #e0555533; border-radius: 8px; padding: 8px 10px; font-size: 12px; color: #1a3a4a; outline: none; font-family: inherit; resize: none; box-sizing: border-box;"
                    />
                    <div style="display: flex; gap: 6px; margin-top: 8px; justify-content: flex-end;">
                        <button @click="showReport = false; reportText = ''" style="padding: 5px 12px; border-radius: 99px; border: 1px solid #e0eef8; background: #f0f8ff; color: #8ba0b0; font-size: 11px; cursor: pointer;">Cancelar</button>
                        <button
                            @click="submitReport"
                            :disabled="!reportText.trim() || reportSending"
                            style="padding: 5px 14px; border-radius: 99px; border: none; background: #e05555; color: white; font-size: 11px; font-weight: 700; cursor: pointer;"
                            :style="{ opacity: !reportText.trim() || reportSending ? 0.5 : 1 }"
                        >{{ reportSending ? 'A enviar...' : 'Enviar' }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reaction bar -->
        <div style="display: flex; margin-top: 12px; border-top: 1px solid rgba(0,154,199,0.08); padding-top: 2px;">

            <!-- Like / Reaction button with picker -->
            <div style="flex: 1; position: relative;" @mouseleave="scheduleHide">

                <!-- Reaction picker popup -->
                <Transition name="picker">
                    <div
                        v-if="showReactionPicker"
                        @mouseenter="cancelHide"
                        @mouseleave="scheduleHide"
                        style="
                            position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%);
                            background: white; border-radius: 99px;
                            border: 1px solid #eef2f8;
                            box-shadow: 0 4px 24px rgba(0,0,0,.13);
                            display: flex; gap: 2px; padding: 5px 8px;
                            z-index: 50;
                        "
                    >
                        <button
                            v-for="r in REACTIONS"
                            :key="r.type"
                            @click.stop="setReaction(r.type)"
                            :title="r.label"
                            :style="{
                                background: localUserReaction === r.type ? '#f0f8ff' : 'none',
                                border: 'none', cursor: 'pointer',
                                padding: '5px 7px', borderRadius: '8px',
                                fontSize: '20px', lineHeight: '1',
                                transition: 'transform .15s',
                            }"
                            @mouseenter="$event.currentTarget.style.transform='scale(1.4)'"
                            @mouseleave="$event.currentTarget.style.transform='scale(1)'"
                        >{{ r.emoji }}</button>
                    </div>
                </Transition>

                <button
                    @click="toggleLike"
                    @mouseenter="onLikeHoverStart"
                    @mouseleave="onLikeHoverEnd"
                    :style="{
                        width: '100%',
                        display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px',
                        padding: '8px 0', background: 'none', border: 'none',
                        cursor: authUser ? 'pointer' : 'default',
                        fontSize: '13px', fontWeight: '600', borderRadius: '8px', transition: 'all .2s',
                        color: localIsLiked ? '#e05f7a' : '#8ba0b0',
                    }"
                    @mouseenter.stop="authUser && ($event.currentTarget.style.background='rgba(224,95,122,0.07)')"
                    @mouseleave.stop="$event.currentTarget.style.background='transparent'"
                >
                    <!-- Show reaction emoji if active, else heart SVG -->
                    <span v-if="localIsLiked && currentEmoji()" style="font-size: 16px; line-height: 1;">{{ currentEmoji() }}</span>
                    <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none">
                        <path
                            :fill="localIsLiked ? '#e05f7a' : 'none'"
                            :stroke="localIsLiked ? '#e05f7a' : 'currentColor'"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                        />
                    </svg>
                    {{ localLikeCount }} {{ localLikeCount === 1 ? 'Reação' : 'Reações' }}
                </button>
            </div>

            <div style="width: 1px; background: rgba(0,154,199,0.08); margin: 6px 0;" />

            <button
                @click="expandedComments = !expandedComments"
                :style="{
                    flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px',
                    padding: '8px 0', background: 'none', border: 'none', cursor: 'pointer',
                    fontSize: '13px', fontWeight: '600', borderRadius: '8px', transition: 'all .2s',
                    color: expandedComments ? accentColor : '#8ba0b0',
                }"
                @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.07)'"
                @mouseleave="$event.currentTarget.style.background='transparent'"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                {{ post.comments.length }} {{ post.comments.length === 1 ? 'Comentário' : 'Comentários' }}
            </button>
        </div>

        <!-- Comments section -->
        <div v-if="expandedComments" style="margin-top: 12px; border-top: 1px solid rgba(0,154,199,0.06); padding-top: 12px;">
            <div v-for="c in post.comments" :key="c.id" style="display: flex; gap: 8px; margin-bottom: 10px; align-items: flex-start;">
                <img
                    v-if="c.author.avatar"
                    :src="c.author.avatar"
                    :style="{ width: '28px', height: '28px', borderRadius: '50%', objectFit: 'cover', border: `1.5px solid ${c.author.avatar_color}`, flexShrink: 0 }"
                />
                <div v-else :style="{
                    width: '28px', height: '28px', borderRadius: '50%', flexShrink: 0,
                    background: c.author.avatar_color ?? '#009ac7',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: '11px', fontWeight: '800', color: 'white',
                }">{{ formatInitial(c.author.name) }}</div>
                <div style="flex: 1; min-width: 0;">
                    <div style="background: rgba(240,248,255,0.8); border-radius: 12px; padding: 8px 12px;">
                        <Link v-if="c.author.username" :href="route('profile.show', c.author.username)" style="font-size: 12px; font-weight: 700; color: #1a3a4a; text-decoration: none;">{{ c.author.name }}</Link>
                        <span v-else style="font-size: 12px; font-weight: 700; color: #1a3a4a;">{{ c.author.name }}</span>
                        <p style="font-size: 13px; color: #2a4a5a; margin: 2px 0 0; line-height: 1.5; white-space: pre-wrap;">{{ c.content }}</p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 3px; padding-left: 10px;">
                        <span style="font-size: 11px; color: #b0c0cc;">{{ c.created_at }}</span>
                        <button
                            v-if="c.is_own"
                            @click="deleteComment(c.id)"
                            style="font-size: 11px; color: #c0c8d0; background: none; border: none; cursor: pointer; padding: 0; transition: color .2s;"
                            @mouseenter="$event.target.style.color='#e05555'"
                            @mouseleave="$event.target.style.color='#c0c8d0'"
                        >Apagar</button>
                    </div>
                </div>
            </div>
            <p v-if="post.comments.length === 0" style="font-size: 12px; color: #b0c0cc; text-align: center; padding: 2px 0 10px; font-style: italic;">Sê o primeiro a comentar!</p>

            <div v-if="authUser" style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
                <img
                    v-if="authUser.avatar"
                    :src="authUser.avatar"
                    :style="{ width: '28px', height: '28px', borderRadius: '50%', objectFit: 'cover', border: `1.5px solid ${authUser.avatar_color ?? '#009ac7'}`, flexShrink: 0 }"
                />
                <div v-else :style="{
                    width: '28px', height: '28px', borderRadius: '50%', flexShrink: 0,
                    background: authUser.avatar_color ?? '#009ac7',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: '11px', fontWeight: '800', color: 'white',
                }">{{ formatInitial(authUser.name) }}</div>
                <div style="flex: 1; display: flex; gap: 6px;">
                    <input
                        v-model="commentText"
                        @keydown.enter.prevent="submitComment"
                        placeholder="Escreve um comentário..."
                        style="flex: 1; min-width: 0; background: rgba(240,248,255,0.8); border: 1.5px solid rgba(0,154,199,0.15); border-radius: 20px; padding: 7px 14px; font-size: 13px; color: #1a3a4a; outline: none; font-family: inherit; transition: border-color .2s;"
                        @focus="$event.target.style.borderColor = accentColor"
                        @blur="$event.target.style.borderColor = 'rgba(0,154,199,0.15)'"
                    />
                    <button
                        @click="submitComment"
                        :style="{
                            padding: '7px 14px', background: accentColor, color: 'white',
                            border: 'none', borderRadius: '20px', fontSize: '12px', fontWeight: '700',
                            cursor: 'pointer', whiteSpace: 'nowrap', flexShrink: 0, transition: 'opacity .2s',
                        }"
                        @mouseenter="$event.target.style.opacity='.8'"
                        @mouseleave="$event.target.style.opacity='1'"
                    >Enviar</button>
                </div>
            </div>
        </div>

    </div>

    <!-- Lightbox -->
    <Teleport to="body">
        <Transition name="lb">
            <div
                v-if="lightboxOpen"
                ref="lightboxEl"
                tabindex="-1"
                style="position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.93); display: flex; align-items: center; justify-content: center; outline: none;"
                @click.self="closeLightbox"
                @wheel.prevent="onWheel"
            >
                <!-- Top toolbar -->
                <div style="position: absolute; top: 0; left: 0; right: 0; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(to bottom, rgba(0,0,0,0.55), transparent); z-index: 1;">
                    <!-- Zoom controls -->
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <button
                            @click.stop="zoom = Math.max(0.5, zoom - 0.25); if (zoom <= 1) { panX = 0; panY = 0 }"
                            style="width: 32px; height: 32px; border-radius: 8px; border: none; background: rgba(255,255,255,0.15); color: white; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s; font-weight: 300; line-height: 1;"
                            @mouseenter="$event.currentTarget.style.background='rgba(255,255,255,0.25)'"
                            @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.15)'"
                        >−</button>
                        <span style="color: rgba(255,255,255,0.75); font-size: 12px; font-weight: 700; min-width: 42px; text-align: center; font-variant-numeric: tabular-nums;">{{ Math.round(zoom * 100) }}%</span>
                        <button
                            @click.stop="zoom = Math.min(5, zoom + 0.25)"
                            style="width: 32px; height: 32px; border-radius: 8px; border: none; background: rgba(255,255,255,0.15); color: white; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s; font-weight: 300; line-height: 1;"
                            @mouseenter="$event.currentTarget.style.background='rgba(255,255,255,0.25)'"
                            @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.15)'"
                        >+</button>
                        <button
                            v-if="zoom !== 1"
                            @click.stop="zoom = 1; panX = 0; panY = 0"
                            style="padding: 0 10px; height: 32px; border-radius: 8px; border: none; background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.7); font-size: 11px; cursor: pointer; font-weight: 600; transition: background .15s;"
                            @mouseenter="$event.currentTarget.style.background='rgba(255,255,255,0.22)'"
                            @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.12)'"
                        >Reset</button>
                    </div>

                    <!-- Download + Close -->
                    <div style="display: flex; gap: 8px;">
                        <button
                            @click.stop="downloadImage"
                            style="width: 36px; height: 36px; border-radius: 10px; border: none; background: rgba(255,255,255,0.15); color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s;"
                            @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.5)'"
                            @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.15)'"
                            title="Baixar imagem"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                        </button>
                        <button
                            @click.stop="closeLightbox"
                            style="width: 36px; height: 36px; border-radius: 10px; border: none; background: rgba(255,255,255,0.15); color: white; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s; line-height: 1;"
                            @mouseenter="$event.currentTarget.style.background='rgba(224,85,85,0.5)'"
                            @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.15)'"
                            title="Fechar (Esc)"
                        >×</button>
                    </div>
                </div>

                <!-- Image -->
                <img
                    :src="clImg(post.image, 2400, 0, 'limit')"
                    draggable="false"
                    :style="{
                        maxWidth: '90vw',
                        maxHeight: '90vh',
                        objectFit: 'contain',
                        borderRadius: zoom > 1 ? '4px' : '12px',
                        transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
                        transition: isDragging ? 'none' : 'transform .2s cubic-bezier(.2,.8,.2,1)',
                        cursor: isDragging ? 'grabbing' : 'grab',
                        userSelect: 'none',
                        boxShadow: '0 8px 60px rgba(0,0,0,0.6)',
                    }"
                    @dblclick.stop="zoomAtPoint"
                    @mousedown.stop="startDrag"
                />

                <!-- Hint bottom -->
                <p style="position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%); color: rgba(255,255,255,0.35); font-size: 11px; white-space: nowrap; pointer-events: none;">Duplo clique para zoom · Scroll para zoom · Arrastar para mover · Esc para fechar</p>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.picker-enter-active { transition: all .18s cubic-bezier(.2,.8,.2,1) }
.picker-leave-active { transition: all .14s ease }
.picker-enter-from   { opacity: 0; transform: translateX(-50%) translateY(6px) scale(.92) }
.picker-leave-to     { opacity: 0; transform: translateX(-50%) translateY(4px) scale(.95) }

.lb-enter-active { transition: opacity .22s ease }
.lb-leave-active { transition: opacity .18s ease }
.lb-enter-from   { opacity: 0 }
.lb-leave-to     { opacity: 0 }
</style>
