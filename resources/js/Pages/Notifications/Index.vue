<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { clImg } from '@/Composables/useCloudinary';
import { useAudio } from '@/Composables/useAudio';

const props = defineProps({
    notifications: { type: Object, default: () => ({ data: [], next_cursor: null }) },
});

const page = usePage();
const { notifSoundEnabled, setNotifSoundEnabled, playClick } = useAudio();
const localNotifications = ref([...props.notifications.data]);
const isLoadingMore = ref(false);
const appendMode = ref(false);

watch(() => props.notifications, (paginator) => {
    if (appendMode.value) {
        localNotifications.value.push(...paginator.data);
    } else {
        localNotifications.value = [...paginator.data];
    }
}, { deep: true });

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
            comment_replied: '↩️',
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

function deleteNotification(id) {
    router.delete(route('notifications.destroy', id), {}, { preserveScroll: true });
}

function deleteAll() {
    if (!confirmDeleteAll.value) {
        confirmDeleteAll.value = true;
        return;
    }
    confirmDeleteAll.value = false;
    router.delete(route('notifications.destroy-all'), {}, { preserveScroll: true });
}

const hasUnread = computed(() => localNotifications.value.some((n) => !n.read));
const hasMore = computed(() => !!props.notifications.next_cursor);

function loadMore() {
    isLoadingMore.value = true;
    appendMode.value = true;
    router.get(
        route('notifications.index'),
        { cursor: props.notifications.next_cursor },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => { isLoadingMore.value = false; appendMode.value = false; },
        }
    );
}

const confirmDeleteAll = ref(false);

const handleNotificationCreated = (e) => { localNotifications.value.unshift(e.notification); };

onMounted(() => {
    window.dispatchEvent(new CustomEvent('notifications-read'));
    if (page.props.auth?.user) {
        window.Echo.private(`user.${page.props.auth.user.id}`)
            .listen('.NotificationCreated', handleNotificationCreated);
    }
});

onUnmounted(() => {
    if (page.props.auth?.user) {
        window.Echo.private(`user.${page.props.auth.user.id}`)
            .stopListening('.NotificationCreated');
    }
});
</script>

