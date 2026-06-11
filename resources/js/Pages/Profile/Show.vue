<script setup>
import axios from 'axios';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useOnlineUsers } from '@/Composables/useOnlineUsers';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PostCard from '@/Components/PostCard.vue';
import PostCardSkeleton from '@/Components/PostCardSkeleton.vue';
import PostReportForm from '@/Components/PostReportForm.vue';
import SiteOwnerBadge from '@/Components/SiteOwnerBadge.vue';
import { clImg } from '@/Composables/useCloudinary';
import { useToast } from '@/Composables/useToast';
import { useClipboardImage } from '@/Composables/useClipboardImage';
import { compressImage } from '@/Composables/useImageCompressor';
import { useAudio } from '@/Composables/useAudio';

const { show: toast } = useToast();
const { playSfx } = useAudio();

const props = defineProps({
    profileUser: Object,
    posts: Array,
    nextCursor: String,
    hasMorePosts: Boolean,
    communities: Array,
    profileFriends: Array,
    isOwn: Boolean,
    friendStatus: String,
    friendId: Number,
});

const localPosts = ref([...props.posts]);
const currentCursor = ref(props.nextCursor);
const hasMore = ref(props.hasMorePosts);
const loadingMore = ref(false);

watch(
    () => props.posts,
    (newPosts) => {
        if (loadingMore.value) return;
        localPosts.value = [...newPosts];
        currentCursor.value = props.nextCursor;
        hasMore.value = props.hasMorePosts;
    },
);

function loadMore() {
    if (!hasMore.value || loadingMore.value) return;
    loadingMore.value = true;
    router.reload({
        data: { cursor: currentCursor.value },
        only: ['posts', 'nextCursor', 'hasMorePosts'],
        preserveScroll: true,
        onSuccess: () => {
            localPosts.value = [...localPosts.value, ...props.posts];
            currentCursor.value = props.nextCursor;
            hasMore.value = props.hasMorePosts;
            loadingMore.value = false;
        },
        onError: () => {
            loadingMore.value = false;
        },
    });
}

const authUser = computed(() => usePage().props.auth?.user);
const { onlineUsers } = useOnlineUsers();
const isOnline = (userId) => userId != null && onlineUsers.value.has(userId);

const postForm = useForm({ content: '', image: null, video: null });
const charCount = computed(() => postForm.content.length);
const mediaInput = ref(null);
const mediaPreview = ref(null);
const isVideoMedia = ref(false);
const uploadProgress = ref(0);
const uploadingServer = ref(false);

async function onMediaChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (mediaPreview.value) URL.revokeObjectURL(mediaPreview.value);
    if (file.type.startsWith('video/')) {
        postForm.image = null;
        postForm.video = file;
        isVideoMedia.value = true;
        mediaPreview.value = URL.createObjectURL(file);
    } else {
        postForm.video = null;
        isVideoMedia.value = false;
        mediaPreview.value = URL.createObjectURL(file);
        postForm.image = file;
        postForm.image = await compressImage(file);
    }
}

function removeMedia() {
    if (mediaPreview.value) URL.revokeObjectURL(mediaPreview.value);
    postForm.image = null;
    postForm.video = null;
    mediaPreview.value = null;
    isVideoMedia.value = false;
    if (mediaInput.value) mediaInput.value.value = '';
}

async function setMediaFile(file) {
    if (mediaPreview.value) URL.revokeObjectURL(mediaPreview.value);
    postForm.video = null;
    isVideoMedia.value = false;
    mediaPreview.value = URL.createObjectURL(file);
    postForm.image = file;
    postForm.image = await compressImage(file);
}

const { handlePaste: handlePostPaste } = useClipboardImage({
    onImage: setMediaFile,
    maxKB: 4096,
    onError: (msg) => toast(msg, 'error'),
});

onUnmounted(() => {
    if (mediaPreview.value) URL.revokeObjectURL(mediaPreview.value);
});

function submitPost() {
    if (!postForm.content.trim()) return;
    playSfx('send');
    uploadProgress.value = 0;
    uploadingServer.value = false;
    postForm.post(route('posts.store'), {
        forceFormData: true,
        preserveScroll: true,
        onProgress: (p) => {
            uploadProgress.value = p?.percentage ?? 0;
            if (uploadProgress.value >= 100) uploadingServer.value = true;
        },
        onSuccess: () => {
            if (usePage().props.flash?.error) return;
            postForm.reset('content', 'image', 'video');
            removeMedia();
            uploadProgress.value = 0;
            uploadingServer.value = false;
            toast('Publicação criada com sucesso.');
        },
        onError: () => {
            uploadProgress.value = 0;
            uploadingServer.value = false;
            toast('Erro ao publicar. Tenta novamente.', 'error');
        },
        onFinish: () => {
            uploadProgress.value = 0;
            uploadingServer.value = false;
        },
    });
}

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}

