<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ToastContainer from '@/Components/ToastContainer.vue';
import { clImg } from '@/Composables/useCloudinary';
import { useToast } from '@/Composables/useToast';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { show: toast } = useToast();

// Flash messages → toasts
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
const pendingFriends = computed(() => page.props.auth?.pending_friends_count ?? 0);
const unreadMessages = computed(() => page.props.auth?.unread_messages_count ?? 0);
const unreadNotifications = computed(() => page.props.auth?.unread_notifications_count ?? 0);
const open = ref(false);
const searchOpen = ref(false);
const searchQuery = ref('');

function avatarInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}

function submitSearch(e) {
    e.preventDefault();
    const q = searchQuery.value.trim();
    if (!q) return;
    router.visit(route('search.index', { q }));
    searchOpen.value = false;
    searchQuery.value = '';
}

function openSearch() {
    searchOpen.value = true;
    setTimeout(() => {
        document.getElementById('global-search-input')?.focus();
    }, 50);
}

function handleGlobalKey(e) {
    if (e.key === 'Escape') searchOpen.value = false;
    // Ctrl+K or Cmd+K opens search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        openSearch();
    }
}

let pollTimer = null;

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKey);
    if (page.props.auth?.user) {
        pollTimer = setInterval(() => {
            router.reload({ only: ['auth'], preserveScroll: true, preserveState: true });
        }, 60000);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKey);
    clearInterval(pollTimer);
});
</script>

