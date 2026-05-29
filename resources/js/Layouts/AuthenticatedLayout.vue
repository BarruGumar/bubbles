<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ToastContainer from '@/Components/ToastContainer.vue';
import SiteOwnerBadge from '@/Components/SiteOwnerBadge.vue';
import AudioControls from '@/Components/AudioControls.vue';
import AnnouncementBanner from '@/Components/AnnouncementBanner.vue';
import AnnouncementModal from '@/Components/AnnouncementModal.vue';
import { clImg } from '@/Composables/useCloudinary';
import { useOnlineUsers } from '@/Composables/useOnlineUsers';
import { useToast } from '@/Composables/useToast';
import { useSearch } from '@/Composables/useSearch';
import { useTheme } from '@/Composables/useTheme';
import { useAudio } from '@/Composables/useAudio';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { show: toast } = useToast();
const { isDark, toggle: toggleTheme } = useTheme();
const { playBgm, playClick, stopBgm, playSfx, currentBgmKey } = useAudio();

// ── BGM: change track whenever the Inertia page URL changes ───────
const bgmKeyForUrl = computed(() => {
    const raw = page.url ?? '';
    // page.url can be a full URL or a relative path depending on the Inertia version
    let path;
    try { path = raw.startsWith('http') ? new URL(raw).pathname : raw; } catch { path = raw; }
    if (path.startsWith('/bubbles') || path === '/') return 'home';
    if (path.startsWith('/feed'))          return 'feed';
    if (path.startsWith('/conversations')) return 'chat';
    if (path.startsWith('/admin'))         return 'admin';
    if (path.startsWith('/c/'))            return 'community';
    if (path.startsWith('/friends'))       return 'friends';
    if (path.startsWith('/u/'))            return page.props.isOwn ? 'profile_own' : 'profile_other';
    return null;
});
watch(bgmKeyForUrl, (key) => {
    if (key) {
        playBgm(key);
    } else if (currentBgmKey.value === 'profile_own' || currentBgmKey.value === 'profile_other') {
        stopBgm();
    }
}, { immediate: true });

// Flash messages → toasts
watch(
    () => page.props.flash?.status,
    (msg) => { if (msg) toast(msg, 'success'); },
);
watch(
    () => page.props.flash?.error,
    (msg) => { if (msg) toast(msg, 'error'); },
);

const { onlineUsers } = useOnlineUsers();
const isOnline = (userId) => userId != null && onlineUsers.value.has(userId);

const pendingFriends = ref(page.props.auth?.pending_friends_count ?? 0);
const unreadMessages = ref(page.props.auth?.unread_messages_count ?? 0);
const unreadNotifications = ref(page.props.auth?.unread_notifications_count ?? 0);

watch(() => page.props.auth?.pending_friends_count, (v) => { if (v !== undefined) pendingFriends.value = v; });
watch(() => page.props.auth?.unread_messages_count, (v) => { if (v !== undefined) unreadMessages.value = v; });
watch(() => page.props.auth?.unread_notifications_count, (v) => { if (v !== undefined) unreadNotifications.value = v; });

// Registered at setup time (not onMounted) so it's ready before child pages mount and dispatch
const _onMessagesRead = (e) => { unreadMessages.value = Math.max(0, unreadMessages.value - e.detail.delta); };
const _onNotificationsRead = () => { unreadNotifications.value = 0; };
window.addEventListener('messages-read', _onMessagesRead);
window.addEventListener('notifications-read', _onNotificationsRead);
const open = ref(false);
const searchOpen = ref(false);
const searchQuery = ref('');
const activeSearchIdx = ref(-1);
const isMobile = ref(window.innerWidth < 640);

const { results: searchResults, loading: searchLoading, error: searchError, search: doSearch, clear: clearSearch } = useSearch();

const hasResults = computed(
    () =>
        searchResults.value &&
        (searchResults.value.users?.length ||
            searchResults.value.communities?.length ||
            searchResults.value.posts?.length),
);

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

function onResize() { isMobile.value = window.innerWidth < 640; }

function avatarInitial(name) { return (name ?? '?')[0].toUpperCase(); }

