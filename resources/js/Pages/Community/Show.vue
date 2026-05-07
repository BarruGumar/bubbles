<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    community:  Object,
    posts:      Array,
    isOwn:      Boolean,
    isMember:   Boolean,
})

const authUser    = computed(() => usePage().props.auth?.user)
const postForm    = useForm({ content: '', image: null })
const charCount   = computed(() => postForm.content.length)
const activityPct = computed(() => Math.min(100, Math.round(props.posts.length / 20 * 100)))

const imageInput   = ref(null)
const imagePreview = ref(null)

// Community image/banner upload
const communityImageInput  = ref(null)
const communityBannerInput = ref(null)
const communityImageForm   = useForm({ image: null })
const communityBannerForm  = useForm({ banner: null })
const communityImagePreview  = ref(props.community.image ?? null)
const communityBannerPreview = ref(props.community.banner ?? null)

function onCommunityImageChange(e) {
    const file = e.target.files[0]
    if (!file) return
    communityImagePreview.value = URL.createObjectURL(file)
    communityImageForm.image = file
    communityImageForm.post(route('community.image', props.community.id), {
        forceFormData: true, preserveScroll: true,
        onSuccess: () => communityImageForm.reset(),
    })
}

function onCommunityBannerChange(e) {
    const file = e.target.files[0]
    if (!file) return
    communityBannerPreview.value = URL.createObjectURL(file)
    communityBannerForm.banner = file
    communityBannerForm.post(route('community.banner', props.community.id), {
        forceFormData: true, preserveScroll: true,
        onSuccess: () => communityBannerForm.reset(),
    })
}

function onImageChange(e) {
    const file = e.target.files[0]
    if (!file) return
    postForm.image   = file
    imagePreview.value = URL.createObjectURL(file)
}

function removeImage() {
    postForm.image     = null
    imagePreview.value = null
    if (imageInput.value) imageInput.value.value = ''
}

function submitPost() {
    if (!postForm.content.trim()) return
    postForm.post(route('community.posts.store', props.community.id), {
        forceFormData:  true,
        preserveScroll: true,
        onSuccess: () => {
            postForm.reset('content', 'image')
            imagePreview.value = null
        },
    })
}

function deletePost(postId) {
    router.delete(route('community.posts.destroy', [props.community.id, postId]), {
        preserveScroll: true,
    })
}

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase()
}

function joinCommunity() {
    router.post(route('community.join', props.community.id), {}, { preserveScroll: true })
}

function leaveCommunity() {
    router.delete(route('community.leave', props.community.id), { preserveScroll: true })
}

const PALETTE = ['#009ac7','#4ebcff','#2ea87e','#e07b4a','#9b6bdf','#c74a6b','#e0a040','#6b9bdf']

const showEdit    = ref(false)
const confirmDel  = ref(false)
const editSaving  = ref(false)
const editDeleting = ref(false)

const editData = reactive({
    label:       props.community.label,
    title:       props.community.title,
    tagline:     props.community.tagline,
    description: props.community.description,
    color:       props.community.color,
    guidelines:  (props.community.guidelines || []).join('\n'),
})

function openEdit() {
    editData.label       = props.community.label
    editData.title       = props.community.title
    editData.tagline     = props.community.tagline
    editData.description = props.community.description
    editData.color       = props.community.color
    editData.guidelines  = (props.community.guidelines || []).join('\n')
    confirmDel.value = false
    showEdit.value = true
}

function saveSettings() {
    editSaving.value = true
    router.put(route('community.update', props.community.id), {
        label:                 editData.label,
        community_title:       editData.title,
        community_tagline:     editData.tagline,
        community_description: editData.description,
        color:                 editData.color,
        community_guidelines:  editData.guidelines.split('\n').map(g => g.trim()).filter(Boolean).slice(0, 5),
    }, {
        preserveScroll: true,
        onSuccess: () => { showEdit.value = false },
        onFinish:  () => { editSaving.value = false },
    })
}

