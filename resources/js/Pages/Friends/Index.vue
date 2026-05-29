<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useOnlineUsers } from '@/Composables/useOnlineUsers';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { clImg } from '@/Composables/useCloudinary';
import { useAudio } from '@/Composables/useAudio';

const { playSfx } = useAudio();
const { onlineUsers } = useOnlineUsers();
const isOnline = (userId) => userId != null && onlineUsers.value.has(userId);

const props = defineProps({
    friends: Array,
    received: Array,
    sent: Array,
});

const page = usePage();
const localFriends  = ref([...(props.friends  ?? [])]);
const localReceived = ref([...(props.received ?? [])]);
const localSent     = ref([...(props.sent     ?? [])]);

watch(() => props.friends,  (v) => { localFriends.value  = [...(v ?? [])]; }, { deep: true });
watch(() => props.received, (v) => { localReceived.value = [...(v ?? [])]; }, { deep: true });
watch(() => props.sent,     (v) => { localSent.value     = [...(v ?? [])]; }, { deep: true });

const handleFriendshipUpdated = (e) => {
    if (e.event === 'request_received') {
        if (!localReceived.value.some(f => f.friendId === e.friend.friendId)) {
            localReceived.value.unshift(e.friend);
        }
        playSfx('notification');
    } else if (e.event === 'request_accepted') {
        if (!localFriends.value.some(f => f.id === e.friend.id)) {
            localFriends.value.unshift(e.friend);
        }
        localSent.value = localSent.value.filter(f => f.friendId !== e.friend.friendId);
    }
};

onMounted(() => {
    if (page.props.auth?.user) {
        window.Echo.private(`user.${page.props.auth.user.id}`)
            .listen('.FriendshipUpdated', handleFriendshipUpdated);
    }
});

onUnmounted(() => {
    if (page.props.auth?.user) {
        window.Echo.private(`user.${page.props.auth.user.id}`)
            .stopListening('.FriendshipUpdated');
    }
});

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}

function accept(friendId) {
    router.patch(route('friends.accept', friendId), {}, { preserveScroll: true });
}

function reject(friendId) {
    router.delete(route('friends.reject', friendId), { preserveScroll: true });
}

function startConversation(friend) {
    router.post(route('conversations.store'), { recipient_id: friend.id });
}
</script>

