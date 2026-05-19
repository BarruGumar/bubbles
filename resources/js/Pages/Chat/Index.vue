<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PostImageLightbox from '@/Components/PostImageLightbox.vue';
import { clImg } from '@/Composables/useCloudinary';
import { useClipboardImage } from '@/Composables/useClipboardImage';
import { compressImage } from '@/Composables/useImageCompressor';
import axios from 'axios';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    activeConversation: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
    hasMoreMessages: { type: Boolean, default: false },
    friends: { type: Array, default: () => [] },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user);

// ── Refs ──────────────────────────────────────────────────────
const messagesEl = ref(null);
const msgContent = ref('');
const msgImage = ref(null);
const imagePreview = ref(null);
const imageInput = ref(null);
const pasteError = ref(null);
const isMobile = ref(false);
const showSidebar = ref(true);
const bgPickerEl = ref(null);

// ── Pagination ────────────────────────────────────────────────
const localMessages = ref([...props.messages]);
const localConversations = ref([...props.conversations]);
const hasMore = ref(props.hasMoreMessages);
const loadingOlder = ref(false);

// ── Send state ────────────────────────────────────────────────
const sendState = ref('idle');
let sentTimer = null;

// ── Read receipts ─────────────────────────────────────────────
const otherLastReadAt = ref(props.activeConversation?.other_last_read_at ?? null);

// ── Reply ─────────────────────────────────────────────────────
const replyingTo = ref(null);

// ── Lightbox ──────────────────────────────────────────────────
const lightboxUrl = ref(null);

// ── Smart scroll ──────────────────────────────────────────────
const isNearBottom = ref(true);
const NEAR_BOTTOM_THRESHOLD = 160;

// ── Polling ───────────────────────────────────────────────────
let pollTimer = null;   // null = stopped, -1 = running (no timer scheduled yet), N = setTimeout ID
let pollFailures = 0;

// ── Typing indicator ──────────────────────────────────────────
const isOtherTyping = ref(false);
let typingExpireTimer = null;  // fail-safe: clears indicator if poll stops arriving
let typingStopTimer = null;    // debounce: sends typing_stop after 2.5s of no input
let lastTypingSentAt = 0;      // timestamp of last typing_start sent (for 3s throttle)
// Scan from the end and return the last confirmed (numeric) ID, skipping temp IDs
// that exist while an optimistic message is in flight.
const lastMsgId = computed(() => {
    for (let i = localMessages.value.length - 1; i >= 0; i--) {
        const id = localMessages.value[i].id;
        if (typeof id === 'number') return id;
    }
    return 0;
});

// ── Chat background ───────────────────────────────────────────
const BG_PRESETS = [
    { id: 'default',  color: '#e8f4f8', label: 'Padrão',  dark: false, pattern: true },
    { id: 'white',    color: '#f5f7f9', label: 'Branco',  dark: false, pattern: false },
    { id: 'warm',     color: '#fef6ec', label: 'Quente',  dark: false, pattern: false },
    { id: 'sage',     color: '#eef5ee', label: 'Verde',   dark: false, pattern: false },
    { id: 'lavender', color: '#f0eef8', label: 'Lavanda', dark: false, pattern: false },
    { id: 'rose',     color: '#fdeef2', label: 'Rosa',    dark: false, pattern: false },
    { id: 'dark',     color: '#1a2634', label: 'Escuro',  dark: true,  pattern: false },
    { id: 'midnight', color: '#0f1923', label: 'Noite',   dark: true,  pattern: false },
];

const chatBgId = ref('default');
const bgPickerOpen = ref(false);
const bgImageUrl = ref(null);
const bgImgInput = ref(null);

function bgKey() {
    return props.activeConversation ? `chat_bg_${props.activeConversation.id}` : null;
}
function bgImgKey() {
    return props.activeConversation ? `chat_bg_img_${props.activeConversation.id}` : null;
}
function loadChatBg() {
    const key = bgKey();
    chatBgId.value = key ? (localStorage.getItem(key) ?? 'default') : 'default';
    const imgKey = bgImgKey();
    bgImageUrl.value = imgKey ? (localStorage.getItem(imgKey) ?? null) : null;
}
function setChatBg(id) {
    chatBgId.value = id;
    const key = bgKey();
    if (key) localStorage.setItem(key, id);
    clearBgImage();
    bgPickerOpen.value = false;
}
function clearBgImage() {
    bgImageUrl.value = null;
    const imgKey = bgImgKey();
    if (imgKey) localStorage.removeItem(imgKey);
}
function compressBgImage(file, maxPx = 1280, quality = 0.75) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            const scale = Math.min(1, maxPx / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width  = Math.round(img.width  * scale);
            canvas.height = Math.round(img.height * scale);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            resolve(canvas.toDataURL('image/jpeg', quality));
        };
        img.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
        img.src = url;
    });
}

async function onBgImageChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    e.target.value = '';
    const dataUrl = await compressBgImage(file);
    if (!dataUrl) return;
    bgImageUrl.value = dataUrl;
    const imgKey = bgImgKey();
    if (imgKey) {
        try { localStorage.setItem(imgKey, dataUrl); }
        catch (_) { /* quota esgotada mesmo após compressão — aplicado apenas nesta sessão */ }
    }
    bgPickerOpen.value = false;
}

const activeBg = computed(() => BG_PRESETS.find(p => p.id === chatBgId.value) ?? BG_PRESETS[0]);
const isDarkBg = computed(() => !bgImageUrl.value && activeBg.value.dark);
const chatBgStyle = computed(() => {
    if (bgImageUrl.value) {
        return {
            backgroundImage: `url("${bgImageUrl.value}")`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            backgroundRepeat: 'no-repeat',
        };
    }
    const p = activeBg.value;
    if (p.pattern) {
        return {
            background: p.color,
            backgroundImage: 'radial-gradient(circle, #009ac712 1px, transparent 1px)',
            backgroundSize: '22px 22px',
        };
    }
    return { background: p.color };
});

// ── Helpers ───────────────────────────────────────────────────
function checkMobile() {
    isMobile.value = window.innerWidth < 640;
    if (!isMobile.value) showSidebar.value = true;
}
function avatarInitial(name) { return (name ?? '?')[0].toUpperCase(); }

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const diff = now - d;
    if (diff < 60_000) return 'agora';
    if (diff < 3_600_000) return Math.floor(diff / 60_000) + 'm';
    if (diff < 86_400_000) return d.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
    return d.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit' });
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return 'Hoje';
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return 'Ontem';
    return d.toLocaleDateString('pt-PT', { weekday: 'long', day: '2-digit', month: 'long' });
}

function autoResize(e) {
    e.target.style.height = 'auto';
    e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
}

// ── Grouped messages with consecutive sender info ─────────────
const groupedMessages = computed(() => {
    const groups = [];
    let lastDate = null;
    let prevSid = null;

    for (let i = 0; i < localMessages.value.length; i++) {
        const msg = localMessages.value[i];
        const next = localMessages.value[i + 1];
        const dateLabel = formatDate(msg.created_at);

        if (dateLabel !== lastDate) {
            groups.push({ type: 'date', label: dateLabel });
            lastDate = dateLabel;
            prevSid = null;
        }

        const sid = msg.is_own ? '__own__' : (msg.author?.id ?? 0);
        const nextDateLabel = next ? formatDate(next.created_at) : null;
        const nextSid = next ? (next.is_own ? '__own__' : (next.author?.id ?? 0)) : null;
        const isLast = nextSid !== sid || nextDateLabel !== dateLabel;

        groups.push({ type: 'msg', ...msg, isFirst: sid !== prevSid, isLast });
        prevSid = sid;
    }
    return groups;
});

// ── Last seen message ─────────────────────────────────────────
const lastSeenMessageId = computed(() => {
    if (!otherLastReadAt.value) return null;
    const readAt = new Date(otherLastReadAt.value);
    let result = null;
    for (const m of localMessages.value) {
        if (m.is_own && new Date(m.created_at) <= readAt) result = m.id;
    }
    return result;
});