<template>
    <Head title="Notificações · bubbles" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px">
            <!-- Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px">
                <h1 style="font-size: 22px; font-weight: 900; color: #3a6478; margin: 0; letter-spacing: -0.02em">
                    Notificações
                </h1>
                <div style="display: flex; gap: 8px; align-items: center">
                    <!-- Toggle som de notificações -->
                    <button
                        @click="setNotifSoundEnabled(!notifSoundEnabled); playClick()"
                        :title="notifSoundEnabled ? 'Silenciar notificações' : 'Ativar som de notificações'"
                        :style="{
                            fontSize: '13px',
                            fontWeight: '700',
                            color: notifSoundEnabled ? '#009ac7' : '#8ba0b0',
                            background: 'none',
                            border: `1.5px solid ${notifSoundEnabled ? '#009ac733' : '#8ba0b033'}`,
                            borderRadius: '99px',
                            padding: '6px 14px',
                            cursor: 'pointer',
                            transition: 'all 0.2s',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '6px',
                        }"
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            <line v-if="!notifSoundEnabled" x1="3" y1="3" x2="21" y2="21"/>
                        </svg>
                        {{ notifSoundEnabled ? 'Som On' : 'Som Off' }}
                    </button>
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
                    <template v-if="localNotifications.length > 0">
                        <template v-if="confirmDeleteAll">
                            <button
                                @click="deleteAll"
                                style="
                                    font-size: 12px;
                                    font-weight: 700;
                                    color: white;
                                    background: #e05353;
                                    border: none;
                                    border-radius: 99px;
                                    padding: 6px 16px;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                "
                            >
                                Sim, apagar
                            </button>
                            <button
                                @click="confirmDeleteAll = false"
                                style="
                                    font-size: 12px;
                                    font-weight: 600;
                                    color: #8ba0b0;
                                    background: none;
                                    border: 1.5px solid #8ba0b033;
                                    border-radius: 99px;
                                    padding: 6px 14px;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                "
                            >
                                Cancelar
                            </button>
                        </template>
                        <button
                            v-else
                            @click="deleteAll"
                            style="
                                font-size: 12px;
                                font-weight: 700;
                                color: #e05353;
                                background: none;
                                border: 1.5px solid #e0535333;
                                border-radius: 99px;
                                padding: 6px 16px;
                                cursor: pointer;
                                transition: all 0.2s;
                            "
                            @mouseenter="$event.currentTarget.style.background = '#e053530c'"
                            @mouseleave="$event.currentTarget.style.background = 'transparent'"
                        >
                            Apagar todas
                        </button>
                    </template>
                </div>
            </div>

            <!-- Empty -->
            <div
                v-if="localNotifications.length === 0"
                style="
                    text-align: center;
                    padding: 60px 20px;
                    background: rgba(255, 255, 255, 0.88);
                    backdrop-filter: blur(20px);
                    border-radius: 18px;
                    border: 1px solid #4ebcff22;
                    box-shadow: 0 4px 16px #009ac708;
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
                    border: 1px solid #4ebcff22;
                    box-shadow: 0 4px 20px #009ac70c;
                    overflow: hidden;
                "
            >
                <component
                    :is="n.url ? Link : 'div'"
                    v-for="(n, i) in localNotifications"
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
                        <span v-if="senderOf(n).avatar" style="position:relative;display:inline-block;border-radius:50%;line-height:0;">
                            <img
                                :src="clImg(senderOf(n).avatar, 88, 88, 'fill', 'face')"
                                :style="{
                                    width: '44px',
                                    height: '44px',
                                    borderRadius: '50%',
                                    objectFit: 'cover',
                                    display: 'block',
                                    border: `2px solid ${senderOf(n).color}`,
                                    boxShadow: `0 2px 10px ${senderOf(n).color}44`,
                                }"
                            />
                            <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.35) 0%,transparent 55%);pointer-events:none;"></span>
                        </span>
                        <div
                            v-else
                            :style="{
                                width: '44px',
                                height: '44px',
                                borderRadius: '50%',
                                position: 'relative',
                                background: `radial-gradient(circle at 38% 30%, rgba(255,255,255,.3), transparent 55%), ${senderOf(n).color}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontSize: '16px',
                                fontWeight: '800',
                                color: 'white',
                            }"
                        >
                            <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.25) 0%,transparent 50%);pointer-events:none;"></span>
                            {{ formatInitial(senderOf(n).name) }}
                        </div>
                        <span style="position: absolute; bottom: -2px; right: -4px; font-size: 14px; line-height: 1">{{
                            typeIcon(n.type)
                        }}</span>
                    </div>

                    <!-- Content -->
                    <div style="flex: 1; min-width: 0">
                        <p
                            style="font-size: 13px; color: #3a6478; margin: 0 0 3px; line-height: 1.4"
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

                    <!-- Action buttons -->
                    <div style="display: flex; flex-direction: column; gap: 4px; flex-shrink: 0; align-items: center">
                        <button
                            v-if="!n.read"
                            @click.prevent.stop="markRead(n.id)"
                            style="
                                background: none;
                                border: none;
                                cursor: pointer;
                                color: #b0c0cc;
                                padding: 4px 6px;
                                border-radius: 6px;
                                transition: color 0.15s;
                                font-size: 13px;
                                line-height: 1;
                            "
                            @mouseenter="$event.currentTarget.style.color = '#009ac7'"
                            @mouseleave="$event.currentTarget.style.color = '#b0c0cc'"
                            title="Marcar como lida"
                        >
                            ✓
                        </button>
                        <button
                            @click.prevent.stop="deleteNotification(n.id)"
                            style="
                                background: none;
                                border: none;
                                cursor: pointer;
                                color: #c8d8e0;
                                padding: 4px 6px;
                                border-radius: 6px;
                                transition: color 0.15s;
                                font-size: 14px;
                                line-height: 1;
                            "
                            @mouseenter="$event.currentTarget.style.color = '#e05353'"
                            @mouseleave="$event.currentTarget.style.color = '#c8d8e0'"
                            title="Apagar notificação"
                        >
                            ×
                        </button>
                    </div>
                </component>
            </div>
            <!-- Carregar mais -->
            <div v-if="hasMore" style="text-align: center; margin-top: 20px">
                <button
                    @click="loadMore"
                    :disabled="isLoadingMore"
                    :style="{
                        fontSize: '13px',
                        fontWeight: '700',
                        color: isLoadingMore ? '#b0c0cc' : '#009ac7',
                        background: 'none',
                        border: `1.5px solid ${isLoadingMore ? '#b0c0cc33' : '#009ac733'}`,
                        borderRadius: '99px',
                        padding: '8px 24px',
                        cursor: isLoadingMore ? 'default' : 'pointer',
                        transition: 'all 0.2s',
                    }"
                >
                    {{ isLoadingMore ? 'A carregar…' : 'Carregar mais' }}
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
