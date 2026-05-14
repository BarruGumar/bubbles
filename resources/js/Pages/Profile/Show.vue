<script setup>
import { computed, onUnmounted, ref } from 'vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PostCard from '@/Components/PostCard.vue'
import PostCardSkeleton from '@/Components/PostCardSkeleton.vue'
import { clImg } from '@/Composables/useCloudinary'
import { useToast } from '@/Composables/useToast'

const { show: toast } = useToast()

const props = defineProps({
    profileUser:    Object,
    posts:          Array,
    nextCursor:     String,
    hasMorePosts:   Boolean,
    communities:    Array,
    profileFriends: Array,
    isOwn:          Boolean,
    friendStatus:   String,
    friendId:       Number,
})

const localPosts    = ref([...props.posts])
const currentCursor = ref(props.nextCursor)
const hasMore       = ref(props.hasMorePosts)
const loadingMore   = ref(false)

function loadMore() {
    if (!hasMore.value || loadingMore.value) return
    loadingMore.value = true
    router.reload({
        data:          { cursor: currentCursor.value },
        only:          ['posts', 'nextCursor', 'hasMorePosts'],
        preserveScroll: true,
        onSuccess: () => {
            localPosts.value    = [...localPosts.value, ...props.posts]
            currentCursor.value = props.nextCursor
            hasMore.value       = props.hasMorePosts
            loadingMore.value   = false
        },
        onError: () => { loadingMore.value = false },
    })
}

const authUser = computed(() => usePage().props.auth?.user)

const postForm = useForm({ content: '', image: null })
const charCount = computed(() => postForm.content.length)
const imageInput = ref(null)
const imagePreview = ref(null)

function onImageChange(e) {
    const file = e.target.files[0]
    if (!file) return
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
    postForm.image = file
    imagePreview.value = URL.createObjectURL(file)
}

function removeImage() {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
    postForm.image = null
    imagePreview.value = null
    if (imageInput.value) imageInput.value.value = ''
}

onUnmounted(() => {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
})

function submitPost() {
    if (!postForm.content.trim()) return
    postForm.post(route('posts.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            postForm.reset('content', 'image')
            removeImage()
            toast('Publicação criada com sucesso.')
        },
        onError: () => toast('Erro ao publicar. Tenta novamente.', 'error'),
    })
}

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase()
}

function sendFriendRequest() {
    router.post(route('friends.send', props.profileUser.username), {}, { preserveScroll: true })
}

function acceptFriendRequest() {
    router.patch(route('friends.accept', props.friendId), {}, { preserveScroll: true })
}

function removeFriend() {
    router.delete(route('friends.reject', props.friendId), { preserveScroll: true })
}

function startConversation() {
    router.post(route('conversations.store'), { recipient_id: props.profileUser.id })
}

// ── Community network layout ───────────────────────────────────────
// All coordinates live in a 580×auto virtual canvas (matches card inner width).
const BUBBLE_R = 32   // community bubble radius in the virtual canvas

function ringR(n) {
    if (n === 0) return 80
    const minArc = 2 * BUBBLE_R + 20   // min arc between adjacent bubble edges
    return Math.max(80, Math.min(minArc * n / (2 * Math.PI), 290 - BUBBLE_R - 16))
}

const communityPos = computed(() => {
    const n  = props.communities.length
    const rR = ringR(n)
    const cy = rR + BUBBLE_R + 24   // vertical center of the ring
    return props.communities.map((c, i) => {
        const angle = (i / n) * 2 * Math.PI - Math.PI / 2   // start from top
        return { ...c, bx: 290 + Math.cos(angle) * rR, by: cy + Math.sin(angle) * rR }
    })
})

const hubPos = computed(() => ({ x: 290, y: ringR(props.communities.length) + BUBBLE_R + 24 }))
const netH   = computed(() => (ringR(props.communities.length) + BUBBLE_R + 24) * 2 + 16)
</script>