// ── Scroll ────────────────────────────────────────────────────
function checkNearBottom() {
    if (!messagesEl.value) return;
    const el = messagesEl.value;
    isNearBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight < NEAR_BOTTOM_THRESHOLD;
}

function scrollToBottom(smooth = true) {
    nextTick(() => {
        if (messagesEl.value) {
            messagesEl.value.scrollTo({ top: messagesEl.value.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
            isNearBottom.value = true;
        }
    });
}

// ── Load older ────────────────────────────────────────────────
async function loadOlderMessages() {
    if (!props.activeConversation || !hasMore.value || loadingOlder.value) return;
    loadingOlder.value = true;
    const oldestId = localMessages.value[0]?.id ?? 0;
    const scrollBefore = messagesEl.value?.scrollHeight ?? 0;
    router.visit(route('conversations.show', props.activeConversation.id) + `?before=${oldestId}`, {
        preserveScroll: true,
        preserveState: true,
        only: ['messages', 'hasMoreMessages'],
        onSuccess: (p) => {
            localMessages.value = [...(p.props.messages ?? []), ...localMessages.value];
            hasMore.value = p.props.hasMoreMessages;
            loadingOlder.value = false;
            nextTick(() => {
                if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight - scrollBefore;
            });
        },
        onError: () => { loadingOlder.value = false; },
    });
}

// ── Typing indicator ──────────────────────────────────────────
function sendTyping(isTyping) {
    if (!props.activeConversation || document.hidden) return;
    axios.post(route('conversations.typing', props.activeConversation.id), { is_typing: isTyping })
        .catch(() => {});
}

function onTypingInput() {
    if (document.hidden || !props.activeConversation) return;
    const now = Date.now();
    if (now - lastTypingSentAt > 3_000) {
        lastTypingSentAt = now;
        sendTyping(true);
    }
    clearTimeout(typingStopTimer);
    typingStopTimer = setTimeout(() => {
        lastTypingSentAt = 0;
        sendTyping(false);
    }, 2_500);
}

function stopTyping() {
    clearTimeout(typingStopTimer);
    typingStopTimer = null;
    if (lastTypingSentAt > 0) {
        lastTypingSentAt = 0;
        sendTyping(false);
    }
}

function clearTypingState() {
    stopTyping();
    clearTimeout(typingExpireTimer);
    typingExpireTimer = null;
    isOtherTyping.value = false;
}

// ── Polling ───────────────────────────────────────────────────
async function poll() {
    if (!props.activeConversation) { pollTimer = null; return; }

    if (!document.hidden) {
        try {
            const res = await axios.get(route('conversations.poll', props.activeConversation.id), {
                params: { after: lastMsgId.value },
            });
            pollFailures = 0;
            const newMsgs = res.data?.messages ?? [];
            if (newMsgs.length) {
                const existingIds = new Set(localMessages.value.map(m => m.id));
                const fresh = newMsgs.filter(m => !existingIds.has(m.id));
                if (fresh.length) {
                    localMessages.value = [...localMessages.value, ...fresh];
                    if (isNearBottom.value) scrollToBottom(true);
                    if (props.activeConversation) {
                        const lastFresh = fresh[fresh.length - 1];
                        const ci = localConversations.value.findIndex(c => c.id === props.activeConversation.id);
                        if (ci !== -1) {
                            const upd = { ...localConversations.value[ci], last_message: { content: lastFresh.content, created_at: lastFresh.created_at, is_own: lastFresh.is_own }, unread_count: 0 };
                            const arr = [...localConversations.value];
                            arr.splice(ci, 1);
                            arr.unshift(upd);
                            localConversations.value = arr;
                        }
                    }
                }
            }
            if (res.data?.other_last_read_at) otherLastReadAt.value = res.data.other_last_read_at;

            // Update typing indicator from poll response
            const typingUsers = res.data?.typing_users ?? [];
            isOtherTyping.value = typingUsers.length > 0;
            clearTimeout(typingExpireTimer);
            if (isOtherTyping.value) {
                typingExpireTimer = setTimeout(() => { isOtherTyping.value = false; }, 8_000);
            }
        } catch {
            pollFailures = Math.min(pollFailures + 1, 5);
        }
    }

    // Reschedule only if polling is still active (not stopped by stopPolling)
    if (pollTimer !== null) {
        const delay = pollFailures === 0 ? 3_000 : Math.min(3_000 * (2 ** pollFailures), 30_000);
        pollTimer = setTimeout(poll, delay);
    }
}

function startPolling() {
    if (pollTimer !== null) return;
    pollFailures = 0;
    pollTimer = -1; // sentinel: polling active, timer not yet set
    poll();
}
function stopPolling() { clearTimeout(pollTimer); pollTimer = null; pollFailures = 0; }

// ── Image ─────────────────────────────────────────────────────
function onImageChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    setImageFile(file);
    e.target.value = '';
}
async function setImageFile(file) {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    // Show preview immediately with original so there's no visible delay
    imagePreview.value = URL.createObjectURL(file);
    msgImage.value = file;
    // Compress in background; preview stays unchanged, only the sent file shrinks
    msgImage.value = await compressImage(file);
}
function removeImage() {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    msgImage.value = null;
    imagePreview.value = null;
}

const { handlePaste: handleMsgPaste } = useClipboardImage({
    onImage: setImageFile,
    maxKB: 5120,
    onError: (msg) => {
        pasteError.value = msg;
        setTimeout(() => { pasteError.value = null; }, 4000);
    },
});

// ── Send ──────────────────────────────────────────────────────
async function send() {
    const hasText = msgContent.value.trim().length > 0;
    const hasImg = !!msgImage.value;
    if ((!hasText && !hasImg) || sendState.value === 'sending' || !props.activeConversation) return;

    stopTyping();
    sendState.value = 'sending';
    clearTimeout(sentTimer);

    // Capture values before clearing (image File must stay alive for FormData)
    const content = msgContent.value.trim();
    const imageFile = msgImage.value;
    const tempPreviewUrl = imagePreview.value;
    const replyTo = replyingTo.value;
    const replyId = replyTo?.id ?? null;

    // Optimistic message — shown immediately, replaced once server confirms
    const tempId = `temp-${Date.now()}`;
    localMessages.value = [
        ...localMessages.value,
        {
            id: tempId,
            content: content || null,
            image_url: tempPreviewUrl ?? null,
            reply_to: replyTo
                ? {
                      id: replyTo.id,
                      content: replyTo.content ?? null,
                      image_url: replyTo.image_url ?? null,
                      author_name: replyTo.author?.name ?? replyTo.author_name ?? '?',
                  }
                : null,
            created_at: new Date().toISOString(),
            is_edited: false,
            is_own: true,
            _sending: true,
            author: {
                id: authUser.value?.id,
                name: authUser.value?.name ?? '?',
                username: authUser.value?.username ?? null,
                avatar: authUser.value?.avatar ?? null,
                avatar_color: authUser.value?.avatar_color ?? '#009ac7',
            },
        },
    ];
    scrollToBottom(true);

    // Clear form immediately so the user can type the next message
    msgContent.value = '';
    msgImage.value = null;
    // Set imagePreview to null to hide preview strip; DON'T revoke ObjectURL yet
    // — the optimistic message still displays it
    imagePreview.value = null;
    replyingTo.value = null;
    await nextTick();
    const ta = document.querySelector('.msg-input');
    if (ta) ta.style.height = 'auto';

    try {
        const fd = new FormData();
        if (content) fd.append('content', content);
        if (imageFile) fd.append('image', imageFile);
        if (replyId) fd.append('reply_to_id', String(replyId));

        const res = await axios.post(route('messages.store', props.activeConversation.id), fd);
        const confirmed = res.data.message;

        // If polling already added this message, just drop the optimistic copy
        if (localMessages.value.some((m) => m.id === confirmed.id)) {
            localMessages.value = localMessages.value.filter((m) => m.id !== tempId);
        } else {
            localMessages.value = localMessages.value.map((m) => (m.id === tempId ? confirmed : m));
        }

        if (tempPreviewUrl) URL.revokeObjectURL(tempPreviewUrl);
        sendState.value = 'sent';
        sentTimer = setTimeout(() => { sendState.value = 'idle'; }, 2000);
        const ci = localConversations.value.findIndex(c => c.id === props.activeConversation?.id);
        if (ci !== -1) {
            const upd = { ...localConversations.value[ci], last_message: { content: confirmed.content, created_at: confirmed.created_at, is_own: true }, unread_count: 0 };
            const arr = [...localConversations.value];
            arr.splice(ci, 1);
            arr.unshift(upd);
            localConversations.value = arr;
        }
    } catch {
        // Rollback: remove optimistic message so the user sees the failure clearly
        localMessages.value = localMessages.value.filter((m) => m.id !== tempId);
        if (tempPreviewUrl) URL.revokeObjectURL(tempPreviewUrl);
        sendState.value = 'error';
        sentTimer = setTimeout(() => { sendState.value = 'idle'; }, 3000);
    }
}

function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
}

