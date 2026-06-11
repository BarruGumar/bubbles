<script setup>
import { onMounted, onUnmounted, ref, computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import Bubble from '@/Components/Bubble.vue';
import ConnectionLines from '@/Components/ConnectionLines.vue';
import CreateCommunityModal from '@/Components/CreateCommunityModal.vue';
import ToastContainer from '@/Components/ToastContainer.vue';
import { clImg } from '@/Composables/useCloudinary';
import { useBubbles } from '@/Composables/useBubbles';
import { useConnections } from '@/Composables/useConnections';
import { usePhysics } from '@/Composables/usePhysics';
import { resolveBadgePos } from '@/Composables/useBadgeLayout';
import { useDrag } from '@/Composables/useDrag';
import { useToast } from '@/Composables/useToast';
import { useTheme } from '@/Composables/useTheme';
import { useSearch } from '@/Composables/useSearch';
import { useAudio } from '@/Composables/useAudio';
import { useOnlineUsers } from '@/Composables/useOnlineUsers';
import AudioControls from '@/Components/AudioControls.vue';
import AnnouncementBanner from '@/Components/AnnouncementBanner.vue';
import AnnouncementModal from '@/Components/AnnouncementModal.vue';
import TrendsSidebar from '@/Components/TrendsSidebar.vue';
import FeedPanel from '@/Components/FeedPanel.vue';
import PunishmentModal from '@/Components/PunishmentModal.vue';

const props = defineProps({
    feed: { type: Array, default: () => [] },
    hasFriends: { type: Boolean, default: false },
    hasCommunities: { type: Boolean, default: false },
    hasMore: { type: Boolean, default: false },
    nextCursor: { type: Number, default: null },
});

const { bubbles, hoveredId, load, add, toggleSelect, savePositions } = useBubbles();
const ready = ref(false);
const { connections, friendConnections, load: loadConnections, loadFriendConnections } = useConnections();
const { step } = usePhysics();
const { show: toast } = useToast();
const { isDark, toggle: toggleTheme } = useTheme();
const { playSfx, playClick, playHoverBubble, playBgm, stopBgm, playNotifSfx } = useAudio();
playBgm('home');

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const pendingFriends = ref(page.props.auth?.pending_friends_count ?? 0);
const unreadMessages = ref(page.props.auth?.unread_messages_count ?? 0);
const unreadNotifications = ref(page.props.auth?.unread_notifications_count ?? 0);
const isAdmin = computed(() => ['admin', 'site_owner'].includes(authUser.value?.role));

function avatarChipStyle(size = 32, color = '#009ac7') {
    return {
        width: `${size}px`,
        height: `${size}px`,
        borderRadius: '50%',
        objectFit: 'cover',
        display: 'block',
        border: `2px solid ${color ?? '#009ac7'}`,
        boxShadow: `0 2px 10px ${color ?? '#009ac7'}44`,
        flexShrink: 0,
    };
}

function avatarFallbackStyle(size = 32, color = '#009ac7') {
    return {
        width: `${size}px`,
        height: `${size}px`,
        borderRadius: '50%',
        background: color ?? '#009ac7',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: `${Math.max(10, size * 0.42)}px`,
        fontWeight: '800',
        color: 'white',
        boxShadow: `0 2px 10px ${color ?? '#009ac7'}44`,
        flexShrink: 0,
    };
}

const feedOpen = ref(false);
const menuOpen = ref(false);

const localFeed = ref([...props.feed]);
const localCursor = ref(props.nextCursor);
const localHasMore = ref(props.hasMore);
const feedLoading = ref(false);

watch(
    () => props.feed,
    (fresh) => {
        localFeed.value = [...fresh];
        localCursor.value = props.nextCursor;
        localHasMore.value = props.hasMore;
    },
);

function toggleFeed() {
    if (!feedOpen.value) playSfx('verFeed');
    feedOpen.value = !feedOpen.value;
}

function loadMoreFeed() {
    if (feedLoading.value || !localHasMore.value) return;
    feedLoading.value = true;

    router.get(
        route('feed.index'),
        { cursor: localCursor.value },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['feed', 'hasMore', 'nextCursor'],
            onSuccess: (page) => {
                localFeed.value.push(...page.props.feed);
                localCursor.value = page.props.nextCursor;
                localHasMore.value = page.props.hasMore;
            },
            onFinish: () => { feedLoading.value = false; },
        },
    );
}
const menuEl = ref(null);

watch(
    () => page.props.flash?.status,
    (msg) => {
        if (msg) toast(msg, 'success');
    },
);
watch(
    () => page.props.flash?.error,
    (msg) => {
        if (msg) toast(msg, 'error');
    },
);


const showAdd = ref(false);
const searchOpen = ref(false);
const searchQuery = ref('');
const activeSearchIdx = ref(-1);

const { results: searchResults, loading: searchLoading, error: searchError, search: doSearch, clear: clearSearch } = useSearch();

const hasResults = computed(
    () =>
        searchResults.value &&
        (searchResults.value.users?.length ||
            searchResults.value.communities?.length ||
            searchResults.value.posts?.length),
);

// Precomputed offsets for keyboard navigation across result sections
const searchOffsets = computed(() => {
    const u = Math.min(searchResults.value?.users?.length ?? 0, 4);
    const c = Math.min(searchResults.value?.communities?.length ?? 0, 4);
    const p = Math.min(searchResults.value?.posts?.length ?? 0, 3);
    return { users: 0, communities: u, posts: u + c, all: u + c + p };
});

watch(searchQuery, (q) => {
    activeSearchIdx.value = -1;
    if (!q.trim()) { clearSearch(); return; }
    doSearch(q);
});

function openSearch() {
    searchOpen.value = true;
    setTimeout(() => document.getElementById('bubble-search-input')?.focus(), 50);
}

function closeSearch() {
    searchOpen.value = false;
    searchQuery.value = '';
    clearSearch();
    activeSearchIdx.value = -1;
}

function goToResult(url) {
    closeSearch();
    router.visit(url);
}

function viewAllResults() {
    const q = searchQuery.value.trim();
    if (!q) return;
    closeSearch();
    router.visit(route('search.index', { q }));
}

function submitSearch(e) {
    e.preventDefault();
    viewAllResults();
}

function handleSearchKey(e) {
    if (!hasResults.value) return;
    const total = searchOffsets.value.all + 1; // +1 for "ver todos"
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeSearchIdx.value = Math.min(activeSearchIdx.value + 1, total - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeSearchIdx.value = Math.max(activeSearchIdx.value - 1, -1);
    } else if (e.key === 'Enter' && activeSearchIdx.value >= 0) {
        e.preventDefault();
        if (activeSearchIdx.value === searchOffsets.value.all) {
            viewAllResults();
            return;
        }
        const urls = [
            ...(searchResults.value?.users?.slice(0, 4) ?? []).map(u =>
                u.username ? route('profile.show', u.username) : null),
            ...(searchResults.value?.communities?.slice(0, 4) ?? []).map(c =>
                route('community.show', c.id)),
            ...(searchResults.value?.posts?.slice(0, 3) ?? []).map(p =>
                p.author.username ? route('profile.show', p.author.username) : null),
        ];
        const url = urls[activeSearchIdx.value];
        if (url) goToResult(url);
    }
}

const {
    dragging,
    startDrag,
    startTouch,
    onMouseMove: moveDrag,
    onTouchMove: moveDragTouch,
    stopDrag,
    getLastTouchTime,
    getLastTouchTapTime,
} = useDrag((id) => {
    toggleSelect(id);
});

