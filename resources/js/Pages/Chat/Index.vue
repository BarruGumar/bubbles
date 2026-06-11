<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PostImageLightbox from '@/Components/PostImageLightbox.vue';
import { clImg } from '@/Composables/useCloudinary';
import { useClipboardImage } from '@/Composables/useClipboardImage';
import { compressImage } from '@/Composables/useImageCompressor';
import { useAudio } from '@/Composables/useAudio';
import { useOnlineUsers } from '@/Composables/useOnlineUsers';
import axios from 'axios';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    activeConversation: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
    hasMoreMessages: { type: Boolean, default: false },
    friends: { type: Array, default: () => [] },
    myBg: { type: Object, default: () => ({ preset: null, image_url: null }) },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const { playSfx, playClick } = useAudio();

const { onlineUsers } = useOnlineUsers();
const isOnline = (userId) => userId != null && onlineUsers.value.has(userId);

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

// ── Background image pre-upload ───────────────────────────────
// Starts uploading to Cloudinary as soon as the user picks an image,
// so by the time they hit send the URL is already ready.
let pendingUpload = null; // { promise: Promise<{url}|null>, controller: AbortController }

// ── Read receipts ─────────────────────────────────────────────
const otherLastReadAt = ref(props.activeConversation?.other_last_read_at ?? null);

// ── Conversation type helpers ─────────────────────────────────
const isGroup  = computed(() => props.activeConversation?.type === 'group');
const isDirect = computed(() => !isGroup.value);

// ── Typing label ──────────────────────────────────────────────
const typingLabel = computed(() => {
    if (!isGroup.value) return `${props.activeConversation?.other_user?.name ?? ''} está a digitar…`;
    const names = typingUsers.value.map(u => u.name);
    if (names.length === 1) return `${names[0]} está a digitar…`;
    if (names.length === 2) return `${names[0]} e ${names[1]} estão a digitar…`;
    return `${names[0]} e mais ${names.length - 1} estão a digitar…`;
});

// ── Group read receipts ───────────────────────────────────────
const groupReadAt = ref({});  // { userId: isoString }

const groupSeenPerMessage = computed(() => {
    if (!isGroup.value) return {};
    const result = {};
    const participants = (props.activeConversation?.participants ?? []).filter(p => p.id !== authUser.value?.id);
    for (const p of participants) {
        const readAt = groupReadAt.value[p.id];
        if (!readAt) continue;
        const readDate = new Date(readAt);
        let lastSeenId = null;
        for (const m of localMessages.value) {
            if (m.is_own && !m._sending && new Date(m.created_at) <= readDate) lastSeenId = m.id;
        }
        if (lastSeenId !== null) {
            if (!result[lastSeenId]) result[lastSeenId] = [];
            result[lastSeenId].push(p);
        }
    }
    return result;
});

// ── Group creation modal ──────────────────────────────────────
const showGroupModal    = ref(false);
const groupModalName    = ref('');
const groupModalSearch  = ref('');
const groupModalFriends = ref([]);
const groupModalSelected = ref([]);
const groupModalLoading = ref(false);
const groupModalError   = ref(null);
const groupModalSubmitting = ref(false);
const groupModalImage   = ref(null);
const groupModalImagePreview = ref(null);
const groupImageInputRef = ref(null);

async function handleGroupImageChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const compressed = await compressImage(file, { maxWidth: 400, maxHeight: 400, quality: 0.88 });
    if (groupModalImagePreview.value) URL.revokeObjectURL(groupModalImagePreview.value);
    groupModalImage.value = compressed;
    groupModalImagePreview.value = URL.createObjectURL(compressed);
}

async function openGroupModal() {
    showGroupModal.value    = true;
    groupModalName.value    = '';
    groupModalSearch.value  = '';
    groupModalSelected.value = [];
    groupModalError.value   = null;
    groupModalLoading.value = true;
    if (groupModalImagePreview.value) URL.revokeObjectURL(groupModalImagePreview.value);
    groupModalImage.value = null;
    groupModalImagePreview.value = null;
    try {
        const res = await axios.get(route('groups.friends'));
        groupModalFriends.value = res.data.friends ?? [];
    } catch {
        groupModalError.value = 'Erro ao carregar amigos.';
    } finally {
        groupModalLoading.value = false;
    }
}

const groupModalFiltered = computed(() => {
    const q = groupModalSearch.value.trim().toLowerCase();
    if (!q) return groupModalFriends.value;
    return groupModalFriends.value.filter(
        f => f.name.toLowerCase().includes(q) || f.username?.toLowerCase().includes(q)
    );
});

function toggleGroupMember(user) {
    const idx = groupModalSelected.value.findIndex(u => u.id === user.id);
    if (idx === -1) groupModalSelected.value.push(user);
    else groupModalSelected.value.splice(idx, 1);
}

function isGroupMemberSelected(user) {
    return groupModalSelected.value.some(u => u.id === user.id);
}

async function submitCreateGroup() {
    if (!groupModalName.value.trim() || groupModalSelected.value.length < 2 || groupModalSubmitting.value) return;
    groupModalSubmitting.value = true;
    const fd = new FormData();
    fd.append('name', groupModalName.value.trim());
    groupModalSelected.value.forEach(u => fd.append('participant_ids[]', u.id));
    if (groupModalImage.value) fd.append('image', groupModalImage.value, 'group-avatar.jpg');
    router.post(route('groups.store'), fd, {
        onFinish: () => { groupModalSubmitting.value = false; },
        onSuccess: () => { showGroupModal.value = false; },
    });
}

// ── Group details panel ───────────────────────────────────────
const showGroupDetails    = ref(false);
const groupDetailsParticipants = computed(() => props.activeConversation?.participants ?? []);
const groupUserRole       = computed(() => props.activeConversation?.user_role ?? 'member');
const groupActionLoading  = ref(false);

// Edit group info (avatar + name)
const groupEditImageFile    = ref(null);
const groupEditImagePreview = ref(null);
const groupEditImageInputRef = ref(null);
const groupEditName         = ref('');
const groupEditSaving       = ref(false);
const canEditGroup          = computed(() => ['owner', 'admin'].includes(groupUserRole.value));

// Add member to group
const showAddMember    = ref(false);
const addMemberSearch  = ref('');
const addMemberFriends = ref([]);
const addMemberLoading = ref(false);

const currentMemberIds = computed(() => new Set(groupDetailsParticipants.value.map(p => p.id)));

const addMemberFiltered = computed(() => {
    const q = addMemberSearch.value.trim().toLowerCase();
    return addMemberFriends.value.filter(f => {
        if (currentMemberIds.value.has(f.id)) return false;
        if (!q) return true;
        return f.name.toLowerCase().includes(q) || f.username?.toLowerCase().includes(q);
    });
});

async function openAddMember() {
    showAddMember.value = !showAddMember.value;
    if (!showAddMember.value) return;
    addMemberSearch.value = '';
    addMemberLoading.value = true;
    try {
        const res = await axios.get(route('groups.friends'));
        addMemberFriends.value = res.data.friends ?? [];
    } catch {} finally { addMemberLoading.value = false; }
}

async function addMemberToGroup(userId) {
    if (groupActionLoading.value || !props.activeConversation) return;
    groupActionLoading.value = true;
    try {
        await axios.post(route('groups.members.add', props.activeConversation.id), { user_id: userId });
        router.reload({ only: ['activeConversation'] });
    } catch (e) {
        alert(e?.response?.data?.message ?? 'Erro ao adicionar membro.');
    } finally { groupActionLoading.value = false; }
}

