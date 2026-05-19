<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ToastContainer from '@/Components/ToastContainer.vue';
import SiteOwnerBadge from '@/Components/SiteOwnerBadge.vue';
import { clImg } from '@/Composables/useCloudinary';
import { useToast } from '@/Composables/useToast';
import { useTheme } from '@/Composables/useTheme';
import { useSearch } from '@/Composables/useSearch';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { show: toast } = useToast();
const { isDark, toggle: toggleTheme } = useTheme();

// Flash messages → toasts
watch(
    () => page.props.flash?.status,
    (msg) => { if (msg) toast(msg, 'success'); },
);
watch(
    () => page.props.flash?.error,
    (msg) => { if (msg) toast(msg, 'error'); },
);

const pendingFriends = computed(() => page.props.auth?.pending_friends_count ?? 0);
const unreadMessages = computed(() => page.props.auth?.unread_messages_count ?? 0);
const unreadNotifications = computed(() => page.props.auth?.unread_notifications_count ?? 0);
const open = ref(false);
const searchOpen = ref(false);
const searchQuery = ref('');
const activeSearchIdx = ref(-1);
const isMobile = ref(window.innerWidth < 640);

const { results: searchResults, loading: searchLoading, error: searchError, search: doSearch, clear: clearSearch } = useSearch();

const hasSearchResults = computed(() =>
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

function submitSearch(e) {
    e.preventDefault();
    const q = searchQuery.value.trim();
    if (!q) return;
    closeSearchOverlay();
    router.visit(route('search.index', { q }));
}

function viewAllSearchResults() {
    const q = searchQuery.value.trim();
    if (!q) return;
    closeSearchOverlay();
    router.visit(route('search.index', { q }));
}

function handleSearchKey(e) {
    if (!hasSearchResults.value) return;
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
            viewAllSearchResults();
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
        if (url) { closeSearchOverlay(); router.visit(url); }
    }
}

function openSearch() {
    searchOpen.value = true;
    activeSearchIdx.value = -1;
    setTimeout(() => { document.getElementById('global-search-input')?.focus(); }, 50);
}

function handleGlobalKey(e) {
    if (e.key === 'Escape') closeSearchOverlay();
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); openSearch(); }
}

let pollTimer = null;

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKey);
    window.addEventListener('resize', onResize);
    if (page.props.auth?.user) {
        pollTimer = setInterval(() => {
            router.reload({ only: ['auth'], preserveScroll: true, preserveState: true });
        }, 60000);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKey);
    window.removeEventListener('resize', onResize);
    clearInterval(pollTimer);
});
</script>

<template>
    <div class="layout-shell" style="font-family: 'Segoe UI', system-ui, sans-serif" @click="open && (open = false)">

        <!-- ── Topbar ─────────────────────────────────────────────── -->
        <nav class="topbar">
            <div :style="{ maxWidth: '1100px', margin: '0 auto', padding: isMobile ? '0 12px' : '0 24px', height: '58px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px' }">

                <!-- Left: logo + nav links -->
                <div style="display:flex;align-items:center;gap:24px;flex-shrink:0">
                    <Link href="/bubbles" style="text-decoration:none">
                        <span style="font-weight:900;font-size:20px;color:#009ac7;letter-spacing:-1px">bubbles</span>
                    </Link>
                    <Link v-if="!isMobile" href="/bubbles" class="nav-link">Explorar</Link>
                    <Link v-if="user && !isMobile" :href="route('friends.index')" class="nav-link" style="position:relative;display:inline-flex;align-items:center;gap:6px">
                        Amigos
                        <span v-if="pendingFriends > 0" class="badge-red">{{ pendingFriends }}</span>
                    </Link>
                    <Link v-if="user && !isMobile" :href="route('feed.index')" class="nav-link">Feed</Link>
                    <Link v-if="user && !isMobile" :href="route('conversations.index')" class="nav-link" style="position:relative;display:inline-flex;align-items:center;gap:6px">
                        Mensagens
                        <span v-if="unreadMessages > 0" class="badge-blue">{{ unreadMessages }}</span>
                    </Link>
                </div>

                <!-- Right: search + notifications + user -->
                <div style="display:flex;align-items:center;gap:8px">

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
                                    <Link v-if="user.role === 'admin' || user.role === 'site_owner'" href="/admin" @click="open = false" class="dropdown-link" style="color:#9b6bdf;font-weight:700">⊞ Painel Admin</Link>
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
                                    <div class="dropdown-sep" />
                                    <Link :href="route('logout')" method="post" as="button" @click="open = false" class="dropdown-link dropdown-logout">Sair</Link>
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
            <div v-if="searchOpen" class="search-overlay" @click="searchOpen = false">
                <form @submit.prevent="submitSearch" @click.stop style="width:100%;max-width:560px;margin:0 20px">
                    <div style="position:relative">
                        <svg style="position:absolute;left:18px;top:50%;transform:translateY(-50%);color:#8ba0b0;pointer-events:none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input
                            id="global-search-input"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Pesquisa pessoas, comunidades ou posts…"
                            class="search-input"
                        />
                        <kbd class="search-kbd">Esc</kbd>
                    </div>
                    <p style="font-size:11px;color:rgba(255,255,255,0.7);margin:10px 0 0;text-align:center">Enter para pesquisar · Esc para fechar</p>
                </form>
            </div>
        </Transition>

        <!-- Ambient orbs -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden" style="z-index:0">
            <div style="position:absolute;top:-60px;left:-60px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle, #009ac714 0%, transparent 70%)"/>
            <div style="position:absolute;bottom:-40px;right:-40px;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle, #4ebcff0e 0%, transparent 70%)"/>
        </div>

        <!-- Page content -->
        <main style="position:relative;z-index:1">
            <slot />
        </main>

        <ToastContainer />
    </div>
</template>

<style scoped>
/* ── Shell ─────────────────────────────────────────────────────── */
.layout-shell { min-height: 100vh; background: var(--layout-bg); }

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
    width: 34px; height: 34px; border-radius: 50%;
    border: 1.5px solid var(--nav-border);
    background: transparent; color: var(--text-3);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
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
    padding-top: 80px;
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
</style>