// Save bubble positions to localStorage whenever a drag ends
watch(dragging, (newVal, oldVal) => {
    if (oldVal !== null && newVal === null) {
        savePositions(authUser.value?.id);
    }
});

function onWindowMouseMove(e) {
    moveDrag(e, bubbles.value);
}
function onWindowMouseUp() {
    stopDrag();
}
function onWindowTouchMove(e) {
    moveDragTouch(e, bubbles.value);
}
function onWindowTouchEnd(e) {
    // Only preventDefault when a bubble tap/drag was in progress — prevents the
    // synthetic click Chrome fires after touchend from landing on the overlay.
    // Without this guard, navbar buttons and links would also be blocked.
    if (dragging.value) e.preventDefault();
    stopDrag();
}

function onBubbleLeave(id) {
    if (hoveredId.value === id) hoveredId.value = null;
}

// Always closes — used by close button, overlay touchend, and Escape.
function clearSelectionForced() {
    bubbles.value.forEach((b) => {
        b.selected = false;
    });
}

// Guarded close — blocks spurious calls (synthetic clicks, touchend.self)
// for 600ms after a touch-tap opened the panel.
function clearSelection() {
    if (Date.now() - getLastTouchTapTime() < 600) return;
    clearSelectionForced();
}

// Overlay @click handler: extra guard against synthetic clicks from touch.
function handleOverlayClick() {
    if (Date.now() - getLastTouchTime() < 600) return;
    clearSelection();
}

// Overlay touchend: guarded so a spurious touchend right after opening doesn't close the panel.
function handleOverlayTouchEnd() {
    if (Date.now() - getLastTouchTapTime() < 500) return;
    clearSelectionForced();
}

function onDocClick(e) {
    if (menuEl.value && !menuEl.value.contains(e.target)) menuOpen.value = false;
}

function onKeyDown(e) {
    if (e.key === 'Escape') {
        if (menuOpen.value) { menuOpen.value = false; return; }
        if (searchOpen.value) {
            closeSearch();
            return;
        }
        clearSelectionForced();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        openSearch();
    }
}

let animId = null;
let lastTime = 0;
let _saveOnUnload = null;

function badgeObstacles() {
    if (!friendConnections.value.length) return [];
    const obstacles = [];
    for (const c of friendConnections.value) {
        const from = bubbleById.value.get(c.from);
        const to = bubbleById.value.get(c.to);
        if (!from || !to) continue;
        const midX = (from.x + from.size / 2 + to.x + to.size / 2) / 2;
        const midY = (from.y + from.size / 2 + to.y + to.size / 2) / 2;
        obstacles.push(resolveBadgePos(midX, midY, c.from, c.to, bubbles.value));
    }
    return obstacles;
}

function loop(timestamp) {
    const dt = timestamp - lastTime;
    // On mobile, cap at 30fps — skip frames arriving faster than ~33ms
    if (isMobile.value && dt < 33) {
        animId = requestAnimationFrame(loop);
        return;
    }
    lastTime = timestamp;
    try {
        if (dt < 150) step(bubbles.value, dragging.value?.id, badgeObstacles());
    } catch (e) {
        console.error('[Physics] Erro no step:', e);
    }
    animId = requestAnimationFrame(loop);
}

function startLoop() {
    if (animId !== null) return;
    lastTime = performance.now();
    animId = requestAnimationFrame(loop);
}

function stopLoop() {
    if (animId === null) return;
    cancelAnimationFrame(animId);
    animId = null;
}

function onVisibilityChange() {
    document.hidden ? stopLoop() : startLoop();
}

onMounted(async () => {
    ambientBubbles.value = generateAmbientBubbles();

    // Fire CSRF refresh without awaiting — not needed until the first POST (bubble create/connect)
    axios.get('/sanctum/csrf-cookie').catch(() => {});

    // Only block on bubble load — physics starts as soon as bubbles are ready.
    // Connections load in the background and add SVG lines when they arrive.
    await load(authUser.value?.id);
    _saveOnUnload = () => savePositions(authUser.value?.id);
    window.addEventListener('beforeunload', _saveOnUnload);
    ready.value = true;
    window.addEventListener('mousemove', onWindowMouseMove);
    window.addEventListener('mouseup', onWindowMouseUp);
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('touchmove', onWindowTouchMove, { passive: false });
    window.addEventListener('touchend', onWindowTouchEnd, { passive: false });
    window.addEventListener('resize', onMobileResize);
    document.addEventListener('visibilitychange', onVisibilityChange);
    document.addEventListener('click', onDocClick);
    startLoop();
    loadConnections();
    loadFriendConnections();
    if (authUser.value) {
        window.Echo.private(`user.${authUser.value.id}`)
            .listen('.BadgeCountUpdated', (e) => {
                if (e.type === 'friends') pendingFriends.value += e.delta;
                if (e.type === 'messages') unreadMessages.value += e.delta;
                if (e.type === 'notifications') { unreadNotifications.value += e.delta; playNotifSfx(); }
            });
        window.addEventListener('messages-read', (e) => {
            unreadMessages.value = Math.max(0, unreadMessages.value - e.detail.delta);
        });

        const { onlineUsers } = useOnlineUsers();
        window.Echo.join('online')
            .here((members) => {
                onlineUsers.value = new Set(members.map(m => m.id));
            })
            .joining((member) => {
                const s = new Set(onlineUsers.value);
                s.add(member.id);
                onlineUsers.value = s;
            })
            .leaving((member) => {
                const s = new Set(onlineUsers.value);
                s.delete(member.id);
                onlineUsers.value = s;
            });
    }
});

onUnmounted(() => {
    savePositions(authUser.value?.id);
    if (_saveOnUnload) window.removeEventListener('beforeunload', _saveOnUnload);
    window.removeEventListener('mousemove', onWindowMouseMove);
    window.removeEventListener('mouseup', onWindowMouseUp);
    window.removeEventListener('keydown', onKeyDown);
    window.removeEventListener('touchmove', onWindowTouchMove);
    window.removeEventListener('touchend', onWindowTouchEnd);
    window.removeEventListener('resize', onMobileResize);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    document.removeEventListener('click', onDocClick);
    stopLoop();
    if (authUser.value) {
        window.Echo.leave(`user.${authUser.value.id}`);
        window.Echo.leave('online');
    }
});

async function handleCreate(data) {
    const newBubble = await add(data.label, data.color, {
        title: data.title,
        description: data.description,
        tagline: data.tagline,
        coverColor: data.color,
        guidelines: data.guidelines,
    });
    showAdd.value = false;
    if (newBubble?.persisted) {
        playSfx('created');
        router.visit(route('community.show', newBubble.id));
    } else if (newBubble === null) {
        toast('Não foi possível criar a bolha. Tenta novamente.', 'error');
    }
}

const selectedBubble = computed(() => bubbles.value.find((b) => b.selected) ?? null);

const bubbleById = computed(() => new Map(bubbles.value.map((b) => [b.id, b])));

// ── Audio SFX ─────────────────────────────────────────────────────
watch(selectedBubble, (newVal, oldVal) => {
    if (!!newVal !== !!oldVal) {
        const keys = ['bubbleExpand', 'bubbleExpand2', 'bubbleExpand3'];
        playSfx(keys[Math.floor(Math.random() * keys.length)]);
    }
});
watch(feedOpen, () => {
    playSfx('feedBox');
});
function onBubbleEnter(id) {
    hoveredId.value = id;
    playHoverBubble();
}

const trends = computed(() => [...bubbles.value].sort((a, b) => b.members - a.members).slice(0, 6));