// ── Edit & delete ─────────────────────────────────────────────
const editingId = ref(null);
const editContent = ref('');
const confirmDeleteId = ref(null);
const actionLoading = ref(false);

function startEdit(msg) {
    editingId.value = msg.id;
    editContent.value = msg.content ?? '';
    confirmDeleteId.value = null;
    nextTick(() => document.getElementById(`edit-${msg.id}`)?.focus());
}
function cancelEdit() { editingId.value = null; editContent.value = ''; }

async function saveEdit(msg) {
    if (!editContent.value.trim() || actionLoading.value) return;
    actionLoading.value = true;
    try {
        const res = await axios.patch(route('messages.update', msg.id), { content: editContent.value.trim() });
        localMessages.value = localMessages.value.map((m) =>
            m.id === msg.id ? { ...m, content: res.data.content, is_edited: res.data.is_edited } : m,
        );
        cancelEdit();
    } catch {} finally { actionLoading.value = false; }
}

function startDelete(id) { confirmDeleteId.value = id; editingId.value = null; editContent.value = ''; }
function cancelDelete() { confirmDeleteId.value = null; }

async function confirmDelete(id) {
    if (actionLoading.value) return;
    actionLoading.value = true;
    const backup = localMessages.value.find((m) => m.id === id);
    localMessages.value = localMessages.value.filter((m) => m.id !== id);
    confirmDeleteId.value = null;
    try {
        await axios.delete(route('messages.destroy', id));
    } catch {
        if (backup) localMessages.value = [...localMessages.value, backup].sort((a, b) => a.id - b.id);
    } finally { actionLoading.value = false; }
}

// ── Reply ─────────────────────────────────────────────────────
function startReply(msg) {
    replyingTo.value = msg;
    nextTick(() => document.querySelector('.msg-input')?.focus());
}
function cancelReply() { replyingTo.value = null; }

// ── Sidebar ───────────────────────────────────────────────────
const totalUnread = computed(() => localConversations.value.reduce((s, c) => s + (c.unread_count ?? 0), 0));
function startWith(recipientId) { router.post(route('conversations.store'), { recipient_id: recipientId }, { replace: true }); }
function switchConversation(convId) {
    if (isMobile.value) showSidebar.value = false;
    if (convId === props.activeConversation?.id) return;
    const ci = localConversations.value.findIndex(c => c.id === convId);
    if (ci !== -1) localConversations.value[ci] = { ...localConversations.value[ci], unread_count: 0 };
    router.visit(route('conversations.show', convId), {
        preserveState: true,
        preserveScroll: true,
        only: ['messages', 'hasMoreMessages', 'activeConversation'],
    });
}

// ── Lifecycle ─────────────────────────────────────────────────
function onVisibilityChange() {
    if (!document.hidden && props.activeConversation && pollTimer !== null) {
        clearTimeout(pollTimer);
        pollTimer = -1;
        poll();
    }
}
function onDocClick(e) {
    if (bgPickerOpen.value && bgPickerEl.value && !bgPickerEl.value.contains(e.target)) bgPickerOpen.value = false;
}

onMounted(() => {
    checkMobile();
    loadChatBg();
    if (props.activeConversation && isMobile.value) showSidebar.value = false;
    window.addEventListener('resize', checkMobile);
    document.addEventListener('visibilitychange', onVisibilityChange);
    document.addEventListener('click', onDocClick);
    if (messagesEl.value) messagesEl.value.addEventListener('scroll', checkNearBottom);
    scrollToBottom(false);
    if (props.activeConversation) startPolling();
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    document.removeEventListener('click', onDocClick);
    if (messagesEl.value) messagesEl.value.removeEventListener('scroll', checkNearBottom);
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    stopPolling();
    clearTypingState();
    clearTimeout(sentTimer);
});

watch(
    () => props.activeConversation?.id,
    (newId) => {
        stopPolling();
        clearTypingState();
        localMessages.value = [...props.messages];
        hasMore.value = props.hasMoreMessages;
        sendState.value = 'idle';
        replyingTo.value = null;
        otherLastReadAt.value = props.activeConversation?.other_last_read_at ?? null;
        loadChatBg();
        scrollToBottom(false);
        if (newId) startPolling();
    },
    { flush: 'post' },
);

// props.messages watch removed: the activeConversation.id watch already syncs
// localMessages when the conversation changes, and send now uses axios (no Inertia
// prop update on submit). Keeping the watch caused loadOlderMessages to overwrite
// the accumulated message list right after its onSuccess prepended older messages.
watch(() => props.conversations, (newConvs) => { localConversations.value = [...newConvs]; });
</script>