function closeSearchOverlay() {
    searchOpen.value = false;
    searchQuery.value = '';
    clearSearch();
    activeSearchIdx.value = -1;
}

function goToResult(url) {
    closeSearchOverlay();
    router.visit(url);
}

function viewAllResults() {
    const q = searchQuery.value.trim();
    if (!q) return;
    closeSearchOverlay();
    router.visit(route('search.index', { q }));
}

function submitSearch(e) {
    e.preventDefault();
    viewAllResults();
}

function openSearch() {
    searchOpen.value = true;
    setTimeout(() => { document.getElementById('global-search-input')?.focus(); }, 50);
}

function handleSearchKey(e) {
    if (!hasResults.value) return;
    const total = searchOffsets.value.all + 1;
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

function handleGlobalKey(e) {
    if (e.key === 'Escape') closeSearchOverlay();
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); openSearch(); }
}

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKey);
    window.addEventListener('resize', onResize);
    if (page.props.auth?.user) {
        window.Echo.private(`user.${page.props.auth.user.id}`)
            .listen('.BadgeCountUpdated', (e) => {
                if (e.type === 'friends') pendingFriends.value += e.delta;
                if (e.type === 'messages') {
                    const alreadyViewing = e.conversation_id && page.url.includes(`/conversations/${e.conversation_id}`);
                    if (!alreadyViewing) unreadMessages.value += e.delta;
                }
                if (e.type === 'notifications') {
                    unreadNotifications.value += e.delta;
                    playSfx('notification');
                }
            });

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
    window.removeEventListener('keydown', handleGlobalKey);
    window.removeEventListener('resize', onResize);
    window.removeEventListener('messages-read', _onMessagesRead);
    window.removeEventListener('notifications-read', _onNotificationsRead);
    if (page.props.auth?.user) {
        window.Echo.leave(`user.${page.props.auth.user.id}`);
        window.Echo.leave('online');
    }
});
</script>