function sendFriendRequest() {
    router.post(route('friends.send', props.profileUser.username), {}, { preserveScroll: true });
}

function acceptFriendRequest() {
    router.patch(route('friends.accept', props.friendId), {}, { preserveScroll: true });
}

function removeFriend() {
    playSfx('leave');
    router.delete(route('friends.reject', props.friendId), { preserveScroll: true });
}

function startConversation() {
    router.post(route('conversations.store'), { recipient_id: props.profileUser.id });
}

const showUserReport = ref(false);
const userReportText = ref('');
const userReportSending = ref(false);

async function submitUserReport() {
    const text = userReportText.value.trim();
    if (!text || userReportSending.value) return;
    userReportSending.value = true;
    try {
        await axios.post(route('users.report', props.profileUser.id), { reason: text });
        showUserReport.value = false;
        userReportText.value = '';
        toast('Denúncia enviada.');
    } catch (e) {
        const msg = e?.response?.data?.errors?.reason?.[0]
            ?? e?.response?.data?.message
            ?? 'Erro ao enviar denúncia.';
        toast(msg, 'error');
    } finally {
        userReportSending.value = false;
    }
}

</script>

<template>
    <Head :title="`@${profileUser.username} · bubbles`" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px">
            <!-- Profile hero -->
            <div
                style="
                    border-radius: 22px;
                    overflow: visible;
                    margin-bottom: 10px;
                    box-shadow: 0 8px 32px #009ac70e;
                    position: relative;
                "
            >
                <!-- Banner -->
                <div
                    :style="{
                        height: '190px',
                        borderRadius: '22px 22px 0 0',
                        position: 'relative',
                        background: profileUser.banner
                            ? `url('${clImg(profileUser.banner, 1200, 400, 'fill')}') center/cover no-repeat`
                            : `linear-gradient(135deg, ${profileUser.avatar_color}cc 0%, ${profileUser.avatar_color} 100%)`,
                    }"
                >
                    <!-- Avatar overlapping -->
                    <div style="position: absolute; bottom: -45px; left: 32px; z-index: 5">
                        <div style="position: relative; display: inline-block">
                            <span v-if="profileUser.avatar" style="position:relative;display:inline-block;border-radius:50%;line-height:0;">
                                <img
                                    :src="clImg(profileUser.avatar, 200, 200, 'fill', 'face')"
                                    :style="{
                                        width: '90px',
                                        height: '90px',
                                        borderRadius: '50%',
                                        objectFit: 'cover',
                                        display: 'block',
                                        border: profileUser.role === 'site_owner' ? '4px solid transparent' : '4px solid white',
                                        boxShadow: profileUser.role === 'site_owner'
                                            ? '0 0 0 3px #d4a017, 0 4px 24px #d4a01755'
                                            : `0 4px 20px ${profileUser.avatar_color}66`,
                                    }"
                                />
                                <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.35) 0%,transparent 55%);pointer-events:none;"></span>
                            </span>
                            <div
                                v-else
                                :style="{
                                    width: '90px',
                                    height: '90px',
                                    borderRadius: '50%',
                                    position: 'relative',
                                    background: `radial-gradient(circle at 38% 30%, rgba(255,255,255,.3), transparent 55%), ${profileUser.avatar_color}`,
                                    border: profileUser.role === 'site_owner' ? '4px solid transparent' : '4px solid white',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: '30px',
                                    fontWeight: '900',
                                    color: 'white',
                                    boxShadow: profileUser.role === 'site_owner'
                                        ? '0 0 0 3px #d4a017, 0 4px 24px #d4a01755'
                                        : `0 4px 20px ${profileUser.avatar_color}66`,
                                }"
                            >
                                <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.25) 0%,transparent 50%);pointer-events:none;"></span>
                                {{ formatInitial(profileUser.name) }}
                            </div>
                            <span
                                v-if="profileUser.role === 'site_owner'"
                                style="position: absolute; bottom: 0; right: 0; font-size: 20px; line-height: 1"
                            >👑</span>
                            <span
                                v-else-if="!isOwn && isOnline(profileUser.id)"
                                style="position:absolute;bottom:4px;right:4px;width:16px;height:16px;border-radius:50%;background:#22c55e;border:3px solid white;box-shadow:0 0 0 1px #22c55e44"
                            ></span>
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div
                    style="
                        background: var(--card-bg);
                        backdrop-filter: blur(20px);
                        border: 1px solid #4ebcff22;
                        border-top: none;
                        border-radius: 0 0 22px 22px;
                        padding: 58px 32px 28px;
                        position: relative;
                        z-index: 1;
                    "
                >
                    <div
                        style="
                            display: flex;
                            align-items: flex-start;
                            justify-content: space-between;
                            gap: 12px;
                            flex-wrap: wrap;
                        "
                    >
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 2px">
                                <h1
                                    :style="{
                                        fontSize: '22px',
                                        fontWeight: '900',
                                        margin: '0',
                                        ...(profileUser.role === 'site_owner' ? {
                                            background: 'linear-gradient(135deg, #d4a017 0%, #c084fc 100%)',
                                            WebkitBackgroundClip: 'text',
                                            WebkitTextFillColor: 'transparent',
                                            backgroundClip: 'text',
                                        } : { color: '#3a6478' }),
                                    }"
                                >
                                    {{ profileUser.name }}
                                </h1>
                                <SiteOwnerBadge v-if="profileUser.role === 'site_owner'" size="md" />
                            </div>
                            <p style="font-size: 13px; color: #009ac7; font-weight: 600; margin: 0">
                                @{{ profileUser.username }}
                            </p>
                        </div>
                        <!-- Editar (próprio perfil) -->
                        <Link
                            v-if="isOwn"
                            :href="route('profile.edit')"
                            style="
                                font-size: 12px;
                                font-weight: 700;
                                color: #009ac7;
                                text-decoration: none;
                                padding: 7px 18px;
                                border-radius: 99px;
                                border: 1.5px solid #009ac744;
                                background: #009ac708;
                                transition: all 0.2s;
                                white-space: nowrap;
                            "
                            >Editar perfil</Link
                        >

                        <!-- Botões de amizade (perfil de outro utilizador) -->
                        <div v-else-if="friendStatus" style="display: flex; gap: 8px; flex-wrap: wrap">
                            <!-- Sem relação → pode enviar pedido -->
                            <button
                                v-if="friendStatus === 'none'"
                                @click="sendFriendRequest"
                                style="
                                    font-size: 12px;
                                    font-weight: 700;
                                    color: white;
                                    padding: 7px 18px;
                                    border-radius: 99px;
                                    border: 1px solid rgba(255,255,255,.45);
                                    background: linear-gradient(180deg, rgba(255,255,255,.22) 0%, rgba(255,255,255,.04) 50%, rgba(0,0,0,.06) 100%), linear-gradient(180deg, #4ebcff 0%, #009ac7 55%, #006d8e 100%);
                                    cursor: pointer;
                                    white-space: nowrap;
                                    box-shadow: 0 6px 24px rgba(0,154,199,.5), inset 0 1px 0 rgba(255,255,255,.35);
                                    transition: opacity 0.2s;
                                "
                                @mouseenter="$event.target.style.opacity = '.8'"
                                @mouseleave="$event.target.style.opacity = '1'"
                            >
                                + Adicionar amigo
                            </button>

                            <!-- Pedido enviado → aguardando -->
                            <template v-else-if="friendStatus === 'pending_sent'">
                                <button
                                    disabled
                                    style="
                                        font-size: 12px;
                                        font-weight: 700;
                                        color: #8ba0b0;
                                        padding: 7px 18px;
                                        border-radius: 99px;
                                        border: 1.5px solid #c8d8e0;
                                        background: #f0f8ff;
                                        cursor: not-allowed;
                                        white-space: nowrap;
                                    "
                                >
                                    Pedido enviado
                                </button>
                                <button
                                    @click="removeFriend"
                                    style="
                                        font-size: 12px;
                                        font-weight: 600;
                                        color: #8ba0b0;
                                        padding: 7px 14px;
                                        border-radius: 99px;
                                        border: 1.5px solid #c8d8e0;
                                        background: transparent;
                                        cursor: pointer;
                                        white-space: nowrap;
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
                            </template>

                            <!-- Pedido recebido → aceitar ou recusar -->
                            <template v-else-if="friendStatus === 'pending_received'">
                                <button
                                    @click="acceptFriendRequest"
                                    style="
                                        font-size: 12px;
                                        font-weight: 700;
                                        color: white;
                                        padding: 7px 18px;
                                        border-radius: 99px;
                                        border: 1px solid rgba(255,255,255,.25);
                                        background: linear-gradient(180deg, #4ebcff 0%, #009ac7 55%, #006d8e 100%);
                                        cursor: pointer;
                                        white-space: nowrap;
                                        box-shadow: 0 3px 12px #009ac740;
                                        transition: opacity 0.2s;
                                    "
                                    @mouseenter="$event.target.style.opacity = '.8'"
                                    @mouseleave="$event.target.style.opacity = '1'"
                                >
                                    Aceitar pedido
                                </button>
                                <button
                                    @click="removeFriend"
                                    style="
                                        font-size: 12px;
                                        font-weight: 600;
                                        color: #8ba0b0;
                                        padding: 7px 14px;
                                        border-radius: 99px;
                                        border: 1.5px solid #c8d8e0;
                                        background: transparent;
                                        cursor: pointer;
                                        white-space: nowrap;
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
                            </template>

                            <!-- Já são amigos -->
                            <template v-else-if="friendStatus === 'accepted'">
                                <button
                                    disabled
                                    style="
                                        font-size: 12px;
                                        font-weight: 700;
                                        color: #2ea87e;
                                        padding: 7px 18px;
                                        border-radius: 99px;
                                        border: 1.5px solid #2ea87e55;
                                        background: #2ea87e0c;
                                        cursor: default;
                                        white-space: nowrap;
                                    "
                                >
                                    ✓ Amigos
                                </button>
                                <button
                                    @click="startConversation"
                                    style="
                                        font-size: 12px;
                                        font-weight: 700;
                                        color: white;
                                        padding: 7px 18px;
                                        border-radius: 99px;
                                        border: 1px solid rgba(255,255,255,.25);
                                        background: linear-gradient(180deg, #4ebcff 0%, #009ac7 55%, #006d8e 100%);
                                        cursor: pointer;
                                        white-space: nowrap;
                                        box-shadow: 0 3px 12px #009ac740;
                                        transition: opacity 0.2s;
                                    "
                                    @mouseenter="$event.target.style.opacity = '.85'"
                                    @mouseleave="$event.target.style.opacity = '1'"
                                >
                                    💬 Mensagem
                                </button>
                                <button
                                    @click="removeFriend"
                                    style="
                                        font-size: 12px;
                                        font-weight: 600;
                                        color: #8ba0b0;
                                        padding: 7px 14px;
                                        border-radius: 99px;
                                        border: 1.5px solid #c8d8e0;
                                        background: transparent;
                                        cursor: pointer;
                                        white-space: nowrap;
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
                            </template>
                        </div>
                    </div>

                    <!-- Report user -->
                    <div v-if="!isOwn && authUser" style="margin-top: 10px">
                        <button
                            v-if="!showUserReport"
                            @click="showUserReport = true"
                            style="
                                font-size: 11px;
                                color: #b0c0cc;
                                background: none;
                                border: none;
                                cursor: pointer;
                                padding: 0;
                                transition: color .2s;
                            "
                            @mouseenter="$event.target.style.color = '#e05555'"
                            @mouseleave="$event.target.style.color = '#b0c0cc'"
                        >⚑ Denunciar utilizador</button>
                        <PostReportForm
                            v-else
                            :text="userReportText"
                            :sending="userReportSending"
                            label="utilizador"
                            @update:text="userReportText = $event"
                            @submit="submitUserReport"
                            @cancel="showUserReport = false; userReportText = ''"
                        />
                    </div>

                    <p
                        v-if="profileUser.bio"
                        style="font-size: 14px; color: #3a5a6a; margin: 14px 0 0; line-height: 1.6"
                    >
                        {{ profileUser.bio }}
                    </p>
                    <p v-else-if="isOwn" style="font-size: 13px; color: #b0c0cc; margin: 14px 0 0; font-style: italic">
                        Adiciona uma bio no teu perfil...
                    </p>

                    <div style="display: flex; gap: 24px; margin-top: 18px">
                        <div>
                            <span style="font-size: 18px; font-weight: 800; color: #3a6478">{{
                                profileUser.posts_count
                            }}</span>
                            <span style="font-size: 11px; color: #8ba0b0; font-weight: 600; margin-left: 5px"
                                >posts</span
                            >
                        </div>
                        <div>
                            <span style="font-size: 11px; color: #8ba0b0; font-weight: 600"
                                >Membro desde {{ profileUser.created_at }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty communities state -->
            <div
                v-if="!communities || communities.length === 0"
                style="
                    background: var(--card-bg);
                    backdrop-filter: blur(20px);
                    border-radius: 16px;
                    border: 1px solid #4ebcff1a;
                    box-shadow: 0 2px 12px #009ac708;
                    padding: 28px 22px;
                    margin-bottom: 16px;
                    text-align: center;
                "
            >
                <p style="font-size: 22px; margin: 0 0 8px">🫧</p>
                <p style="font-size: 13px; color: #8ba0b0; margin: 0">
                    {{
                        isOwn
                            ? 'Ainda não fazes parte de nenhuma comunidade. Explora as bolhas!'
                            : 'Ainda não faz parte de nenhuma comunidade.'
                    }}
                </p>
            </div>

            <div
                v-if="communities && communities.length"
                style="
                    background: var(--card-bg);
                    backdrop-filter: blur(20px);
                    border-radius: 16px;
                    border: 1px solid #4ebcff1a;
                    box-shadow: 0 2px 12px #009ac708;
                    padding: 16px 22px 20px;
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
                        margin: 0 0 2px;
                    "
                >
                    Comunidades · {{ communities.length }}
                </p>

                <div style="display: flex; padding-top: 12px; gap: 6px;">
                    <Link
                        v-for="c in communities.slice(0, 4)"
                        :key="c.id"
                        :href="route('community.show', c.id)"
                        style="flex: 1; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 8px; min-width: 0;"
                    >
                        <div
                            :title="c.title !== c.label ? c.title : undefined"
                            :style="{
                                width: '68px',
                                height: '68px',
                                borderRadius: '50%',
                                backgroundImage: c.image
                                    ? `radial-gradient(circle at 38% 32%, ${c.color}55 0%, ${c.color}99 100%), url('${c.image}')`
                                    : `radial-gradient(circle at 38% 32%, ${c.color}ee 0%, ${c.color} 60%)`,
                                backgroundSize: 'cover',
                                backgroundPosition: 'center',
                                position: 'relative',
                                overflow: 'hidden',
                                boxShadow: `0 4px 16px ${c.color}44, 0 2px 6px ${c.color}22`,
                                transition: 'transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .22s',
                                flexShrink: '0',
                            }"
                            @mouseenter="e => { e.currentTarget.style.transform = 'scale(1.1)'; e.currentTarget.style.boxShadow = `0 8px 24px ${c.color}66, 0 3px 8px ${c.color}33`; }"
                            @mouseleave="e => { e.currentTarget.style.transform = 'scale(1)'; e.currentTarget.style.boxShadow = `0 4px 16px ${c.color}44, 0 2px 6px ${c.color}22`; }"
                        >
                            <div style="position:absolute;top:7px;left:14%;width:72%;height:34%;border-radius:50%;background:rgba(255,255,255,0.22);transform:rotate(-10deg);pointer-events:none;" />
                            <span style="position:relative;font-size:9px;font-weight:800;color:white;text-align:center;padding:0 5px;line-height:1.2;text-shadow:0 1px 3px rgba(0,0,0,.35);word-break:break-word;max-width:100%;display:flex;align-items:center;justify-content:center;height:100%;">{{ c.label }}</span>
                        </div>
                        <span style="font-size: 11px; font-weight: 700; color: #5a7a8a; text-align: center; line-height: 1.2; word-break: break-word; width: 100%; display: block;">
                            {{ c.title ?? c.label }}
                        </span>
                    </Link>

                    <!-- Ver mais -->
                    <Link
                        v-if="communities.length > 4"
                        :href="isOwn ? route('communities.index') : route('profile.communities', profileUser.username)"
                        style="flex: 1; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 8px; min-width: 0;"
                    >
                        <div
                            style="
                                width: 68px;
                                height: 68px;
                                border-radius: 50%;
                                background: rgba(0,154,199,0.10);
                                border: 2px dashed #009ac755;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 15px;
                                color: #009ac7;
                                font-weight: 800;
                                transition: background .2s, transform .22s cubic-bezier(.34,1.56,.64,1);
                                cursor: pointer;
                                flex-shrink: 0;
                            "
                            @mouseenter="e => { e.currentTarget.style.background = 'rgba(0,154,199,0.18)'; e.currentTarget.style.transform = 'scale(1.08)'; }"
                            @mouseleave="e => { e.currentTarget.style.background = 'rgba(0,154,199,0.10)'; e.currentTarget.style.transform = 'scale(1)'; }"
                        >+{{ communities.length - 4 }}</div>
                        <span style="font-size: 11px; font-weight: 700; color: #009ac7; text-align: center; line-height: 1.2;">Ver mais</span>
                    </Link>
                </div>
            </div>

            <!-- Amigos -->
            <div
                v-if="profileFriends && profileFriends.length"
                style="
                    background: var(--card-bg);
                    backdrop-filter: blur(20px);
                    border-radius: 16px;
                    border: 1px solid #4ebcff1a;
                    box-shadow: 0 2px 12px #009ac708;
                    padding: 16px 22px;
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
                        margin: 0 0 14px;
                    "
                >
                    Amigos · {{ profileFriends.length }}
                </p>
                <div style="display: flex; gap: 6px;">
                    <Link
                        v-for="f in profileFriends.slice(0, 4)"
                        :key="f.id"
                        :href="route('profile.show', f.username)"
                        style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px; text-decoration: none; min-width: 0;"
                    >
                        <span
                            v-if="f.avatar"
                            style="position:relative;display:inline-block;border-radius:50%;line-height:0;transition:transform .22s cubic-bezier(.34,1.56,.64,1);"
                            @mouseenter="$event.currentTarget.style.transform = 'scale(1.08)'"
                            @mouseleave="$event.currentTarget.style.transform = 'scale(1)'"
                        >
                            <img
                                :src="f.avatar"
                                loading="lazy"
                                :style="{ width:'62px', height:'62px', borderRadius:'50%', objectFit:'cover', display:'block', border:`2.5px solid ${f.avatar_color}`, boxShadow:`0 3px 12px ${f.avatar_color}44` }"
                            />
                            <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.35) 0%,transparent 55%);pointer-events:none;"></span>
                        </span>
                        <div
                            v-else
                            :style="{
                                width: '62px',
                                height: '62px',
                                borderRadius: '50%',
                                position: 'relative',
                                background: `radial-gradient(circle at 38% 30%, rgba(255,255,255,.3), transparent 55%), ${f.avatar_color}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontSize: '22px',
                                fontWeight: '800',
                                color: 'white',
                                boxShadow: `0 3px 12px ${f.avatar_color}44`,
                                flexShrink: '0',
                                transition: 'transform .22s cubic-bezier(.34,1.56,.64,1)',
                            }"
                            @mouseenter="$event.currentTarget.style.transform = 'scale(1.08)'"
                            @mouseleave="$event.currentTarget.style.transform = 'scale(1)'"
                        >
                            <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.25) 0%,transparent 50%);pointer-events:none;"></span>
                            {{ formatInitial(f.name) }}
                        </div>
                        <span
                            style="
                                font-size: 11px;
                                font-weight: 600;
                                color: #4a6a7a;
                                text-align: center;
                                width: 100%;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                white-space: nowrap;
                            "
                            >{{ f.name.split(' ')[0] }}</span
                        >
                    </Link>

                    <!-- Ver mais amigos -->
                    <Link
                        v-if="profileFriends.length > 4"
                        :href="isOwn ? route('friends.index') : route('profile.friends', profileUser.username)"
                        style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px; text-decoration: none; min-width: 0;"
                    >
                        <div
                            style="
                                width: 62px;
                                height: 62px;
                                border-radius: 50%;
                                background: rgba(0,154,199,0.10);
                                border: 2px dashed #009ac755;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 15px;
                                color: #009ac7;
                                font-weight: 800;
                                transition: background .2s, transform .22s cubic-bezier(.34,1.56,.64,1);
                                cursor: pointer;
                                flex-shrink: 0;
                            "
                            @mouseenter="e => { e.currentTarget.style.background = 'rgba(0,154,199,0.18)'; e.currentTarget.style.transform = 'scale(1.08)'; }"
                            @mouseleave="e => { e.currentTarget.style.background = 'rgba(0,154,199,0.10)'; e.currentTarget.style.transform = 'scale(1)'; }"
                        >+{{ profileFriends.length - 4 }}</div>
                        <span style="font-size: 11px; font-weight: 700; color: #009ac7; text-align: center; line-height: 1.2;">Ver mais</span>
                    </Link>
                </div>
            </div>

            <!-- New post box (only own profile + logged in) -->
            <div
                v-if="isOwn"
                style="
                    background: var(--card-bg);
                    backdrop-filter: blur(20px);
                    border-radius: 18px;
                    border: 1px solid #4ebcff22;
                    box-shadow: 0 4px 16px #009ac70a;
                    padding: 20px;
                    margin-bottom: 16px;
                "
            >
                <div style="display: flex; gap: 14px; align-items: flex-start">
                    <!-- Mini avatar -->
                    <img
                        v-if="authUser?.avatar"
                        :src="authUser.avatar"
                        :style="{
                            width: '36px',
                            height: '36px',
                            borderRadius: '50%',
                            flexShrink: 0,
                            objectFit: 'cover',
                            border: `2px solid ${authUser.avatar_color ?? '#009ac7'}`,
                        }"
                    />
                    <div
                        v-else
                        :style="{
                            width: '36px',
                            height: '36px',
                            borderRadius: '50%',
                            flexShrink: 0,
                            background: authUser?.avatar_color ?? '#009ac7',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: '14px',
                            fontWeight: '800',
                            color: 'white',
                        }"
                    >
                        {{ formatInitial(authUser?.name) }}
                    </div>

                    <div style="flex: 1">
                        <textarea
                            v-model="postForm.content"
                            placeholder="O que estás a pensar?"
                            maxlength="1000"
                            rows="3"
                            style="
                                width: 100%;
                                background: #f0f8ff;
                                border: 1.5px solid #4ebcff33;
                                border-radius: 12px;
                                padding: 12px 14px;
                                font-size: 14px;
                                color: #3a6478;
                                outline: none;
                                font-family: inherit;
                                resize: vertical;
                                transition: border-color 0.2s;
                                box-sizing: border-box;
                            "
                            @focus="$event.target.style.borderColor = '#009ac7'"
                            @blur="$event.target.style.borderColor = '#4ebcff33'"
                            @keydown.ctrl.enter="submitPost"
                            @paste="handlePostPaste"
                        />
                        <div v-if="mediaPreview" style="margin-top: 10px; position: relative; display: inline-block">
                            <video
                                v-if="isVideoMedia"
                                :src="mediaPreview"
                                style="
                                    max-height: 200px;
                                    max-width: 100%;
                                    border-radius: 10px;
                                    display: block;
                                    border: 1px solid #4ebcff22;
                                "
                                controls
                                preload="metadata"
                            />
                            <img
                                v-else
                                :src="mediaPreview"
                                style="
                                    max-height: 160px;
                                    max-width: 100%;
                                    border-radius: 10px;
                                    object-fit: cover;
                                    border: 1px solid #4ebcff22;
                                "
                            />
                            <button
                                type="button"
                                @click="removeMedia"
                                style="
                                    position: absolute;
                                    top: 6px;
                                    right: 6px;
                                    background: rgba(0, 0, 0, 0.45);
                                    border: none;
                                    border-radius: 50%;
                                    width: 22px;
                                    height: 22px;
                                    cursor: pointer;
                                    color: white;
                                    font-size: 14px;
                                    line-height: 1;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                "
                            >
                                ×
                            </button>
                        </div>

                        <div
                            style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px"
                        >
                            <div style="display: flex; align-items: center; gap: 8px">
                                <span style="font-size: 11px; color: #b0c0cc"
                                    >{{ charCount }}/1000 · Ctrl+Enter para publicar</span
                                >
                                <button
                                    type="button"
                                    @click="mediaInput.click()"
                                    :style="{
                                        background: 'none',
                                        border: 'none',
                                        cursor: 'pointer',
                                        color: postForm.image || postForm.video ? '#009ac7' : '#b0c0cc',
                                        padding: '3px',
                                        borderRadius: '6px',
                                        transition: 'color .2s',
                                        display: 'flex',
                                        alignItems: 'center',
                                    }"
                                    title="Adicionar imagem ou vídeo"
                                >
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <rect
                                            x="1.5"
                                            y="2.5"
                                            width="13"
                                            height="11"
                                            rx="2"
                                            stroke="currentColor"
                                            stroke-width="1.3"
                                        />
                                        <circle cx="5.5" cy="6" r="1.2" fill="currentColor" />
                                        <path
                                            d="M1.5 11l3.5-3 3 3 2-2 3.5 3.5"
                                            stroke="currentColor"
                                            stroke-width="1.3"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </button>
                                <input
                                    ref="mediaInput"
                                    type="file"
                                    accept="image/*,video/mp4,video/webm,video/quicktime"
                                    style="display: none"
                                    @change="onMediaChange"
                                />
                            </div>
                            <button
                                @click="submitPost"
                                :disabled="postForm.processing || !postForm.content.trim()"
                                style="
                                    padding: 8px 20px;
                                    border-radius: 99px;
                                    border: 1px solid rgba(255,255,255,.45);
                                    background: linear-gradient(180deg, rgba(255,255,255,.22) 0%, rgba(255,255,255,.04) 50%, rgba(0,0,0,.06) 100%), linear-gradient(180deg, #4ebcff 0%, #009ac7 55%, #006d8e 100%);
                                    color: white;
                                    font-size: 12px;
                                    font-weight: 700;
                                    cursor: pointer;
                                    box-shadow: 0 6px 24px rgba(0,154,199,.5), inset 0 1px 0 rgba(255,255,255,.35);
                                    transition: opacity 0.2s;
                                "
                                :style="{
                                    opacity: postForm.processing || !postForm.content.trim() ? 0.5 : 1,
                                    cursor: !postForm.content.trim() ? 'not-allowed' : 'pointer',
                                }"
                            >
                                {{
                                    postForm.processing
                                        ? uploadingServer
                                            ? 'A processar...'
                                            : `A carregar... ${uploadProgress}%`
                                        : 'Publicar'
                                }}
                            </button>
                        </div>

                        <!-- Video upload progress bar -->
                        <div v-if="postForm.processing && postForm.video" style="margin-top: 8px">
                            <div style="height: 4px; background: #e8f4fb; border-radius: 99px; overflow: hidden">
                                <div
                                    :style="{
                                        width: uploadingServer ? '100%' : uploadProgress + '%',
                                        height: '100%',
                                        background: '#009ac7',
                                        borderRadius: '99px',
                                        transition: uploadingServer ? 'none' : 'width .3s ease',
                                    }"
                                />
                            </div>
                            <p style="font-size: 10px; color: #8ba0b0; margin: 4px 0 0">
                                {{
                                    uploadingServer
                                        ? 'A guardar no servidor... pode demorar alguns segundos.'
                                        : 'A enviar vídeo...'
                                }}
                            </p>
                        </div>

                        <p v-if="postForm.errors.content" style="font-size: 11px; color: #e05555; margin: 6px 0 0">
                            {{ postForm.errors.content }}
                        </p>
                        <p v-if="postForm.errors.image" style="font-size: 11px; color: #e05555; margin: 6px 0 0">
                            {{ postForm.errors.image }}
                        </p>
                        <p v-if="postForm.errors.video" style="font-size: 11px; color: #e05555; margin: 6px 0 0">
                            {{ postForm.errors.video }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Posts feed -->
            <div v-if="localPosts.length === 0" style="text-align: center; padding: 60px 20px">
                <p style="font-size: 32px; margin: 0 0 12px">🫧</p>
                <p style="font-size: 14px; color: #8ba0b0">
                    {{ isOwn ? 'Ainda não publicaste nada. Começa agora!' : 'Ainda não há posts aqui.' }}
                </p>
            </div>

            <div v-else style="display: flex; flex-direction: column; gap: 12px">
                <PostCard
                    v-for="post in localPosts"
                    :key="post.id"
                    :post="post"
                    :author="{
                        name: profileUser.name,
                        username: profileUser.username,
                        avatar: profileUser.avatar,
                        avatar_color: profileUser.avatar_color,
                    }"
                    :auth-user="authUser"
                    :can-edit="isOwn"
                    :can-delete="isOwn"
                    :like-route="route('posts.like', post.id)"
                    :reactors-route="route('posts.reactors', post.id)"
                    :comment-route="route('posts.comments.store', post.id)"
                    :delete-route="route('posts.destroy', post.id)"
                    :edit-route="route('posts.update', post.id)"
                    :report-route="!isOwn && authUser ? route('posts.report', post.id) : null"
                    @deleted="localPosts = localPosts.filter(p => p.id !== $event)"
                />
            </div>

            <!-- Skeleton cards enquanto carrega mais -->
            <div v-if="loadingMore" style="display: flex; flex-direction: column; gap: 12px; margin-top: 12px">
                <PostCardSkeleton v-for="n in 3" :key="n" />
            </div>

            <!-- Load more -->
            <div v-if="hasMore && !loadingMore" style="text-align: center; margin-top: 8px">
                <button
                    @click="loadMore"
                    style="
                        padding: 10px 28px;
                        border-radius: 99px;
                        border: 1.5px solid #4ebcff44;
                        background: rgba(255, 255, 255, 0.85);
                        color: #009ac7;
                        font-size: 13px;
                        font-weight: 700;
                        cursor: pointer;
                        transition: all 0.2s;
                        backdrop-filter: blur(10px);
                    "
                    @mouseenter="$event.currentTarget.style.background = '#e8f7ff'"
                    @mouseleave="$event.currentTarget.style.background = 'rgba(255,255,255,0.85)'"
                >
                    Carregar mais
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