<template>
    <Head :title="`@${profileUser.username} · bubbles`" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px;">

            <!-- Profile hero -->
            <div style="border-radius: 22px; overflow: visible; margin-bottom: 10px; box-shadow: 0 8px 32px #009ac70e; position: relative;">

                <!-- Banner -->
                <div :style="{
                    height: '190px', borderRadius: '22px 22px 0 0', position: 'relative',
                    background: profileUser.banner
                        ? `url('${clImg(profileUser.banner, 1200, 400, 'fill')}') center/cover no-repeat`
                        : `linear-gradient(135deg, ${profileUser.avatar_color}cc 0%, ${profileUser.avatar_color} 100%)`,
                }">
                    <!-- Avatar overlapping -->
                    <div style="position: absolute; bottom: -45px; left: 32px; z-index: 5;">
                        <img
                            v-if="profileUser.avatar"
                            :src="clImg(profileUser.avatar, 200, 200, 'fill', 'face')"
                            :style="{
                                width: '90px', height: '90px', borderRadius: '50%',
                                objectFit: 'cover', border: '4px solid white',
                                boxShadow: `0 4px 20px ${profileUser.avatar_color}66`,
                                display: 'block',
                            }"
                        />
                        <div v-else :style="{
                            width: '90px', height: '90px', borderRadius: '50%',
                            background: profileUser.avatar_color,
                            border: '4px solid white',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: '30px', fontWeight: '900', color: 'white',
                            boxShadow: `0 4px 20px ${profileUser.avatar_color}66`,
                        }">{{ formatInitial(profileUser.name) }}</div>
                    </div>
                </div>

                <!-- Body -->
                <div style="background: rgba(255,255,255,0.92); backdrop-filter: blur(20px); border: 1px solid #4ebcff22; border-top: none; border-radius: 0 0 22px 22px; padding: 58px 32px 28px; position: relative; z-index: 1;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        <div>
                            <h1 style="font-size: 22px; font-weight: 900; color: #1a3a4a; margin: 0 0 2px;">{{ profileUser.name }}</h1>
                            <p style="font-size: 13px; color: #009ac7; font-weight: 600; margin: 0;">@{{ profileUser.username }}</p>
                        </div>
                        <!-- Editar (próprio perfil) -->
                        <Link
                            v-if="isOwn"
                            :href="route('profile.edit')"
                            style="font-size: 12px; font-weight: 700; color: #009ac7; text-decoration: none; padding: 7px 18px; border-radius: 99px; border: 1.5px solid #009ac744; background: #009ac708; transition: all .2s; white-space: nowrap;"
                        >Editar perfil</Link>

                        <!-- Botões de amizade (perfil de outro utilizador) -->
                        <div v-else-if="friendStatus" style="display: flex; gap: 8px; flex-wrap: wrap;">

                            <!-- Sem relação → pode enviar pedido -->
                            <button
                                v-if="friendStatus === 'none'"
                                @click="sendFriendRequest"
                                style="font-size: 12px; font-weight: 700; color: white; padding: 7px 18px; border-radius: 99px; border: none; background: #009ac7; cursor: pointer; white-space: nowrap; box-shadow: 0 3px 12px #009ac730; transition: opacity .2s;"
                                @mouseenter="$event.target.style.opacity='.8'"
                                @mouseleave="$event.target.style.opacity='1'"
                            >+ Adicionar amigo</button>

                            <!-- Pedido enviado → aguardando -->
                            <template v-else-if="friendStatus === 'pending_sent'">
                                <button
                                    disabled
                                    style="font-size: 12px; font-weight: 700; color: #8ba0b0; padding: 7px 18px; border-radius: 99px; border: 1.5px solid #c8d8e0; background: #f0f8ff; cursor: not-allowed; white-space: nowrap;"
                                >Pedido enviado</button>
                                <button
                                    @click="removeFriend"
                                    style="font-size: 12px; font-weight: 600; color: #8ba0b0; padding: 7px 14px; border-radius: 99px; border: 1.5px solid #c8d8e0; background: transparent; cursor: pointer; white-space: nowrap; transition: all .2s;"
                                    @mouseenter="$event.currentTarget.style.borderColor='#e05555'; $event.currentTarget.style.color='#e05555'"
                                    @mouseleave="$event.currentTarget.style.borderColor='#c8d8e0'; $event.currentTarget.style.color='#8ba0b0'"
                                >Cancelar</button>
                            </template>

                            <!-- Pedido recebido → aceitar ou recusar -->
                            <template v-else-if="friendStatus === 'pending_received'">
                                <button
                                    @click="acceptFriendRequest"
                                    style="font-size: 12px; font-weight: 700; color: white; padding: 7px 18px; border-radius: 99px; border: none; background: #009ac7; cursor: pointer; white-space: nowrap; box-shadow: 0 3px 12px #009ac730; transition: opacity .2s;"
                                    @mouseenter="$event.target.style.opacity='.8'"
                                    @mouseleave="$event.target.style.opacity='1'"
                                >Aceitar pedido</button>
                                <button
                                    @click="removeFriend"
                                    style="font-size: 12px; font-weight: 600; color: #8ba0b0; padding: 7px 14px; border-radius: 99px; border: 1.5px solid #c8d8e0; background: transparent; cursor: pointer; white-space: nowrap; transition: all .2s;"
                                    @mouseenter="$event.currentTarget.style.borderColor='#e05555'; $event.currentTarget.style.color='#e05555'"
                                    @mouseleave="$event.currentTarget.style.borderColor='#c8d8e0'; $event.currentTarget.style.color='#8ba0b0'"
                                >Recusar</button>
                            </template>

                            <!-- Já são amigos -->
                            <template v-else-if="friendStatus === 'accepted'">
                                <button
                                    disabled
                                    style="font-size: 12px; font-weight: 700; color: #2ea87e; padding: 7px 18px; border-radius: 99px; border: 1.5px solid #2ea87e55; background: #2ea87e0c; cursor: default; white-space: nowrap;"
                                >✓ Amigos</button>
                                <button
                                    @click="startConversation"
                                    style="font-size: 12px; font-weight: 700; color: white; padding: 7px 18px; border-radius: 99px; border: none; background: linear-gradient(135deg,#009ac7,#4ebcff); cursor: pointer; white-space: nowrap; box-shadow: 0 3px 12px #009ac730; transition: opacity .2s;"
                                    @mouseenter="$event.target.style.opacity='.85'"
                                    @mouseleave="$event.target.style.opacity='1'"
                                >💬 Mensagem</button>
                                <button
                                    @click="removeFriend"
                                    style="font-size: 12px; font-weight: 600; color: #8ba0b0; padding: 7px 14px; border-radius: 99px; border: 1.5px solid #c8d8e0; background: transparent; cursor: pointer; white-space: nowrap; transition: all .2s;"
                                    @mouseenter="$event.currentTarget.style.borderColor='#e05555'; $event.currentTarget.style.color='#e05555'"
                                    @mouseleave="$event.currentTarget.style.borderColor='#c8d8e0'; $event.currentTarget.style.color='#8ba0b0'"
                                >Remover</button>
                            </template>

                        </div>
                    </div>

                    <p v-if="profileUser.bio" style="font-size: 14px; color: #3a5a6a; margin: 14px 0 0; line-height: 1.6;">{{ profileUser.bio }}</p>
                    <p v-else-if="isOwn" style="font-size: 13px; color: #b0c0cc; margin: 14px 0 0; font-style: italic;">Adiciona uma bio no teu perfil...</p>

                    <div style="display: flex; gap: 24px; margin-top: 18px;">
                        <div>
                            <span style="font-size: 18px; font-weight: 800; color: #1a3a4a;">{{ profileUser.posts_count }}</span>
                            <span style="font-size: 11px; color: #8ba0b0; font-weight: 600; margin-left: 5px;">posts</span>
                        </div>
                        <div>
                            <span style="font-size: 11px; color: #8ba0b0; font-weight: 600;">Membro desde {{ profileUser.created_at }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty communities state -->
            <div
                v-if="!communities || communities.length === 0"
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid #4ebcff1a; box-shadow: 0 2px 12px #009ac708; padding: 28px 22px; margin-bottom: 16px; text-align: center;"
            >
                <p style="font-size: 22px; margin: 0 0 8px;">🫧</p>
                <p style="font-size: 13px; color: #8ba0b0; margin: 0;">
                    {{ isOwn ? 'Ainda não fazes parte de nenhuma comunidade. Explora as bolhas!' : 'Ainda não faz parte de nenhuma comunidade.' }}
                </p>
            </div>

            <div
                v-if="communities && communities.length"
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid #4ebcff1a; box-shadow: 0 2px 12px #009ac708; padding: 16px 22px 20px; margin-bottom: 16px;"
            >
                <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 2px;">Comunidades · {{ communities.length }}</p>

                <div style="position: relative; width: 100%;" :style="{ paddingTop: (netH / 580 * 100) + '%' }">

                    <svg
                        :viewBox="`0 0 580 ${netH}`"
                        preserveAspectRatio="xMidYMid meet"
                        style="position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; overflow: visible;"
                    >
                        <line
                            v-for="c in communityPos"
                            :key="'h-' + c.id"
                            :x1="hubPos.x" :y1="hubPos.y"
                            :x2="c.bx"     :y2="c.by"
                            :stroke="c.color"
                            stroke-opacity="0.28"
                            stroke-width="1.5"
                            stroke-dasharray="5 4"
                        />
                        <template v-if="communityPos.length >= 3">
                            <line
                                v-for="(c, i) in communityPos"
                                :key="'r-' + c.id"
                                :x1="c.bx" :y1="c.by"
                                :x2="communityPos[(i + 1) % communityPos.length].bx"
                                :y2="communityPos[(i + 1) % communityPos.length].by"
                                :stroke="c.color"
                                stroke-opacity="0.14"
                                stroke-width="1"
                            />
                        </template>
                    </svg>

                    <div
                        style="position: absolute; transform: translate(-50%, -50%); z-index: 2;"
                        :style="{ left: (hubPos.x / 580 * 100) + '%', top: (hubPos.y / netH * 100) + '%' }"
                    >
                        <img
                            v-if="profileUser.avatar"
                            :src="profileUser.avatar"
                            :style="{
                                width: '46px', height: '46px', borderRadius: '50%',
                                objectFit: 'cover', border: '3px solid white', display: 'block',
                                boxShadow: `0 4px 14px ${profileUser.avatar_color}55`,
                            }"
                        />
                        <div v-else :style="{
                            width: '46px', height: '46px', borderRadius: '50%',
                            background: `radial-gradient(circle at 38% 32%, ${profileUser.avatar_color}ee, ${profileUser.avatar_color})`,
                            border: '3px solid white',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: '17px', fontWeight: '900', color: 'white',
                            boxShadow: `0 4px 14px ${profileUser.avatar_color}55`,
                        }">{{ formatInitial(profileUser.name) }}</div>
                    </div>

                    <Link
                        v-for="c in communityPos"
                        :key="c.id"
                        :href="route('community.show', c.id)"
                        style="position: absolute; transform: translate(-50%, -50%); text-decoration: none; z-index: 3;"
                        :style="{ left: (c.bx / 580 * 100) + '%', top: (c.by / netH * 100) + '%' }"
                    >
                        <div
                            :title="c.title !== c.label ? c.title : undefined"
                            :style="{
                                width: '64px', height: '64px', borderRadius: '50%',
                                backgroundImage: c.image
                                    ? `radial-gradient(circle at 38% 32%, ${c.color}55 0%, ${c.color}99 100%), url('${c.image}')`
                                    : `radial-gradient(circle at 38% 32%, ${c.color}ee 0%, ${c.color} 60%)`,
                                backgroundSize: 'cover',
                                backgroundPosition: 'center',
                                display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                                position: 'relative', overflow: 'hidden', cursor: 'pointer',
                                boxShadow: `0 6px 20px ${c.color}55, 0 2px 6px ${c.color}33`,
                                transition: 'transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .22s',
                            }"
                            @mouseenter="e => { e.currentTarget.style.transform='scale(1.13)'; e.currentTarget.style.boxShadow=`0 10px 30px ${c.color}77, 0 4px 12px ${c.color}44`; }"
                            @mouseleave="e => { e.currentTarget.style.transform='scale(1)';    e.currentTarget.style.boxShadow=`0 6px 20px ${c.color}55, 0 2px 6px ${c.color}33`; }"
                        >
                            <div style="position: absolute; top: 7px; left: 14%; width: 72%; height: 36%; border-radius: 50%; background: rgba(255,255,255,.22); transform: rotate(-10deg); pointer-events: none;" />
                            <!-- Label -->
                            <span style="position: relative; font-size: 9px; font-weight: 800; color: white; text-align: center; padding: 0 5px; line-height: 1.25; text-shadow: 0 1px 3px rgba(0,0,0,.35); word-break: break-word; max-width: 100%;">{{ c.label }}</span>
                        </div>
                    </Link>

                </div>
            </div>

            <!-- Amigos -->
            <div
                v-if="profileFriends && profileFriends.length"
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid #4ebcff1a; box-shadow: 0 2px 12px #009ac708; padding: 16px 22px; margin-bottom: 16px;"
            >
                <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 14px;">Amigos · {{ profileFriends.length }}</p>
                <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                    <Link
                        v-for="f in profileFriends"
                        :key="f.id"
                        :href="route('profile.show', f.username)"
                        style="display: flex; flex-direction: column; align-items: center; gap: 6px; text-decoration: none; width: 60px;"
                    >
                        <img
                            v-if="f.avatar"
                            :src="f.avatar"
                            :style="{
                                width: '46px', height: '46px', borderRadius: '50%',
                                objectFit: 'cover', border: `2px solid ${f.avatar_color}`,
                                boxShadow: `0 2px 8px ${f.avatar_color}44`, display: 'block',
                            }"
                        />
                        <div v-else :style="{
                            width: '46px', height: '46px', borderRadius: '50%',
                            background: f.avatar_color,
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: '17px', fontWeight: '800', color: 'white',
                            boxShadow: `0 2px 8px ${f.avatar_color}44`,
                        }">{{ formatInitial(f.name) }}</div>
                        <span style="font-size: 10px; font-weight: 600; color: #4a6a7a; text-align: center; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ f.name.split(' ')[0] }}</span>
                    </Link>
                </div>
            </div>

            <!-- New post box (only own profile + logged in) -->
            <div
                v-if="isOwn"
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 18px; border: 1px solid #4ebcff22; box-shadow: 0 4px 16px #009ac70a; padding: 20px; margin-bottom: 16px;"
            >
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <!-- Mini avatar -->
                    <img
                        v-if="authUser?.avatar"
                        :src="authUser.avatar"
                        :style="{
                            width: '36px', height: '36px', borderRadius: '50%', flexShrink: 0,
                            objectFit: 'cover', border: `2px solid ${authUser.avatar_color ?? '#009ac7'}`,
                        }"
                    />
                    <div v-else :style="{
                        width: '36px', height: '36px', borderRadius: '50%', flexShrink: 0,
                        background: authUser?.avatar_color ?? '#009ac7',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        fontSize: '14px', fontWeight: '800', color: 'white',
                    }">{{ formatInitial(authUser?.name) }}</div>

                    <div style="flex: 1;">
                        <textarea
                            v-model="postForm.content"
                            placeholder="O que estás a pensar?"
                            maxlength="1000"
                            rows="3"
                            style="width: 100%; background: #f0f8ff; border: 1.5px solid #4ebcff33; border-radius: 12px; padding: 12px 14px; font-size: 14px; color: #1a3a4a; outline: none; font-family: inherit; resize: vertical; transition: border-color .2s; box-sizing: border-box;"
                            @focus="$event.target.style.borderColor='#009ac7'"
                            @blur="$event.target.style.borderColor='#4ebcff33'"
                            @keydown.ctrl.enter="submitPost"
                        />
                        <div v-if="imagePreview" style="margin-top: 10px; position: relative; display: inline-block;">
                            <img :src="imagePreview" style="max-height: 160px; max-width: 100%; border-radius: 10px; object-fit: cover; border: 1px solid #4ebcff22;" />
                            <button
                                type="button"
                                @click="removeImage"
                                style="position: absolute; top: 6px; right: 6px; background: rgba(0,0,0,.45); border: none; border-radius: 50%; width: 22px; height: 22px; cursor: pointer; color: white; font-size: 14px; line-height: 1; display: flex; align-items: center; justify-content: center;"
                            >×</button>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 11px; color: #b0c0cc;">{{ charCount }}/1000 · Ctrl+Enter para publicar</span>
                                <button
                                    type="button"
                                    @click="imageInput.click()"
                                    :style="{
                                        background: 'none', border: 'none', cursor: 'pointer',
                                        color: postForm.image ? '#009ac7' : '#b0c0cc',
                                        padding: '3px', borderRadius: '6px', transition: 'color .2s',
                                        display: 'flex', alignItems: 'center',
                                    }"
                                    title="Adicionar imagem"
                                >
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <rect x="1.5" y="2.5" width="13" height="11" rx="2" stroke="currentColor" stroke-width="1.3"/>
                                        <circle cx="5.5" cy="6" r="1.2" fill="currentColor"/>
                                        <path d="M1.5 11l3.5-3 3 3 2-2 3.5 3.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                                <input ref="imageInput" type="file" accept="image/*" style="display:none;" @change="onImageChange" />
                            </div>
                            <button
                                @click="submitPost"
                                :disabled="postForm.processing || !postForm.content.trim()"
                                style="padding: 8px 20px; border-radius: 99px; background: #009ac7; border: none; color: white; font-size: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 3px 12px #009ac730; transition: opacity .2s;"
                                :style="{ opacity: postForm.processing || !postForm.content.trim() ? 0.5 : 1, cursor: !postForm.content.trim() ? 'not-allowed' : 'pointer' }"
                            >{{ postForm.processing ? 'A publicar...' : 'Publicar' }}</button>
                        </div>
                        <p v-if="postForm.errors.content" style="font-size: 11px; color: #e05555; margin: 6px 0 0;">{{ postForm.errors.content }}</p>
                        <p v-if="postForm.errors.image" style="font-size: 11px; color: #e05555; margin: 6px 0 0;">{{ postForm.errors.image }}</p>
                    </div>
                </div>
            </div>

            <!-- Posts feed -->
            <div v-if="localPosts.length === 0" style="text-align: center; padding: 60px 20px;">
                <p style="font-size: 32px; margin: 0 0 12px;">🫧</p>
                <p style="font-size: 14px; color: #8ba0b0;">{{ isOwn ? 'Ainda não publicaste nada. Começa agora!' : 'Ainda não há posts aqui.' }}</p>
            </div>

            <div v-else style="display: flex; flex-direction: column; gap: 12px;">
                <PostCard
                    v-for="post in localPosts"
                    :key="post.id"
                    :post="post"
                    :author="{ name: profileUser.name, username: profileUser.username, avatar: profileUser.avatar, avatar_color: profileUser.avatar_color }"
                    :auth-user="authUser"
                    :can-edit="isOwn"
                    :can-delete="isOwn"
                    :like-route="route('posts.like', post.id)"
                    :comment-route="route('posts.comments.store', post.id)"
                    :delete-route="route('posts.destroy', post.id)"
                    :edit-route="route('posts.update', post.id)"
                    :report-route="!isOwn && authUser ? route('posts.report', post.id) : null"
                />
            </div>

            <!-- Skeleton cards enquanto carrega mais -->
            <div v-if="loadingMore" style="display: flex; flex-direction: column; gap: 12px; margin-top: 12px;">
                <PostCardSkeleton v-for="n in 3" :key="n" />
            </div>

            <!-- Load more -->
            <div v-if="hasMore && !loadingMore" style="text-align: center; margin-top: 8px;">
                <button
                    @click="loadMore"
                    style="padding: 10px 28px; border-radius: 99px; border: 1.5px solid #4ebcff44; background: rgba(255,255,255,0.85); color: #009ac7; font-size: 13px; font-weight: 700; cursor: pointer; transition: all .2s; backdrop-filter: blur(10px);"
                    @mouseenter="$event.currentTarget.style.background='#e8f7ff'"
                    @mouseleave="$event.currentTarget.style.background='rgba(255,255,255,0.85)'"
                >Carregar mais</button>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