const trendsOpen = ref(window.innerWidth >= 640);
function toggleTrends() {
    trendsOpen.value = !trendsOpen.value;
    playSfx(trendsOpen.value ? 'openGlobal' : 'closeGlobal');
}
const isMobile = ref(window.innerWidth < 640);
const mobilePanelLeft = computed(() => `${Math.max(10, Math.min(window.innerWidth - 270, window.innerWidth / 2 - 130))}px`);
const mobilePanelTop = computed(() => `${window.innerHeight / 2 - 140}px`);
const ambientBubbles = ref([]);

function generateAmbientBubbles() {
    return Array.from({ length: 14 }, (_, index) => {
        const size = 14 + Math.round(Math.random() * 28 + (index % 4 === 0 ? 8 : 0));
        const left = `${(3 + Math.random() * 92).toFixed(1)}%`;
        const opacity = Number((0.18 + Math.random() * 0.45).toFixed(2));
        const duration = 11 + Math.round(Math.random() * 14);
        const delay = -Math.random() * 16;

        return {
            id: index + 1,
            size: `${size}px`,
            left,
            opacity,
            duration: `${duration}s`,
            delay: `${delay.toFixed(1)}s`,
        };
    });
}

function onMobileResize() { isMobile.value = window.innerWidth < 640; }
</script>