watch(showGroupDetails, (open) => {
    if (open) {
        groupEditName.value = props.activeConversation?.group_name ?? '';
        showAddMember.value = false;
        addMemberFriends.value = [];
    }
    if (groupEditImagePreview.value) URL.revokeObjectURL(groupEditImagePreview.value);
    groupEditImageFile.value = null;
    groupEditImagePreview.value = null;
});

async function handleGroupEditImageChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const compressed = await compressImage(file, { maxWidth: 400, maxHeight: 400, quality: 0.88 });
    if (groupEditImagePreview.value) URL.revokeObjectURL(groupEditImagePreview.value);
    groupEditImageFile.value = compressed;
    groupEditImagePreview.value = URL.createObjectURL(compressed);
}

async function saveGroupInfo() {
    if (groupEditSaving.value || !props.activeConversation) return;
    const nameTrimmed = groupEditName.value.trim();
    if (!nameTrimmed && !groupEditImageFile.value) return;
    groupEditSaving.value = true;
    try {
        const fd = new FormData();
        if (nameTrimmed) fd.append('name', nameTrimmed);
        if (groupEditImageFile.value) fd.append('image', groupEditImageFile.value, 'group-avatar.jpg');
        fd.append('_method', 'PATCH');
        await axios.post(route('groups.update', props.activeConversation.id), fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        router.reload({ only: ['activeConversation', 'conversations'] });
        if (groupEditImagePreview.value) URL.revokeObjectURL(groupEditImagePreview.value);
        groupEditImageFile.value = null;
        groupEditImagePreview.value = null;
    } catch (e) {
        alert(e?.response?.data?.message ?? 'Erro ao guardar.');
    } finally { groupEditSaving.value = false; }
}

async function runGroupAction(fn) {
    if (groupActionLoading.value || !props.activeConversation) return;
    groupActionLoading.value = true;
    try { await fn(); router.reload({ only: ['activeConversation'] }); }
    catch {} finally { groupActionLoading.value = false; }
}

function removeMember(userId) {
    runGroupAction(() => axios.delete(route('groups.members.remove', { conversation: props.activeConversation.id, user: userId })));
}
function promoteToAdmin(userId) {
    runGroupAction(() => axios.patch(route('groups.members.promote', props.activeConversation.id), { user_id: userId }));
}
function demoteToMember(userId) {
    runGroupAction(() => axios.patch(route('groups.members.demote', props.activeConversation.id), { user_id: userId }));
}

async function leaveGroup() {
    if (groupActionLoading.value || !props.activeConversation) return;
    groupActionLoading.value = true;
    playSfx('leave');
    try {
        await axios.delete(route('groups.leave', props.activeConversation.id));
        router.visit(route('conversations.index'));
    } catch (e) {
        const msg = e?.response?.data?.message ?? 'Erro ao sair do grupo.';
        alert(msg);
    } finally { groupActionLoading.value = false; }
}

// ── Reply ─────────────────────────────────────────────────────
const replyingTo = ref(null);

// ── Lightbox ──────────────────────────────────────────────────
const lightboxUrl = ref(null);

// ── Smart scroll ──────────────────────────────────────────────
const isNearBottom = ref(true);
const NEAR_BOTTOM_THRESHOLD = 160;
const unreadBelowCount = ref(0);
const messagesReady = ref(false);
let pendingImageCount = 0;
let messagesReadyTimer = null;

// ── Typing indicator ──────────────────────────────────────────
const typingUsers = ref([]);   // [{id, name}] — supports multiple typers in groups
const typingExpireTimers = {}; // per-user fail-safe timers
let typingStopTimer = null;
let lastTypingSentAt = 0;

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
    if (props.myBg?.image_url) {
        bgImageUrl.value = props.myBg.image_url;
        chatBgId.value = 'default';
        return;
    }
    if (props.myBg?.preset) {
        chatBgId.value = props.myBg.preset;
        bgImageUrl.value = null;
        return;
    }
    // Fallback: localStorage (retrocompatibilidade)
    const key = bgKey();
    chatBgId.value = key ? (localStorage.getItem(key) ?? 'default') : 'default';
    const imgKey = bgImgKey();
    bgImageUrl.value = imgKey ? (localStorage.getItem(imgKey) ?? null) : null;
}
async function setChatBg(id) {
    chatBgId.value = id;
    bgImageUrl.value = null;
    bgPickerOpen.value = false;
    if (!props.activeConversation) return;
    try {
        await axios.post(route('conversations.background', props.activeConversation.id), { preset: id });
    } catch (_) {}
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
    const localUrl = URL.createObjectURL(file);
    bgImageUrl.value = localUrl;
    bgPickerOpen.value = false;
    if (!props.activeConversation) return;
    const fd = new FormData();
    fd.append('image', file);
    try {
        const res = await axios.post(route('conversations.background', props.activeConversation.id), fd);
        URL.revokeObjectURL(localUrl);
        bgImageUrl.value = res.data.bg_image_url;
    } catch (_) {
        // Preview local mantém-se se o upload falhar
    }
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
    if (isNearBottom.value) unreadBelowCount.value = 0;
}

function scrollToBottom() {
    unreadBelowCount.value = 0;
    nextTick(() => {
        requestAnimationFrame(() => {
            if (messagesEl.value) {
                messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
                isNearBottom.value = true;
                // Count lazy images that haven't finished loading yet.
                // Keep content hidden until they settle so the scroll position is stable.
                const pending = [...messagesEl.value.querySelectorAll('img.msg-img')]
                    .filter(img => !img.complete);
                pendingImageCount = pending.length;
                if (pendingImageCount === 0) {
                    messagesReady.value = true;
                } else {
                    // Fallback: reveal after 1s even if images are still loading
                    clearTimeout(messagesReadyTimer);
                    messagesReadyTimer = setTimeout(() => { messagesReady.value = true; }, 1000);
                }
            } else {
                messagesReady.value = true;
            }
        });
    });
}

// Called on image load OR error — corrects scroll position and tracks pending count.
// Synchronous so the correction happens before the browser paints.
function onMsgImageSettle() {
    if (isNearBottom.value && messagesEl.value) {
        messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }
    if (!messagesReady.value && pendingImageCount > 0) {
        pendingImageCount--;
        if (pendingImageCount === 0) {
            clearTimeout(messagesReadyTimer);
            messagesReady.value = true;
        }
    }
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
    Object.values(typingExpireTimers).forEach(t => clearTimeout(t));
    Object.keys(typingExpireTimers).forEach(k => delete typingExpireTimers[k]);
    typingUsers.value = [];
}

// ── Polling ───────────────────────────────────────────────────
function bumpConversation(convId, lastMessage) {
    const ci = localConversations.value.findIndex(c => c.id === convId);
    if (ci === -1) return;
    const updated = { ...localConversations.value[ci], last_message: lastMessage, unread_count: 0 };
    const arr = [...localConversations.value];
    arr.splice(ci, 1);
    arr.unshift(updated);
    localConversations.value = arr;
}