<template>
    <Head title="Amigos · bubbles" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px">
            <h1 style="font-size: 22px; font-weight: 900; color: #3a6478; margin: 0 0 24px; letter-spacing: -0.02em">
                Amigos
            </h1>

            <!-- Pedidos recebidos -->
            <div
                v-if="localReceived.length"
                style="
                    background: rgba(255, 255, 255, 0.88);
                    backdrop-filter: blur(20px);
                    border-radius: 18px;
                    border: 1px solid #c74a6b22;
                    box-shadow: 0 4px 16px #c74a6b08;
                    padding: 20px;
                    margin-bottom: 16px;
                "
            >
                <p
                    style="
                        font-size: 10px;
                        font-weight: 800;
                        color: #c74a6b;
                        text-transform: uppercase;
                        letter-spacing: 0.1em;
                        margin: 0 0 16px;
                    "
                >
                    Pedidos recebidos · {{ localReceived.length }}
                </p>
                <div style="display: flex; flex-direction: column; gap: 14px">
                    <div
                        v-for="req in localReceived"
                        :key="req.friendId"
                        style="display: flex; align-items: center; gap: 14px"
                    >
                        <!-- Avatar clicável -->
                        <Link :href="route('profile.show', req.username)" style="text-decoration: none; flex-shrink: 0">
                            <img
                                v-if="req.avatar"
                                :src="clImg(req.avatar, 96, 96, 'fill', 'face')"
                                :style="{
                                    width: '46px',
                                    height: '46px',
                                    borderRadius: '50%',
                                    objectFit: 'cover',
                                    border: `2px solid ${req.avatar_color}`,
                                    boxShadow: `0 2px 8px ${req.avatar_color}44`,
                                    display: 'block',
                                }"
                            />
                            <div
                                v-else
                                :style="{
                                    width: '46px',
                                    height: '46px',
                                    borderRadius: '50%',
                                    background: req.avatar_color,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: '17px',
                                    fontWeight: '800',
                                    color: 'white',
                                    boxShadow: `0 2px 8px ${req.avatar_color}44`,
                                }"
                            >
                                {{ formatInitial(req.name) }}
                            </div>
                        </Link>

                        <!-- Info + Ações -->
                        <div class="list-content">
                        <Link
                            :href="route('profile.show', req.username)"
                            style="text-decoration: none; min-width: 0; flex: 1"
                        >
                            <p
                                style="
                                    font-size: 13px;
                                    font-weight: 700;
                                    color: #3a6478;
                                    margin: 0;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                "
                            >
                                {{ req.name }}
                            </p>
                            <p v-if="req.username" style="font-size: 11px; color: #009ac7; margin: 2px 0 0">
                                @{{ req.username }}
                            </p>
                        </Link>

                        <!-- Ações -->
                        <div class="list-actions">
                            <button
                                @click="accept(req.friendId)"
                                style="
                                    padding: 7px 16px;
                                    border-radius: 99px;
                                    border: none;
                                    background: #009ac7;
                                    color: white;
                                    font-size: 12px;
                                    font-weight: 700;
                                    cursor: pointer;
                                    box-shadow: 0 3px 10px #009ac730;
                                    transition: opacity 0.2s;
                                "
                                @mouseenter="$event.target.style.opacity = '.8'"
                                @mouseleave="$event.target.style.opacity = '1'"
                            >
                                Aceitar
                            </button>
                            <button
                                @click="reject(req.friendId)"
                                style="
                                    padding: 7px 14px;
                                    border-radius: 99px;
                                    border: 1.5px solid #c8d8e0;
                                    background: transparent;
                                    color: #8ba0b0;
                                    font-size: 12px;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                "
                                @mouseenter="
                                    $event.currentTarget.style.borderColor = '#e05555';
                                    $event.currentTarget.style.color = '#e05555';
                                "
                                @mouseleave="
                                    $event.currentTarget.style.borderColor = '#c8d8e0';
                                    $event.currentTarget.style.color = '#8ba0b0';
                                "
                            >
                                Recusar
                            </button>
                        </div>
                        </div><!-- /list-content -->
                    </div>
                </div>
            </div>

            <!-- Pedidos enviados -->
            <div
                v-if="localSent.length"
                style="
                    background: rgba(255, 255, 255, 0.88);
                    backdrop-filter: blur(20px);
                    border-radius: 18px;
                    border: 1px solid #4ebcff22;
                    box-shadow: 0 4px 16px #009ac70a;
                    padding: 20px;
                    margin-bottom: 16px;
                "
            >
                <p
                    style="
                        font-size: 10px;
                        font-weight: 800;
                        color: #8ba0b0;
                        text-transform: uppercase;
                        letter-spacing: 0.1em;
                        margin: 0 0 16px;
                    "
                >
                    Pedidos enviados · {{ localSent.length }}
                </p>
                <div style="display: flex; flex-direction: column; gap: 14px">
                    <div v-for="req in localSent" :key="req.friendId" style="display: flex; align-items: center; gap: 14px">
                        <Link :href="route('profile.show', req.username)" style="text-decoration: none; flex-shrink: 0">
                            <img
                                v-if="req.avatar"
                                :src="clImg(req.avatar, 96, 96, 'fill', 'face')"
                                :style="{
                                    width: '46px',
                                    height: '46px',
                                    borderRadius: '50%',
                                    objectFit: 'cover',
                                    border: `2px solid ${req.avatar_color}`,
                                    boxShadow: `0 2px 8px ${req.avatar_color}44`,
                                    display: 'block',
                                }"
                            />
                            <div
                                v-else
                                :style="{
                                    width: '46px',
                                    height: '46px',
                                    borderRadius: '50%',
                                    background: req.avatar_color,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: '17px',
                                    fontWeight: '800',
                                    color: 'white',
                                    boxShadow: `0 2px 8px ${req.avatar_color}44`,
                                }"
                            >
                                {{ formatInitial(req.name) }}
                            </div>
                        </Link>

                        <div class="list-content">
                        <Link
                            :href="route('profile.show', req.username)"
                            style="text-decoration: none; min-width: 0; flex: 1"
                        >
                            <p
                                style="
                                    font-size: 13px;
                                    font-weight: 700;
                                    color: #3a6478;
                                    margin: 0;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                "
                            >
                                {{ req.name }}
                            </p>
                            <p v-if="req.username" style="font-size: 11px; color: #009ac7; margin: 2px 0 0">
                                @{{ req.username }}
                            </p>
                        </Link>

                        <div class="list-actions">
                        <button
                            @click="reject(req.friendId)"
                            style="
                                padding: 7px 14px;
                                border-radius: 99px;
                                border: 1.5px solid #c8d8e0;
                                background: transparent;
                                color: #8ba0b0;
                                font-size: 12px;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.2s;
                            "
                            @mouseenter="
                                $event.currentTarget.style.borderColor = '#e05555';
                                $event.currentTarget.style.color = '#e05555';
                            "
                            @mouseleave="
                                $event.currentTarget.style.borderColor = '#c8d8e0';
                                $event.currentTarget.style.color = '#8ba0b0';
                            "
                        >
                            Cancelar
                        </button>
                        </div><!-- /list-actions -->
                        </div><!-- /list-content -->
                    </div>
                </div>
            </div>

            <!-- Lista de amigos aceites -->
            <div
                style="
                    background: rgba(255, 255, 255, 0.88);
                    backdrop-filter: blur(20px);
                    border-radius: 18px;
                    border: 1px solid #4ebcff22;
                    box-shadow: 0 4px 16px #009ac70a;
                    padding: 20px;
                "
            >
                <p
                    style="
                        font-size: 10px;
                        font-weight: 800;
                        color: #8ba0b0;
                        text-transform: uppercase;
                        letter-spacing: 0.1em;
                        margin: 0 0 16px;
                    "
                >
                    Amigos · {{ localFriends.length }}
                </p>

                <!-- Estado vazio -->
                <div
                    v-if="localFriends.length === 0 && localReceived.length === 0 && localSent.length === 0"
                    style="text-align: center; padding: 40px 20px"
                >
                    <p style="font-size: 32px; margin: 0 0 12px">🫧</p>
                    <p style="font-size: 14px; color: #8ba0b0">Ainda não tens amigos no bubbles.</p>
                    <p style="font-size: 12px; color: #b0c0cc; margin-top: 6px">
                        Visita o perfil de alguém e envia um pedido de amizade.
                    </p>
                </div>
                <div v-else-if="localFriends.length === 0" style="text-align: center; padding: 20px">
                    <p style="font-size: 13px; color: #b0c0cc">Nenhum amigo aceite ainda.</p>
                </div>

                <div v-else style="display: flex; flex-direction: column; gap: 14px">
                    <div
                        v-for="friend in localFriends"
                        :key="friend.friendId"
                        style="display: flex; align-items: center; gap: 14px"
                    >
                        <div style="position:relative;flex-shrink:0">
                            <Link
                                :href="route('profile.show', friend.username)"
                                style="text-decoration: none; display: block"
                            >
                                <img
                                    v-if="friend.avatar"
                                    :src="clImg(friend.avatar, 96, 96, 'fill', 'face')"
                                    :style="{
                                        width: '46px',
                                        height: '46px',
                                        borderRadius: '50%',
                                        objectFit: 'cover',
                                        border: `2px solid ${friend.avatar_color}`,
                                        boxShadow: `0 2px 8px ${friend.avatar_color}44`,
                                        display: 'block',
                                    }"
                                />
                                <div
                                    v-else
                                    :style="{
                                        width: '46px',
                                        height: '46px',
                                        borderRadius: '50%',
                                        background: friend.avatar_color,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        fontSize: '17px',
                                        fontWeight: '800',
                                        color: 'white',
                                        boxShadow: `0 2px 8px ${friend.avatar_color}44`,
                                    }"
                                >
                                    {{ formatInitial(friend.name) }}
                                </div>
                            </Link>
                            <span v-if="isOnline(friend.id)" class="online-dot"></span>
                        </div>

                        <div class="list-content">
                        <Link
                            :href="route('profile.show', friend.username)"
                            style="text-decoration: none; min-width: 0; flex: 1"
                        >
                            <p
                                style="
                                    font-size: 13px;
                                    font-weight: 700;
                                    color: #3a6478;
                                    margin: 0;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                "
                            >
                                {{ friend.name }}
                            </p>
                            <p v-if="friend.username" style="font-size: 11px; color: #009ac7; margin: 2px 0 0">
                                @{{ friend.username }}
                            </p>
                        </Link>

                        <div class="list-actions">
                        <button
                            @click="startConversation(friend)"
                            style="
                                font-size: 12px;
                                font-weight: 700;
                                color: white;
                                padding: 7px 18px;
                                border-radius: 99px;
                                border: none;
                                background: linear-gradient(135deg, #009ac7, #4ebcff);
                                cursor: pointer;
                                white-space: nowrap;
                                box-shadow: 0 3px 12px #009ac730;
                                transition: opacity 0.2s;
                            "
                            @mouseenter="$event.target.style.opacity = '.85'"
                            @mouseleave="$event.target.style.opacity = '1'"
                        >
                            💬 Mensagem
                        </button>

                        <button
                            @click="playSfx('leave'); reject(friend.friendId)"
                            style="
                                padding: 7px 14px;
                                border-radius: 99px;
                                border: 1.5px solid #c8d8e0;
                                background: transparent;
                                color: #8ba0b0;
                                font-size: 12px;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.2s;
                            "
                            @mouseenter="
                                $event.currentTarget.style.borderColor = '#e05555';
                                $event.currentTarget.style.color = '#e05555';
                            "
                            @mouseleave="
                                $event.currentTarget.style.borderColor = '#c8d8e0';
                                $event.currentTarget.style.color = '#8ba0b0';
                            "
                        >
                            Remover
                        </button>
                        </div><!-- /list-actions -->
                        </div><!-- /list-content -->
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.list-content {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
}
.list-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

@media (max-width: 640px) {
    .list-content {
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
    }
}

.online-dot {
    position: absolute; bottom: 1px; right: 1px;
    width: 12px; height: 12px; border-radius: 50%;
    background: #22c55e;
    border: 2.5px solid white;
    box-shadow: 0 0 0 1px #22c55e44;
}
</style>