<template>
    <div :class="['layout-shell', user && isMobile ? 'has-bottom-nav' : '']" style="font-family: 'Segoe UI', system-ui, sans-serif" @click="open && (open = false)">

        <!-- ── Topbar ─────────────────────────────────────────────── -->
        <nav class="topbar">
            <div :style="{ maxWidth: '1100px', margin: '0 auto', padding: isMobile ? '0 12px' : '0 24px', height: '58px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px' }">

                <!-- Left: logo + nav links -->
                <div style="display:flex;align-items:center;gap:24px;flex-shrink:0">
                    <Link href="/bubbles" style="text-decoration:none">
                        <span style="font-weight:900;font-size:20px;color:#009ac7;letter-spacing:-1px">bubbles</span>
                    </Link>
                    <Link v-if="!isMobile" href="/bubbles" class="nav-link" @click="playClick()">Explorar</Link>
                    <Link v-if="user && !isMobile" :href="route('friends.index')" class="nav-link" style="position:relative;display:inline-flex;align-items:center;gap:6px" @click="playClick()">
                        Amigos
                        <span v-if="pendingFriends > 0" class="badge-red">{{ pendingFriends }}</span>
                    </Link>
                    <Link v-if="user && !isMobile" :href="route('feed.index')" class="nav-link" @click="playClick()">Feed</Link>
                    <Link v-if="user && !isMobile" :href="route('conversations.index')" class="nav-link" style="position:relative;display:inline-flex;align-items:center;gap:6px" @click="playClick()">
                        Mensagens
                        <span v-if="unreadMessages > 0" class="badge-blue">{{ unreadMessages }}</span>
                    </Link>
                </div>

                <!-- Right: search + notifications + user -->
                <div style="display:flex;align-items:center;gap:8px">

                    <!-- Audio controls -->
                    <AudioControls v-if="user" />

                    <!-- Search button -->
                    <button @click.stop="openSearch" class="icon-btn-nav" title="Pesquisar (Ctrl+K)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </button>

                    <!-- Notifications bell -->
                    <Link v-if="user" :href="route('notifications.index')" class="icon-btn-nav" style="position:relative;text-decoration:none" title="Notificações">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span v-if="unreadNotifications > 0" class="notif-dot">{{ unreadNotifications > 9 ? '9+' : unreadNotifications }}</span>
                    </Link>

                    <!-- User avatar dropdown -->
                    <div v-if="user" style="position:relative">
                        <button @click.stop="open = !open" class="avatar-btn">
                            <div style="position:relative;flex-shrink:0">
                                <img
                                    v-if="user.avatar"
                                    :src="clImg(user.avatar, 64, 64, 'fill', 'face')"
                                    :style="{
                                        width:'32px', height:'32px', borderRadius:'50%', objectFit:'cover',
                                        border: user.role === 'site_owner' ? '2.5px solid transparent' : `2px solid ${user.avatar_color ?? '#009ac7'}`,
                                        boxShadow: user.role === 'site_owner' ? '0 0 0 2px #d4a017, 0 2px 10px #d4a01755' : `0 2px 8px ${user.avatar_color ?? '#009ac7'}44`,
                                        background: user.role === 'site_owner' ? 'linear-gradient(135deg,#f5d060,#c084fc) border-box' : undefined,
                                    }"
                                />
                                <div v-else :style="{
                                    width:'32px', height:'32px', borderRadius:'50%',
                                    background: user.avatar_color ?? '#009ac7',
                                    display:'flex', alignItems:'center', justifyContent:'center',
                                    fontSize:'13px', fontWeight:'800', color:'white',
                                    boxShadow: user.role === 'site_owner' ? '0 0 0 2px #d4a017, 0 2px 10px #d4a01755' : `0 2px 8px ${user.avatar_color ?? '#009ac7'}44`,
                                }">
                                    {{ avatarInitial(user.name) }}
                                </div>
                                <span v-if="user.role === 'site_owner'" style="position:absolute;bottom:-3px;right:-4px;font-size:11px;line-height:1">👑</span>
                            </div>
                            <span v-if="!isMobile" class="avatar-name" :class="{ 'owner-name': user.role === 'site_owner' }">{{ user.name }}</span>
                            <svg v-if="!isMobile" width="12" height="12" viewBox="0 0 12 12" fill="none" style="color:#8ba0b0">
                                <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <Transition name="dropdown">
                            <div v-if="open" class="dropdown-menu" @click.stop>
                                <!-- User info header -->
                                <div class="dropdown-header">
                                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                                        <p class="dropdown-name" :class="{ 'owner-name': user.role === 'site_owner' }">{{ user.name }}</p>
                                        <SiteOwnerBadge v-if="user.role === 'site_owner'" size="sm" />
                                    </div>
                                    <p v-if="user.username" class="dropdown-username">@{{ user.username }}</p>
                                </div>
                                <div style="padding:6px">
                                    <Link v-if="user.username" :href="route('profile.show', user.username)" @click="open = false" class="dropdown-link">O meu perfil</Link>
                                    <Link :href="route('profile.edit')" @click="open = false" class="dropdown-link">Definições</Link>
                                    <!-- Theme toggle -->
                                    <button @click="toggleTheme(); open = false" class="dropdown-link dropdown-theme-btn">
                                        <span>{{ isDark ? '☀️ Tema claro' : '🌙 Tema escuro' }}</span>
                                    </button>
                                    <Link
                                        :href="route('notifications.index')"
                                        @click="open = false"
                                        class="dropdown-link"
                                        style="display:flex;align-items:center;justify-content:space-between"
                                    >
                                        Notificações
                                        <span v-if="unreadNotifications > 0" class="badge-red">{{ unreadNotifications }}</span>
                                    </Link>
                                    <Link v-if="user.role === 'admin' || user.role === 'site_owner'" href="/admin" @click="open = false" class="dropdown-link" style="color:#9b6bdf;font-weight:700">⊞ Painel Admin</Link>
                                    <div class="dropdown-sep" />
                                    <Link :href="route('logout')" method="post" as="button" @click="stopBgm(); open = false" class="dropdown-link dropdown-logout">Sair</Link>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Not logged in -->
                    <div v-else style="display:flex;gap:10px">
                        <Link :href="route('login')" style="font-size:13px;font-weight:600;color:#009ac7;text-decoration:none;padding:7px 16px;border-radius:99px;border:1.5px solid #009ac7;transition:all 0.2s">Entrar</Link>
                        <Link :href="route('register')" style="font-size:13px;font-weight:700;color:white;text-decoration:none;padding:7px 16px;border-radius:99px;background:#009ac7;box-shadow:0 3px 12px #009ac740">Registo</Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- ── Global search overlay ──────────────────────────────── -->
        <Transition name="overlay">
            <div v-if="searchOpen" class="search-overlay" @click="closeSearchOverlay">
                <div @click.stop style="width:100%;max-width:560px;margin:0 20px">
                    <form @submit.prevent="submitSearch" style="position:relative">
                        <svg style="position:absolute;left:18px;top:50%;transform:translateY(-50%);color:#8ba0b0;pointer-events:none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input
                            id="global-search-input"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Pesquisa pessoas, comunidades ou posts…"
                            class="search-input"
                            @keydown="handleSearchKey"
                        />
                        <kbd class="search-kbd">Esc</kbd>
                    </form>

                    <!-- Resultados ao vivo -->
                    <div
                        v-if="searchQuery.trim() && (hasResults || searchResults || searchLoading)"
                        style="margin-top:8px;background:var(--dropdown-bg);border-radius:16px;box-shadow:0 16px 48px rgba(0,0,0,0.18);overflow:hidden;max-height:420px;overflow-y:auto"
                        @mouseleave="activeSearchIdx = -1"
                    >
                        <div v-if="searchError" style="padding:24px;text-align:center;color:#e05555;font-size:13px;font-weight:600">
                            Erro de rede. Tenta novamente.
                        </div>
                        <div v-else-if="searchResults && !hasResults && !searchLoading" style="padding:24px;text-align:center;color:var(--text-3);font-size:13px">
                            Sem resultados para "{{ searchQuery }}"
                        </div>
                        <template v-if="hasResults">
                            <!-- Pessoas -->
                            <template v-if="searchResults.users?.length">
                                <p style="font-size:10px;font-weight:800;color:var(--text-3);text-transform:uppercase;letter-spacing:0.1em;margin:0;padding:12px 16px 6px">Pessoas</p>
                                <div
                                    v-for="(u, i) in searchResults.users.slice(0, 4)"
                                    :key="'u' + u.id"
                                    @click="goToResult(u.username ? route('profile.show', u.username) : '#')"
                                    :style="{ display:'flex', alignItems:'center', gap:'12px', padding:'10px 16px', cursor:'pointer', transition:'background 0.12s', background: activeSearchIdx === searchOffsets.users + i ? 'var(--item-hover)' : 'transparent' }"
                                    @mouseenter="activeSearchIdx = searchOffsets.users + i"
                                >
                                    <div style="position:relative;flex-shrink:0">
                                        <img v-if="u.avatar" :src="clImg(u.avatar, 72, 72, 'fill', 'face')" :style="{ width:'36px', height:'36px', borderRadius:'50%', objectFit:'cover', border:`2px solid ${u.avatar_color}` }" />
                                        <div v-else :style="{ width:'36px', height:'36px', borderRadius:'50%', background:u.avatar_color, display:'flex', alignItems:'center', justifyContent:'center', fontSize:'13px', fontWeight:'800', color:'white' }">
                                            {{ (u.name ?? '?')[0].toUpperCase() }}
                                        </div>
                                        <span v-if="isOnline(u.id)" class="search-online-dot"></span>
                                    </div>
                                    <div style="flex:1;min-width:0">
                                        <p style="font-size:13px;font-weight:700;color:var(--text);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ u.name }}</p>
                                        <p v-if="u.username" style="font-size:11px;color:#009ac7;margin:0">@{{ u.username }}</p>
                                    </div>
                                </div>
                            </template>
                            <!-- Comunidades -->
                            <template v-if="searchResults.communities?.length">
                                <div style="height:1px;background:var(--dropdown-sep);margin:4px 0" />
                                <p style="font-size:10px;font-weight:800;color:var(--text-3);text-transform:uppercase;letter-spacing:0.1em;margin:0;padding:8px 16px 6px">Comunidades</p>
                                <div
                                    v-for="(c, i) in searchResults.communities.slice(0, 4)"
                                    :key="'c' + c.id"
                                    @click="goToResult(route('community.show', c.id))"
                                    :style="{ display:'flex', alignItems:'center', gap:'12px', padding:'10px 16px', cursor:'pointer', transition:'background 0.12s', background: activeSearchIdx === searchOffsets.communities + i ? 'var(--item-hover)' : 'transparent' }"
                                    @mouseenter="activeSearchIdx = searchOffsets.communities + i"
                                >
                                    <div :style="{ width:'36px', height:'36px', borderRadius:'50%', flexShrink:'0', overflow:'hidden', boxShadow:`0 3px 10px ${c.color}44`, background:`radial-gradient(circle at 38% 32%, ${c.color}ee 0%, ${c.color} 60%)`, display:'flex', alignItems:'center', justifyContent:'center' }">
                                        <img v-if="c.image" :src="c.image" style="width:100%;height:100%;object-fit:cover;display:block" />
                                        <span v-else style="font-size:9px;font-weight:800;color:white">{{ c.label?.slice(0, 3) }}</span>
                                    </div>
                                    <div style="flex:1;min-width:0">
                                        <p style="font-size:13px;font-weight:700;color:var(--text);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ c.title }}</p>
                                        <p style="font-size:11px;color:var(--text-3);margin:0">{{ c.members }} membros</p>
                                    </div>
                                </div>
                            </template>
                            <!-- Posts -->
                            <template v-if="searchResults.posts?.length">
                                <div style="height:1px;background:var(--dropdown-sep);margin:4px 0" />
                                <p style="font-size:10px;font-weight:800;color:var(--text-3);text-transform:uppercase;letter-spacing:0.1em;margin:0;padding:8px 16px 6px">Posts</p>
                                <div
                                    v-for="(p, i) in searchResults.posts.slice(0, 3)"
                                    :key="'p' + p.id"
                                    @click="goToResult(p.author.username ? route('profile.show', p.author.username) : '#')"
                                    :style="{ display:'flex', alignItems:'flex-start', gap:'10px', padding:'10px 16px', cursor:'pointer', transition:'background 0.12s', background: activeSearchIdx === searchOffsets.posts + i ? 'var(--item-hover)' : 'transparent' }"
                                    @mouseenter="activeSearchIdx = searchOffsets.posts + i"
                                >
                                    <img v-if="p.author.avatar" :src="clImg(p.author.avatar, 48, 48, 'fill', 'face')" :style="{ width:'28px', height:'28px', borderRadius:'50%', objectFit:'cover', border:`1.5px solid ${p.author.avatar_color}`, flexShrink:'0', marginTop:'1px' }" />
                                    <div v-else :style="{ width:'28px', height:'28px', borderRadius:'50%', background:p.author.avatar_color, flexShrink:'0', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'10px', fontWeight:'800', color:'white', marginTop:'1px' }">
                                        {{ (p.author.name ?? '?')[0].toUpperCase() }}
                                    </div>
                                    <div style="flex:1;min-width:0">
                                        <p style="font-size:12px;font-weight:700;color:var(--text);margin:0 0 2px">{{ p.author.name }}</p>
                                        <p style="font-size:11px;color:var(--text-2);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ p.content }}</p>
                                    </div>
                                </div>
                            </template>
                            <!-- Ver todos -->
                            <div
                                @click="viewAllResults"
                                :style="{ padding:'12px 16px', textAlign:'center', fontSize:'12px', fontWeight:'700', color:'#009ac7', cursor:'pointer', borderTop:'1px solid var(--dropdown-sep)', transition:'background 0.12s', background: activeSearchIdx === searchOffsets.all ? 'var(--item-hover)' : 'transparent' }"
                                @mouseenter="activeSearchIdx = searchOffsets.all"
                            >
                                Ver todos os resultados →
                            </div>
                        </template>
                    </div>

                    <p v-if="!searchQuery.trim()" style="font-size:11px;color:rgba(255,255,255,0.7);margin:10px 0 0;text-align:center">
                        Enter para pesquisar · Esc para fechar
                    </p>
                </div>
            </div>
        </Transition>

        <!-- Announcements -->
        <AnnouncementBanner />
        <AnnouncementModal />

        <!-- Ambient orbs -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden" style="z-index:0">
            <div style="position:absolute;top:-60px;left:-60px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle, #009ac714 0%, transparent 70%)"/>
            <div style="position:absolute;bottom:-40px;right:-40px;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle, #4ebcff0e 0%, transparent 70%)"/>
        </div>

        <!-- Page content -->
        <main style="position:relative;z-index:1">
            <slot />
        </main>

        <!-- Mobile bottom nav -->
        <nav v-if="user && isMobile" class="bottom-nav">
            <Link href="/bubbles" class="bnav-item" @click="playClick()">
                <span class="bnav-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </span>
                <span class="bnav-label">Explorar</span>
            </Link>
            <Link :href="route('friends.index')" class="bnav-item" @click="playClick()">
                <span class="bnav-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span v-if="pendingFriends > 0" class="bnav-badge">{{ pendingFriends }}</span>
                </span>
                <span class="bnav-label">Amigos</span>
            </Link>
            <Link :href="route('feed.index')" class="bnav-item" @click="playClick()">
                <span class="bnav-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </span>
                <span class="bnav-label">Feed</span>
            </Link>
            <Link :href="route('conversations.index')" class="bnav-item" @click="playClick()">
                <span class="bnav-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span v-if="unreadMessages > 0" class="bnav-badge bnav-badge-blue">{{ unreadMessages }}</span>
                </span>
                <span class="bnav-label">Mensagens</span>
            </Link>
        </nav>

        <ToastContainer />

    </div>