function subscribeToConversation(id) {
    if (!id) return;
    window.Echo.private(`conversation.${id}`)
        .listen('.MessageSent', (e) => {
            if (localMessages.value.some(m => m.id === e.message.id)) return;
            const msg = { ...e.message, is_own: e.message.author.id === authUser.value?.id };
            localMessages.value = [...localMessages.value, msg];
            bumpConversation(e.conversation_id, {
                content: msg.content,
                created_at: msg.created_at,
                is_own: msg.is_own,
            });
            if (!msg.is_own) {
                playSfx('message');
                markAsRead();
                if (isNearBottom.value) {
                    scrollToBottom();
                } else {
                    unreadBelowCount.value += 1;
                }
            } else {
                scrollToBottom();
            }
        })
        .listen('.TypingUpdated', (e) => {
            if (e.is_typing) {
                if (!typingUsers.value.some(u => u.id === e.user_id)) {
                    typingUsers.value = [...typingUsers.value, { id: e.user_id, name: e.user_name }];
                }
                clearTimeout(typingExpireTimers[e.user_id]);
                typingExpireTimers[e.user_id] = setTimeout(() => {
                    typingUsers.value = typingUsers.value.filter(u => u.id !== e.user_id);
                    delete typingExpireTimers[e.user_id];
                }, 8_000);
            } else {
                clearTimeout(typingExpireTimers[e.user_id]);
                delete typingExpireTimers[e.user_id];
                typingUsers.value = typingUsers.value.filter(u => u.id !== e.user_id);
            }
        })
        .listen('.MessageRead', (e) => {
            if (e.read_by_user_id !== authUser.value?.id) {
                if (isGroup.value) {
                    groupReadAt.value = { ...groupReadAt.value, [e.read_by_user_id]: e.read_at };
                } else {
                    otherLastReadAt.value = e.read_at;
                }
            }
        })
        .listen('.MessageUpdated', (e) => {
            localMessages.value = localMessages.value.map((m) =>
                m.id === e.message_id
                    ? { ...m, content: e.content, is_edited: e.is_edited }
                    : m
            );
        })
        .listen('.MessageDeleted', (e) => {
            localMessages.value = localMessages.value.filter((m) => m.id !== e.message_id);
        });
}

async function markAsRead() {
    if (!props.activeConversation) return;
    try {
        await axios.post(route('conversations.read', props.activeConversation.id));
    } catch {}
}

// ── Image ─────────────────────────────────────────────────────
function onImageChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    setImageFile(file);
    e.target.value = '';
}
async function setImageFile(file) {
    if (file.size > 5 * 1024 * 1024) {
        pasteError.value = `Ficheiro demasiado grande (${(file.size / 1024 / 1024).toFixed(1)} MB). Máximo 5 MB.`;
        setTimeout(() => { pasteError.value = null; }, 5000);
        return;
    }
    if (pendingUpload) { pendingUpload.controller.abort(); pendingUpload = null; }
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = URL.createObjectURL(file);
    msgImage.value = file;
    const uploadFile = file.type === 'image/gif' ? file : await compressImage(file);
    msgImage.value = uploadFile;
    // Start Cloudinary upload in background — result used in send() to skip re-upload
    const controller = new AbortController();
    const fd = new FormData();
    fd.append('image', uploadFile);
    const promise = axios.post(route('conversations.upload-image'), fd, { signal: controller.signal })
        .then(r => r.data)
        .catch(() => null);
    pendingUpload = { promise, controller };
}
function removeImage() {
    if (pendingUpload) { pendingUpload.controller.abort(); pendingUpload = null; }
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
    // Capture and clear the background upload so removeImage() can't cancel it mid-send
    const imgUpload = pendingUpload;
    pendingUpload = null;

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
    scrollToBottom();

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
        if (replyId) fd.append('reply_to_id', String(replyId));
        if (imageFile) {
            const uploaded = imgUpload ? await imgUpload.promise : null;
            if (uploaded?.url) {
                fd.append('image_url', uploaded.url); // pre-uploaded: no file transfer needed
            } else {
                fd.append('image', imageFile); // fallback: upload with the message
            }
        }

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
        if (props.activeConversation) {
            bumpConversation(props.activeConversation.id, {
                content: confirmed.content,
                created_at: confirmed.created_at,
                is_own: true,
            });
        }
    } catch (err) {
        // Rollback optimistic message and restore input so user can retry without retyping
        localMessages.value = localMessages.value.filter((m) => m.id !== tempId);
        msgContent.value = content;
        if (imageFile) {
            msgImage.value = imageFile;
            imagePreview.value = tempPreviewUrl; // reuse ObjectURL — not revoked yet
        } else if (tempPreviewUrl) {
            URL.revokeObjectURL(tempPreviewUrl);
        }
        const status = err?.response?.status;
        if (status === 422 && err?.response?.data?.errors?.image) {
            pasteError.value = 'Imagem inválida ou demasiado grande (máx. 5 MB).';
            setTimeout(() => { pasteError.value = null; }, 5000);
            sendState.value = 'idle';
        } else if (status === 413) {
            pasteError.value = 'Ficheiro demasiado grande para o servidor.';
            setTimeout(() => { pasteError.value = null; }, 5000);
            sendState.value = 'idle';
        } else {
            sendState.value = 'error';
        }
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
    playSfx('conversation');
    const ci = localConversations.value.findIndex(c => c.id === convId);
    if (ci !== -1) {
        const unread = localConversations.value[ci].unread_count ?? 0;
        localConversations.value[ci] = { ...localConversations.value[ci], unread_count: 0 };
        if (unread > 0) {
            window.dispatchEvent(new CustomEvent('messages-read', { detail: { delta: unread } }));
        }
    }
    router.visit(route('conversations.show', convId), {
        preserveState: true,
        preserveScroll: true,
        only: ['messages', 'hasMoreMessages', 'activeConversation'],
    });
}

// ── Lifecycle ─────────────────────────────────────────────────
function onDocClick(e) {
    if (bgPickerOpen.value && bgPickerEl.value && !bgPickerEl.value.contains(e.target)) bgPickerOpen.value = false;
}