<template>
    <div
        class="w-screen h-screen overflow-hidden relative select-none"
        style="background: transparent; font-family: 'Segoe UI', system-ui, sans-serif; touch-action: none"
        @click.self="clearSelection"
        @touchend.self="clearSelection"
    >
        <!-- BACKGROUND GRID -->
        <svg class="absolute inset-0 w-full h-full pointer-events-none" style="opacity: 0.04">
            <defs>
                <pattern id="bg-grid" width="44" height="44" patternUnits="userSpaceOnUse">
                    <path d="M 44 0 L 0 0 0 44" fill="none" stroke="#009ac7" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#bg-grid)" />
        </svg>

        <!-- AMBIENT ORBS -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div
                style="
                    position: absolute;
                    top: -80px;
                    left: -80px;
                    width: 320px;
                    height: 320px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #009ac718 0%, transparent 70%);
                "
            />
            <div
                style="
                    position: absolute;
                    bottom: -60px;
                    right: -60px;
                    width: 280px;
                    height: 280px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #4ebcff14 0%, transparent 70%);
                "
            />
            <div
                style="
                    position: absolute;
                    top: 40%;
                    left: 65%;
                    width: 200px;
                    height: 200px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #9b6bdf0c 0%, transparent 70%);
                "
            />
        </div>

        <!-- FLOATING BACKGROUND BUBBLES -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div
                v-for="bubble in ambientBubbles"
                :key="bubble.id"
                class="fb"
                :style="{
                    left: bubble.left,
                    width: bubble.size,
                    height: bubble.size,
                    opacity: bubble.opacity,
                    animationDuration: bubble.duration,
                    animationDelay: bubble.delay,
                }"
            />
        </div>

        <!-- TOP BAR -->
        <div
            class="absolute top-0 left-0 right-0 z-40 flex items-center justify-between"
            :style="{
                background: 'var(--nav-bg)',
                backdropFilter: isMobile ? 'none' : 'blur(16px)',
                borderBottom: '1px solid var(--nav-border)',
                height: '58px',
                padding: isMobile ? '0 10px' : '0 24px',
            }"
        >
            <span style="font-weight: 900; font-size: 30px; color: #009ac7; letter-spacing: -1px; user-select: none"
                >bubbles</span
            >

            <div :style="{ display: 'flex', alignItems: 'center', gap: isMobile ? '1px' : '4px' }">
                <!-- Áudio -->
                <AudioControls v-if="authUser" sphere />

                <!-- Pesquisa -->
                <button
                    @click.stop="openSearch(); playSfx('search')"
                    :style="{
                        width: isMobile ? '30px' : '36px',
                        height: isMobile ? '30px' : '36px',
                        borderRadius: '10px',
                        border: 'none',
                        background: 'transparent',
                        color: 'var(--text-2)',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        transition: 'background .15s',
                    }"
                    @mouseenter="$event.currentTarget.style.background = '#009ac714'"
                    @mouseleave="$event.currentTarget.style.background = 'transparent'"
                    title="Pesquisar (Ctrl+K)"
                >
                    <svg :width="isMobile ? '24' : '36'" :height="isMobile ? '24' : '36'" viewBox="0 0 52 52" style="display:block">
                        <defs>
                            <radialGradient id="grad-search" cx="40%" cy="30%" r="65%">
                                <stop offset="0%" stop-color="#7de8ff"/>
                                <stop offset="100%" stop-color="#005a85"/>
                            </radialGradient>
                        </defs>
                        <circle cx="26" cy="26" r="24" fill="url(#grad-search)" fill-opacity=".58" stroke="rgba(255,255,255,.12)" stroke-width="1"/>
                        <ellipse cx="19" cy="17" rx="9" ry="5" fill="rgba(255,255,255,.30)" transform="rotate(-15,19,17)"/>
                        <g transform="translate(8,8) scale(1.5)" fill="none" stroke="white" stroke-width="1.47" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </g>
                    </svg>
                </button>

                <!-- Feed toggle -->
                <button
                    @click.stop="toggleFeed()"
                    :style="{
                        width: isMobile ? '30px' : '36px',
                        height: isMobile ? '30px' : '36px',
                        borderRadius: '10px',
                        border: 'none',
                        background: feedOpen ? '#009ac714' : 'transparent',
                        color: feedOpen ? '#009ac7' : 'var(--text-2)',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        transition: 'background .15s, color .15s',
                    }"
                    @mouseenter="$event.currentTarget.style.background = '#009ac714'"
                    @mouseleave="$event.currentTarget.style.background = feedOpen ? '#009ac714' : 'transparent'"
                    title="Feed"
                >
                    <svg :width="isMobile ? '24' : '36'" :height="isMobile ? '24' : '36'" viewBox="0 0 52 52" style="display:block">
                        <defs>
                            <radialGradient id="grad-feed" cx="40%" cy="30%" r="65%">
                                <stop offset="0%" stop-color="#7de8ff"/>
                                <stop offset="100%" stop-color="#005a85"/>
                            </radialGradient>
                        </defs>
                        <circle cx="26" cy="26" r="24" fill="url(#grad-feed)" fill-opacity=".58" stroke="rgba(255,255,255,.12)" stroke-width="1"/>
                        <ellipse cx="19" cy="17" rx="9" ry="5" fill="rgba(255,255,255,.30)" transform="rotate(-15,19,17)"/>
                        <g transform="translate(8,8) scale(1.5)" fill="none" stroke="white" stroke-width="1.47" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6h16M4 10h16M4 14h12M4 18h8"/>
                        </g>
                    </svg>
                </button>

                <!-- Hamburger → Nova bolha -->
                <button
                    @click.stop="showAdd = !showAdd; playSfx('newBubble')"
                    :style="{
                        width: isMobile ? '30px' : '36px',
                        height: isMobile ? '30px' : '36px',
                        borderRadius: '10px',
                        border: 'none',
                        background: showAdd ? '#009ac714' : 'transparent',
                        color: 'var(--text-2)',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        transition: 'background .15s',
                    }"
                    @mouseenter="$event.currentTarget.style.background = '#009ac714'"
                    @mouseleave="$event.currentTarget.style.background = showAdd ? '#009ac714' : 'transparent'"
                    title="Nova bolha"
                >
                    <svg :width="isMobile ? '24' : '36'" :height="isMobile ? '24' : '36'" viewBox="0 0 52 52" style="display:block">
                        <defs>
                            <radialGradient id="grad-bubble" cx="40%" cy="30%" r="65%">
                                <stop offset="0%" stop-color="#7de8ff"/>
                                <stop offset="100%" stop-color="#005a85"/>
                            </radialGradient>
                        </defs>
                        <circle cx="26" cy="26" r="24" fill="url(#grad-bubble)" fill-opacity=".58" stroke="rgba(255,255,255,.12)" stroke-width="1"/>
                        <ellipse cx="19" cy="17" rx="9" ry="5" fill="rgba(255,255,255,.30)" transform="rotate(-15,19,17)"/>
                        <g transform="translate(8,8) scale(2)">
                            <rect x="8.2" y="2" width="1.6" height="14" rx=".8" fill="white"/>
                            <rect x="2" y="8.2" width="14" height="1.6" rx=".8" fill="white"/>
                        </g>
                    </svg>
                </button>

                <!-- Mensagens -->
                <Link
                    v-if="authUser"
                    :href="route('conversations.index')"
                    :style="{
                        width: isMobile ? '30px' : '36px',
                        height: isMobile ? '30px' : '36px',
                        borderRadius: '10px',
                        background: 'transparent',
                        color: 'var(--text-2)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        transition: 'background .15s',
                        textDecoration: 'none',
                        position: 'relative',
                    }"
                    @mouseenter="$event.currentTarget.style.background = '#009ac714'"
                    @mouseleave="$event.currentTarget.style.background = 'transparent'"
                    title="Mensagens"
                    @click="playClick()"
                >
                    <svg :width="isMobile ? '24' : '36'" :height="isMobile ? '24' : '36'" viewBox="0 0 52 52" style="display:block">
                        <defs>
                            <radialGradient id="grad-msg" cx="40%" cy="30%" r="65%">
                                <stop offset="0%" stop-color="#7de8ff"/>
                                <stop offset="100%" stop-color="#005a85"/>
                            </radialGradient>
                        </defs>
                        <circle cx="26" cy="26" r="24" fill="url(#grad-msg)" fill-opacity=".58" stroke="rgba(255,255,255,.12)" stroke-width="1"/>
                        <ellipse cx="19" cy="17" rx="9" ry="5" fill="rgba(255,255,255,.30)" transform="rotate(-15,19,17)"/>
                        <g transform="translate(8,8) scale(2)" fill="none" stroke="white" stroke-width="0.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 2.5H3a1 1 0 00-1 1v7.5a1 1 0 001 1h3.5l2.5 3 2.5-3H15a1 1 0 001-1V3.5a1 1 0 00-1-1z"/>
                        </g>
                    </svg>
                    <span
                        v-if="unreadMessages > 0"
                        style="
                            position: absolute;
                            top: 4px;
                            right: 4px;
                            min-width: 14px;
                            height: 14px;
                            padding: 0 3px;
                            background: #009ac7;
                            color: white;
                            border-radius: 99px;
                            font-size: 9px;
                            font-weight: 800;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            line-height: 1;
                        "
                        >{{ unreadMessages }}</span
                    >
                </Link>

                <!-- Notificações -->
                <Link
                    v-if="authUser"
                    :href="route('notifications.index')"
                    :style="{
                        width: isMobile ? '30px' : '36px',
                        height: isMobile ? '30px' : '36px',
                        borderRadius: '10px',
                        background: 'transparent',
                        color: 'var(--text-2)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        transition: 'background .15s',
                        textDecoration: 'none',
                        position: 'relative',
                    }"
                    @mouseenter="$event.currentTarget.style.background = '#009ac714'"
                    @mouseleave="$event.currentTarget.style.background = 'transparent'"
                    title="Notificações"
                    @click="playSfx('notificationPage')"
                >
                    <svg :width="isMobile ? '24' : '36'" :height="isMobile ? '24' : '36'" viewBox="0 0 52 52" style="display:block">
                        <defs>
                            <radialGradient id="grad-notif" cx="40%" cy="30%" r="65%">
                                <stop offset="0%" stop-color="#5ee0a0"/>
                                <stop offset="100%" stop-color="#1a6e3a"/>
                            </radialGradient>
                        </defs>
                        <circle cx="26" cy="26" r="24" fill="url(#grad-notif)" fill-opacity=".58" stroke="rgba(255,255,255,.12)" stroke-width="1"/>
                        <ellipse cx="19" cy="17" rx="9" ry="5" fill="rgba(255,255,255,.30)" transform="rotate(-15,19,17)"/>
                        <g transform="translate(8,8) scale(1.5)" fill="none" stroke="white" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </g>
                    </svg>
                    <span
                        v-if="unreadNotifications > 0"
                        style="
                            position: absolute;
                            top: 4px;
                            right: 4px;
                            min-width: 14px;
                            height: 14px;
                            padding: 0 3px;
                            background: #c74a6b;
                            color: white;
                            border-radius: 99px;
                            font-size: 9px;
                            font-weight: 800;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            line-height: 1;
                        "
                        >{{ unreadNotifications > 9 ? '9+' : unreadNotifications }}</span
                    >
                </Link>

                <!-- Amigos -->
                <Link
                    v-if="authUser"
                    :href="route('friends.index')"
                    :style="{
                        width: isMobile ? '30px' : '36px',
                        height: isMobile ? '30px' : '36px',
                        borderRadius: '10px',
                        background: 'transparent',
                        color: 'var(--text-2)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        transition: 'background .15s',
                        textDecoration: 'none',
                        position: 'relative',
                    }"
                    @mouseenter="$event.currentTarget.style.background = '#009ac714'"
                    @mouseleave="$event.currentTarget.style.background = 'transparent'"
                    title="Amigos"
                    @click="playClick()"
                >
                    <svg :width="isMobile ? '24' : '36'" :height="isMobile ? '24' : '36'" viewBox="0 0 52 52" style="display:block">
                        <defs>
                            <radialGradient id="grad-friends" cx="40%" cy="30%" r="65%">
                                <stop offset="0%" stop-color="#7de8ff"/>
                                <stop offset="100%" stop-color="#005a85"/>
                            </radialGradient>
                        </defs>
                        <circle cx="26" cy="26" r="24" fill="url(#grad-friends)" fill-opacity=".58" stroke="rgba(255,255,255,.12)" stroke-width="1"/>
                        <ellipse cx="19" cy="17" rx="9" ry="5" fill="rgba(255,255,255,.30)" transform="rotate(-15,19,17)"/>
                        <g transform="translate(8,8) scale(2)" fill="none" stroke="white" stroke-width="0.7" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="6.5" cy="5.5" r="2.3"/>
                            <path d="M1.5 14.5c.8-2.5 2.8-3.8 5-3.8s4.2 1.3 5 3.8"/>
                            <path d="M13 7.5a2 2 0 010 4m2.5 3c-.6-1.8-1.8-2.8-3-3"/>
                        </g>
                    </svg>
                    <span
                        v-if="pendingFriends > 0"
                        style="
                            position: absolute;
                            top: 4px;
                            right: 4px;
                            min-width: 14px;
                            height: 14px;
                            padding: 0 3px;
                            background: #c74a6b;
                            color: white;
                            border-radius: 99px;
                            font-size: 9px;
                            font-weight: 800;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            line-height: 1;
                        "
                        >{{ pendingFriends }}</span
                    >
                </Link>

                <!-- Menu de definições (gear) -->
                <div ref="menuEl" v-if="authUser && !isMobile" style="position: relative">
                    <button
                        @click.stop="menuOpen = !menuOpen; playClick()"
                        :style="{
                            width: '36px',
                            height: '36px',
                            borderRadius: '10px',
                            border: 'none',
                            background: menuOpen ? '#009ac714' : 'transparent',
                            color: menuOpen ? '#009ac7' : 'var(--text-2)',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            transition: 'background .15s, color .15s',
                        }"
                        @mouseenter="$event.currentTarget.style.background = '#009ac714'"
                        @mouseleave="$event.currentTarget.style.background = menuOpen ? '#009ac714' : 'transparent'"
                        aria-label="Menu de definições"
                        :aria-expanded="menuOpen"
                        aria-haspopup="menu"
                        title="Definições"
                    >
                        <svg :width="isMobile ? '24' : '36'" :height="isMobile ? '24' : '36'" viewBox="0 0 52 52" style="display:block">
                            <defs>
                                <radialGradient id="grad-gear" cx="40%" cy="30%" r="65%">
                                    <stop offset="0%" stop-color="#c08af0"/>
                                    <stop offset="100%" stop-color="#4a1d90"/>
                                </radialGradient>
                            </defs>
                            <circle cx="26" cy="26" r="24" fill="url(#grad-gear)" fill-opacity=".58" stroke="rgba(255,255,255,.12)" stroke-width="1"/>
                            <ellipse cx="19" cy="17" rx="9" ry="5" fill="rgba(255,255,255,.30)" transform="rotate(-15,19,17)"/>
                            <g transform="translate(8,8) scale(1.5)" fill="none" stroke="white" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                            </g>
                        </svg>
                    </button>

                    <!-- Dropdown -->
                    <Transition name="fade">
                        <div
                            v-if="menuOpen"
                            role="menu"
                            style="position: absolute; top: 44px; right: 0; width: 210px; background: var(--dropdown-bg); backdrop-filter: blur(20px); border-radius: 14px; border: 1px solid var(--nav-border); box-shadow: 0 8px 32px rgba(0,0,0,0.18); padding: 6px; z-index: 100; display: flex; flex-direction: column; gap: 1px;"
                            @click.stop
                        >
                            <!-- Cabeçalho -->
                            <div style="padding: 10px 12px 8px; border-bottom: 1px solid var(--dropdown-sep); margin-bottom: 4px;">
                                <p style="font-size: 13px; font-weight: 800; color: var(--text); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ authUser.name }}</p>
                                <p v-if="authUser.username" style="font-size: 11px; color: #009ac7; margin: 2px 0 0;">@{{ authUser.username }}</p>
                            </div>

                            <!-- O meu perfil -->
                            <Link
                                :href="authUser.username ? route('profile.show', authUser.username) : route('profile.edit')"
                                @click="menuOpen = false; playClick()"
                                role="menuitem"
                                style="display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; text-decoration: none; color: var(--text); font-size: 13px; font-weight: 600; transition: background .12s; cursor: pointer;"
                                @mouseenter="$event.currentTarget.style.background = 'var(--item-hover)'"
                                @mouseleave="$event.currentTarget.style.background = 'transparent'"
                            >
                                <svg width="15" height="15" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="5.5" r="2.8" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 15c1-3 3.5-4.5 6.5-4.5s5.5 1.5 6.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                O meu perfil
                            </Link>

                            <!-- Definições -->
                            <Link
                                :href="route('profile.edit')"
                                @click="menuOpen = false; playClick()"
                                role="menuitem"
                                style="display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; text-decoration: none; color: var(--text); font-size: 13px; font-weight: 600; transition: background .12s; cursor: pointer;"
                                @mouseenter="$event.currentTarget.style.background = 'var(--item-hover)'"
                                @mouseleave="$event.currentTarget.style.background = 'transparent'"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                                Definições
                            </Link>

                            <!-- Separador -->
                            <div style="height: 1px; background: var(--dropdown-sep); margin: 4px 0;" />

                            <!-- Tema -->
                            <button
                                @click.stop="toggleTheme(); menuOpen = false; playClick()"
                                role="menuitem"
                                style="display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; color: var(--text); font-size: 13px; font-weight: 600; transition: background .12s; cursor: pointer; border: none; background: transparent; width: 100%; font-family: inherit; text-align: left;"
                                @mouseenter="$event.currentTarget.style.background = 'var(--item-hover)'"
                                @mouseleave="$event.currentTarget.style.background = 'transparent'"
                            >
                                <span>{{ isDark ? '☀️' : '🌙' }}</span>
                                {{ isDark ? 'Tema claro' : 'Tema escuro' }}
                            </button>

                            <!-- Admin -->
                            <Link
                                v-if="isAdmin"
                                :href="route('admin.dashboard')"
                                @click="menuOpen = false; playClick()"
                                role="menuitem"
                                style="display: block; padding: 9px 12px; border-radius: 10px; text-decoration: none; color: #9b6bdf; font-size: 13px; font-weight: 700; transition: background .12s; cursor: pointer;"
                                @mouseenter="$event.currentTarget.style.background = 'var(--item-hover)'"
                                @mouseleave="$event.currentTarget.style.background = 'transparent'"
                            >
                                ⊞ Painel Admin
                            </Link>

                            <!-- Separador -->
                            <div style="height: 1px; background: var(--dropdown-sep); margin: 4px 0;" />

                            <!-- Sair -->
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                @click="stopBgm(); menuOpen = false; playClick()"
                                role="menuitem"
                                style="display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; text-decoration: none; color: #e05555; font-size: 13px; font-weight: 600; transition: background .12s; cursor: pointer; border: none; background: transparent; width: 100%; font-family: inherit; text-align: left;"
                                @mouseenter="$event.currentTarget.style.background = 'var(--item-hover-red)'"
                                @mouseleave="$event.currentTarget.style.background = 'transparent'"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                                Sair
                            </Link>
                        </div>
                    </Transition>
                </div>

                <!-- Divisor -->
                <div v-if="!isMobile" style="width: 1px; height: 20px; background: var(--nav-border); margin: 0 6px" />

                <!-- Avatar do utilizador → perfil -->
                <Link
                    v-if="authUser && authUser.username"
                    :href="route('profile.show', authUser.username)"
                    @click="playClick()"
                    style="
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                        text-decoration: none;
                        transition: box-shadow 0.2s;
                        border-radius: 50%;
                    "
                >
                    <img
                        v-if="authUser.avatar"
                        :src="clImg(authUser.avatar, 64, 64, 'fill', 'face')"
                        :style="avatarChipStyle(32, authUser.avatar_color ?? '#009ac7')"
                    />
                    <div
                        v-else
                        :style="avatarFallbackStyle(32, authUser.avatar_color ?? '#009ac7')"
                    >
                        {{ authUser.name?.[0]?.toUpperCase() ?? '?' }}
                    </div>
                </Link>
                <div
                    v-else-if="authUser"
                    :style="avatarFallbackStyle(32, authUser.avatar_color ?? '#009ac7')"
                >
                    {{ authUser.name?.[0]?.toUpperCase() ?? '?' }}
                </div>
            </div>
        </div>

        <!-- SEARCH OVERLAY -->
        <Transition name="overlay">
            <div
                v-if="searchOpen"
                style="
                    position: fixed;
                    inset: 0;
                    z-index: 100;
                    background: rgba(0, 0, 0, 0.28);
                    backdrop-filter: blur(4px);
                    display: flex;
                    align-items: flex-start;
                    justify-content: center;
                    padding-top: 80px;
                "
                @click="closeSearch"
            >
                <div @click.stop style="width: 100%; max-width: 560px; margin: 0 20px">
                    <!-- Input -->
                    <form @submit.prevent="submitSearch" style="position: relative">
                        <svg
                            style="
                                position: absolute;
                                left: 18px;
                                top: 50%;
                                transform: translateY(-50%);
                                color: #8ba0b0;
                                pointer-events: none;
                            "
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input
                            id="bubble-search-input"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Pesquisa pessoas, comunidades ou posts…"
                            style="
                                width: 100%;
                                box-sizing: border-box;
                                background: var(--search-input-bg);
                                border: none;
                                border-radius: 16px;
                                padding: 16px 50px 16px 50px;
                                font-size: 16px;
                                color: var(--text);
                                outline: none;
                                font-family: inherit;
                                box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
                            "
                            @keydown="handleSearchKey"
                        />
                        <!-- Loading spinner ou Esc hint -->
                        <div style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%)">
                            <svg
                                v-if="searchLoading"
                                style="color: #009ac7; animation: spin 1s linear infinite"
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                            >
                                <path
                                    d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"
                                />
                            </svg>
                            <kbd
                                v-else
                                style="
                                    font-size: 11px;
                                    color: var(--text-4);
                                    background: var(--kbd-bg);
                                    border-radius: 6px;
                                    padding: 2px 7px;
                                    font-family: inherit;
                                "
                                >Esc</kbd
                            >
                        </div>
                    </form>

                    <!-- Resultados inline -->
                    <div
                        v-if="searchQuery.trim() && (hasResults || searchResults || searchLoading)"
                        style="
                            margin-top: 8px;
                            background: var(--dropdown-bg);
                            border-radius: 16px;
                            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.18);
                            overflow: hidden;
                            max-height: 420px;
                            overflow-y: auto;
                        "
                        @mouseleave="activeSearchIdx = -1"
                    >
                        <!-- Erro de rede -->
                        <div
                            v-if="searchError"
                            style="padding: 24px; text-align: center; color: #e05555; font-size: 13px; font-weight: 600"
                        >
                            Erro de rede. Tenta novamente.
                        </div>

                        <!-- Sem resultados -->
                        <div
                            v-else-if="searchResults && !hasResults && !searchLoading"
                            style="padding: 24px; text-align: center; color: var(--text-3); font-size: 13px"
                        >
                            Sem resultados para "{{ searchQuery }}"
                        </div>

                        <template v-if="hasResults">
                            <!-- Pessoas -->
                            <template v-if="searchResults.users?.length">
                                <p
                                    style="
                                        font-size: 10px;
                                        font-weight: 800;
                                        color: var(--text-3);
                                        text-transform: uppercase;
                                        letter-spacing: 0.1em;
                                        margin: 0;
                                        padding: 12px 16px 6px;
                                    "
                                >
                                    Pessoas
                                </p>
                                <div
                                    v-for="(u, i) in searchResults.users.slice(0, 4)"
                                    :key="'u' + u.id"
                                    @click="goToResult(u.username ? route('profile.show', u.username) : '#')"
                                    :style="{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '12px',
                                        padding: '10px 16px',
                                        cursor: 'pointer',
                                        transition: 'background 0.12s',
                                        background: activeSearchIdx === searchOffsets.users + i ? 'var(--item-hover)' : 'transparent',
                                    }"
                                    @mouseenter="activeSearchIdx = searchOffsets.users + i"
                                >
                                    <img
                                        v-if="u.avatar"
                                        :src="clImg(u.avatar, 72, 72, 'fill', 'face')"
                                        :style="avatarChipStyle(36, u.avatar_color)"
                                    />
                                    <div
                                        v-else
                                        :style="avatarFallbackStyle(36, u.avatar_color)"
                                    >
                                        {{ (u.name ?? '?')[0].toUpperCase() }}
                                    </div>
                                    <div style="flex: 1; min-width: 0">
                                        <p
                                            style="
                                                font-size: 13px;
                                                font-weight: 700;
                                                color: var(--text);
                                                margin: 0;
                                                white-space: nowrap;
                                                overflow: hidden;
                                                text-overflow: ellipsis;
                                            "
                                        >
                                            {{ u.name }}
                                        </p>
                                        <p v-if="u.username" style="font-size: 11px; color: #009ac7; margin: 0">
                                            @{{ u.username }}
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <!-- Comunidades -->
                            <template v-if="searchResults.communities?.length">
                                <div style="height: 1px; background: var(--dropdown-sep); margin: 4px 0" />
                                <p
                                    style="
                                        font-size: 10px;
                                        font-weight: 800;
                                        color: var(--text-3);
                                        text-transform: uppercase;
                                        letter-spacing: 0.1em;
                                        margin: 0;
                                        padding: 8px 16px 6px;
                                    "
                                >
                                    Comunidades
                                </p>
                                <div
                                    v-for="(c, i) in searchResults.communities.slice(0, 4)"
                                    :key="'c' + c.id"
                                    @click="goToResult(route('community.show', c.id))"
                                    :style="{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '12px',
                                        padding: '10px 16px',
                                        cursor: 'pointer',
                                        transition: 'background 0.12s',
                                        background: activeSearchIdx === searchOffsets.communities + i ? 'var(--item-hover)' : 'transparent',
                                    }"
                                    @mouseenter="activeSearchIdx = searchOffsets.communities + i"
                                >
                                    <div
                                        :style="{
                                            width: '36px',
                                            height: '36px',
                                            borderRadius: '50%',
                                            flexShrink: '0',
                                            overflow: 'hidden',
                                            boxShadow: `0 3px 10px ${c.color}44`,
                                            background: `radial-gradient(circle at 38% 32%, ${c.color}ee 0%, ${c.color} 60%)`,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                        }"
                                    >
                                        <img
                                            v-if="c.image"
                                            :src="c.image"
                                            style="width: 100%; height: 100%; object-fit: cover; display: block"
                                        />
                                        <span v-else style="font-size: 9px; font-weight: 800; color: white">{{
                                            c.label?.slice(0, 3)
                                        }}</span>
                                    </div>
                                    <div style="flex: 1; min-width: 0">
                                        <p
                                            style="
                                                font-size: 13px;
                                                font-weight: 700;
                                                color: var(--text);
                                                margin: 0;
                                                white-space: nowrap;
                                                overflow: hidden;
                                                text-overflow: ellipsis;
                                            "
                                        >
                                            {{ c.title }}
                                        </p>
                                        <p style="font-size: 11px; color: var(--text-3); margin: 0">
                                            {{ c.members }} membros
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <!-- Posts -->
                            <template v-if="searchResults.posts?.length">
                                <div style="height: 1px; background: var(--dropdown-sep); margin: 4px 0" />
                                <p
                                    style="
                                        font-size: 10px;
                                        font-weight: 800;
                                        color: var(--text-3);
                                        text-transform: uppercase;
                                        letter-spacing: 0.1em;
                                        margin: 0;
                                        padding: 8px 16px 6px;
                                    "
                                >
                                    Posts
                                </p>
                                <div
                                    v-for="(p, i) in searchResults.posts.slice(0, 3)"
                                    :key="'p' + p.id"
                                    @click="goToResult(p.author.username ? route('profile.show', p.author.username) : '#')"
                                    :style="{
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        gap: '10px',
                                        padding: '10px 16px',
                                        cursor: 'pointer',
                                        transition: 'background 0.12s',
                                        background: activeSearchIdx === searchOffsets.posts + i ? 'var(--item-hover)' : 'transparent',
                                    }"
                                    @mouseenter="activeSearchIdx = searchOffsets.posts + i"
                                >
                                    <img
                                        v-if="p.author.avatar"
                                        :src="clImg(p.author.avatar, 48, 48, 'fill', 'face')"
                                        :style="avatarChipStyle(28, p.author.avatar_color)"
                                    />
                                    <div
                                        v-else
                                        :style="avatarFallbackStyle(28, p.author.avatar_color)"
                                    >
                                        {{ (p.author.name ?? '?')[0].toUpperCase() }}
                                    </div>
                                    <div style="flex: 1; min-width: 0">
                                        <p style="font-size: 12px; font-weight: 700; color: var(--text); margin: 0 0 2px">
                                            {{ p.author.name }}
                                        </p>
                                        <p
                                            style="
                                                font-size: 11px;
                                                color: var(--text-2);
                                                margin: 0;
                                                overflow: hidden;
                                                text-overflow: ellipsis;
                                                white-space: nowrap;
                                            "
                                        >
                                            {{ p.content }}
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <!-- Ver todos -->
                            <div
                                @click="viewAllResults"
                                :style="{
                                    padding: '12px 16px',
                                    textAlign: 'center',
                                    fontSize: '12px',
                                    fontWeight: '700',
                                    color: '#009ac7',
                                    cursor: 'pointer',
                                    borderTop: '1px solid var(--dropdown-sep)',
                                    transition: 'background 0.12s',
                                    background: activeSearchIdx === searchOffsets.all ? 'var(--item-hover)' : 'transparent',
                                }"
                                @mouseenter="activeSearchIdx = searchOffsets.all"
                            >
                                Ver todos os resultados →
                            </div>
                        </template>
                    </div>

                    <p
                        v-if="!searchQuery.trim()"
                        style="font-size: 11px; color: rgba(255, 255, 255, 0.7); margin: 10px 0 0; text-align: center"
                    >
                        Enter para pesquisar · Esc para fechar
                    </p>
                </div>
            </div>
        </Transition>

        <!-- MODAL: Nova bolha -->
        <CreateCommunityModal v-if="showAdd" @create="handleCreate" @cancel="showAdd = false" />

        <!-- LOADING -->
        <div
            v-if="!ready"
            style="
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                pointer-events: none;
                z-index: 10;
            "
        >
            <div style="display: flex; flex-direction: column; align-items: center; gap: 14px; opacity: 0.6">
                <div class="bubble-loader">
                    <div v-for="i in 8" :key="i" :class="`bl-arm bl-arm-${i}`"><div class="bl-dot"></div></div>
                </div>
                <span style="font-size: 12px; font-weight: 600; color: #009ac7; letter-spacing: 0.04em"
                    >A carregar bolhas...</span
                >
            </div>
        </div>

        <!-- EMPTY STATE -->
        <div
            v-if="ready && !bubbles.length"
            style="
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                pointer-events: none;
                z-index: 10;
            "
        >
            <div style="display: flex; flex-direction: column; align-items: center; gap: 10px; opacity: 0.55; text-align: center; padding: 0 24px">
                <span style="font-size: 36px">🫧</span>
                <span style="font-size: 13px; font-weight: 700; color: #009ac7">Ainda não há comunidades</span>
                <span style="font-size: 11px; color: #8ba0b0">Cria a primeira com o botão +</span>
            </div>
        </div>

        <!-- SVG LAYER: connections + avatars -->
        <ConnectionLines
            v-if="ready"
            :connections="connections"
            :friend-connections="friendConnections"
            :bubbles="bubbles"
        />

        <!-- BUBBLES -->
        <Bubble
            v-for="b in bubbles"
            :key="b.id"
            :bubble="b"
            :isDragging="dragging?.id === b.id"
            :isHovered="hoveredId === b.id"
            :anyHovered="hoveredId !== null"
            @mousedown="startDrag(b, $event)"
            @touchstart="startTouch(b, $event)"
            @mouseenter="onBubbleEnter(b.id)"
            @mouseleave="onBubbleLeave(b.id)"
        />

        <!-- EXPANDED BUBBLE PANEL -->
        <Transition name="bubble-expand">
            <div
                v-if="selectedBubble"
                :style="{
                    position: 'absolute',
                    zIndex: 36,
                    left: isMobile
                        ? mobilePanelLeft
                        : `${selectedBubble.x + selectedBubble.size / 2 - 150}px`,
                    top: isMobile
                        ? mobilePanelTop
                        : `${selectedBubble.y + selectedBubble.size / 2 - 150}px`,
                    width: isMobile ? '260px' : '300px',
                    height: isMobile ? '260px' : '300px',
                    borderRadius: '50%',
                    backgroundImage: selectedBubble.image
                        ? `radial-gradient(circle at 38% 32%, ${selectedBubble.color}55 0%, ${selectedBubble.color}99 100%), url(${selectedBubble.image})`
                        : `radial-gradient(circle at 38% 32%, ${selectedBubble.color}ee 0%, ${selectedBubble.color} 60%)`,
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    boxShadow: `0 0 0 5px white, 0 0 0 9px ${selectedBubble.color}88, 0 24px 64px ${selectedBubble.color}55`,
                    overflow: 'hidden',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    cursor: 'default',
                }"
                @click.stop
                @mousedown.stop
                @touchstart.stop
                @touchend.stop
            >
                <!-- Glass highlight -->
                <div
                    style="
                        position: absolute;
                        top: 18px;
                        left: 16%;
                        width: 68%;
                        height: 28%;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.16);
                        pointer-events: none;
                        transform: rotate(-10deg);
                    "
                />

                <!-- Close button -->
                <button
                    @click.stop="clearSelectionForced"
                    :style="{
                        position: 'absolute',
                        top: isMobile ? '44px' : '54px',
                        right: isMobile ? '44px' : '54px',
                        background: 'rgba(255,255,255,0.22)',
                        border: 'none',
                        borderRadius: '50%',
                        width: '26px',
                        height: '26px',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontSize: '16px',
                        lineHeight: 1,
                        color: 'white',
                        fontWeight: '400',
                        zIndex: 2,
                        transition: 'background .15s',
                    }"
                    @mouseenter="$event.currentTarget.style.background = 'rgba(255,255,255,0.38)'"
                    @mouseleave="$event.currentTarget.style.background = 'rgba(255,255,255,0.22)'"
                >
                    ×
                </button>

                <!-- Label -->
                <span
                    :style="{
                        fontSize: '18px',
                        fontWeight: '900',
                        color: 'white',
                        letterSpacing: '-.02em',
                        textShadow: '0 2px 10px rgba(0,0,0,.28)',
                        textAlign: 'center',
                        position: 'relative',
                        zIndex: 1,
                    }"
                    >{{ selectedBubble.label }}</span
                >

                <!-- Members -->
                <span
                    :style="{
                        fontSize: '10px',
                        color: 'rgba(255,255,255,.72)',
                        fontWeight: '600',
                        position: 'relative',
                        zIndex: 1,
                        marginBottom: '10px',
                    }"
                    >{{ selectedBubble.members }} membros</span
                >

                <!-- Avatar row -->
                <div
                    :style="{
                        display: 'flex',
                        gap: '5px',
                        marginBottom: '10px',
                        position: 'relative',
                        zIndex: 1,
                    }"
                >
                    <div
                        v-for="av in (selectedBubble.avatars || []).slice(0, 4)"
                        :key="av.id"
                        :title="av.name"
                        :style="{
                            width: '30px',
                            height: '30px',
                            borderRadius: '50%',
                            background: 'rgba(255,255,255,0.28)',
                            border: '2px solid rgba(255,255,255,0.55)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: '12px',
                            fontWeight: '800',
                            color: 'white',
                        }"
                    >
                        {{ av.name[0] }}
                    </div>
                </div>

                <!-- Mini posts -->
                <div
                    :style="{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: '5px',
                        width: '72%',
                        position: 'relative',
                        zIndex: 1,
                    }"
                >
                    <div
                        v-for="p in (selectedBubble.posts || []).slice(0, 3)"
                        :key="p.id"
                        :style="{
                            background: 'rgba(255,255,255,0.18)',
                            borderRadius: '8px',
                            padding: '5px 9px',
                        }"
                    >
                        <span :style="{ fontSize: '9px', fontWeight: '800', color: 'rgba(255,255,255,.95)' }">{{
                            p.author
                        }}</span>
                        <span :style="{ fontSize: '9px', color: 'rgba(255,255,255,.72)', marginLeft: '5px' }">{{
                            p.text
                        }}</span>
                    </div>
                </div>

                <!-- Enter button → community page (só para bolhas persistidas) -->
                <Link
                    v-if="selectedBubble.persisted"
                    :href="route('community.show', selectedBubble.id)"
                    :style="{
                        marginTop: '14px',
                        padding: '8px 22px',
                        borderRadius: '99px',
                        background: 'rgba(255,255,255,0.20)',
                        border: '1.5px solid rgba(255,255,255,0.52)',
                        color: 'white',
                        fontSize: '11px',
                        fontWeight: '800',
                        cursor: 'pointer',
                        position: 'relative',
                        zIndex: 1,
                        letterSpacing: '.04em',
                        transition: 'background .2s',
                        textDecoration: 'none',
                        display: 'inline-block',
                    }"
                    @mouseenter="$event.currentTarget.style.background = 'rgba(255,255,255,0.34)'"
                    @mouseleave="$event.currentTarget.style.background = 'rgba(255,255,255,0.20)'"
                    @click.stop="playSfx('back')"
                    >Entrar na comunidade →</Link
                >
                <span
                    v-else
                    :style="{
                        marginTop: '12px',
                        fontSize: '9px',
                        color: 'rgba(255,255,255,.45)',
                        position: 'relative',
                        zIndex: 1,
                        textAlign: 'center',
                        padding: '0 24px',
                        lineHeight: 1.4,
                    }"
                    >Demo · Cria uma comunidade com ≡</span
                >
            </div>
        </Transition>

        <!-- Overlay: absorbs the synthetic click Chrome fires after a touch-to-select.
             handleOverlayClick has the time guard; @touchend.prevent="clearSelection"
             is called for genuine tap-to-close (no guard needed, always intentional).
             @click.stop prevents the (now-handled) click from reaching the root div. -->
        <div
            v-if="selectedBubble"
            style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 35;"
            @click.stop="handleOverlayClick"
            @touchend.prevent="handleOverlayTouchEnd"
        />

        <!-- GLOBAL TRENDS SIDEBAR -->
        <TrendsSidebar
            :trends="trends"
            :isMobile="isMobile"
            :open="trendsOpen"
            @toggle="toggleTrends"
            @select="toggleSelect"
        />

        <!-- FEED PANEL -->
        <FeedPanel
            :open="feedOpen"
            :feed="localFeed"
            :auth-user="authUser"
            :isMobile="isMobile"
            :has-more="localHasMore"
            :loading="feedLoading"
            @load-more="loadMoreFeed"
        />

        <ToastContainer />
        <AnnouncementModal />

        <!-- ANNOUNCEMENT BANNER — strip below top bar -->
        <div style="position: absolute; top: 58px; left: 0; right: 0; z-index: 39">
            <AnnouncementBanner />
        </div>

        <!-- FLOOR GRADIENT -->
        <div
            style="
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 100px;
                background: linear-gradient(to top, var(--floor-gradient) 0%, transparent 100%);
                pointer-events: none;
                z-index: 1;
            "
        />

        <!-- HINT -->
        <div
            style="
                position: absolute;
                bottom: calc(12px + env(safe-area-inset-bottom, 0px));
                left: 50%;
                transform: translateX(-50%);
                z-index: 10;
                pointer-events: none;
            "
        >
            <span
                style="
                    font-size: 10px;
                    color: #009ac7aa;
                    background: var(--surface);
                    padding: 5px 16px;
                    border-radius: 99px;
                    backdrop-filter: blur(8px);
                "
            >
                {{ isMobile ? 'Toca para expandir · Arrasta para mover' : 'Segura para arrastar · Clica para expandir · Esc para fechar' }}
            </span>
        </div>

        <!-- ── Punishment notification modal ─────────────────────── -->
        <PunishmentModal />
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}