</template>

<style scoped>
/* ── Shell ─────────────────────────────────────────────────────── */
.layout-shell { min-height: 100dvh; background: var(--layout-bg); }
.has-bottom-nav { padding-bottom: calc(56px + env(safe-area-inset-bottom, 0px)); }

/* ── Topbar ────────────────────────────────────────────────────── */
.topbar {
    position: sticky; top: 0; z-index: 40;
    background: var(--nav-bg);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--nav-border);
}

/* ── Nav links ─────────────────────────────────────────────────── */
.nav-link {
    font-size: 13px; font-weight: 600; color: var(--text-2);
    text-decoration: none; transition: color 0.2s;
}
.nav-link:hover { color: #009ac7; }

/* ── Badges ────────────────────────────────────────────────────── */
.badge-red {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; padding: 0 5px;
    background: #c74a6b; color: white; border-radius: 99px;
    font-size: 10px; font-weight: 800; line-height: 1;
}
.badge-blue {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; padding: 0 5px;
    background: #009ac7; color: white; border-radius: 99px;
    font-size: 10px; font-weight: 800; line-height: 1;
}

/* ── Icon buttons (search, bell) ───────────────────────────────── */
.icon-btn-nav {
    width: 44px; height: 44px; border-radius: 50%;
    border: 1.5px solid var(--nav-border);
    background: transparent; color: var(--text-3);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
    -webkit-tap-highlight-color: transparent;
}
.icon-btn-nav:hover {
    background: rgba(0, 154, 199, 0.08); color: #009ac7;
    border-color: rgba(0, 154, 199, 0.28);
}

/* ── Notification dot ──────────────────────────────────────────── */
.notif-dot {
    position: absolute; top: -3px; right: -3px;
    min-width: 16px; height: 16px; padding: 0 4px;
    background: #c74a6b; color: white; border-radius: 99px;
    font-size: 9px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--nav-bg-solid); line-height: 1;
}