onMounted(() => {
    checkMobile();
    loadChatBg();
    if (props.activeConversation && isMobile.value) showSidebar.value = false;
    window.addEventListener('resize', checkMobile);
    document.addEventListener('click', onDocClick);
    if (messagesEl.value) messagesEl.value.addEventListener('scroll', checkNearBottom);
    scrollToBottom();
    if (props.activeConversation) {
        subscribeToConversation(props.activeConversation.id);
        const conv = localConversations.value.find(c => c.id === props.activeConversation.id);
        if (conv?.unread_count > 0) {
            window.dispatchEvent(new CustomEvent('messages-read', { detail: { delta: conv.unread_count } }));
        }
        markAsRead();
        if (props.activeConversation.type === 'group' && props.activeConversation.participants) {
            const map = {};
            for (const p of props.activeConversation.participants) {
                if (p.id !== authUser.value?.id && p.last_read_at) map[p.id] = p.last_read_at;
            }
            groupReadAt.value = map;
        }
    }
    if (authUser.value) {
        window.Echo.private(`user.${authUser.value.id}`)
            .listen('.ConversationCreated', (e) => {
                if (!localConversations.value.some(c => c.id === e.conversation.id)) {
                    localConversations.value = [e.conversation, ...localConversations.value];
                }
            });
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    document.removeEventListener('click', onDocClick);
    if (messagesEl.value) messagesEl.value.removeEventListener('scroll', checkNearBottom);
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    if (props.activeConversation?.id) window.Echo.leave(`conversation.${props.activeConversation.id}`);
    clearTypingState();
    clearTimeout(sentTimer);
    if (pendingUpload) { pendingUpload.controller.abort(); pendingUpload = null; }
    if (authUser.value) {
        window.Echo.private(`user.${authUser.value.id}`).stopListening('.ConversationCreated');
    }
});

watch(
    () => props.activeConversation?.id,
    (newId, oldId) => {
        if (oldId) window.Echo.leave(`conversation.${oldId}`);
        clearTypingState();
        localMessages.value = [...props.messages];
        hasMore.value = props.hasMoreMessages;
        sendState.value = 'idle';
        replyingTo.value = null;
        unreadBelowCount.value = 0;
        messagesReady.value = false;
        pendingImageCount = 0;
        clearTimeout(messagesReadyTimer);
        otherLastReadAt.value = props.activeConversation?.other_last_read_at ?? null;
        if (props.activeConversation?.type === 'group' && props.activeConversation.participants) {
            const map = {};
            for (const p of props.activeConversation.participants) {
                if (p.id !== authUser.value?.id && p.last_read_at) map[p.id] = p.last_read_at;
            }
            groupReadAt.value = map;
        } else {
            groupReadAt.value = {};
        }
        loadChatBg();
        scrollToBottom();
        if (newId) {
            subscribeToConversation(newId);
            markAsRead();
        }
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
                    <div style="display:flex;gap:6px;align-items:center">
                        <button @click="openGroupModal(); playClick()" class="new-conv-btn" title="Novo grupo" style="background:linear-gradient(135deg,#4ebcff,#009ac7)">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </button>
                        <Link :href="route('friends.index')" class="new-conv-btn" title="Nova conversa" @click="playClick()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                        </Link>
                    </div>
                </div>

                <div class="sidebar-list">
                    <!-- Friends (no conversations yet) -->
                    <template v-if="localConversations.length === 0 && friends.length > 0">
                        <p class="sidebar-label">Os teus amigos</p>
                        <button v-for="f in friends" :key="f.id" @click="startWith(f.id); playClick()" class="sidebar-item">
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
                            <!-- Group avatar -->
                            <template v-if="conv.type === 'group'">
                                <img v-if="conv.group_avatar" :src="clImg(conv.group_avatar, 88, 88, 'fill')" class="s-avatar" style="border:2px solid #009ac7;box-shadow:0 2px 8px #009ac733" loading="lazy" />
                                <div v-else class="s-avatar-init" style="background:linear-gradient(135deg,#009ac7,#4ebcff);font-size:11px">
                                    {{ avatarInitial(conv.group_name) }}
                                </div>
                            </template>
                            <!-- Direct avatar -->
                            <template v-else>
                                <img v-if="conv.other_user?.avatar" :src="clImg(conv.other_user.avatar, 88, 88, 'fill', 'face')" class="s-avatar" :style="{ border: `2px solid ${conv.other_user.avatar_color}`, boxShadow: `0 2px 8px ${conv.other_user.avatar_color}33` }" loading="lazy" />
                                <div v-else class="s-avatar-init" :style="{ background: conv.other_user?.avatar_color ?? '#009ac7' }">{{ avatarInitial(conv.other_user?.name) }}</div>
                                <span v-if="isOnline(conv.other_user?.id)" class="online-dot"></span>
                            </template>
                        </div>
                        <div class="conv-info">
                            <div class="conv-row">
                                <p class="conv-name" :style="{ fontWeight: conv.unread_count > 0 ? '800' : '700' }">
                                    {{ conv.type === 'group' ? (conv.group_name ?? 'Grupo') : (conv.other_user?.name ?? '—') }}
                                </p>
                                <span class="conv-time" :style="{ color: conv.unread_count > 0 ? '#009ac7' : 'var(--text-4)', fontWeight: conv.unread_count > 0 ? '700' : '400' }">{{ formatTime(conv.last_message?.created_at) }}</span>
                            </div>
                            <p class="conv-sub" :style="{ color: conv.unread_count > 0 ? '#009ac7' : 'var(--text-3)', fontWeight: conv.unread_count > 0 ? '600' : '400' }">
                                <span v-if="conv.last_message?.is_own" style="color:var(--text-4)">Eu: </span>
                                {{ conv.last_message?.content ?? (conv.last_message ? '📷 Imagem' : 'Sem mensagens.') }}
                                <span v-if="conv.type === 'group' && conv.participants_count" style="color:var(--text-4);font-size:10px"> · {{ conv.participants_count }} membros</span>
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
                                <button v-for="(f, i) in friends" :key="f.id" @click="startWith(f.id); playClick()" style="width:100%;display:flex;align-items:center;gap:14px;padding:14px 20px;border:none;background:transparent;cursor:pointer;text-align:left;transition:background 0.15s" :style="{ borderTop: i > 0 ? '1px solid #009ac70c' : 'none' }" @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.05)'" @mouseleave="$event.currentTarget.style.background='transparent'">
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

                        <!-- Group header -->
                        <template v-if="isGroup">
                            <img v-if="activeConversation.group_avatar" :src="clImg(activeConversation.group_avatar, 80, 80, 'fill')" class="h-avatar" style="border:2px solid #009ac7;box-shadow:0 2px 8px #009ac744" />
                            <div v-else class="h-avatar-init" style="background:linear-gradient(135deg,#009ac7,#4ebcff);font-size:13px">{{ avatarInitial(activeConversation.group_name) }}</div>
                            <div class="header-info">
                                <p class="header-name">{{ activeConversation.group_name }}</p>
                                <p class="header-username">{{ activeConversation.participants_count }} membros</p>
                            </div>
                        </template>

                        <!-- Direct header -->
                        <template v-else>
                            <div style="position:relative;flex-shrink:0">
                                <img v-if="activeConversation.other_user?.avatar" :src="clImg(activeConversation.other_user.avatar, 80, 80, 'fill', 'face')" class="h-avatar" :style="{ border: `2px solid ${activeConversation.other_user.avatar_color}`, boxShadow: `0 2px 8px ${activeConversation.other_user.avatar_color}44` }" />
                                <div v-else class="h-avatar-init" :style="{ background: activeConversation.other_user?.avatar_color ?? '#009ac7' }">{{ avatarInitial(activeConversation.other_user?.name) }}</div>
                                <span v-if="isOnline(activeConversation.other_user?.id)" class="online-dot online-dot-lg"></span>
                            </div>
                            <div class="header-info">
                                <p class="header-name">{{ activeConversation.other_user?.name }}</p>
                                <p class="header-username" :style="{ color: isOnline(activeConversation.other_user?.id) ? '#22c55e' : undefined }">
                                    {{ isOnline(activeConversation.other_user?.id) ? 'Online' : (activeConversation.other_user?.username ? '@' + activeConversation.other_user.username : '') }}
                                </p>
                            </div>
                        </template>

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

                        <button v-if="isGroup" @click="showGroupDetails = !showGroupDetails" class="btn-outline">Detalhes</button>
                        <Link v-else-if="activeConversation.other_user?.username" :href="route('profile.show', activeConversation.other_user.username)" class="btn-outline">Ver perfil</Link>
                    </div>

                    <!-- Group details panel -->
                    <Transition name="slide-panel">
                        <div v-if="isGroup && showGroupDetails" class="group-details-panel">
                            <div class="group-details-header">
                                <p class="group-details-title">Detalhes do grupo</p>
                                <button @click="showGroupDetails = false" class="group-details-close">×</button>
                            </div>

                            <!-- Group info / edit section -->
                            <div class="gd-info-section">
                                <div class="gd-avatar-wrap">
                                    <template v-if="canEditGroup">
                                        <button type="button" class="gd-avatar-btn" @click="groupEditImageInputRef.click()" title="Alterar foto">
                                            <img v-if="groupEditImagePreview" :src="groupEditImagePreview" class="gd-avatar-img" />
                                            <img v-else-if="activeConversation.group_avatar" :src="clImg(activeConversation.group_avatar, 80, 80, 'fill', 'face')" class="gd-avatar-img" />
                                            <div v-else class="gd-avatar-placeholder">
                                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                                </svg>
                                            </div>
                                            <div class="gd-avatar-overlay">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>
                                                </svg>
                                            </div>
                                        </button>
                                        <input ref="groupEditImageInputRef" type="file" accept="image/*" style="display:none" @change="handleGroupEditImageChange" />
                                    </template>
                                    <template v-else>
                                        <img v-if="activeConversation.group_avatar" :src="clImg(activeConversation.group_avatar, 80, 80, 'fill', 'face')" class="gd-avatar-img gd-avatar-static" />
                                        <div v-else class="gd-avatar-placeholder gd-avatar-static">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                            </svg>
                                        </div>
                                    </template>
                                </div>

                                <div class="gd-name-wrap">
                                    <input
                                        v-if="canEditGroup"
                                        v-model="groupEditName"
                                        type="text"
                                        maxlength="100"
                                        class="gd-name-input"
                                        placeholder="Nome do grupo"
                                    />
                                    <p v-else class="gd-name-static">{{ activeConversation.group_name }}</p>
                                    <p class="gd-members-count">{{ activeConversation.participants_count }} membros</p>
                                </div>

                                <button
                                    v-if="canEditGroup && (groupEditImageFile || groupEditName.trim() !== (activeConversation.group_name ?? ''))"
                                    @click="saveGroupInfo"
                                    :disabled="groupEditSaving"
                                    class="gd-save-btn"
                                >{{ groupEditSaving ? '…' : 'Guardar' }}</button>
                            </div>

                            <div class="gd-divider"></div>

                            <!-- Members section header -->
                            <div class="gd-section-row">
                                <p class="gd-section-label">Membros ({{ activeConversation.participants_count }})</p>
                                <button v-if="canEditGroup" @click="openAddMember" class="gd-add-btn" :class="{ active: showAddMember }" title="Adicionar membro">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                    Adicionar
                                </button>
                            </div>

                            <!-- Add member panel -->
                            <Transition name="gd-expand">
                                <div v-if="showAddMember && canEditGroup" class="gd-add-section">
                                    <input
                                        v-model="addMemberSearch"
                                        type="text"
                                        placeholder="Pesquisar amigos…"
                                        class="gd-add-search"
                                        autofocus
                                    />
                                    <div class="gd-add-list">
                                        <p v-if="addMemberLoading" class="gd-add-empty">A carregar…</p>
                                        <p v-else-if="addMemberFiltered.length === 0" class="gd-add-empty">Nenhum amigo disponível.</p>
                                        <div v-else v-for="f in addMemberFiltered" :key="f.id" class="gd-friend-row">
                                            <img v-if="f.avatar" :src="clImg(f.avatar, 40, 40, 'fill', 'face')" class="gd-friend-avatar" />
                                            <div v-else class="gd-friend-avatar-init" :style="{ background: f.avatar_color ?? '#009ac7' }">{{ avatarInitial(f.name) }}</div>
                                            <div style="flex:1;min-width:0">
                                                <p class="gd-friend-name">{{ f.name }}</p>
                                                <p class="gd-friend-user">@{{ f.username }}</p>
                                            </div>
                                            <button @click="addMemberToGroup(f.id)" :disabled="groupActionLoading" class="gd-friend-add-btn">Adicionar</button>
                                        </div>
                                    </div>
                                </div>
                            </Transition>

                            <!-- Members list -->
                            <div class="group-details-list">
                                <div v-for="member in groupDetailsParticipants" :key="member.id" class="group-member-row">
                                    <img v-if="member.avatar" :src="clImg(member.avatar, 40, 40, 'fill', 'face')" class="gm-avatar" :style="{ border: `1.5px solid ${member.avatar_color}` }" loading="lazy" />
                                    <div v-else class="gm-avatar-init" :style="{ background: member.avatar_color }">{{ avatarInitial(member.name) }}</div>
                                    <div style="flex:1;min-width:0">
                                        <p class="gm-name">{{ member.name }}</p>
                                        <span class="gm-role" :class="`role-${member.role}`">{{ { owner: 'Dono', admin: 'Admin', member: 'Membro' }[member.role] }}</span>
                                    </div>
                                    <div v-if="member.id !== authUser?.id" class="gm-actions">
                                        <template v-if="groupUserRole === 'owner'">
                                            <button v-if="member.role === 'member'" @click="promoteToAdmin(member.id)" :disabled="groupActionLoading" class="gm-btn" title="Promover a admin">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
                                            </button>
                                            <button v-if="member.role === 'admin'" @click="demoteToMember(member.id)" :disabled="groupActionLoading" class="gm-btn" title="Rebaixar a membro">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                            </button>
                                            <button @click="removeMember(member.id)" :disabled="groupActionLoading" class="gm-btn gm-btn-danger" title="Remover do grupo">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                        </template>
                                        <template v-else-if="groupUserRole === 'admin' && member.role === 'member'">
                                            <button @click="removeMember(member.id)" :disabled="groupActionLoading" class="gm-btn gm-btn-danger" title="Remover do grupo">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div class="group-details-footer">
                                <button @click="leaveGroup" :disabled="groupActionLoading" class="btn-leave-group">
                                    {{ groupUserRole === 'owner' ? 'Transferir e sair' : 'Sair do grupo' }}
                                </button>
                            </div>
                        </div>
                    </Transition>

                    <!-- Messages area -->
                    <div ref="messagesEl" class="messages-area" :class="{ 'chat-dark': isDarkBg }" :style="[chatBgStyle, messagesReady ? {} : { visibility: 'hidden' }]">
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
                                    <!-- Group sender avatar (received only) -->
                                    <template v-if="isGroup && !item.is_own">
                                        <img v-if="item.isFirst && item.author?.avatar" :src="clImg(item.author.avatar, 30, 30, 'fill', 'face')" class="gm-sender-av" :style="{ border: `1.5px solid ${item.author?.avatar_color ?? '#009ac7'}` }" />
                                        <div v-else-if="item.isFirst" class="gm-sender-av gm-sender-av-init" :style="{ background: item.author?.avatar_color ?? '#009ac7' }">{{ avatarInitial(item.author?.name) }}</div>
                                        <div v-else class="gm-sender-av-space"></div>
                                    </template>
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
                                            <!-- Sender name inside bubble — group received, first in run -->
                                            <p v-if="isGroup && !item.is_own && item.isFirst" class="bubble-sender-name" :style="{ color: item.author?.avatar_color ?? '#009ac7' }">{{ item.author?.name }}</p>
                                            <!-- Reply quote -->
                                            <div v-if="item.reply_to" class="reply-quote" :class="item.is_own ? 'reply-own' : 'reply-recv'">
                                                <p class="reply-author">{{ item.reply_to.author_name }}</p>
                                                <p class="reply-text">{{ item.reply_to.content ?? '[imagem]' }}</p>
                                            </div>
                                            <!-- Image -->
                                            <img v-if="item.image_url" :src="clImg(item.image_url, 520, 0, 'limit')" loading="lazy" class="msg-img" style="cursor:zoom-in" @load="onMsgImageSettle" @error="onMsgImageSettle" @click.stop="lightboxUrl = item.image_url" />
                                            <!-- Text -->
                                            <span v-if="item.content" :class="item.image_url ? 'text-after-img' : ''">{{ item.content }}</span>
                                            <!-- Meta inside bubble (only when has content/reply) -->
                                            <div v-if="!item.image_url || item.content || item.reply_to" class="msg-meta">
                                                <span class="msg-ts">{{ formatTime(item.created_at) }}</span>
                                                <span v-if="item.is_edited" class="msg-ts-edited">editado</span>
                                                <span v-if="isDirect && item.is_own && item.id === lastSeenMessageId" class="msg-seen">
                                                    <img v-if="activeConversation.other_user?.avatar" :src="clImg(activeConversation.other_user.avatar, 32, 32, 'fill', 'face')" class="seen-avatar" :style="{ border: `1px solid ${activeConversation.other_user.avatar_color}` }" />
                                                    <div v-else class="seen-initial" :style="{ background: activeConversation.other_user?.avatar_color ?? '#009ac7' }">{{ avatarInitial(activeConversation.other_user?.name) }}</div>
                                                </span>
                                                <span v-if="isGroup && item.is_own && groupSeenPerMessage[item.id]?.length" class="msg-seen">
                                                    <template v-for="p in (groupSeenPerMessage[item.id] ?? []).slice(0, 5)" :key="p.id">
                                                        <img v-if="p.avatar" :src="clImg(p.avatar, 32, 32, 'fill', 'face')" class="seen-avatar" :style="{ border: `1px solid ${p.avatar_color}` }" :title="p.name" />
                                                        <div v-else class="seen-initial" :style="{ background: p.avatar_color }" :title="p.name">{{ avatarInitial(p.name) }}</div>
                                                    </template>
                                                    <span v-if="groupSeenPerMessage[item.id].length > 5" class="seen-overflow">+{{ groupSeenPerMessage[item.id].length - 5 }}</span>
                                                </span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Meta below bubble for image-only messages -->
                                <div v-if="item.image_url && !item.content && !item.reply_to && editingId !== item.id" class="msg-meta img-only-meta">
                                    <span class="msg-ts">{{ formatTime(item.created_at) }}</span>
                                    <span v-if="item.is_edited" class="msg-ts-edited">editado</span>
                                    <span v-if="isDirect && item.is_own && item.id === lastSeenMessageId" class="msg-seen">
                                        <img v-if="activeConversation.other_user?.avatar" :src="clImg(activeConversation.other_user.avatar, 32, 32, 'fill', 'face')" class="seen-avatar" :style="{ border: `1px solid ${activeConversation.other_user.avatar_color}` }" />
                                        <div v-else class="seen-initial" :style="{ background: activeConversation.other_user?.avatar_color ?? '#009ac7' }">{{ avatarInitial(activeConversation.other_user?.name) }}</div>
                                    </span>
                                    <span v-if="isGroup && item.is_own && groupSeenPerMessage[item.id]?.length" class="msg-seen">
                                        <template v-for="p in (groupSeenPerMessage[item.id] ?? []).slice(0, 5)" :key="p.id">
                                            <img v-if="p.avatar" :src="clImg(p.avatar, 32, 32, 'fill', 'face')" class="seen-avatar" :style="{ border: `1px solid ${p.avatar_color}` }" :title="p.name" />
                                            <div v-else class="seen-initial" :style="{ background: p.avatar_color }" :title="p.name">{{ avatarInitial(p.name) }}</div>
                                        </template>
                                        <span v-if="groupSeenPerMessage[item.id].length > 5" class="seen-overflow">+{{ groupSeenPerMessage[item.id].length - 5 }}</span>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Scroll to bottom -->
                    <Transition name="fade">
                        <div v-if="!isNearBottom" class="scroll-btn-wrap">
                            <button @click="scrollToBottom()" class="scroll-btn">
                                <span v-if="unreadBelowCount > 0" class="scroll-unread-badge">{{ unreadBelowCount > 99 ? '99+' : unreadBelowCount }}</span>
                                ↓
                            </button>
                        </div>
                    </Transition>

                    <!-- Typing indicator -->
                    <Transition name="typing-slide">
                        <div v-if="typingUsers.length > 0" class="typing-bar">
                            <div class="typing-bubble">
                                <div class="typing-dots">
                                    <span></span><span></span><span></span>
                                </div>
                            </div>
                            <span class="typing-label">{{ typingLabel }}</span>
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
                                @blur="stopTyping"
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

                        <p v-if="sendState === 'error'" class="send-error">Erro ao enviar. A mensagem foi restaurada — tenta novamente.</p>
                        <p v-if="pasteError" class="send-error">{{ pasteError }}</p>
                    </div>
                </template>
            </main>
        </div>
    </AuthenticatedLayout>

    <PostImageLightbox v-if="lightboxUrl" :image-url="lightboxUrl" :open="!!lightboxUrl" @close="lightboxUrl = null" />

    <!-- ═══ GROUP CREATION MODAL ══════════════════════════════════ -->
    <Transition name="modal-fade">
        <div v-if="showGroupModal" class="modal-backdrop" @click.self="showGroupModal = false">
            <div class="modal-box group-modal">
                <div class="modal-header">
                    <p class="modal-title">Novo grupo</p>
                    <button @click="showGroupModal = false" class="modal-close">×</button>
                </div>

                <div class="modal-body">
                    <!-- Group avatar picker -->
                    <div class="group-avatar-picker-wrap">
                        <button type="button" class="group-avatar-picker" @click="groupImageInputRef.click()" :title="groupModalImagePreview ? 'Alterar foto' : 'Adicionar foto'">
                            <img v-if="groupModalImagePreview" :src="groupModalImagePreview" class="group-avatar-preview" alt="Foto do grupo" />
                            <span v-else class="group-avatar-placeholder">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                    <circle cx="12" cy="13" r="4"/>
                                </svg>
                                <span style="font-size:10px;margin-top:4px;display:block;line-height:1.1">Foto<br>do grupo</span>
                            </span>
                        </button>
                        <button v-if="groupModalImagePreview" type="button" class="group-avatar-remove" @click="groupModalImage=null; groupModalImagePreview=null; groupImageInputRef.value=''" title="Remover foto">×</button>
                        <input ref="groupImageInputRef" type="file" accept="image/*" style="display:none" @change="handleGroupImageChange" />
                    </div>

                    <!-- Group name -->
                    <label class="modal-label">Nome do grupo</label>
                    <input
                        v-model="groupModalName"
                        type="text"
                        maxlength="100"
                        placeholder="Ex: Equipa de projeto"
                        class="modal-input"
                    />

                    <!-- Participant search -->
                    <label class="modal-label" style="margin-top:16px">Adicionar participantes <span style="color:#8ba0b0;font-weight:400">(mín. 2)</span></label>
                    <input
                        v-model="groupModalSearch"
                        type="text"
                        placeholder="Pesquisar amigos…"
                        class="modal-input"
                        style="margin-bottom:10px"
                    />

                    <!-- Selected chips -->
                    <div v-if="groupModalSelected.length > 0" class="modal-chips">
                        <span v-for="u in groupModalSelected" :key="u.id" class="modal-chip">
                            {{ u.name }}
                            <button @click="toggleGroupMember(u)" class="chip-remove">×</button>
                        </span>
                    </div>

                    <!-- Friends list -->
                    <div class="modal-friends-list">
                        <p v-if="groupModalLoading" style="font-size:13px;color:#8ba0b0;text-align:center;padding:16px 0">A carregar…</p>
                        <p v-else-if="groupModalError" style="font-size:13px;color:#e05555;text-align:center;padding:12px 0">{{ groupModalError }}</p>
                        <p v-else-if="groupModalFiltered.length === 0" style="font-size:13px;color:#8ba0b0;text-align:center;padding:12px 0">Nenhum amigo encontrado.</p>
                        <button
                            v-else
                            v-for="f in groupModalFiltered"
                            :key="f.id"
                            @click="toggleGroupMember(f)"
                            class="modal-friend-row"
                            :class="{ 'is-selected': isGroupMemberSelected(f) }"
                        >
                            <img v-if="f.avatar" :src="clImg(f.avatar, 64, 64, 'fill', 'face')" class="mf-avatar" :style="{ border: `1.5px solid ${f.avatar_color}` }" />
                            <div v-else class="mf-avatar-init" :style="{ background: f.avatar_color }">{{ avatarInitial(f.name) }}</div>
                            <div style="flex:1;min-width:0">
                                <p class="mf-name">{{ f.name }}</p>
                                <p v-if="f.username" class="mf-username">@{{ f.username }}</p>
                            </div>
                            <div class="mf-check" :class="{ 'is-checked': isGroupMemberSelected(f) }">
                                <svg v-if="isGroupMemberSelected(f)" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button @click="showGroupModal = false" class="btn-cancel-modal">Cancelar</button>
                    <button
                        @click="submitCreateGroup"
                        :disabled="!groupModalName.trim() || groupModalSelected.length < 2 || groupModalSubmitting"
                        class="btn-create-group"
                    >
                        {{ groupModalSubmitting ? 'A criar…' : `Criar grupo (${groupModalSelected.length + 1})` }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
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
    border-right: 1px solid rgba(0, 154, 199, 0.14);
    background: var(--chat-sidebar-bg);
    backdrop-filter: blur(32px);
    box-shadow: inset -1px 0 0 rgba(0,154,199,0.10);
}
:global(html.dark) .chat-sidebar {
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

.online-dot {
    position: absolute; bottom: 1px; right: 1px;
    width: 11px; height: 11px; border-radius: 50%;
    background: #22c55e;
    border: 2px solid var(--nav-bg, #fff);
    box-shadow: 0 0 0 1px #22c55e44;
}
.online-dot-lg {
    width: 13px; height: 13px;
    bottom: 2px; right: 2px;
}
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
    background: var(--chat-header-bg);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(0, 154, 199, 0.13);
    box-shadow: var(--chat-header-shadow);
    z-index: 10;
}
:global(html.dark) .chat-header {
    border-color: rgba(0, 154, 199, 0.15);
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
.seen-overflow { font-size: 9px; color: #8ba0b0; font-weight: 700; margin-left: 1px; }

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
    position: relative;
    width: 38px; height: 38px; border-radius: 50%; border: 1px solid rgba(255,255,255,.45);
    background: rgba(0,154,199,.45); backdrop-filter: blur(16px) saturate(160%); -webkit-backdrop-filter: blur(16px) saturate(160%); color: white; font-size: 16px; cursor: pointer;
    box-shadow: 0 6px 20px rgba(0,154,199,.4), inset 0 1px 0 rgba(255,255,255,.4); display: flex; align-items: center; justify-content: center;
    transition: transform 0.2s;
}
.scroll-btn:hover { transform: scale(1.1); }
.scroll-unread-badge {
    position: absolute; top: -7px; left: 50%; transform: translateX(-50%);
    background: #e05555; color: white; font-size: 9px; font-weight: 800;
    min-width: 18px; height: 18px; border-radius: 99px; padding: 0 4px;
    display: flex; align-items: center; justify-content: center;
    white-space: nowrap; box-shadow: 0 2px 6px #e0555540;
}

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
.btn-save { padding: 4px 12px; border-radius: 99px; border: 1px solid rgba(255,255,255,.45); background: rgba(0,154,199,.45); backdrop-filter: blur(16px) saturate(160%); -webkit-backdrop-filter: blur(16px) saturate(160%); color: white; text-shadow: 0 1px 4px rgba(0,60,100,.6); font-size: 11px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 16px rgba(0,154,199,.4), inset 0 1px 0 rgba(255,255,255,.4); }
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

/* ── Sender avatar + name (group chats) ─────────────────────── */
.gm-sender-av {
    width: 30px; height: 30px; border-radius: 50%; object-fit: cover;
    flex-shrink: 0; align-self: flex-end; display: block;
}
.gm-sender-av-init {
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: #fff;
}
.gm-sender-av-space { width: 30px; flex-shrink: 0; }
.bubble-sender-name {
    font-size: 11px; font-weight: 700; margin: 0 0 5px; padding: 0; line-height: 1;
}

/* ── Group details panel ─────────────────────────────────────── */
.group-details-panel {
    position: absolute;
    top: 0;
    right: 0;
    width: 280px;
    height: 100%;
    background: var(--bg-1, #fff);
    border-left: 1px solid #009ac712;
    box-shadow: -4px 0 24px #009ac70a;
    display: flex;
    flex-direction: column;
    z-index: 10;
}
.group-details-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px;
    border-bottom: 1px solid #009ac710;
}
.group-details-title { font-size: 13px; font-weight: 800; color: #3a6478; margin: 0; }
.group-details-close {
    width: 26px; height: 26px; border-radius: 50%; border: none;
    background: #009ac710; color: #5a7a8a; font-size: 16px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.group-details-list { flex: 1; overflow-y: auto; padding: 10px 0; }
.group-member-row {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 18px;
}
.gm-avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.gm-avatar-init {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 800; color: white;
}
.gm-name { font-size: 13px; font-weight: 700; color: #3a6478; margin: 0; }
.gm-role { font-size: 10px; font-weight: 600; margin: 1px 0 0; text-transform: uppercase; letter-spacing: .03em; }
.role-owner { color: #f59e0b; }
.role-admin  { color: #009ac7; }
.role-member { color: #8ba0b0; }
.gm-actions { display: flex; gap: 4px; flex-shrink: 0; }
.gm-btn {
    width: 24px; height: 24px; border-radius: 50%; border: none;
    background: #009ac710; color: #009ac7; font-size: 13px; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.gm-btn:hover { background: #009ac720; }
.gm-btn-danger { background: #e0555510; color: #e05555; }
.gm-btn-danger:hover { background: #e0555520; }
.gd-info-section {
    display: flex; flex-direction: column; align-items: center;
    gap: 10px; padding: 20px 18px 16px;
}
.gd-avatar-wrap { position: relative; flex-shrink: 0; }
.gd-avatar-btn {
    width: 76px; height: 76px; border-radius: 50%; border: none; padding: 0;
    cursor: pointer; overflow: hidden; position: relative;
    background: #e6f5fb; display: flex; align-items: center; justify-content: center;
}
.gd-avatar-img { width: 76px; height: 76px; object-fit: cover; border-radius: 50%; display: block; }
.gd-avatar-static { width: 76px; height: 76px; border-radius: 50%; object-fit: cover; display: block; }
.gd-avatar-placeholder {
    width: 76px; height: 76px; border-radius: 50%; background: #dff0f8;
    display: flex; align-items: center; justify-content: center; color: #5a9ab5;
}
.gd-avatar-overlay {
    position: absolute; inset: 0; border-radius: 50%; background: rgba(0,0,0,.35);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity .18s;
}
.gd-avatar-btn:hover .gd-avatar-overlay { opacity: 1; }
.gd-name-wrap { display: flex; flex-direction: column; align-items: center; gap: 3px; width: 100%; }
.gd-name-input {
    width: 100%; text-align: center; font-size: 14px; font-weight: 700; color: #3a6478;
    border: 1.5px solid transparent; border-radius: 8px; padding: 5px 10px;
    background: transparent; outline: none; transition: border-color .15s, background .15s;
    box-sizing: border-box;
}
.gd-name-input:hover { border-color: #009ac730; background: #f5f9fb; }
.gd-name-input:focus { border-color: #009ac760; background: #fff; }
.gd-name-static { font-size: 14px; font-weight: 700; color: #3a6478; margin: 0; text-align: center; }
.gd-members-count { font-size: 11px; color: #8ba0b0; margin: 0; }
.gd-save-btn {
    padding: 6px 18px; border-radius: 20px; border: 1px solid rgba(255,255,255,.45);
    background: rgba(0,154,199,.45); backdrop-filter: blur(16px) saturate(160%); -webkit-backdrop-filter: blur(16px) saturate(160%); color: #fff; text-shadow: 0 1px 4px rgba(0,60,100,.6); font-size: 12px; font-weight: 700;
    cursor: pointer; box-shadow: 0 6px 20px rgba(0,154,199,.4), inset 0 1px 0 rgba(255,255,255,.4); transition: opacity .15s;
}
.gd-save-btn:hover:not(:disabled) { opacity: .88; }
.gd-save-btn:disabled { opacity: .5; cursor: default; }
.gd-divider { height: 1px; background: #009ac710; margin: 0 18px; }
.gd-section-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 18px 4px;
}
.gd-section-label {
    font-size: 10px; font-weight: 800; color: #5a7a8a; text-transform: uppercase;
    letter-spacing: .06em; margin: 0; padding: 0;
}
.gd-add-btn {
    display: flex; align-items: center; gap: 4px; padding: 4px 10px;
    border-radius: 20px; border: 1.5px solid #009ac730; background: transparent;
    color: #009ac7; font-size: 11px; font-weight: 700; cursor: pointer;
    transition: background .15s, border-color .15s;
}
.gd-add-btn:hover, .gd-add-btn.active { background: #009ac712; border-color: #009ac760; }
.gd-add-section {
    margin: 4px 18px 6px; border: 1.5px solid #009ac720;
    border-radius: 12px; overflow: hidden; background: #f8fcfe;
}
.gd-add-search {
    width: 100%; padding: 9px 14px; border: none; border-bottom: 1px solid #009ac715;
    font-size: 13px; color: #3a6478; background: transparent; outline: none;
    box-sizing: border-box;
}
.gd-add-list { max-height: 180px; overflow-y: auto; }
.gd-add-empty { font-size: 12px; color: #8ba0b0; text-align: center; padding: 12px; margin: 0; }
.gd-friend-row {
    display: flex; align-items: center; gap: 9px; padding: 7px 12px;
    border-bottom: 1px solid #009ac708;
}
.gd-friend-row:last-child { border-bottom: none; }
.gd-friend-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.gd-friend-avatar-init {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; color: #fff;
}
.gd-friend-name { font-size: 12px; font-weight: 700; color: #3a6478; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.gd-friend-user { font-size: 11px; color: #8ba0b0; margin: 0; }
.gd-friend-add-btn {
    padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(255,255,255,.45);
    background: rgba(0,154,199,.45); backdrop-filter: blur(16px) saturate(160%); -webkit-backdrop-filter: blur(16px) saturate(160%); color: #fff; text-shadow: 0 1px 4px rgba(0,60,100,.6); font-size: 11px; font-weight: 700;
    cursor: pointer; white-space: nowrap; box-shadow: 0 4px 16px rgba(0,154,199,.4), inset 0 1px 0 rgba(255,255,255,.4); transition: opacity .15s; flex-shrink: 0;
}
.gd-friend-add-btn:hover:not(:disabled) { opacity: .88; }
.gd-friend-add-btn:disabled { opacity: .5; cursor: default; }
.gd-expand-enter-active, .gd-expand-leave-active { transition: max-height .22s ease, opacity .18s ease; max-height: 300px; overflow: hidden; }
.gd-expand-enter-from, .gd-expand-leave-to { max-height: 0; opacity: 0; }
.group-details-footer {
    padding: 14px 18px;
    border-top: 1px solid #009ac710;
}
.btn-leave-group {
    width: 100%; padding: 10px; border: none; border-radius: 10px;
    background: #e0555510; color: #e05555; font-size: 13px; font-weight: 700;
    cursor: pointer; transition: background .15s;
}
.btn-leave-group:hover { background: #e0555520; }

/* slide panel transition */
.slide-panel-enter-active, .slide-panel-leave-active { transition: transform .2s ease; }
.slide-panel-enter-from, .slide-panel-leave-to { transform: translateX(100%); }

/* ── Group creation modal ─────────────────────────────────────── */
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.45);
    display: flex; align-items: center; justify-content: center; z-index: 200;
}
.modal-box {
    background: #fff; border-radius: 20px; width: 440px; max-width: 95vw;
    max-height: 85vh; display: flex; flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
}
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px 14px; border-bottom: 1px solid #009ac710;
}
.modal-title { font-size: 15px; font-weight: 800; color: #3a6478; margin: 0; }
.modal-close {
    width: 28px; height: 28px; border-radius: 50%; border: none;
    background: #009ac710; color: #5a7a8a; font-size: 18px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.modal-body { padding: 18px 22px; flex: 1; overflow-y: auto; }
.modal-label { font-size: 11px; font-weight: 700; color: #5a7a8a; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 6px; }
.modal-input {
    width: 100%; padding: 10px 14px; border: 1.5px solid #009ac720; border-radius: 10px;
    font-size: 13.5px; color: #3a6478; background: #f5f9fb; outline: none;
    box-sizing: border-box; transition: border-color .15s;
}
.modal-input:focus { border-color: #009ac750; background: #fff; }

.group-avatar-picker-wrap {
    display: flex; align-items: center; justify-content: center;
    position: relative; margin: 0 auto 20px; width: fit-content;
}
.group-avatar-picker {
    width: 82px; height: 82px; border-radius: 50%; border: 2px dashed #009ac740;
    background: #f0f8fc; cursor: pointer; overflow: hidden;
    display: flex; align-items: center; justify-content: center;
    transition: border-color .2s, background .2s; padding: 0;
    color: #5a9ab5;
}
.group-avatar-picker:hover { border-color: #009ac7; background: #e6f5fb; }
.group-avatar-preview { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.group-avatar-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; }
.group-avatar-remove {
    position: absolute; top: -2px; right: -2px;
    width: 22px; height: 22px; border-radius: 50%; border: none;
    background: #e05555; color: #fff; font-size: 15px; line-height: 1;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    padding: 0; transition: background .15s;
}
.group-avatar-remove:hover { background: #c03030; }

.modal-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.modal-chip {
    display: flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 99px;
    background: #009ac715; color: #009ac7; font-size: 12px; font-weight: 700;
}
.chip-remove {
    border: none; background: none; color: #009ac7; font-size: 14px;
    cursor: pointer; padding: 0; line-height: 1; display: flex; align-items: center;
}

.modal-friends-list { max-height: 280px; overflow-y: auto; margin: 0 -22px; padding: 0 22px; }
.modal-friend-row {
    width: 100%; display: flex; align-items: center; gap: 10px;
    padding: 9px 0; border: none; background: transparent; cursor: pointer;
    border-bottom: 1px solid #009ac708; text-align: left; transition: background .12s;
    border-radius: 10px; margin: 1px 0; padding: 9px 10px;
}
.modal-friend-row:hover { background: #009ac708; }
.modal-friend-row.is-selected { background: #009ac710; }
.mf-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.mf-avatar-init {
    width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800; color: white;
}
.mf-name { font-size: 13.5px; font-weight: 700; color: #3a6478; margin: 0; }
.mf-username { font-size: 11px; color: #009ac7; margin: 1px 0 0; }
.mf-check {
    width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
    border: 2px solid #009ac730; display: flex; align-items: center; justify-content: center;
    transition: background .15s, border-color .15s;
}
.mf-check.is-checked { background: #009ac7; border-color: #009ac7; }

.modal-footer {
    display: flex; gap: 10px; padding: 14px 22px; border-top: 1px solid #009ac710;
}
.btn-cancel-modal {
    flex: 1; padding: 10px; border: 1.5px solid #009ac720; border-radius: 10px;
    background: transparent; color: #5a7a8a; font-size: 13px; font-weight: 700; cursor: pointer;
}
.btn-create-group {
    flex: 2; padding: 10px; border: none; border-radius: 10px;
    background: linear-gradient(135deg, #009ac7, #4ebcff); color: white;
    font-size: 13px; font-weight: 700; cursor: pointer;
    box-shadow: 0 4px 14px #009ac730; transition: opacity .15s;
}
.btn-create-group:disabled { opacity: .5; cursor: default; }

/* modal transition */
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity .18s; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