.overlay-enter-active,
.overlay-leave-active {
    transition: opacity 0.2s ease;
}
.overlay-enter-from,
.overlay-leave-to {
    opacity: 0;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.bubble-expand-enter-active {
    transition:
        opacity 0.3s ease,
        transform 0.45s cubic-bezier(0.22, 0.78, 0.26, 1);
}
.bubble-expand-leave-active {
    transition:
        opacity 0.22s ease,
        transform 0.32s cubic-bezier(0.6, 0, 0.4, 1);
}
.bubble-expand-enter-from,
.bubble-expand-leave-to {
    opacity: 0;
    transform: scale(0.32);
}

input::placeholder {
    color: #4ebcff77;
}
input:focus {
    border-color: #4ebcff !important;
}

/* ── Bubble loader (Section 4) ───────────────────────────────────── */
.bubble-loader {
    position: relative;
    width: 44px;
    height: 44px;
}
.bl-arm {
    position: absolute;
    top: 50%; left: 50%;
    width: 22px; height: 4px;
    margin-top: -2px; margin-left: 0;
    transform-origin: 0 50%;
}
.bl-arm-1  { transform: rotate(0deg); }
.bl-arm-2  { transform: rotate(45deg); }
.bl-arm-3  { transform: rotate(90deg); }
.bl-arm-4  { transform: rotate(135deg); }
.bl-arm-5  { transform: rotate(180deg); }
.bl-arm-6  { transform: rotate(225deg); }
.bl-arm-7  { transform: rotate(270deg); }
.bl-arm-8  { transform: rotate(315deg); }
.bl-dot {
    position: absolute;
    right: 0; top: 50%;
    width: 7px; height: 7px;
    margin-top: -3.5px;
    border-radius: 50%;
    background: #009ac7;
    box-shadow: 0 0 4px #009ac788;
    animation: bl-fade 0.8s ease-in-out infinite;
}
.bl-arm-1 .bl-dot  { animation-delay: -0.7s; }
.bl-arm-2 .bl-dot  { animation-delay: -0.6s; }
.bl-arm-3 .bl-dot  { animation-delay: -0.5s; }
.bl-arm-4 .bl-dot  { animation-delay: -0.4s; }
.bl-arm-5 .bl-dot  { animation-delay: -0.3s; }
.bl-arm-6 .bl-dot  { animation-delay: -0.2s; }
.bl-arm-7 .bl-dot  { animation-delay: -0.1s; }
.bl-arm-8 .bl-dot  { animation-delay: 0s; }
@keyframes bl-fade {
    0%, 100% { opacity: 0.15; transform: scale(0.7); }
    50%       { opacity: 1;    transform: scale(1); }
}

/* ── Floating background bubbles (Section 9) ─────────────────────── */
.fb {
    position: absolute;
    bottom: -70px;
    border-radius: 50%;
    background: radial-gradient(circle at 38% 32%, rgba(255,255,255,.38) 0%, rgba(78,188,255,.18) 58%, rgba(78,188,255,.06) 82%, transparent 100%);
    border: 1px solid rgba(78,188,255,.24);
    box-shadow: 0 0 14px rgba(78,188,255,.12), inset 0 0 10px rgba(255,255,255,.12);
    filter: saturate(1.05);
    animation: fbrise linear infinite;
    will-change: transform;
}
@keyframes fbrise {
    0%   { transform: translateY(0) scale(1); }
    100% { transform: translateY(calc(-100vh - 90px)) scale(1.08); }
}
</style>