/* ── Avatar button ─────────────────────────────────────────────── */
.avatar-btn {
    display: flex; align-items: center; gap: 10px;
    background: none; border: none; cursor: pointer;
    padding: 4px 8px; border-radius: 99px; transition: background 0.2s;
}
.avatar-btn:hover { background: rgba(0, 154, 199, 0.08); }
.avatar-name { font-size: 13px; font-weight: 700; color: var(--text); }
.owner-name {
    background: linear-gradient(135deg, #d4a017 0%, #c084fc 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 800;
}

/* ── Dropdown ──────────────────────────────────────────────────── */
.dropdown-menu {
    position: absolute; right: 0; top: calc(100% + 8px);
    min-width: 190px;
    background: var(--dropdown-bg);
    backdrop-filter: blur(20px);
    border-radius: 14px;
    border: 1px solid var(--nav-border);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
    overflow: hidden; z-index: 50;
}
.dropdown-header {
    padding: 12px 16px 10px;
    border-bottom: 1px solid var(--dropdown-sep);
}
.dropdown-name  { font-size: 12px; font-weight: 700; color: var(--text); margin: 0; }
.dropdown-username { font-size: 11px; color: var(--text-3); margin: 2px 0 0; }

.dropdown-link {
    display: block; padding: 9px 12px; border-radius: 9px;
    font-size: 13px; color: var(--text-2);
    text-decoration: none; transition: background 0.15s;
    cursor: pointer;
}
.dropdown-link:hover { background: var(--item-hover); }

.dropdown-theme-btn {
    width: 100%; text-align: left;
    border: none; background: none; font-family: inherit;
}
.dropdown-sep { height: 1px; background: var(--dropdown-sep); margin: 4px 0; }
.dropdown-logout { color: #c74a6b !important; }
.dropdown-logout:hover { background: var(--item-hover-red) !important; }

/* ── Search overlay ────────────────────────────────────────────── */
.search-overlay {
    position: fixed; inset: 0; z-index: 100;
    background: rgba(0, 0, 0, 0.32);
    backdrop-filter: blur(4px);
    display: flex; align-items: flex-start; justify-content: center;
    padding-top: clamp(16px, 10svh, 80px);
}
.search-input {
    width: 100%; box-sizing: border-box;
    background: var(--search-input-bg);
    border: none; border-radius: 16px;
    padding: 16px 16px 16px 50px;
    font-size: 15px; color: var(--text);
    outline: none; font-family: inherit;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.25);
}
.search-kbd {
    position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
    font-size: 11px; color: var(--text-3); background: var(--kbd-bg);
    border-radius: 6px; padding: 2px 7px; font-family: inherit;
}

/* ── Transitions ───────────────────────────────────────────────── */
.dropdown-enter-active, .dropdown-leave-active {
    transition: opacity 0.18s ease, transform 0.22s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: translateY(-6px) scale(0.97); }

.overlay-enter-active, .overlay-leave-active { transition: opacity 0.2s ease; }
.overlay-enter-from, .overlay-leave-to { opacity: 0; }

/* ── Mobile bottom nav ─────────────────────────────────────────── */
.bottom-nav {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 40;
    background: var(--nav-bg);
    backdrop-filter: blur(16px);
    border-top: 1px solid var(--nav-border);
    display: flex;
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
.bnav-item {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 3px; height: 56px; min-width: 44px;
    color: var(--text-3); text-decoration: none;
    transition: color 0.15s;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}
.bnav-item:active { color: #009ac7; }
.bnav-icon-wrap { position: relative; display: flex; align-items: center; justify-content: center; }
.bnav-label { font-size: 10px; font-weight: 600; }
.bnav-badge {
    position: absolute; top: -6px; right: -8px;
    min-width: 15px; height: 15px; padding: 0 4px;
    background: #c74a6b; color: white; border-radius: 99px;
    font-size: 9px; font-weight: 800; line-height: 1;
    display: flex; align-items: center; justify-content: center;
}
.bnav-badge-blue { background: #009ac7; }

/* ── Online dot (search results) ───────────────────────────────── */
.search-online-dot {
    position: absolute; bottom: 0; right: 0;
    width: 10px; height: 10px; border-radius: 50%;
    background: #22c55e;
    border: 2px solid var(--dropdown-bg, #fff);
}

</style>