<template>
    <div
        class="min-h-screen"
        style="background: rgba(240, 248, 255, 0.45); font-family: 'Segoe UI', system-ui, sans-serif"
        @click="open && (open = false)"
    >
        <!-- Topbar -->
        <nav
            style="
                position: sticky;
                top: 0;
                z-index: 40;
                background: rgba(255, 255, 255, 0.75);
                backdrop-filter: blur(16px);
                border-bottom: 1px solid #009ac71a;
            "
        >
            <div
                style="
                    max-width: 1100px;
                    margin: 0 auto;
                    padding: 0 24px;
                    height: 58px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                "
            >
                <!-- Left: logo + nav links -->
                <div style="display: flex; align-items: center; gap: 24px; flex-shrink: 0">
                    <Link href="/bubbles" style="text-decoration: none">
                        <span style="font-weight: 900; font-size: 20px; color: #009ac7; letter-spacing: -1px"
                            >bubbles</span
                        >
                    </Link>
                    <Link
                        href="/bubbles"
                        style="
                            font-size: 13px;
                            font-weight: 600;
                            color: #5a7a8a;
                            text-decoration: none;
                            transition: color 0.2s;
                        "
                        @mouseenter="$event.target.style.color = '#009ac7'"
                        @mouseleave="$event.target.style.color = '#5a7a8a'"
                        >Explorar</Link
                    >
                    <Link
                        v-if="user"
                        :href="route('friends.index')"
                        style="
                            font-size: 13px;
                            font-weight: 600;
                            color: #5a7a8a;
                            text-decoration: none;
                            transition: color 0.2s;
                            position: relative;
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                        "
                        @mouseenter="$event.currentTarget.style.color = '#009ac7'"
                        @mouseleave="$event.currentTarget.style.color = '#5a7a8a'"
                    >
                        Amigos
                        <span
                            v-if="pendingFriends > 0"
                            style="
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                min-width: 18px;
                                height: 18px;
                                padding: 0 5px;
                                background: #c74a6b;
                                color: white;
                                border-radius: 99px;
                                font-size: 10px;
                                font-weight: 800;
                                line-height: 1;
                            "
                            >{{ pendingFriends }}</span
                        >
                    </Link>
                    <Link
                        v-if="user"
                        :href="route('feed.index')"
                        style="
                            font-size: 13px;
                            font-weight: 600;
                            color: #5a7a8a;
                            text-decoration: none;
                            transition: color 0.2s;
                        "
                        @mouseenter="$event.currentTarget.style.color = '#009ac7'"
                        @mouseleave="$event.currentTarget.style.color = '#5a7a8a'"
                        >Feed</Link
                    >
                    <Link
                        v-if="user"
                        :href="route('conversations.index')"
                        style="
                            font-size: 13px;
                            font-weight: 600;
                            color: #5a7a8a;
                            text-decoration: none;
                            transition: color 0.2s;
                            position: relative;
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                        "
                        @mouseenter="$event.currentTarget.style.color = '#009ac7'"
                        @mouseleave="$event.currentTarget.style.color = '#5a7a8a'"
                    >
                        Mensagens
                        <span
                            v-if="unreadMessages > 0"
                            style="
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                min-width: 18px;
                                height: 18px;
                                padding: 0 5px;
                                background: #009ac7;
                                color: white;
                                border-radius: 99px;
                                font-size: 10px;
                                font-weight: 800;
                                line-height: 1;
                            "
                            >{{ unreadMessages }}</span
                        >
                    </Link>
                </div>

                <!-- Right: search + notifications + user -->
                <div style="display: flex; align-items: center; gap: 8px">
                    <!-- Search button -->
                    <button
                        @click.stop="openSearch"
                        style="
                            width: 34px;
                            height: 34px;
                            border-radius: 50%;
                            border: 1.5px solid #009ac722;
                            background: transparent;
                            color: #8ba0b0;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            transition: all 0.2s;
                        "
                        @mouseenter="
                            $event.currentTarget.style.background = '#009ac70c';
                            $event.currentTarget.style.color = '#009ac7';
                        "
                        @mouseleave="
                            $event.currentTarget.style.background = 'transparent';
                            $event.currentTarget.style.color = '#8ba0b0';
                        "
                        title="Pesquisar"
                    >
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                    </button>

                    <!-- Notifications bell -->
                    <Link
                        v-if="user"
                        :href="route('notifications.index')"
                        style="
                            position: relative;
                            width: 34px;
                            height: 34px;
                            border-radius: 50%;
                            border: 1.5px solid #009ac722;
                            background: transparent;
                            color: #8ba0b0;
                            text-decoration: none;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            transition: all 0.2s;
                        "
                        @mouseenter="
                            $event.currentTarget.style.background = '#009ac70c';
                            $event.currentTarget.style.color = '#009ac7';
                        "
                        @mouseleave="
                            $event.currentTarget.style.background = 'transparent';
                            $event.currentTarget.style.color = '#8ba0b0';
                        "
                        title="Notificações"
                    >
                        <svg
                            width="15"
                            height="15"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                        </svg>
                        <span
                            v-if="unreadNotifications > 0"
                            style="
                                position: absolute;
                                top: -3px;
                                right: -3px;
                                min-width: 16px;
                                height: 16px;
                                padding: 0 4px;
                                background: #c74a6b;
                                color: white;
                                border-radius: 99px;
                                font-size: 9px;
                                font-weight: 800;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                border: 2px solid white;
                                line-height: 1;
                            "
                            >{{ unreadNotifications > 9 ? '9+' : unreadNotifications }}</span
                        >
                    </Link>

                    <!-- User avatar dropdown -->
                    <div v-if="user" style="position: relative">
                        <button
                            @click.stop="open = !open"
                            style="
                                display: flex;
                                align-items: center;
                                gap: 10px;
                                background: none;
                                border: none;
                                cursor: pointer;
                                padding: 4px 8px;
                                border-radius: 99px;
                                transition: background 0.2s;
                            "
                            @mouseenter="$event.currentTarget.style.background = '#009ac70c'"
                            @mouseleave="$event.currentTarget.style.background = 'transparent'"
                        >
                            <img
                                v-if="user.avatar"
                                :src="clImg(user.avatar, 64, 64, 'fill', 'face')"
                                :style="{
                                    width: '32px',
                                    height: '32px',
                                    borderRadius: '50%',
                                    objectFit: 'cover',
                                    border: `2px solid ${user.avatar_color ?? '#009ac7'}`,
                                    boxShadow: `0 2px 8px ${user.avatar_color ?? '#009ac7'}44`,
                                }"
                            />
                            <div
                                v-else
                                :style="{
                                    width: '32px',
                                    height: '32px',
                                    borderRadius: '50%',
                                    background: user.avatar_color ?? '#009ac7',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: '13px',
                                    fontWeight: '800',
                                    color: 'white',
                                    boxShadow: `0 2px 8px ${user.avatar_color ?? '#009ac7'}44`,
                                }"
                            >
                                {{ avatarInitial(user.name) }}
                            </div>
                            <span style="font-size: 13px; font-weight: 700; color: #1a3a4a">{{ user.name }}</span>
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" style="color: #8ba0b0">
                                <path
                                    d="M2 4l4 4 4-4"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </button>

                        <Transition name="dropdown">
                            <div
                                v-if="open"
                                style="
                                    position: absolute;
                                    right: 0;
                                    top: calc(100% + 8px);
                                    min-width: 180px;
                                    background: rgba(255, 255, 255, 0.95);
                                    backdrop-filter: blur(16px);
                                    border-radius: 14px;
                                    border: 1px solid #4ebcff22;
                                    box-shadow: 0 8px 32px #009ac71a;
                                    overflow: hidden;
                                    z-index: 50;
                                "
                                @click.stop
                            >
                                <div style="padding: 12px 16px 10px; border-bottom: 1px solid #f0f4f8">
                                    <p style="font-size: 12px; font-weight: 700; color: #1a3a4a; margin: 0">
                                        {{ user.name }}
                                    </p>
                                    <p v-if="user.username" style="font-size: 11px; color: #8ba0b0; margin: 2px 0 0">
                                        @{{ user.username }}
                                    </p>
                                </div>
                                <div style="padding: 6px">
                                    <Link
                                        v-if="user.username"
                                        :href="route('profile.show', user.username)"
                                        @click="open = false"
                                        style="
                                            display: block;
                                            padding: 9px 12px;
                                            border-radius: 9px;
                                            font-size: 13px;
                                            color: #2a4a5a;
                                            text-decoration: none;
                                            transition: background 0.15s;
                                        "
                                        @mouseenter="$event.target.style.background = '#f0f8ff'"
                                        @mouseleave="$event.target.style.background = 'transparent'"
                                        >O meu perfil</Link
                                    >
                                    <Link
                                        :href="route('profile.edit')"
                                        @click="open = false"
                                        style="
                                            display: block;
                                            padding: 9px 12px;
                                            border-radius: 9px;
                                            font-size: 13px;
                                            color: #2a4a5a;
                                            text-decoration: none;
                                            transition: background 0.15s;
                                        "
                                        @mouseenter="$event.target.style.background = '#f0f8ff'"
                                        @mouseleave="$event.target.style.background = 'transparent'"
                                        >Definições</Link
                                    >
                                    <Link
                                        :href="route('notifications.index')"
                                        @click="open = false"
                                        style="
                                            display: flex;
                                            align-items: center;
                                            justify-content: space-between;
                                            padding: 9px 12px;
                                            border-radius: 9px;
                                            font-size: 13px;
                                            color: #2a4a5a;
                                            text-decoration: none;
                                            transition: background 0.15s;
                                        "
                                        @mouseenter="$event.currentTarget.style.background = '#f0f8ff'"
                                        @mouseleave="$event.currentTarget.style.background = 'transparent'"
                                    >
                                        Notificações
                                        <span
                                            v-if="unreadNotifications > 0"
                                            style="
                                                min-width: 18px;
                                                height: 18px;
                                                padding: 0 5px;
                                                background: #c74a6b;
                                                color: white;
                                                border-radius: 99px;
                                                font-size: 10px;
                                                font-weight: 800;
                                                display: inline-flex;
                                                align-items: center;
                                                justify-content: center;
                                            "
                                            >{{ unreadNotifications }}</span
                                        >
                                    </Link>
                                    <div style="height: 1px; background: #f0f4f8; margin: 4px 0" />
                                    <Link
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                        style="
                                            display: block;
                                            width: 100%;
                                            text-align: left;
                                            padding: 9px 12px;
                                            border-radius: 9px;
                                            font-size: 13px;
                                            color: #c74a6b;
                                            background: none;
                                            border: none;
                                            cursor: pointer;
                                            transition: background 0.15s;
                                        "
                                        @mouseenter="$event.target.style.background = '#fef0f4'"
                                        @mouseleave="$event.target.style.background = 'transparent'"
                                        >Sair</Link
                                    >
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Not logged in -->
                    <div v-else style="display: flex; gap: 10px">
                        <Link
                            :href="route('login')"
                            style="
                                font-size: 13px;
                                font-weight: 600;
                                color: #009ac7;
                                text-decoration: none;
                                padding: 7px 16px;
                                border-radius: 99px;
                                border: 1.5px solid #009ac7;
                                transition: all 0.2s;
                            "
                            >Entrar</Link
                        >
                        <Link
                            :href="route('register')"
                            style="
                                font-size: 13px;
                                font-weight: 700;
                                color: white;
                                text-decoration: none;
                                padding: 7px 16px;
                                border-radius: 99px;
                                background: #009ac7;
                                box-shadow: 0 3px 12px #009ac740;
                            "
                            >Registo</Link
                        >
                    </div>
                </div>
            </div>
        </nav>

        <!-- Global search overlay -->
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
                @click="searchOpen = false"
            >
                <form @submit.prevent="submitSearch" @click.stop style="width: 100%; max-width: 560px; margin: 0 20px">
                    <div style="position: relative">
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
                            id="global-search-input"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Pesquisa pessoas, comunidades ou posts…"
                            style="
                                width: 100%;
                                box-sizing: border-box;
                                background: rgba(255, 255, 255, 0.97);
                                border: none;
                                border-radius: 16px;
                                padding: 16px 16px 16px 50px;
                                font-size: 15px;
                                color: #1a3a4a;
                                outline: none;
                                font-family: inherit;
                                box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
                            "
                        />
                        <kbd
                            style="
                                position: absolute;
                                right: 16px;
                                top: 50%;
                                transform: translateY(-50%);
                                font-size: 11px;
                                color: #b0c0cc;
                                background: #f0f4f8;
                                border-radius: 6px;
                                padding: 2px 7px;
                                font-family: inherit;
                            "
                            >Esc</kbd
                        >
                    </div>
                    <p style="font-size: 11px; color: rgba(255, 255, 255, 0.7); margin: 10px 0 0; text-align: center">
                        Enter para pesquisar · Esc para fechar
                    </p>
                </form>
            </div>
        </Transition>

        <!-- Ambient orbs -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden" style="z-index: 0">
            <div
                style="
                    position: absolute;
                    top: -60px;
                    left: -60px;
                    width: 280px;
                    height: 280px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #009ac714 0%, transparent 70%);
                "
            />
            <div
                style="
                    position: absolute;
                    bottom: -40px;
                    right: -40px;
                    width: 240px;
                    height: 240px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #4ebcff0e 0%, transparent 70%);
                "
            />
        </div>

        <!-- Page content -->
        <main style="position: relative; z-index: 1">
            <slot />
        </main>

        <ToastContainer />
    </div>
</template>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.22s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
}

.overlay-enter-active,
.overlay-leave-active {
    transition: opacity 0.2s ease;
}
.overlay-enter-from,
.overlay-leave-to {
    opacity: 0;
}
</style>
