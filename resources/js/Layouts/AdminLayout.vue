<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ToastContainer from '@/Components/ToastContainer.vue';
import AudioControls from '@/Components/AudioControls.vue';
import { useToast } from '@/Composables/useToast';
import { useAudio } from '@/Composables/useAudio';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const sidebarOpen = ref(false);
function toggleSidebar() { sidebarOpen.value = !sidebarOpen.value; }
function closeSidebar() { sidebarOpen.value = false; }
const { show: toast } = useToast();
const { playBgm, playSfx } = useAudio();

const adminClickKeys = ['buttonAdmin', 'buttonAdmin2', 'buttonAdmin3'];
function onAdminClick(e) {
    if (e.target.closest('button, a[href]'))
        playSfx(adminClickKeys[Math.floor(Math.random() * adminClickKeys.length)]);
}

playBgm('admin');

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

const navLinks = [
    { label: 'Dashboard', route: 'admin.dashboard', icon: '⊞' },
    { label: 'Utilizadores', route: 'admin.users', icon: '👤' },
    { label: 'Posts', route: 'admin.posts', icon: '📝' },
    { label: 'Posts Comunidades', route: 'admin.community-posts', icon: '💬' },
    { label: 'Comunidades', route: 'admin.communities', icon: '🫧' },
    { label: 'Denúncias', route: 'admin.reports', icon: '⚑' },
    { label: 'Punições', route: 'admin.punishments', icon: '🔒' },
    { label: 'Avisos', route: 'admin.announcements', icon: '📢' },
    { label: 'Audit Log', route: 'admin.audit-logs', icon: '📋' },
];
</script>

<template>
    <div style="min-height: 100vh; background: #f0f4f8; font-family: 'Segoe UI', system-ui, sans-serif; display: flex" @click.capture="onAdminClick">
        <!-- Mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="sidebar-overlay"
            @click="closeSidebar"
        />

        <!-- Sidebar -->
        <aside
            class="admin-sidebar"
            :class="{ open: sidebarOpen }"
            style="
                width: 220px;
                flex-shrink: 0;
                background: #1a2a3a;
                min-height: 100vh;
                position: sticky;
                top: 0;
                height: 100vh;
                display: flex;
                flex-direction: column;
            "
        >
            <div style="padding: 20px 18px; border-bottom: 1px solid #ffffff14">
                <Link href="/bubbles" style="text-decoration: none">
                    <span style="font-weight: 900; font-size: 26px; color: #009ac7; letter-spacing: -1px">bubbles</span>
                </Link>
                <p
                    style="
                        font-size: 10px;
                        color: #4a7a9a;
                        margin: 2px 0 0;
                        font-weight: 700;
                        text-transform: uppercase;
                        letter-spacing: 0.08em;
                    "
                >
                    Painel Admin
                </p>
            </div>

            <nav style="padding: 12px 10px; flex: 1">
                <Link
                    v-for="link in navLinks"
                    :key="link.route"
                    :href="route(link.route)"
                    @click="closeSidebar"
                    style="
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 10px 12px;
                        border-radius: 10px;
                        text-decoration: none;
                        margin-bottom: 2px;
                        font-size: 13px;
                        font-weight: 600;
                        color: #7a9ab0;
                        transition: all 0.15s;
                    "
                    @mouseenter="
                        $event.currentTarget.style.background = '#ffffff12';
                        $event.currentTarget.style.color = 'white';
                    "
                    @mouseleave="
                        $event.currentTarget.style.background = 'transparent';
                        $event.currentTarget.style.color = '#7a9ab0';
                    "
                >
                    <span style="font-size: 14px">{{ link.icon }}</span>
                    {{ link.label }}
                </Link>
            </nav>

            <div style="padding: 14px 16px; border-top: 1px solid #ffffff14">
                <p style="font-size: 11px; color: #4a7a9a; margin: 0">Logado como</p>
                <p style="font-size: 12px; font-weight: 700; color: #7ab0cc; margin: 2px 0 0">{{ user?.name }}</p>
            </div>
        </aside>

        <!-- Main -->
        <div style="flex: 1; min-width: 0">
            <header
                class="admin-header"
                style="
                    background: white;
                    border-bottom: 1px solid #e8f0f8;
                    padding: 0 28px;
                    height: 54px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    position: sticky;
                    top: 0;
                    z-index: 10;
                "
            >
                <div style="display: flex; align-items: center; gap: 10px; min-width: 0">
                    <button class="hamburger-btn" @click="toggleSidebar">☰</button>
                    <slot name="header">
                        <h1 style="font-size: 16px; font-weight: 800; color: #3a6478; margin: 0">Admin</h1>
                    </slot>
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-shrink:0">
                    <AudioControls />
                    <Link href="/bubbles" class="back-link" style="font-size: 12px; color: #009ac7; text-decoration: none; font-weight: 600">← Voltar ao site</Link>
                </div>
            </header>

            <main style="padding: 28px">
                <slot />
            </main>
        </div>

        <ToastContainer />
    </div>
</template>

<style scoped>
/* Hamburger: hidden on desktop */
.hamburger-btn {
    display: none;
    background: none;
    border: none;
    font-size: 20px;
    color: #3a6478;
    cursor: pointer;
    padding: 4px 6px;
    line-height: 1;
    flex-shrink: 0;
}

/* Back link: hide text on very small screens */
@media (max-width: 400px) {
    .back-link { font-size: 0; }
    .back-link::after { content: '←'; font-size: 16px; }
}

@media (max-width: 768px) {
    .hamburger-btn {
        display: block;
    }

    /* Sidebar: fixed drawer, hidden off-screen */
    .admin-sidebar {
        position: fixed !important;
        top: 0;
        left: 0;
        z-index: 100;
        transform: translateX(-220px);
        transition: transform 0.28s cubic-bezier(0.2, 0.8, 0.2, 1);
        box-shadow: none;
    }
    .admin-sidebar.open {
        transform: translateX(0);
        box-shadow: 4px 0 32px rgba(0, 0, 0, 0.4);
    }

    /* Overlay */
    .sidebar-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 99;
    }

    /* Reduce header padding */
    .admin-header {
        padding: 0 16px !important;
    }
}
</style>