function deleteCommunity() {
    editDeleting.value = true
    router.delete(route('community.delete', props.community.id), {
        onFinish: () => { editDeleting.value = false },
    })
}
</script>

<template>
    <Head :title="`${community.title} · bubbles`" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px;">

            <!-- Hero da comunidade -->
            <div style="border-radius: 22px; overflow: visible; margin-bottom: 20px; box-shadow: 0 8px 32px #009ac70e; position: relative;">

                <!-- Banner -->
                <div
                    :style="{
                        height: '180px', position: 'relative', borderRadius: '22px 22px 0 0',
                        background: communityBannerPreview
                            ? `url('${communityBannerPreview}') center/cover no-repeat`
                            : `linear-gradient(135deg, ${community.color}dd 0%, ${community.cover_color ?? community.color} 100%)`,
                    }"
                >
                    <input ref="communityBannerInput" type="file" accept="image/*" style="display:none;" @change="onCommunityBannerChange" @click.stop />

                    <!-- Círculo / imagem da comunidade sobressaído -->
                    <div style="position: absolute; bottom: -42px; left: 32px; z-index: 5;">
                        <div style="position: relative; width: 86px; height: 86px;">
                            <img
                                v-if="communityImagePreview"
                                :src="communityImagePreview"
                                :style="{
                                    width: '86px', height: '86px', borderRadius: '50%',
                                    objectFit: 'cover', border: '5px solid white',
                                    boxShadow: `0 4px 20px ${community.color}66`,
                                    display: 'block',
                                }"
                            />
                            <div v-else :style="{
                                width: '86px', height: '86px', borderRadius: '50%',
                                background: community.color,
                                border: '5px solid white',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                fontSize: '30px', fontWeight: '900', color: 'white',
                                boxShadow: `0 4px 20px ${community.color}66`,
                            }">{{ community.label.replace('#', '').charAt(0).toUpperCase() }}</div>
                        </div>
                        <input ref="communityImageInput" type="file" accept="image/*" style="display:none;" @change="onCommunityImageChange" @click.stop />
                    </div>
                </div>

                <!-- Corpo do card -->
                <div style="background: rgba(255,255,255,0.92); backdrop-filter: blur(20px); border: 1px solid #4ebcff22; border-top: none; border-radius: 0 0 22px 22px; padding: 58px 32px 28px; position: relative; z-index: 1;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <h1 style="font-size: 22px; font-weight: 900; color: #1a3a4a; margin: 0 0 3px; letter-spacing: -.02em;">{{ community.title }}</h1>
                            <p style="font-size: 12px; color: #5a7a8a; margin: 0 0 10px; font-style: italic;">{{ community.tagline }}</p>
                            <p style="font-size: 13px; font-weight: 700; margin: 0;" :style="{ color: community.color }">{{ community.members }} membros · {{ posts.length }} posts</p>
                        </div>
                        <!-- Badge do criador + botão Editar -->
                        <div v-if="authUser && isOwn" style="display: flex; gap: 8px; align-items: center; flex-shrink: 0; margin-top: 4px;">
                            <div :style="{
                                padding: '9px 18px', borderRadius: '99px',
                                border: `1.5px solid ${community.color}44`,
                                background: community.color + '12', color: community.color,
                                fontSize: '12px', fontWeight: '700', whiteSpace: 'nowrap',
                            }">✦ Criador</div>
                            <button
                                @click="openEdit"
                                style="padding: 9px 16px; border-radius: 99px; border: none; background: #f0f8ff; color: #5a7a8a; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all .2s;"
                                @mouseenter="$event.currentTarget.style.background='#e0f0fc'; $event.currentTarget.style.color='#009ac7'"
                                @mouseleave="$event.currentTarget.style.background='#f0f8ff'; $event.currentTarget.style.color='#5a7a8a'"
                            >⚙ Editar</button>
                        </div>

                        <!-- Botão Entrar / Membro para não criadores -->
                        <template v-else-if="authUser && !isOwn">
                            <button
                                v-if="!isMember"
                                :style="{
                                    padding: '9px 22px', borderRadius: '99px', border: 'none',
                                    background: community.color, color: 'white',
                                    fontSize: '12px', fontWeight: '700', cursor: 'pointer',
                                    boxShadow: `0 4px 14px ${community.color}44`, whiteSpace: 'nowrap',
                                    transition: 'opacity .2s', alignSelf: 'flex-start', marginTop: '4px',
                                }"
                                @click="joinCommunity"
                                @mouseenter="$event.target.style.opacity = '.8'"
                                @mouseleave="$event.target.style.opacity = '1'"
                            >Entrar</button>
                            <button
                                v-else
                                :style="{
                                    padding: '9px 22px', borderRadius: '99px',
                                    border: `1.5px solid ${community.color}66`,
                                    background: 'transparent', color: community.color,
                                    fontSize: '12px', fontWeight: '700', cursor: 'pointer',
                                    whiteSpace: 'nowrap', transition: 'all .2s',
                                    alignSelf: 'flex-start', marginTop: '4px',
                                }"
                                @click="leaveCommunity"
                                @mouseenter="$event.currentTarget.style.background = community.color + '14'"
                                @mouseleave="$event.currentTarget.style.background = 'transparent'"
                            >✓ Membro</button>
                        </template>

                        <!-- Utilizador não autenticado — redireciona para login -->
                        <Link
                            v-else-if="!authUser"
                            :href="route('login')"
                            :style="{
                                padding: '9px 22px', borderRadius: '99px', border: 'none',
                                background: community.color, color: 'white',
                                fontSize: '12px', fontWeight: '700', cursor: 'pointer',
                                boxShadow: `0 4px 14px ${community.color}44`, whiteSpace: 'nowrap',
                                transition: 'opacity .2s', alignSelf: 'flex-start', marginTop: '4px',
                                textDecoration: 'none', display: 'inline-block',
                            }"
                            @mouseenter="$event.currentTarget.style.opacity = '.8'"
                            @mouseleave="$event.currentTarget.style.opacity = '1'"
                        >Entrar</Link>
                    </div>

                    <p v-if="community.description" style="font-size: 13px; color: #4a6a7a; margin: 16px 0 0; line-height: 1.6;">{{ community.description }}</p>

                    <!-- Barra de atividade -->
                    <div style="margin-top: 18px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-size: 10px; color: #8ba0b0; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;">Atividade</span>
                            <span style="font-size: 10px; color: #b0c0cc;">{{ activityPct }}%</span>
                        </div>
                        <div style="height: 4px; background: #e8f4fb; border-radius: 99px; overflow: hidden;">
                            <div :style="{
                                height: '100%', borderRadius: '99px',
                                background: community.color,
                                width: `${Math.max(activityPct, posts.length ? 4 : 0)}%`,
                                transition: 'width .5s ease',
                            }" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Avatares dos membros -->
            <div
                v-if="community.member_avatars && community.member_avatars.length"
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid #4ebcff1a; box-shadow: 0 2px 12px #009ac708; padding: 16px 22px; margin-bottom: 16px;"
            >
                <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 12px;">Membros</p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <component
                        :is="member.username ? Link : 'div'"
                        v-for="member in community.member_avatars"
                        :key="member.username ?? member.name"
                        :href="member.username ? route('profile.show', member.username) : undefined"
                        style="display: flex; flex-direction: column; align-items: center; gap: 5px; text-decoration: none;"
                        :title="member.name"
                    >
                        <img
                            v-if="member.avatar"
                            :src="member.avatar"
                            :style="{
                                width: '38px', height: '38px', borderRadius: '50%',
                                objectFit: 'cover', border: `2px solid ${member.avatar_color}`,
                                boxShadow: `0 2px 8px ${member.avatar_color}44`,
                                transition: 'transform .2s',
                            }"
                            @mouseenter="$event.target.style.transform='scale(1.08)'"
                            @mouseleave="$event.target.style.transform='scale(1)'"
                        />
                        <div v-else :style="{
                            width: '38px', height: '38px', borderRadius: '50%',
                            background: member.avatar_color,
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: '15px', fontWeight: '800', color: 'white',
                            boxShadow: `0 2px 8px ${member.avatar_color}44`,
                            transition: 'transform .2s',
                        }"
                        @mouseenter="$event.currentTarget.style.transform='scale(1.08)'"
                        @mouseleave="$event.currentTarget.style.transform='scale(1)'"
                        >{{ formatInitial(member.name) }}</div>
                        <span style="font-size: 10px; color: #8ba0b0; font-weight: 600; max-width: 48px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            {{ member.username ? '@' + member.username : member.name }}
                        </span>
                    </component>
                </div>
            </div>

            <!-- Regras da comunidade -->
            <div
                v-if="community.guidelines && community.guidelines.length"
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid #4ebcff1a; box-shadow: 0 2px 12px #009ac708; padding: 18px 22px; margin-bottom: 16px;"
            >
                <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 10px;">Regras</p>
                <ol style="margin: 0; padding-left: 18px; color: #2a4a5a; font-size: 13px; line-height: 1.8;">
                    <li v-for="(rule, i) in community.guidelines" :key="i">{{ rule }}</li>
                </ol>
            </div>

            <!-- Caixa de novo post -->
            <div
                v-if="authUser"
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 18px; border: 1px solid #4ebcff22; box-shadow: 0 4px 16px #009ac70a; padding: 20px; margin-bottom: 16px;"
            >
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <img
                        v-if="authUser.avatar"
                        :src="authUser.avatar"
                        :style="{
                            width: '36px', height: '36px', borderRadius: '50%', flexShrink: 0,
                            objectFit: 'cover', border: `2px solid ${authUser.avatar_color ?? '#009ac7'}`,
                        }"
                    />
                    <div v-else :style="{
                        width: '36px', height: '36px', borderRadius: '50%', flexShrink: 0,
                        background: authUser.avatar_color ?? '#009ac7',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        fontSize: '14px', fontWeight: '800', color: 'white',
                    }">{{ formatInitial(authUser.name) }}</div>

                    <div style="flex: 1;">
                        <textarea
                            v-model="postForm.content"
                            placeholder="Escreve algo para a comunidade..."
                            maxlength="1000"
                            rows="3"
                            style="width: 100%; background: #f0f8ff; border: 1.5px solid #4ebcff33; border-radius: 12px; padding: 12px 14px; font-size: 14px; color: #1a3a4a; outline: none; font-family: inherit; resize: vertical; transition: border-color .2s; box-sizing: border-box;"
                            @focus="$event.target.style.borderColor = community.color"
                            @blur="$event.target.style.borderColor = '#4ebcff33'"
                            @keydown.ctrl.enter="submitPost"
                        />
                        <!-- Image preview before submit -->
                        <div v-if="imagePreview" style="margin-top: 10px; position: relative; display: inline-block;">
                            <img :src="imagePreview" style="max-height: 160px; max-width: 100%; border-radius: 10px; object-fit: cover; border: 1px solid #4ebcff22;" />
                            <button
                                @click="removeImage"
                                style="position: absolute; top: 6px; right: 6px; background: rgba(0,0,0,.45); border: none; border-radius: 50%; width: 22px; height: 22px; cursor: pointer; color: white; font-size: 14px; line-height: 1; display: flex; align-items: center; justify-content: center;"
                            >×</button>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 11px; color: #b0c0cc;">{{ charCount }}/1000 · Ctrl+Enter</span>
                                <!-- Image attach button -->
                                <button
                                    type="button"
                                    @click="imageInput.click()"
                                    :style="{
                                        background: 'none', border: 'none', cursor: 'pointer',
                                        color: postForm.image ? community.color : '#b0c0cc',
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
                                :style="{
                                    padding: '8px 20px', borderRadius: '99px',
                                    background: community.color, border: 'none',
                                    color: 'white', fontSize: '12px', fontWeight: '700',
                                    cursor: postForm.content.trim() ? 'pointer' : 'not-allowed',
                                    opacity: (postForm.processing || !postForm.content.trim()) ? 0.5 : 1,
                                    transition: 'opacity .2s',
                                }"
                            >{{ postForm.processing ? 'A publicar...' : 'Publicar' }}</button>
                        </div>
                        <p v-if="postForm.errors.content" style="font-size: 11px; color: #e05555; margin: 6px 0 0;">{{ postForm.errors.content }}</p>
                    </div>
                </div>
            </div>

            <!-- Estado vazio -->
            <div v-if="posts.length === 0" style="text-align: center; padding: 60px 20px;">
                <p style="font-size: 32px; margin: 0 0 12px;">🫧</p>
                <p style="font-size: 14px; color: #8ba0b0;">Ainda não há posts. Sê o primeiro!</p>
            </div>

            <!-- Feed de posts -->
            <div v-else style="display: flex; flex-direction: column; gap: 12px;">
                <div
                    v-for="post in posts"
                    :key="post.id"
                    style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid #4ebcff1a; box-shadow: 0 2px 12px #009ac708; padding: 20px;"
                >
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <!-- Avatar do autor (clicável) -->
                        <component
                            :is="post.author.username ? Link : 'div'"
                            :href="post.author.username ? route('profile.show', post.author.username) : undefined"
                            style="text-decoration: none; flex-shrink: 0; display: block;"
                        >
                            <img
                                v-if="post.author.avatar"
                                :src="post.author.avatar"
                                :style="{
                                    width: '38px', height: '38px', borderRadius: '50%',
                                    objectFit: 'cover', border: `2px solid ${post.author.avatar_color}`,
                                    display: 'block', transition: 'opacity .2s',
                                }"
                                @mouseenter="$event.target.style.opacity='.75'"
                                @mouseleave="$event.target.style.opacity='1'"
                            />
                            <div v-else :style="{
                                width: '38px', height: '38px', borderRadius: '50%',
                                background: post.author.avatar_color,
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                fontSize: '15px', fontWeight: '800', color: 'white',
                                transition: 'opacity .2s',
                            }"
                            @mouseenter="$event.currentTarget.style.opacity='.75'"
                            @mouseleave="$event.currentTarget.style.opacity='1'"
                            >{{ formatInitial(post.author.name) }}</div>
                        </component>

                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <div>
                                    <!-- Nome clicável -->
                                    <component
                                        :is="post.author.username ? Link : 'span'"
                                        :href="post.author.username ? route('profile.show', post.author.username) : undefined"
                                        style="font-size: 13px; font-weight: 700; color: #1a3a4a; text-decoration: none; transition: color .15s;"
                                        @mouseenter="post.author.username && ($event.currentTarget.style.color='#009ac7')"
                                        @mouseleave="post.author.username && ($event.currentTarget.style.color='#1a3a4a')"
                                    >{{ post.author.name }}</component>
                                    <span v-if="post.author.username" style="font-size: 11px; color: #009ac7; margin-left: 5px;">@{{ post.author.username }}</span>
                                    <span style="font-size: 11px; color: #8ba0b0; margin-left: 8px;">{{ post.created_at }}</span>
                                </div>
                                <button
                                    v-if="post.isOwn"
                                    @click="deletePost(post.id)"
                                    style="background: none; border: none; cursor: pointer; color: #c0c8d0; font-size: 16px; padding: 2px 4px; border-radius: 6px; line-height: 1; transition: color .2s;"
                                    @mouseenter="$event.target.style.color = '#e05555'"
                                    @mouseleave="$event.target.style.color = '#c0c8d0'"
                                >×</button>
                            </div>
                            <p style="font-size: 14px; color: #2a4a5a; line-height: 1.65; margin: 0; white-space: pre-wrap;">{{ post.content }}</p>
                            <!-- Post image -->
                            <img
                                v-if="post.image"
                                :src="post.image"
                                style="margin-top: 12px; max-width: 100%; border-radius: 12px; object-fit: cover; max-height: 400px; display: block; border: 1px solid #4ebcff1a;"
                            />
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- Modal de edição -->
        <Transition name="fade">
            <div
                v-if="showEdit"
                style="position: fixed; inset: 0; z-index: 200; background: rgba(10,30,40,.55); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; padding: 20px;"
                @click.self="showEdit = false"
            >
                <div style="width: min(520px, 100%); max-height: calc(100vh - 60px); overflow-y: auto; background: white; border-radius: 22px; box-shadow: 0 24px 64px rgba(0,0,0,.22); padding: 28px;">

                    <!-- Cabeçalho -->
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px;">
                        <p style="font-size: 16px; font-weight: 900; color: #1a3a4a; margin: 0; letter-spacing: -.02em;">Editar comunidade</p>
                        <button
                            @click="showEdit = false"
                            style="width: 30px; height: 30px; border-radius: 50%; border: none; background: #f0f8ff; color: #5a7a8a; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s;"
                            @mouseenter="$event.currentTarget.style.background='#e0eef8'"
                            @mouseleave="$event.currentTarget.style.background='#f0f8ff'"
                        >×</button>
                    </div>

                    <!-- Preview clicável — banner + imagem -->
                    <div style="margin-bottom: 22px; border-radius: 16px; overflow: visible; position: relative;">
                        <!-- Banner clicável -->
                        <div
                            class="edit-banner-area"
                            :style="{
                                height: '110px', borderRadius: '16px 16px 0 0', cursor: 'pointer', position: 'relative',
                                background: communityBannerPreview
                                    ? `url('${communityBannerPreview}') center/cover no-repeat`
                                    : `linear-gradient(135deg, ${editData.color}dd 0%, ${editData.color} 100%)`,
                            }"
                            @click="communityBannerInput.click()"
                        >
                            <div class="edit-banner-overlay" style="position: absolute; inset: 0; border-radius: 16px 16px 0 0; background: rgba(0,0,0,0); display: flex; align-items: center; justify-content: center; transition: background .2s;">
                                <span class="edit-banner-label" style="font-size: 11px; color: white; background: rgba(0,0,0,.5); padding: 5px 14px; border-radius: 99px; opacity: 0; transition: opacity .2s; pointer-events: none;">
                                    {{ communityBannerForm.processing ? 'A enviar...' : 'Alterar banner' }}
                                </span>
                            </div>
                        </div>
                        <!-- Parte inferior do card (só para posicionar o círculo) -->
                        <div :style="{ height: '36px', background: '#f8fafc', borderRadius: '0 0 16px 16px', border: '1px solid #e8f0f8', borderTop: 'none' }" />
                        <!-- Círculo clicável -->
                        <div style="position: absolute; bottom: -2px; left: 20px; z-index: 5;">
                            <div
                                class="edit-image-area"
                                style="position: relative; width: 64px; height: 64px; cursor: pointer;"
                                @click="communityImageInput.click()"
                            >
                                <img
                                    v-if="communityImagePreview"
                                    :src="communityImagePreview"
                                    :style="{
                                        width: '64px', height: '64px', borderRadius: '50%',
                                        objectFit: 'cover', border: '4px solid white',
                                        boxShadow: `0 3px 14px ${editData.color}55`, display: 'block',
                                    }"
                                />
                                <div v-else :style="{
                                    width: '64px', height: '64px', borderRadius: '50%',
                                    background: editData.color, border: '4px solid white',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    fontSize: '22px', fontWeight: '900', color: 'white',
                                    boxShadow: `0 3px 14px ${editData.color}55`,
                                }">{{ community.label.replace('#', '').charAt(0).toUpperCase() }}</div>
                                <div class="edit-image-overlay" style="position: absolute; inset: 0; border-radius: 50%; background: rgba(0,0,0,0); display: flex; align-items: center; justify-content: center; transition: background .2s;">
                                    <svg class="edit-cam-icon" width="16" height="16" viewBox="0 0 18 18" fill="none" style="opacity:0;transition:opacity .2s;">
                                        <path d="M9 1.5v9M5.5 5 9 1.5 12.5 5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M3 12v3.5h12V12" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cor -->
                    <div style="margin-bottom: 18px;">
                        <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 10px;">Cor</p>
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <button
                                v-for="c in PALETTE"
                                :key="c"
                                type="button"
                                @click="editData.color = c"
                                :style="{
                                    width: '24px', height: '24px', borderRadius: '50%', background: c,
                                    border: 'none', cursor: 'pointer', flexShrink: 0,
                                    boxShadow: editData.color === c ? `0 0 0 2px white, 0 0 0 4px ${c}` : 'none',
                                    transition: 'box-shadow .15s',
                                }"
                            />
                            <label :style="{
                                width: '24px', height: '24px', borderRadius: '50%', cursor: 'pointer',
                                border: '2px dashed #b0c8d8', display: 'flex', alignItems: 'center',
                                justifyContent: 'center', flexShrink: 0, position: 'relative', overflow: 'hidden',
                                boxShadow: !PALETTE.includes(editData.color) ? `0 0 0 2px white, 0 0 0 4px ${editData.color}` : 'none',
                                background: !PALETTE.includes(editData.color) ? editData.color : 'transparent',
                            }" title="Cor personalizada">
                                <input type="color" v-model="editData.color" style="position: absolute; width: 200%; height: 200%; opacity: 0; cursor: pointer;" />
                                <span v-if="PALETTE.includes(editData.color)" style="font-size: 12px; color: #8ba0b0; position: relative; pointer-events: none;">+</span>
                            </label>
                        </div>
                    </div>

                    <!-- Campos de texto -->
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div>
                            <label style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; display: block; margin-bottom: 6px;">Hashtag</label>
                            <input v-model="editData.label" placeholder="#hashtag"
                                style="width: 100%; background: #f0f8ff; border: 1.5px solid #4ebcff33; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #1a3a4a; outline: none; font-family: inherit; box-sizing: border-box; transition: border-color .2s;"
                                @focus="$event.target.style.borderColor = editData.color"
                                @blur="$event.target.style.borderColor = '#4ebcff33'"
                            />
                        </div>
                        <div>
                            <label style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; display: block; margin-bottom: 6px;">Título</label>
                            <input v-model="editData.title" placeholder="Título da comunidade"
                                style="width: 100%; background: #f0f8ff; border: 1.5px solid #4ebcff33; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #1a3a4a; outline: none; font-family: inherit; box-sizing: border-box; transition: border-color .2s;"
                                @focus="$event.target.style.borderColor = editData.color"
                                @blur="$event.target.style.borderColor = '#4ebcff33'"
                            />
                        </div>
                        <div>
                            <label style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; display: block; margin-bottom: 6px;">Tagline</label>
                            <input v-model="editData.tagline" placeholder="Tagline curta"
                                style="width: 100%; background: #f0f8ff; border: 1.5px solid #4ebcff33; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #1a3a4a; outline: none; font-family: inherit; box-sizing: border-box; transition: border-color .2s;"
                                @focus="$event.target.style.borderColor = editData.color"
                                @blur="$event.target.style.borderColor = '#4ebcff33'"
                            />
                        </div>
                        <div>
                            <label style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; display: block; margin-bottom: 6px;">Descrição</label>
                            <textarea v-model="editData.description" placeholder="Descrição da comunidade" rows="3"
                                style="width: 100%; background: #f0f8ff; border: 1.5px solid #4ebcff33; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #1a3a4a; outline: none; font-family: inherit; resize: vertical; box-sizing: border-box; transition: border-color .2s;"
                                @focus="$event.target.style.borderColor = editData.color"
                                @blur="$event.target.style.borderColor = '#4ebcff33'"
                            />
                        </div>
                        <div>
                            <label style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; display: block; margin-bottom: 6px;">
                                Regras <span style="font-weight: 500; text-transform: none; letter-spacing: 0;">(uma por linha · máx. 5)</span>
                            </label>
                            <textarea v-model="editData.guidelines" placeholder="Regra 1&#10;Regra 2&#10;..." rows="4"
                                style="width: 100%; background: #f0f8ff; border: 1.5px solid #4ebcff33; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #1a3a4a; outline: none; font-family: inherit; resize: vertical; box-sizing: border-box; transition: border-color .2s;"
                                @focus="$event.target.style.borderColor = editData.color"
                                @blur="$event.target.style.borderColor = '#4ebcff33'"
                            />
                        </div>
                    </div>

                    <!-- Ações principais -->
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button
                            @click="showEdit = false"
                            style="flex: 1; padding: 11px; border-radius: 10px; background: #f0f8ff; border: 1.5px solid #e0eef8; color: #8ba0b0; font-size: 13px; font-weight: 600; cursor: pointer; transition: background .2s;"
                            @mouseenter="$event.currentTarget.style.background='#e0eef8'"
                            @mouseleave="$event.currentTarget.style.background='#f0f8ff'"
                        >Cancelar</button>
                        <button
                            @click="saveSettings"
                            :disabled="editSaving"
                            :style="{
                                flex: 1, padding: '11px', borderRadius: '10px', border: 'none',
                                background: editData.color, color: 'white',
                                fontSize: '13px', fontWeight: '700',
                                cursor: editSaving ? 'not-allowed' : 'pointer',
                                opacity: editSaving ? 0.7 : 1, transition: 'opacity .2s',
                            }"
                        >{{ editSaving ? 'A guardar...' : 'Guardar alterações' }}</button>
                    </div>

                    <!-- Zona de perigo -->
                    <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #f0f4f8;">
                        <p style="font-size: 10px; font-weight: 800; color: #e05555; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 12px;">Zona de perigo</p>
                        <div v-if="!confirmDel">
                            <button
                                @click="confirmDel = true"
                                style="padding: 9px 18px; border-radius: 10px; border: 1.5px solid #e0555533; background: transparent; color: #e05555; font-size: 12px; font-weight: 600; cursor: pointer; transition: all .2s;"
                                @mouseenter="$event.currentTarget.style.background='#fff0f0'; $event.currentTarget.style.borderColor='#e05555'"
                                @mouseleave="$event.currentTarget.style.background='transparent'; $event.currentTarget.style.borderColor='#e0555533'"
                            >Excluir comunidade</button>
                        </div>
                        <div v-else style="background: #fff8f8; border: 1.5px solid #e0555533; border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px;">
                            <p style="font-size: 13px; color: #e05555; margin: 0; font-weight: 600;">Tens a certeza? Esta ação é irreversível.</p>
                            <div style="display: flex; gap: 8px;">
                                <button
                                    @click="confirmDel = false"
                                    style="flex: 1; padding: 9px; border-radius: 9px; background: #f0f8ff; border: 1px solid #e0eef8; color: #8ba0b0; font-size: 12px; cursor: pointer;"
                                >Cancelar</button>
                                <button
                                    @click="deleteCommunity"
                                    :disabled="editDeleting"
                                    style="flex: 1; padding: 9px; border-radius: 9px; border: none; background: #e05555; color: white; font-size: 12px; font-weight: 700; cursor: pointer; transition: opacity .2s;"
                                    :style="{ opacity: editDeleting ? 0.7 : 1 }"
                                >{{ editDeleting ? 'A excluir...' : 'Confirmar exclusão' }}</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>

    </AuthenticatedLayout>
</template>

<style scoped>
textarea::placeholder { color: #b0c8d8; }

/* Modal — banner hover */
.edit-banner-area:hover .edit-banner-overlay { background: rgba(0,0,0,.32) !important; }
.edit-banner-area:hover .edit-banner-label   { opacity: 1 !important; }

/* Modal — círculo hover */
.edit-image-area:hover .edit-image-overlay { background: rgba(0,0,0,.38) !important; }
.edit-image-area:hover .edit-cam-icon      { opacity: 1 !important; }
</style>
