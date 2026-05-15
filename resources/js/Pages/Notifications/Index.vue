<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { clImg } from '@/Composables/useCloudinary';

const props = defineProps({
    notifications: { type: Array, default: () => [] },
});

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}

function senderOf(n) {
    const d = n.data;
    return {
        name: d.sender_name ?? d.liker_name ?? d.commenter_name ?? '?',
        username: d.sender_username ?? d.liker_username ?? d.commenter_username ?? null,
        avatar: d.sender_avatar ?? d.liker_avatar ?? d.commenter_avatar ?? null,
        color: d.sender_color ?? d.liker_color ?? d.commenter_color ?? '#009ac7',
    };
}

function typeIcon(type) {
    return (
        {
            friend_request_received: '👋',
            friend_request_accepted: '✅',
            post_liked: '❤️',
            post_commented: '💬',
            message_received: '✉️',
        }[type] ?? '🔔'
    );
}

function markRead(id) {
    router.patch(route('notifications.read', id), {}, { preserveScroll: true });
}

function markAllRead() {
    router.patch(route('notifications.read-all'), {}, { preserveScroll: true });
}

const hasUnread = props.notifications.some((n) => !n.read);
</script>

<template>
    <Head title="Notificações · bubbles" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px">
            <!-- Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px">
                <h1 style="font-size: 22px; font-weight: 900; color: #1a3a4a; margin: 0; letter-spacing: -0.02em">
                    Notificações
                </h1>
                <button
                    v-if="hasUnread"
                    @click="markAllRead"
                    style="
                        font-size: 12px;
                        font-weight: 700;
                        color: #009ac7;
                        background: none;
                        border: 1.5px solid #009ac733;
                        border-radius: 99px;
                        padding: 6px 16px;
                        cursor: pointer;
                        transition: all 0.2s;
                    "
                    @mouseenter="$event.currentTarget.style.background = '#009ac70c'"
                    @mouseleave="$event.currentTarget.style.background = 'transparent'"
                >
                    Marcar todas como lidas
                </button>
            </div>

            <!-- Empty -->
            <div
                v-if="notifications.length === 0"
                style="
                    text-align: center;
                    padding: 60px 20px;
                    background: rgba(255, 255, 255, 0.88);
                    border-radius: 18px;
                    border: 1px solid #4ebcff1a;
                "
            >
                <p style="font-size: 32px; margin: 0 0 12px">🔔</p>
                <p style="font-size: 14px; color: #8ba0b0; margin: 0">Sem notificações ainda.</p>
            </div>

            <!-- List -->
            <div
                v-else
                style="
                    background: rgba(255, 255, 255, 0.88);
                    backdrop-filter: blur(20px);
                    border-radius: 18px;
                    border: 1px solid #4ebcff1a;
                    box-shadow: 0 4px 16px #009ac708;
                    overflow: hidden;
                "
            >
                <component
                    :is="n.url ? Link : 'div'"
                    v-for="(n, i) in notifications"
                    :key="n.id"
                    :href="n.url ?? undefined"
                    @click="!n.read && markRead(n.id)"
                    style="
                        display: flex;
                        align-items: flex-start;
                        gap: 14px;
                        padding: 16px 20px;
                        text-decoration: none;
                        cursor: pointer;
                        transition: background 0.15s;
                        position: relative;
                    "
                    :style="{
                        borderTop: i > 0 ? '1px solid #009ac70a' : 'none',
                        background: n.read ? 'transparent' : 'rgba(0,154,199,0.035)',
                    }"
                    @mouseenter="
                        $event.currentTarget.style.background = n.read ? 'rgba(0,154,199,0.03)' : 'rgba(0,154,199,0.06)'
                    "
                    @mouseleave="
                        $event.currentTarget.style.background = n.read ? 'transparent' : 'rgba(0,154,199,0.035)'
                    "
                >
                    <!-- Unread dot -->
                    <span
                        v-if="!n.read"
                        style="
                            position: absolute;
                            top: 18px;
                            left: 8px;
                            width: 6px;
                            height: 6px;
                            border-radius: 50%;
                            background: #009ac7;
                            flex-shrink: 0;
                        "
                    />

                    <!-- Avatar with icon overlay -->
                    <div style="position: relative; flex-shrink: 0">
                        <div :style="{ width: '44px', height: '44px' }">
                            <img
                                v-if="senderOf(n).avatar"
                                :src="clImg(senderOf(n).avatar, 88, 88, 'fill', 'face')"
                                :style="{
                                    width: '44px',
                                    height: '44px',
                                    borderRadius: '50%',
                                    objectFit: 'cover',
                                    border: `2px solid ${senderOf(n).color}`,
                                    display: 'block',
                                }"
                            />
                            <div
                                v-else
                                :style="{
                                    width: '44px',
                                    height: '44px',
                                    borderRadius: '50%',
                                    background: senderOf(n).color,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: '16px',
                                    fontWeight: '800',
                                    color: 'white',
                                }"
                            >
                                {{ formatInitial(senderOf(n).name) }}
                            </div>
                        </div>
                        <span style="position: absolute; bottom: -2px; right: -4px; font-size: 14px; line-height: 1">{{
                            typeIcon(n.type)
                        }}</span>
                    </div>

                    <!-- Content -->
                    <div style="flex: 1; min-width: 0">
                        <p
                            style="font-size: 13px; color: #1a3a4a; margin: 0 0 3px; line-height: 1.4"
                            :style="{ fontWeight: n.read ? '400' : '600' }"
                        >
                            {{ n.data.message }}
                        </p>
                        <p
                            v-if="n.data.comment_excerpt || n.data.excerpt"
                            style="
                                font-size: 11px;
                                color: #8ba0b0;
                                margin: 0 0 3px;
                                font-style: italic;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                white-space: nowrap;
                            "
                        >
                            "{{ n.data.comment_excerpt ?? n.data.excerpt }}"
                        </p>
                        <p style="font-size: 10px; color: #b0c0cc; margin: 0">{{ n.created_at }}</p>
                    </div>

                    <!-- Mark read button (if unread) -->
                    <button
                        v-if="!n.read"
                        @click.prevent.stop="markRead(n.id)"
                        style="
                            flex-shrink: 0;
                            background: none;
                            border: none;
                            cursor: pointer;
                            color: #b0c0cc;
                            padding: 4px;
                            border-radius: 6px;
                            transition: color 0.15s;
                            font-size: 11px;
                        "
                        @mouseenter="$event.currentTarget.style.color = '#009ac7'"
                        @mouseleave="$event.currentTarget.style.color = '#b0c0cc'"
                        title="Marcar como lida"
                    >
                        ✓
                    </button>
                </component>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