<template>
    <Head title="Mensagens · bubbles" />

    <AuthenticatedLayout>
        <div class="chat-shell">

            <!-- ═══ SIDEBAR ══════════════════════════════════════ -->
            <aside class="chat-sidebar" v-show="!isMobile || showSidebar">
                <div class="sidebar-header">
                    <div>
                        <h2 class="sidebar-title">Mensagens</h2>
                        <p v-if="totalUnread > 0" class="sidebar-unread-hint">
                            {{ totalUnread }} não {{ totalUnread === 1 ? 'lida' : 'lidas' }}
                        </p>
                    </div>
                    <Link :href="route('friends.index')" class="new-conv-btn" title="Nova conversa">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                    </Link>
                </div>

                <div class="sidebar-list">
                    <!-- Friends (no conversations yet) -->
                    <template v-if="localConversations.length === 0 && friends.length > 0">
                        <p class="sidebar-label">Os teus amigos</p>
                        <button v-for="f in friends" :key="f.id" @click="startWith(f.id)" class="sidebar-item">
                            <img v-if="f.avatar" :src="clImg(f.avatar, 80, 80, 'fill', 'face')" class="s-avatar" :style="{ border: `2px solid ${f.avatar_color}` }" loading="lazy" />
                            <div v-else class="s-avatar-init" :style="{ background: f.avatar_color }">{{ avatarInitial(f.name) }}</div>
                            <div class="conv-info">
                                <p class="conv-name">{{ f.name }}</p>
                                <p class="conv-sub" style="color:#009ac7">Iniciar conversa</p>
                            </div>
                        </button>
                    </template>

                    <!-- Empty state -->
                    <div v-else-if="localConversations.length === 0" class="sidebar-empty">
                        <p style="font-size:28px;margin:0 0 10px">💬</p>
                        <p style="font-size:13px;color:#8ba0b0;margin:0">Nenhuma conversa ainda.</p>
                        <p style="font-size:11px;color:#b0c0cc;margin:6px 0 0">
                            Vai aos <Link :href="route('friends.index')" style="color:#009ac7;text-decoration:none;font-weight:700">amigos</Link> e inicia uma conversa.
                        </p>
                    </div>

                    <!-- Conversation list -->
                    <a
                        v-for="conv in localConversations"
                        :key="conv.id"
                        :href="route('conversations.show', conv.id)"
                        @click.prevent="switchConversation(conv.id)"
                        class="sidebar-item conv-item"
                        :class="{ 'is-active': activeConversation?.id === conv.id }"
                    >
                        <div style="position:relative;flex-shrink:0">
                            <img v-if="conv.other_user?.avatar" :src="clImg(conv.other_user.avatar, 88, 88, 'fill', 'face')" class="s-avatar" :style="{ border: `2px solid ${conv.other_user.avatar_color}`, boxShadow: `0 2px 8px ${conv.other_user.avatar_color}33` }" loading="lazy" />
                            <div v-else class="s-avatar-init" :style="{ background: conv.other_user?.avatar_color ?? '#009ac7' }">{{ avatarInitial(conv.other_user?.name) }}</div>
                        </div>
                        <div class="conv-info">
                            <div class="conv-row">
                                <p class="conv-name" :style="{ fontWeight: conv.unread_count > 0 ? '800' : '700' }">{{ conv.other_user?.name ?? '—' }}</p>
                                <span class="conv-time" :style="{ color: conv.unread_count > 0 ? '#009ac7' : 'var(--text-4)', fontWeight: conv.unread_count > 0 ? '700' : '400' }">{{ formatTime(conv.last_message?.created_at) }}</span>
                            </div>
                            <p class="conv-sub" :style="{ color: conv.unread_count > 0 ? '#009ac7' : 'var(--text-3)', fontWeight: conv.unread_count > 0 ? '600' : '400' }">
                                <span v-if="conv.last_message?.is_own" style="color:var(--text-4)">Eu: </span>
                                {{ conv.last_message?.content ?? (conv.last_message ? '📷 Imagem' : 'Sem mensagens.') }}
                            </p>
                        </div>
                        <span v-if="conv.unread_count > 0" class="unread-badge">{{ conv.unread_count }}</span>
                    </a>
                </div>
            </aside>

            <!-- ═══ MAIN ══════════════════════════════════════════ -->
            <main class="chat-main" v-show="!isMobile || !showSidebar">

                <!-- No conversation -->
                <template v-if="!activeConversation">
                    <div style="flex:1;overflow-y:auto;display:flex;flex-direction:column">
                        <div v-if="friends.length > 0" style="max-width:480px;width:100%;margin:0 auto;padding:40px 28px 60px">
                            <div style="text-align:center;margin-bottom:32px">
                                <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#009ac714,#4ebcff0e);border:2px solid #009ac722;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 14px">💬</div>
                                <p style="font-size:16px;font-weight:800;color:#3a6478;margin:0 0 6px">As tuas mensagens</p>
                                <p style="font-size:13px;color:#8ba0b0;margin:0;line-height:1.5">Escolhe um amigo para começar a conversar.</p>
                            </div>
                            <div style="background:rgba(255,255,255,0.8);backdrop-filter:blur(20px);border:1px solid #009ac714;border-radius:20px;box-shadow:0 4px 20px #009ac70a;overflow:hidden">
                                <button v-for="(f, i) in friends" :key="f.id" @click="startWith(f.id)" style="width:100%;display:flex;align-items:center;gap:14px;padding:14px 20px;border:none;background:transparent;cursor:pointer;text-align:left;transition:background 0.15s" :style="{ borderTop: i > 0 ? '1px solid #009ac70c' : 'none' }" @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.05)'" @mouseleave="$event.currentTarget.style.background='transparent'">
                                    <img v-if="f.avatar" :src="clImg(f.avatar, 96, 96, 'fill', 'face')" :style="{ width:'46px',height:'46px',borderRadius:'50%',objectFit:'cover',flexShrink:'0',border:`2.5px solid ${f.avatar_color}`,boxShadow:`0 2px 10px ${f.avatar_color}33` }" />
                                    <div v-else :style="{ width:'46px',height:'46px',borderRadius:'50%',flexShrink:'0',background:f.avatar_color,display:'flex',alignItems:'center',justifyContent:'center',fontSize:'17px',fontWeight:'800',color:'white' }">{{ avatarInitial(f.name) }}</div>
                                    <div style="flex:1;min-width:0">
                                        <p style="font-size:14px;font-weight:700;color:#3a6478;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ f.name }}</p>
                                        <p v-if="f.username" style="font-size:12px;color:#009ac7;margin:2px 0 0">@{{ f.username }}</p>
                                    </div>
                                    <span style="flex-shrink:0;padding:7px 16px;border-radius:99px;background:linear-gradient(135deg,#009ac7,#4ebcff);color:white;font-size:12px;font-weight:700;box-shadow:0 3px 10px #009ac730">Enviar mensagem</span>
                                </button>
                            </div>
                        </div>
                        <div v-else style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:40px">
                            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#009ac714,#4ebcff0e);border:2px solid #009ac722;display:flex;align-items:center;justify-content:center;font-size:36px">💬</div>
                            <p style="font-size:15px;font-weight:700;color:#3a6478;margin:0">As tuas mensagens</p>
                            <p style="font-size:13px;color:#8ba0b0;margin:0;text-align:center;max-width:260px;line-height:1.5">Adiciona amigos para começar a conversar.</p>
                            <Link :href="route('friends.index')" style="margin-top:4px;padding:10px 22px;background:linear-gradient(135deg,#009ac7,#4ebcff);color:white;font-size:13px;font-weight:700;border-radius:99px;text-decoration:none;box-shadow:0 4px 16px #009ac730">Ver amigos</Link>
                        </div>
                    </div>
                </template>

                <!-- Active conversation -->
                <template v-else>
                    <!-- Header -->
                    <div class="chat-header">
                        <button v-if="isMobile" @click="showSidebar = true" class="back-btn">←</button>
                        <img v-if="activeConversation.other_user?.avatar" :src="clImg(activeConversation.other_user.avatar, 80, 80, 'fill', 'face')" class="h-avatar" :style="{ border: `2px solid ${activeConversation.other_user.avatar_color}`, boxShadow: `0 2px 8px ${activeConversation.other_user.avatar_color}44` }" />
                        <div v-else class="h-avatar-init" :style="{ background: activeConversation.other_user?.avatar_color ?? '#009ac7' }">{{ avatarInitial(activeConversation.other_user?.name) }}</div>
                        <div class="header-info">
                            <p class="header-name">{{ activeConversation.other_user?.name }}</p>
                            <p v-if="activeConversation.other_user?.username" class="header-username">@{{ activeConversation.other_user.username }}</p>
                        </div>
                        <!-- Background picker -->
                        <div ref="bgPickerEl" style="position:relative;flex-shrink:0">
                            <button @click.stop="bgPickerOpen = !bgPickerOpen" class="icon-btn" :class="{ 'is-active': bgPickerOpen }" title="Fundo da conversa">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="13.5" cy="6.5" r="1"/><circle cx="17.5" cy="10.5" r="1"/><circle cx="8.5" cy="7.5" r="1"/><circle cx="6.5" cy="12.5" r="1"/>
                                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>
                                </svg>
                            </button>
                            <div v-if="bgPickerOpen" class="bg-picker">
                                <p class="bg-picker-title">Fundo da conversa</p>
                                <div class="bg-grid">
                                    <button v-for="p in BG_PRESETS" :key="p.id" @click="setChatBg(p.id)" class="bg-swatch" :class="{ 'is-selected': chatBgId === p.id && !bgImageUrl }" :style="{ background: p.color, backgroundImage: p.pattern ? 'radial-gradient(circle, #009ac720 1px, transparent 1px)' : 'none', backgroundSize: '10px 10px' }" :title="p.label">
                                        <svg v-if="chatBgId === p.id && !bgImageUrl" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#009ac7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </div>
                                <div class="bg-labels">
                                    <span v-for="p in BG_PRESETS" :key="p.id" class="bg-label" :style="{ color: chatBgId === p.id && !bgImageUrl ? '#009ac7' : '#8ba0b0', fontWeight: chatBgId === p.id && !bgImageUrl ? '700' : '400' }">{{ p.label }}</span>
                                </div>
                                <div class="bg-divider"></div>
                                <p class="bg-picker-subtitle">Imagem personalizada</p>
                                <div class="bg-custom-area">
                                    <button v-if="bgImageUrl" class="bg-custom-preview" @click="clearBgImage" title="Remover imagem">
                                        <img :src="bgImageUrl" class="bg-custom-img" />
                                        <div class="bg-custom-remove">×</div>
                                    </button>
                                    <label class="bg-upload-btn" :class="{ 'has-img': bgImageUrl }">
                                        <input ref="bgImgInput" type="file" accept="image/*" style="display:none" @change="onBgImageChange" />
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        <span>{{ bgImageUrl ? 'Mudar imagem' : 'Carregar imagem' }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <Link v-if="activeConversation.other_user?.username" :href="route('profile.show', activeConversation.other_user.username)" class="btn-outline">Ver perfil</Link>
                    </div>

                    <!-- Messages area -->
                    <div ref="messagesEl" class="messages-area" :class="{ 'chat-dark': isDarkBg }" :style="chatBgStyle">
                        <!-- Load older -->
                        <div v-if="hasMore" style="text-align:center;margin-bottom:12px;flex-shrink:0">
                            <button @click="loadOlderMessages" :disabled="loadingOlder" class="load-older-btn">
                                {{ loadingOlder ? 'A carregar…' : '↑ Carregar mensagens antigas' }}
                            </button>
                        </div>

                        <!-- Empty state -->
                        <div v-if="localMessages.length === 0" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;opacity:0.6">
                            <p style="font-size:22px;margin:0">👋</p>
                            <p style="font-size:13px;color:#8ba0b0;margin:0">Sem mensagens ainda. Diz olá!</p>
                        </div>

                        <!-- Messages -->
                        <template v-for="(item, idx) in groupedMessages" :key="idx">
                            <!-- Date chip -->
                            <div v-if="item.type === 'date'" class="date-chip">
                                <span>{{ item.label }}</span>
                            </div>

                            <!-- Message group -->
                            <div v-else class="msg-group" :class="[item.is_own ? 'msg-own' : 'msg-recv', item.isFirst ? 'is-start' : '']">
                                <!-- Delete confirmation -->
                                <div v-if="confirmDeleteId === item.id" class="delete-confirm">
                                    <span style="font-size:11px;color:#5a7a8a;font-weight:600">Eliminar mensagem?</span>
                                    <button @click="confirmDelete(item.id)" :disabled="actionLoading" class="btn-del-yes">Sim</button>
                                    <button @click="cancelDelete" class="btn-del-no">Não</button>
                                </div>

                                <!-- Bubble + actions row -->
                                <div class="bubble-row" :style="{ flexDirection: item.is_own ? 'row-reverse' : 'row' }">
                                    <!-- Action buttons (beside bubble) — hidden while message is in flight -->
                                    <div v-if="editingId !== item.id && confirmDeleteId !== item.id && !item._sending" class="msg-actions">
                                        <button @click.stop="startReply(item)" class="action-btn" title="Responder">
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                                        </button>
                                        <template v-if="item.is_own">
                                            <button v-if="item.content" @click.stop="startEdit(item)" class="action-btn" title="Editar">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                            <button @click.stop="startDelete(item.id)" class="action-btn action-delete" title="Eliminar">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                            </button>
                                        </template>
                                    </div>

                                    <!-- Bubble -->
                                    <div
                                        class="bubble"
                                        :class="[
                                            editingId === item.id ? 'bubble-editing' : (item.is_own ? 'bubble-own' : 'bubble-recv'),
                                            item.isLast && editingId !== item.id ? 'is-last' : '',
                                            item.image_url && !item.content && !item.reply_to && editingId !== item.id ? 'image-only' : '',
                                            item._sending ? 'bubble-sending' : '',
                                        ]"
                                    >
                                        <!-- Edit mode -->
                                        <template v-if="editingId === item.id">
                                            <textarea
                                                :id="`edit-${item.id}`"
                                                v-model="editContent"
                                                rows="1"
                                                @keydown.enter.exact.prevent="saveEdit(item)"
                                                @keydown.esc="cancelEdit"
                                                @input="autoResize"
                                                style="width:100%;resize:none;border:none;border-radius:14px;padding:10px 14px;font-size:13.5px;font-family:inherit;background:transparent;color:#3a6478;outline:none;line-height:1.5;box-sizing:border-box;display:block"
                                            />
                                            <div class="edit-actions">
                                                <button @click="cancelEdit" class="btn-cancel">Cancelar</button>
                                                <button @click="saveEdit(item)" :disabled="!editContent.trim() || actionLoading" class="btn-save">Guardar</button>
                                            </div>
                                        </template>

                                        <!-- Normal display -->
                                        <template v-else>
                                            <!-- Reply quote -->
                                            <div v-if="item.reply_to" class="reply-quote" :class="item.is_own ? 'reply-own' : 'reply-recv'">
                                                <p class="reply-author">{{ item.reply_to.author_name }}</p>
                                                <p class="reply-text">{{ item.reply_to.content ?? '[imagem]' }}</p>
                                            </div>
                                            <!-- Image -->
                                            <img v-if="item.image_url" :src="clImg(item.image_url, 520, 0, 'limit')" loading="lazy" class="msg-img" style="cursor:zoom-in" @click.stop="lightboxUrl = item.image_url" />
                                            <!-- Text -->
                                            <span v-if="item.content" :class="item.image_url ? 'text-after-img' : ''">{{ item.content }}</span>
                                            <!-- Meta inside bubble (only when has content/reply) -->
                                            <div v-if="!item.image_url || item.content || item.reply_to" class="msg-meta">
                                                <span class="msg-ts">{{ formatTime(item.created_at) }}</span>
                                                <span v-if="item.is_edited" class="msg-ts-edited">editado</span>
                                                <span v-if="item.is_own && item.id === lastSeenMessageId" class="msg-seen">
                                                    <img v-if="activeConversation.other_user?.avatar" :src="clImg(activeConversation.other_user.avatar, 32, 32, 'fill', 'face')" class="seen-avatar" :style="{ border: `1px solid ${activeConversation.other_user.avatar_color}` }" />
                                                    <div v-else class="seen-initial" :style="{ background: activeConversation.other_user?.avatar_color ?? '#009ac7' }">{{ avatarInitial(activeConversation.other_user?.name) }}</div>
                                                </span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Meta below bubble for image-only messages -->
                                <div v-if="item.image_url && !item.content && !item.reply_to && editingId !== item.id" class="msg-meta img-only-meta">
                                    <span class="msg-ts">{{ formatTime(item.created_at) }}</span>
                                    <span v-if="item.is_edited" class="msg-ts-edited">editado</span>
                                    <span v-if="item.is_own && item.id === lastSeenMessageId" class="msg-seen">
                                        <img v-if="activeConversation.other_user?.avatar" :src="clImg(activeConversation.other_user.avatar, 32, 32, 'fill', 'face')" class="seen-avatar" :style="{ border: `1px solid ${activeConversation.other_user.avatar_color}` }" />
                                        <div v-else class="seen-initial" :style="{ background: activeConversation.other_user?.avatar_color ?? '#009ac7' }">{{ avatarInitial(activeConversation.other_user?.name) }}</div>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Scroll to bottom -->
                    <Transition name="fade">
                        <div v-if="!isNearBottom" class="scroll-btn-wrap">
                            <button @click="scrollToBottom(true)" class="scroll-btn">↓</button>
                        </div>
                    </Transition>

                    <!-- Typing indicator -->
                    <Transition name="typing-slide">
                        <div v-if="isOtherTyping" class="typing-bar">
                            <div class="typing-bubble">
                                <div class="typing-dots">
                                    <span></span><span></span><span></span>
                                </div>
                            </div>
                            <span class="typing-label">{{ activeConversation.other_user?.name }} está a digitar…</span>
                        </div>
                    </Transition>

                    <!-- Input bar -->
                    <div class="input-bar">
                        <!-- Reply preview -->
                        <div v-if="replyingTo" class="reply-preview">
                            <div style="flex:1;min-width:0">
                                <p style="font-size:10px;font-weight:700;color:#009ac7;margin:0 0 2px">A responder a {{ replyingTo.author?.name ?? replyingTo.author_name }}</p>
                                <p style="font-size:11px;color:#5a7a8a;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ replyingTo.content ?? '[imagem]' }}</p>
                            </div>
                            <button @click="cancelReply" class="reply-close">×</button>
                        </div>

                        <!-- Image preview -->
                        <div v-if="imagePreview" style="display:flex;align-items:flex-start;gap:8px">
                            <div style="position:relative;display:inline-block">
                                <img :src="imagePreview" style="max-height:80px;max-width:140px;border-radius:10px;display:block;border:1.5px solid #009ac730" />
                                <button @click="removeImage" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;border:none;background:#e05555;color:white;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1">×</button>
                            </div>
                        </div>

                        <!-- Input row -->
                        <div class="input-row">
                            <label class="img-btn" :class="{ 'has-img': imagePreview }">
                                <input ref="imageInput" type="file" accept="image/*" style="display:none" @change="onImageChange" />
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </label>

                            <textarea
                                v-model="msgContent"
                                placeholder="Escreve uma mensagem…"
                                rows="1"
                                class="msg-input"
                                @keydown="handleKeydown"
                                @input="e => { autoResize(e); onTypingInput(); }"
                                @paste="handleMsgPaste"
                            />

                            <button
                                @click="send"
                                :disabled="(!msgContent.trim() && !msgImage) || sendState === 'sending'"
                                class="send-btn"
                                :class="sendState"
                            >
                                <svg v-if="sendState === 'sending'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" style="animation:spin 1s linear infinite;transform-origin:center"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                                <svg v-else-if="sendState === 'sent'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <svg v-else-if="sendState === 'error'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            </button>
                        </div>

                        <p v-if="sendState === 'error'" class="send-error">Erro ao enviar. Tenta novamente.</p>
                        <p v-if="pasteError" class="send-error">{{ pasteError }}</p>
                    </div>
                </template>
            </main>
        </div>
    </AuthenticatedLayout>

    <PostImageLightbox v-if="lightboxUrl" :image-url="lightboxUrl" :open="!!lightboxUrl" @close="lightboxUrl = null" />
