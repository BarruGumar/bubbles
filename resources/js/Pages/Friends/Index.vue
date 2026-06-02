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
    friends:  { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1, total: 0 }) },
    received: Array,
    sent:     Array,
    search:   { type: String, default: '' },
});

const page = usePage();
const localFriends  = ref([...(props.friends.data ?? [])]);
const localReceived = ref([...(props.received ?? [])]);
const localSent     = ref([...(props.sent     ?? [])]);
const localTotal    = ref(props.friends.total ?? 0);
const isLoadingMoreFriends = ref(false);
const searchTerm = ref(props.search ?? '');
let searchTimer = null;

watch(() => props.friends, (paginator) => {
    if (paginator.current_page > 1) {
        localFriends.value.push(...(paginator.data ?? []));
    } else {
        localFriends.value = [...(paginator.data ?? [])];
    }
    localTotal.value = paginator.total ?? 0;
}, { deep: true });
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
            localTotal.value++;
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
    const wasAccepted = localFriends.value.some(f => f.friendId === friendId);
    if (wasAccepted) localTotal.value--;
    localFriends.value = localFriends.value.filter(f => f.friendId !== friendId);
    localReceived.value = localReceived.value.filter(f => f.friendId !== friendId);
    localSent.value = localSent.value.filter(f => f.friendId !== friendId);
    router.delete(route('friends.reject', friendId), { preserveScroll: true });
}

function startConversation(friend) {
    router.post(route('conversations.store'), { recipient_id: friend.id });
}

const hasMoreFriends = computed(() => props.friends.current_page < props.friends.last_page);

function onSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            route('friends.index'),
            { search: searchTerm.value || undefined },
            { preserveScroll: true, preserveState: true }
        );
    }, 300);
}

function loadMoreFriends() {
    isLoadingMoreFriends.value = true;
    router.get(
        route('friends.index'),
        { page: props.friends.current_page + 1, search: searchTerm.value || undefined },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => { isLoadingMoreFriends.value = false; },
        }
    );
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
                <!-- Pesquisa -->
                <div style="position: relative; margin-bottom: 16px">
                    <svg
                        style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #b0c0cc; pointer-events: none"
                        width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                    >
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input
                        v-model="searchTerm"
                        @input="onSearch"
                        type="text"
                        placeholder="Pesquisar amigos…"
                        style="
                            width: 100%;
                            box-sizing: border-box;
                            padding: 9px 16px 9px 34px;
                            border-radius: 99px;
                            border: 1.5px solid #009ac720;
                            background: rgba(255,255,255,0.7);
                            font-size: 13px;
                            color: #3a6478;
                            outline: none;
                            transition: border-color 0.2s;
                        "
                        @focus="$event.target.style.borderColor = '#009ac750'"
                        @blur="$event.target.style.borderColor = '#009ac720'"
                    />
                </div>

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
                    Amigos · {{ localTotal }}
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

                <!-- Carregar mais amigos -->
                <div v-if="hasMoreFriends" style="text-align: center; margin-top: 20px">
                    <button
                        @click="loadMoreFriends"
                        :disabled="isLoadingMoreFriends"
                        :style="{
                            fontSize: '13px',
                            fontWeight: '700',
                            color: isLoadingMoreFriends ? '#b0c0cc' : '#009ac7',
                            background: 'none',
                            border: `1.5px solid ${isLoadingMoreFriends ? '#b0c0cc33' : '#009ac733'}`,
                            borderRadius: '99px',
                            padding: '8px 24px',
                            cursor: isLoadingMoreFriends ? 'default' : 'pointer',
                            transition: 'all 0.2s',
                        }"
                    >
                        {{ isLoadingMoreFriends ? 'A carregar…' : 'Carregar mais' }}
                    </button>
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