</template>

<style scoped>
/* ── Shell ────────────────────────────────────────────────────── */
.chat-shell {
    display: flex;
    height: calc(100vh - 58px);
    overflow: hidden;
}

/* ── Sidebar ──────────────────────────────────────────────────── */
.chat-sidebar {
    width: 300px;
    min-width: 300px;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(0, 154, 199, 0.15);
    background: linear-gradient(180deg, rgba(61, 73, 81, 0.858) 0%, rgba(76, 95, 108, 0.836) 100%);
    backdrop-filter: blur(32px);
    box-shadow: inset -1px 0 0 rgba(0,154,199,0.08);
}
:global(html.dark) .chat-sidebar {
    background: linear-gradient(180deg, rgba(4, 10, 24, 0.97) 0%, rgba(2, 7, 16, 0.98) 100%);
    border-color: rgba(0, 154, 199, 0.18);
    box-shadow: inset -1px 0 0 rgba(0,154,199,0.08), 4px 0 24px rgba(0,0,0,0.4);
}
@media (max-width: 639px) {
    .chat-sidebar { width: 100%; min-width: unset; }
}

.sidebar-header {
    padding: 20px 20px 14px;
    border-bottom: 1px solid #009ac710;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.sidebar-title { font-size: 17px; font-weight: 900; color: var(--text); margin: 0; letter-spacing: -0.02em; }
.sidebar-unread-hint { font-size: 11px; color: #009ac7; margin: 2px 0 0; font-weight: 700; }

.new-conv-btn {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, #009ac7, #4ebcff);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 3px 12px #009ac730; text-decoration: none;
    transition: transform 0.2s, box-shadow 0.2s; flex-shrink: 0;
}
.new-conv-btn:hover { transform: scale(1.08); box-shadow: 0 6px 20px #009ac740; }

.sidebar-list { flex: 1; overflow-y: auto; padding: 8px; }
.sidebar-label { font-size: 10px; font-weight: 800; color: var(--text-4); text-transform: uppercase; letter-spacing: 0.08em; margin: 8px 12px 10px; }
.sidebar-empty { text-align: center; padding: 60px 20px; }

.sidebar-item {
    width: 100%; display: flex; align-items: center; gap: 11px;
    padding: 10px 12px; border-radius: 14px; border: 1px solid transparent;
    background: transparent; cursor: pointer; text-align: left;
    transition: background 0.15s; margin-bottom: 2px;
    text-decoration: none; color: inherit;
}
.sidebar-item:hover { background: rgba(0,154,199,0.08); }
.sidebar-item.is-active {
    background: linear-gradient(135deg, rgba(0,154,199,0.13) 0%, rgba(78,188,255,0.08) 100%);
    border-color: rgba(0,154,199,0.22);
    box-shadow: 0 2px 8px rgba(0,154,199,0.08);
}

.s-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; display: block; flex-shrink: 0; }
.s-avatar-init {
    width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 800; color: white;
}
.sidebar-item:not(.conv-item) .s-avatar,
.sidebar-item:not(.conv-item) .s-avatar-init { width: 38px; height: 38px; font-size: 14px; }

.conv-info { flex: 1; min-width: 0; }
.conv-row { display: flex; align-items: baseline; justify-content: space-between; gap: 6px; }
.conv-name { font-size: 13px; font-weight: 700; color: var(--text); margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.conv-time { font-size: 10px; white-space: nowrap; flex-shrink: 0; }
.conv-sub { font-size: 11px; margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.unread-badge {
    flex-shrink: 0; min-width: 20px; height: 20px; padding: 0 6px;
    background: #009ac7; color: white; border-radius: 99px;
    font-size: 10px; font-weight: 800; display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 8px #009ac740;
}

/* ── Main ─────────────────────────────────────────────────────── */
.chat-main { flex: 1; display: flex; flex-direction: column; min-width: 0; position: relative; }
@media (max-width: 639px) { .chat-main { width: 100%; } }

/* ── Header ───────────────────────────────────────────────────── */
.chat-header {
    height: 64px; flex-shrink: 0; display: flex; align-items: center; gap: 12px;
    padding: 0 16px;
    background: linear-gradient(180deg, rgba(104, 123, 136, 0.75) 0%, rgba(117, 143, 162, 0.8) 100%);
    backdrop-filter: blur(32px);
    border-bottom: 1px solid rgba(0, 154, 199, 0.15); z-index: 10;
}
:global(html.dark) .chat-header {
    background: linear-gradient(180deg, rgba(4, 10, 24, 0.97) 0%, rgba(2, 7, 16, 0.98) 100%);
    border-color: rgba(0, 154, 199, 0.18);
}
.header-info { flex: 1; min-width: 0; }
.header-name { font-size: 14px; font-weight: 800; color: var(--text); margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.header-username { font-size: 11px; color: #009ac7; margin: 1px 0 0; }
.h-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.h-avatar-init {
    width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 800; color: white;
}
.back-btn {
    flex-shrink: 0; width: 34px; height: 34px; border-radius: 50%;
    border: 1.5px solid #009ac733; background: rgba(0,154,199,0.06);
    color: #009ac7; font-size: 16px; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center; transition: background 0.15s;
}
.back-btn:hover { background: rgba(0,154,199,0.14); }
.icon-btn {
    width: 34px; height: 34px; border-radius: 50%; border: 1.5px solid #009ac722;
    background: transparent; color: #8ba0b0; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s; flex-shrink: 0;
}
.icon-btn:hover, .icon-btn.is-active { background: rgba(0,154,199,0.08); color: #009ac7; border-color: #009ac744; }
.btn-outline {
    padding: 7px 14px; border-radius: 99px; border: 1.5px solid #009ac733;
    color: #009ac7; font-size: 12px; font-weight: 700; text-decoration: none;
    background: rgba(0,154,199,0.06); transition: all 0.2s; white-space: nowrap; flex-shrink: 0;
}
.btn-outline:hover { background: rgba(0,154,199,0.14); border-color: #009ac7; }

/* ── Background picker ────────────────────────────────────────── */
.bg-picker {
    position: absolute; right: 0; top: calc(100% + 8px);
    background: rgba(255,255,255,0.97); backdrop-filter: blur(20px);
    border: 1px solid #009ac718; border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12); padding: 14px; width: 224px; z-index: 200;
}
.bg-picker-title { font-size: 10px; font-weight: 700; color: #8ba0b0; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 0.05em; }
.bg-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.bg-labels { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 5px; }
.bg-label { font-size: 9px; text-align: center; }
.bg-swatch {
    width: 100%; aspect-ratio: 1; border-radius: 10px; border: 2.5px solid transparent;
    cursor: pointer; transition: all 0.15s; position: relative;
    display: flex; align-items: center; justify-content: center;
}
.bg-swatch:hover { transform: scale(1.06); }
.bg-swatch.is-selected { border-color: #009ac7; box-shadow: 0 0 0 2px #009ac740; }
.bg-divider { height: 1px; background: #009ac710; margin: 12px 0 10px; }
.bg-picker-subtitle { font-size: 10px; font-weight: 700; color: #8ba0b0; margin: 0 0 8px; text-transform: uppercase; letter-spacing: 0.05em; }
.bg-custom-area { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.bg-upload-btn {
    display: flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 10px; border: 1.5px dashed #009ac744;
    background: rgba(0,154,199,0.04); color: #009ac7;
    font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.bg-upload-btn:hover { background: rgba(0,154,199,0.10); border-color: #009ac7; }
.bg-upload-btn.has-img { border-style: solid; border-color: #009ac755; }
.bg-custom-preview {
    position: relative; width: 46px; height: 46px; border-radius: 10px;
    border: 2.5px solid #009ac7; overflow: hidden; cursor: pointer;
    padding: 0; flex-shrink: 0; box-shadow: 0 0 0 2px #009ac740;
    background: transparent;
}
.bg-custom-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.bg-custom-remove {
    position: absolute; inset: 0; background: rgba(224,85,85,0.80);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 20px; font-weight: 700; opacity: 0; transition: opacity 0.15s;
}
.bg-custom-preview:hover .bg-custom-remove { opacity: 1; }

/* ── Messages area ────────────────────────────────────────────── */
.messages-area {
    flex: 1; overflow-y: auto; padding: 12px 16px 8px;
    display: flex; flex-direction: column; gap: 2px;
}

/* ── Date chip (WhatsApp pill style) ──────────────────────────── */
.date-chip { display: flex; justify-content: center; margin: 14px 0 4px; pointer-events: none; }
.date-chip span {
    background: rgba(255,255,255,0.85); backdrop-filter: blur(8px);
    border-radius: 99px; padding: 4px 14px;
    font-size: 10px; font-weight: 600; color: #5a7a8a;
    box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 0.03em;
}
.chat-dark .date-chip span { background: rgba(0,0,0,0.40); color: #8ba0b0; }

/* ── Load older button ────────────────────────────────────────── */
.load-older-btn {
    padding: 6px 20px; border-radius: 99px; border: 1.5px solid #009ac733;
    background: rgba(255,255,255,0.80); color: #009ac7; font-size: 11px;
    font-weight: 700; cursor: pointer; backdrop-filter: blur(8px); transition: all 0.2s;
}
.load-older-btn:hover { background: rgba(0,154,199,0.08); }

/* ── Message group ────────────────────────────────────────────── */
.msg-group { display: flex; flex-direction: column; margin-top: 2px; }
.msg-group.is-start { margin-top: 10px; }
.msg-own { align-items: flex-end; }
.msg-recv { align-items: flex-start; }

/* ── Bubble row ───────────────────────────────────────────────── */
.bubble-row {
    display: flex; align-items: flex-end; gap: 4px;
    max-width: min(75%, 520px);
}

/* ── Action buttons (beside bubble) ──────────────────────────── */
.msg-actions { display: flex; flex-direction: column; gap: 3px; opacity: 0; transition: opacity 0.15s; flex-shrink: 0; }
.msg-group:hover .msg-actions { opacity: 1; }
@media (max-width: 639px) { .msg-actions { opacity: 1; } }

.action-btn {
    width: 26px; height: 26px; border-radius: 8px; border: none;
    background: rgba(255,255,255,0.92); color: #8ba0b0;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.15s; backdrop-filter: blur(8px); box-shadow: 0 1px 4px rgba(0,0,0,0.12);
}
.action-btn:hover { background: rgba(0,154,199,0.12); color: #009ac7; }
.action-btn.action-delete:hover { background: rgba(224,85,85,0.10); color: #e05555; }
.chat-dark .action-btn { background: rgba(255,255,255,0.12); color: #a0b4c0; }
.chat-dark .action-btn:hover { background: rgba(0,154,199,0.25); color: #4ebcff; }

/* ── Bubble ───────────────────────────────────────────────────── */
.bubble {
    position: relative; max-width: 100%;
    font-size: 13.5px; line-height: 1.5; word-break: break-word;
}
.bubble-own {
    background: #d4f1fa; color: #3a6478;
    border-radius: 18px 18px 5px 18px;
    padding: 8px 12px 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.bubble-recv {
    background: #ffffff; color: #3a6478;
    border-radius: 18px 18px 18px 5px;
    padding: 8px 12px 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.05);
}

/* Tails */
.bubble-own.is-last::after {
    content: ''; position: absolute; bottom: 0; right: -7px;
    border-width: 0 0 10px 8px; border-style: solid;
    border-color: transparent transparent #d4f1fa transparent;
}
.bubble-recv.is-last::after {
    content: ''; position: absolute; bottom: 0; left: -7px;
    border-width: 0 8px 10px 0; border-style: solid;
    border-color: transparent #ffffff transparent transparent;
}

/* Dark mode */
.chat-dark .bubble-own { background: #056778; color: #e4f6fb; box-shadow: 0 1px 3px rgba(0,0,0,0.25); }
.chat-dark .bubble-recv { background: #1e3347; color: #d0e0ec; border-color: rgba(255,255,255,0.06); box-shadow: 0 1px 3px rgba(0,0,0,0.25); }
.chat-dark .bubble-own.is-last::after { border-color: transparent transparent #056778 transparent; }
.chat-dark .bubble-recv.is-last::after { border-color: transparent #1e3347 transparent transparent; }

/* Image-only */
.bubble.image-only {
    background: transparent !important; padding: 0 !important;
    border: none !important; box-shadow: none !important;
    border-radius: 14px; overflow: hidden;
}
.bubble.image-only::after { display: none; }

/* Optimistic (in-flight) */
.bubble-sending { opacity: 0.60; }

/* Editing */
.bubble-editing {
    background: rgba(255,255,255,0.97); border: 1.5px solid #009ac744;
    backdrop-filter: blur(12px); box-shadow: 0 4px 20px #009ac71a;
    padding: 2px; border-radius: 18px; min-width: 220px;
}

/* ── Message content ──────────────────────────────────────────── */
.msg-img { display: block; max-width: 260px; max-height: 320px; border-radius: 14px; width: 100%; }
.text-after-img { display: block; padding: 8px 0 4px; }

/* ── Reply quote ──────────────────────────────────────────────── */
.reply-quote { padding: 6px 10px; border-radius: 10px; margin-bottom: 6px; overflow: hidden; }
.reply-own { border-left: 3px solid rgba(255,255,255,0.5); background: rgba(0,0,0,0.10); }
.reply-recv { border-left: 3px solid #009ac755; background: rgba(0,154,199,0.07); }
.reply-author { font-size: 10px; font-weight: 700; margin: 0 0 2px; }
.reply-own .reply-author { color: rgba(255,255,255,0.85); }
.reply-recv .reply-author { color: #009ac7; }
.reply-text { font-size: 11px; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.reply-own .reply-text { color: rgba(255,255,255,0.65); }
.reply-recv .reply-text { color: #5a7a8a; }

/* ── Meta (timestamp + seen) ──────────────────────────────────── */
.msg-meta { display: flex; align-items: center; gap: 4px; margin-top: 3px; justify-content: flex-end; }
.img-only-meta { padding: 2px 3px 0; }
.msg-ts { font-size: 10px; color: rgba(26,58,74,0.50); white-space: nowrap; }
.chat-dark .msg-ts { color: rgba(255,255,255,0.40); }
.msg-ts-edited { font-size: 9.5px; font-style: italic; color: rgba(26,58,74,0.40); }
.chat-dark .msg-ts-edited { color: rgba(255,255,255,0.30); }
.msg-seen { display: flex; align-items: center; gap: 2px; }
.seen-avatar { width: 13px; height: 13px; border-radius: 50%; object-fit: cover; }
.seen-initial {
    width: 13px; height: 13px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 7px; font-weight: 800; color: white;
}

/* ── Delete confirm ───────────────────────────────────────────── */
.delete-confirm {
    display: flex; align-items: center; gap: 6px; margin-bottom: 4px;
    background: rgba(255,255,255,0.95); border: 1px solid #e0555533;
    border-radius: 10px; padding: 6px 10px; backdrop-filter: blur(8px);
    box-shadow: 0 2px 10px rgba(224,85,85,0.06);
}
.btn-del-yes { padding: 3px 10px; border-radius: 99px; border: none; background: #e05555; color: white; font-size: 11px; font-weight: 700; cursor: pointer; }
.btn-del-yes:disabled { opacity: 0.6; }
.btn-del-no { padding: 3px 10px; border-radius: 99px; border: 1.5px solid #009ac744; background: transparent; color: #009ac7; font-size: 11px; font-weight: 700; cursor: pointer; }

/* ── Scroll to bottom ─────────────────────────────────────────── */
.scroll-btn-wrap { position: absolute; bottom: 90px; right: 20px; z-index: 10; }
.scroll-btn {
    width: 38px; height: 38px; border-radius: 50%; border: none;
    background: #009ac7; color: white; font-size: 16px; cursor: pointer;
    box-shadow: 0 4px 16px #009ac740; display: flex; align-items: center; justify-content: center;
    transition: transform 0.2s;
}
.scroll-btn:hover { transform: scale(1.1); }

/* ── Input bar ────────────────────────────────────────────────── */
.input-bar {
    flex-shrink: 0; padding: 12px 16px;
    background: #ffffff; border-top: 1px solid #009ac71a;
    display: flex; flex-direction: column; gap: 8px;
}
:global(html.dark) .input-bar {
    background: #0d1e30;
    border-color: rgba(0, 154, 199, 0.14);
}
.input-row { display: flex; align-items: flex-end; gap: 8px; }
.reply-preview {
    display: flex; align-items: center; gap: 10px; padding: 8px 12px;
    background: rgba(0,154,199,0.06); border-radius: 12px; border-left: 3px solid #009ac7;
}
.reply-close {
    flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
    border: none; background: rgba(0,0,0,0.07); color: #8ba0b0;
    font-size: 14px; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center; line-height: 1;
}

.img-btn {
    flex-shrink: 0; width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid #c8d8e066; background: transparent;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    color: #b0c0cc; transition: all 0.2s;
}
.img-btn.has-img { border-color: #009ac7; background: #009ac714; color: #009ac7; }
.img-btn:hover { border-color: #009ac755; color: #009ac7; }

.msg-input {
    flex: 1; resize: none; border: 1.5px solid #009ac733;
    border-radius: 22px; padding: 10px 16px; font-size: 13.5px;
    font-family: inherit; color: #3a6478; background: #f4f8fc;
    outline: none; line-height: 1.5; max-height: 120px; overflow-y: auto;
    transition: border-color 0.2s, box-shadow 0.2s;
}
:global(html.dark) .msg-input { color: #cce4f4; background: #0a1828; }
.msg-input::placeholder { color: #8ba0b0; }
:global(html.dark) .msg-input::placeholder { color: #456070; }
.msg-input:focus { border-color: #009ac7; box-shadow: 0 0 0 3px #009ac714; }

.send-btn {
    flex-shrink: 0; width: 44px; height: 44px; border-radius: 50%; border: none;
    background: linear-gradient(135deg, #009ac7, #4ebcff); color: white;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; box-shadow: 0 3px 12px #009ac740;
    transition: transform 0.2s, box-shadow 0.2s, background 0.3s, opacity 0.2s;
}
.send-btn:hover:not(:disabled) { transform: scale(1.1); box-shadow: 0 6px 20px #009ac750; }
.send-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.send-btn.error { background: #e05555; }
.send-btn.sent { background: #2ea87e; }
.send-error { font-size: 10px; color: #e05555; margin: 0; text-align: center; font-weight: 600; }

/* Edit mode buttons */
.edit-actions { display: flex; gap: 6px; justify-content: flex-end; padding: 0 6px 6px; }
.btn-cancel { padding: 4px 12px; border-radius: 99px; border: 1.5px solid #009ac744; background: transparent; color: #009ac7; font-size: 11px; font-weight: 700; cursor: pointer; }
.btn-save { padding: 4px 12px; border-radius: 99px; border: none; background: #009ac7; color: white; font-size: 11px; font-weight: 700; cursor: pointer; }
.btn-save:disabled { opacity: 0.6; }

/* ── Scrollbar ────────────────────────────────────────────────── */
.sidebar-list::-webkit-scrollbar,
.messages-area::-webkit-scrollbar { width: 4px; }
.sidebar-list::-webkit-scrollbar-track,
.messages-area::-webkit-scrollbar-track { background: transparent; }
.sidebar-list::-webkit-scrollbar-thumb,
.messages-area::-webkit-scrollbar-thumb { background: #009ac722; border-radius: 99px; }

/* ── Transitions ──────────────────────────────────────────────── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@keyframes spin { to { transform: rotate(360deg); } }

/* ── Typing indicator ─────────────────────────────────────────── */
.typing-bar {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 16px 2px; flex-shrink: 0;
}
.typing-bubble {
    background: #ffffff; border: 1px solid rgba(0,0,0,0.06);
    border-radius: 18px 18px 18px 5px;
    padding: 8px 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    display: flex; align-items: center;
}
:global(html.dark) .typing-bubble {
    background: #1e2d3d; border-color: rgba(255,255,255,0.06);
}
.typing-dots { display: flex; align-items: center; gap: 4px; }
.typing-dots span {
    width: 7px; height: 7px; border-radius: 50%;
    background: #009ac7; display: block;
    animation: typing-bounce 1.2s infinite ease-in-out;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing-bounce {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
    30% { transform: translateY(-5px); opacity: 1; }
}
.typing-label { font-size: 11px; color: var(--text-3); font-style: italic; }

.typing-slide-enter-active { transition: opacity 0.2s, transform 0.2s; }
.typing-slide-leave-active { transition: opacity 0.15s, transform 0.15s; }
.typing-slide-enter-from { opacity: 0; transform: translateY(4px); }
.typing-slide-leave-to { opacity: 0; transform: translateY(4px); }
</style>
