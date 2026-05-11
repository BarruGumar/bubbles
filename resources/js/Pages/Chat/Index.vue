<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    conversations:      { type: Array,  default: () => [] },
    activeConversation: { type: Object, default: null },
    messages:           { type: Array,  default: () => [] },
    friends:            { type: Array,  default: () => [] },
})

const page     = usePage()
const authUser = computed(() => page.props.auth?.user)

const messagesEl   = ref(null)
const msgForm      = useForm({ content: '', image: null })
const imagePreview = ref(null)
const imageInput   = ref(null)

function avatarInitial(name) {
    return (name ?? '?')[0].toUpperCase()
}

function formatTime(iso) {
    if (!iso) return ''
    const d    = new Date(iso)
    const now  = new Date()
    const diff = now - d
    if (diff < 60_000)    return 'agora'
    if (diff < 3_600_000) return Math.floor(diff / 60_000) + 'm'
    if (diff < 86_400_000) return d.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' })
    return d.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit' })
}

function formatDate(iso) {
    if (!iso) return ''
    const d   = new Date(iso)
    const now = new Date()
    if (d.toDateString() === now.toDateString()) return 'Hoje'
    const yesterday = new Date(now)
    yesterday.setDate(yesterday.getDate() - 1)
    if (d.toDateString() === yesterday.toDateString()) return 'Ontem'
    return d.toLocaleDateString('pt-PT', { weekday: 'long', day: '2-digit', month: 'long' })
}

function groupedMessages() {
    const groups = []
    let lastDate = null
    for (const msg of props.messages) {
        const dateLabel = formatDate(msg.created_at)
        if (dateLabel !== lastDate) {
            groups.push({ type: 'date', label: dateLabel })
            lastDate = dateLabel
        }
        groups.push({ type: 'msg', ...msg })
    }
    return groups
}

function scrollToBottom(smooth = true) {
    nextTick(() => {
        if (messagesEl.value) {
            messagesEl.value.scrollTo({
                top:      messagesEl.value.scrollHeight,
                behavior: smooth ? 'smooth' : 'instant',
            })
        }
    })
}

onMounted(() => scrollToBottom(false))

watch(() => props.messages, () => scrollToBottom(true), { deep: true })

function onImageChange(e) {
    const file = e.target.files[0]
    if (!file) return
    msgForm.image  = file
    imagePreview.value = URL.createObjectURL(file)
    e.target.value = ''
}

function removeImage() {
    msgForm.image  = null
    imagePreview.value = null
}

function send() {
    const hasText  = msgForm.content.trim().length > 0
    const hasImage = !!msgForm.image
    if ((!hasText && !hasImage) || msgForm.processing || !props.activeConversation) return
    msgForm.post(
        route('messages.store', props.activeConversation.id),
        {
            forceFormData:  true,
            preserveScroll: true,
            onSuccess: () => {
                msgForm.reset('content', 'image')
                imagePreview.value = null
            },
        }
    )
}

function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        send()
    }
}

const totalUnread = computed(() =>
    props.conversations.reduce((s, c) => s + (c.unread_count ?? 0), 0)
)

function startWith(recipientId) {
    router.post(route('conversations.store'), { recipient_id: recipientId })
}
</script>

<template>
    <Head title="Mensagens · bubbles" />

    <AuthenticatedLayout>
        <!-- Full-height chat shell -->
        <div style="display: flex; height: calc(100vh - 58px); overflow: hidden; background: transparent;">

            <!-- ═══════════════════════════════════════
                 SIDEBAR  ─  conversation list
            ════════════════════════════════════════ -->
            <aside style="
                width: 300px;
                min-width: 300px;
                display: flex;
                flex-direction: column;
                border-right: 1px solid #009ac712;
                background: rgba(255,255,255,0.60);
                backdrop-filter: blur(24px);
            ">
                <!-- Sidebar header -->
                <div style="
                    padding: 20px 20px 14px;
                    border-bottom: 1px solid #009ac710;
                    flex-shrink: 0;
                ">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h2 style="font-size: 17px; font-weight: 900; color: #1a3a4a; margin: 0; letter-spacing: -.02em;">
                                Mensagens
                            </h2>
                            <p v-if="totalUnread > 0" style="font-size: 11px; color: #009ac7; margin: 2px 0 0; font-weight: 700;">
                                {{ totalUnread }} não {{ totalUnread === 1 ? 'lida' : 'lidas' }}
                            </p>
                        </div>
                        <!-- Compose: go to friends to start a chat -->
                        <Link
                            :href="route('friends.index')"
                            title="Ir para amigos"
                            style="
                                width: 34px; height: 34px; border-radius: 50%;
                                background: linear-gradient(135deg,#009ac7,#4ebcff);
                                display: flex; align-items: center; justify-content: center;
                                box-shadow: 0 3px 12px #009ac730;
                                text-decoration: none;
                                transition: transform .2s, box-shadow .2s;
                            "
                            @mouseenter="$event.currentTarget.style.transform='scale(1.08)'; $event.currentTarget.style.boxShadow='0 6px 20px #009ac740'"
                            @mouseleave="$event.currentTarget.style.transform='scale(1)'; $event.currentTarget.style.boxShadow='0 3px 12px #009ac730'"
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                        </Link>
                    </div>
                </div>

                <!-- Conversation list -->
                <div style="flex: 1; overflow-y: auto; padding: 8px 8px;">
                    <!-- Empty state sidebar: show friends to start -->
                    <div v-if="conversations.length === 0 && friends.length > 0">
                        <p style="font-size: 10px; font-weight: 800; color: #b0c0cc; text-transform: uppercase; letter-spacing: .08em; margin: 8px 12px 10px;">
                            Os teus amigos
                        </p>
                        <button
                            v-for="f in friends"
                            :key="f.id"
                            @click="startWith(f.id)"
                            style="
                                width: 100%; display: flex; align-items: center; gap: 11px;
                                padding: 10px 12px; border-radius: 14px;
                                border: none; background: transparent; cursor: pointer;
                                text-align: left; transition: background .15s; margin-bottom: 2px;
                            "
                            @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.06)'"
                            @mouseleave="$event.currentTarget.style.background='transparent'"
                        >
                            <img
                                v-if="f.avatar"
                                :src="f.avatar"
                                :style="{
                                    width: '38px', height: '38px', borderRadius: '50%',
                                    objectFit: 'cover', flexShrink: '0',
                                    border: `2px solid ${f.avatar_color}`,
                                }"
                            />
                            <div v-else :style="{
                                width: '38px', height: '38px', borderRadius: '50%', flexShrink: '0',
                                background: f.avatar_color,
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                fontSize: '14px', fontWeight: '800', color: 'white',
                            }">{{ avatarInitial(f.name) }}</div>
                            <div style="flex: 1; min-width: 0;">
                                <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ f.name }}</p>
                                <p style="font-size: 11px; color: #009ac7; margin: 1px 0 0;">Iniciar conversa</p>
                            </div>
                        </button>
                    </div>

                    <!-- Empty state sidebar: no friends either -->
                    <div
                        v-else-if="conversations.length === 0"
                        style="text-align: center; padding: 60px 20px;"
                    >
                        <p style="font-size: 28px; margin: 0 0 10px;">💬</p>
                        <p style="font-size: 13px; color: #8ba0b0; margin: 0;">Nenhuma conversa ainda.</p>
                        <p style="font-size: 11px; color: #b0c0cc; margin: 6px 0 0;">Vai aos <Link :href="route('friends.index')" style="color:#009ac7;text-decoration:none;font-weight:700;">amigos</Link> e inicia uma conversa.</p>
                    </div>

                    <!-- Conversation items -->
                    <Link
                        v-for="conv in conversations"
                        :key="conv.id"
                        :href="route('conversations.show', conv.id)"
                        style="
                            display: flex;
                            align-items: center;
                            gap: 11px;
                            padding: 11px 12px;
                            border-radius: 14px;
                            text-decoration: none;
                            transition: background .15s;
                            margin-bottom: 2px;
                            position: relative;
                        "
                        :style="{
                            background: activeConversation?.id === conv.id
                                ? 'rgba(0,154,199,0.10)'
                                : 'transparent',
                            border: activeConversation?.id === conv.id
                                ? '1px solid #009ac722'
                                : '1px solid transparent',
                        }"
                        @mouseenter="e => { if (activeConversation?.id !== conv.id) e.currentTarget.style.background = 'rgba(0,154,199,0.05)' }"
                        @mouseleave="e => { if (activeConversation?.id !== conv.id) e.currentTarget.style.background = 'transparent' }"
                    >
                        <!-- Avatar -->
                        <div style="position: relative; flex-shrink: 0;">
                            <img
                                v-if="conv.other_user?.avatar"
                                :src="conv.other_user.avatar"
                                :style="{
                                    width: '42px', height: '42px', borderRadius: '50%',
                                    objectFit: 'cover',
                                    border: `2px solid ${conv.other_user.avatar_color}`,
                                    boxShadow: `0 2px 8px ${conv.other_user.avatar_color}33`,
                                    display: 'block',
                                }"
                            />
                            <div v-else :style="{
                                width: '42px', height: '42px', borderRadius: '50%',
                                background: conv.other_user?.avatar_color ?? '#009ac7',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                fontSize: '16px', fontWeight: '800', color: 'white',
                                boxShadow: `0 2px 8px ${conv.other_user?.avatar_color ?? '#009ac7'}33`,
                                flexShrink: '0',
                            }">{{ avatarInitial(conv.other_user?.name) }}</div>

                            <!-- Unread dot -->
                            <span
                                v-if="conv.unread_count > 0"
                                style="
                                    position: absolute; bottom: 0; right: 0;
                                    width: 10px; height: 10px; border-radius: 50%;
                                    background: #009ac7;
                                    border: 2px solid white;
                                "
                            />
                        </div>

                        <!-- Info -->
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 6px;">
                                <p style="
                                    font-size: 13px; font-weight: 700; color: #1a3a4a;
                                    margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                                ">{{ conv.other_user?.name ?? '—' }}</p>
                                <span style="font-size: 10px; color: #b0c0cc; white-space: nowrap; flex-shrink: 0;">
                                    {{ formatTime(conv.last_message?.created_at) }}
                                </span>
                            </div>
                            <p style="
                                font-size: 11px; margin: 2px 0 0;
                                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                            " :style="{ color: conv.unread_count > 0 ? '#009ac7' : '#8ba0b0', fontWeight: conv.unread_count > 0 ? '600' : '400' }">
                                <span v-if="conv.last_message?.is_own" style="color: #b0c0cc;">Eu: </span>
                                {{ conv.last_message?.content ?? 'Sem mensagens ainda.' }}
                            </p>
                        </div>

                        <!-- Unread badge -->
                        <span
                            v-if="conv.unread_count > 0"
                            style="
                                flex-shrink: 0;
                                min-width: 20px; height: 20px; padding: 0 6px;
                                background: #009ac7; color: white;
                                border-radius: 99px; font-size: 10px; font-weight: 800;
                                display: flex; align-items: center; justify-content: center;
                                box-shadow: 0 2px 8px #009ac740;
                            "
                        >{{ conv.unread_count }}</span>
                    </Link>
                </div>
            </aside>

            <!-- ═══════════════════════════════════════
                 MAIN  ─  message area
            ════════════════════════════════════════ -->
            <main style="
                flex: 1;
                display: flex;
                flex-direction: column;
                min-width: 0;
                background: transparent;
            ">

                <!-- ── No conversation selected ── -->
                <div
                    v-if="!activeConversation"
                    style="flex: 1; overflow-y: auto; display: flex; flex-direction: column;"
                >
                    <!-- Has friends → show them to start a chat -->
                    <div v-if="friends.length > 0" style="max-width: 480px; width: 100%; margin: 0 auto; padding: 40px 28px 60px;">
                        <div style="text-align: center; margin-bottom: 32px;">
                            <div style="
                                width: 64px; height: 64px; border-radius: 50%;
                                background: linear-gradient(135deg,#009ac714,#4ebcff0e);
                                border: 2px solid #009ac722;
                                display: flex; align-items: center; justify-content: center;
                                font-size: 28px; margin: 0 auto 14px;
                            ">💬</div>
                            <p style="font-size: 16px; font-weight: 800; color: #1a3a4a; margin: 0 0 6px;">As tuas mensagens</p>
                            <p style="font-size: 13px; color: #8ba0b0; margin: 0; line-height: 1.5;">Escolhe um amigo para começar a conversar.</p>
                        </div>

                        <!-- Friends list -->
                        <div style="
                            background: rgba(255,255,255,0.80);
                            backdrop-filter: blur(20px);
                            border: 1px solid #009ac714;
                            border-radius: 20px;
                            box-shadow: 0 4px 20px #009ac70a;
                            overflow: hidden;
                        ">
                            <button
                                v-for="(f, i) in friends"
                                :key="f.id"
                                @click="startWith(f.id)"
                                style="
                                    width: 100%; display: flex; align-items: center; gap: 14px;
                                    padding: 14px 20px; border: none; background: transparent;
                                    cursor: pointer; text-align: left; transition: background .15s;
                                "
                                :style="{ borderTop: i > 0 ? '1px solid #009ac70c' : 'none' }"
                                @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.05)'"
                                @mouseleave="$event.currentTarget.style.background='transparent'"
                            >
                                <!-- Avatar -->
                                <img
                                    v-if="f.avatar"
                                    :src="f.avatar"
                                    :style="{
                                        width: '46px', height: '46px', borderRadius: '50%',
                                        objectFit: 'cover', flexShrink: '0',
                                        border: `2.5px solid ${f.avatar_color}`,
                                        boxShadow: `0 2px 10px ${f.avatar_color}33`,
                                    }"
                                />
                                <div v-else :style="{
                                    width: '46px', height: '46px', borderRadius: '50%', flexShrink: '0',
                                    background: f.avatar_color,
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    fontSize: '17px', fontWeight: '800', color: 'white',
                                    boxShadow: `0 2px 10px ${f.avatar_color}33`,
                                }">{{ avatarInitial(f.name) }}</div>

                                <!-- Info -->
                                <div style="flex: 1; min-width: 0;">
                                    <p style="font-size: 14px; font-weight: 700; color: #1a3a4a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ f.name }}</p>
                                    <p v-if="f.username" style="font-size: 12px; color: #009ac7; margin: 2px 0 0;">@{{ f.username }}</p>
                                </div>

                                <!-- CTA -->
                                <span style="
                                    flex-shrink: 0;
                                    padding: 7px 16px; border-radius: 99px;
                                    background: linear-gradient(135deg,#009ac7,#4ebcff);
                                    color: white; font-size: 12px; font-weight: 700;
                                    box-shadow: 0 3px 10px #009ac730;
                                ">Enviar mensagem</span>
                            </button>
                        </div>
                    </div>

                    <!-- No friends either → generic empty state -->
                    <div v-else style="
                        flex: 1;
                        display: flex; flex-direction: column;
                        align-items: center; justify-content: center;
                        gap: 14px; padding: 40px;
                    ">
                        <div style="
                            width: 80px; height: 80px; border-radius: 50%;
                            background: linear-gradient(135deg,#009ac714,#4ebcff0e);
                            border: 2px solid #009ac722;
                            display: flex; align-items: center; justify-content: center;
                            font-size: 36px;
                        ">💬</div>
                        <p style="font-size: 15px; font-weight: 700; color: #1a3a4a; margin: 0;">As tuas mensagens</p>
                        <p style="font-size: 13px; color: #8ba0b0; margin: 0; text-align: center; max-width: 260px; line-height: 1.5;">
                            Adiciona amigos para começar a conversar com eles.
                        </p>
                        <Link
                            :href="route('friends.index')"
                            style="
                                margin-top: 4px; padding: 10px 22px;
                                background: linear-gradient(135deg,#009ac7,#4ebcff);
                                color: white; font-size: 13px; font-weight: 700;
                                border-radius: 99px; text-decoration: none;
                                box-shadow: 0 4px 16px #009ac730;
                                transition: transform .2s, box-shadow .2s;
                            "
                            @mouseenter="$event.currentTarget.style.transform='translateY(-2px)'; $event.currentTarget.style.boxShadow='0 8px 24px #009ac740'"
                            @mouseleave="$event.currentTarget.style.transform='translateY(0)'; $event.currentTarget.style.boxShadow='0 4px 16px #009ac730'"
                        >Ver amigos</Link>
                    </div>
                </div>

                <!-- ── Active conversation ── -->
                <template v-else>

                    <!-- Header -->
                    <div style="
                        height: 64px; flex-shrink: 0;
                        display: flex; align-items: center; gap: 14px;
                        padding: 0 24px;
                        background: rgba(255,255,255,0.65);
                        backdrop-filter: blur(20px);
                        border-bottom: 1px solid #009ac712;
                    ">
                        <img
                            v-if="activeConversation.other_user?.avatar"
                            :src="activeConversation.other_user.avatar"
                            :style="{
                                width: '38px', height: '38px', borderRadius: '50%',
                                objectFit: 'cover',
                                border: `2px solid ${activeConversation.other_user.avatar_color}`,
                                boxShadow: `0 2px 8px ${activeConversation.other_user.avatar_color}44`,
                                flexShrink: '0',
                            }"
                        />
                        <div v-else :style="{
                            width: '38px', height: '38px', borderRadius: '50%', flexShrink: '0',
                            background: activeConversation.other_user?.avatar_color ?? '#009ac7',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: '14px', fontWeight: '800', color: 'white',
                        }">{{ avatarInitial(activeConversation.other_user?.name) }}</div>

                        <div style="flex: 1; min-width: 0;">
                            <p style="font-size: 14px; font-weight: 800; color: #1a3a4a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ activeConversation.other_user?.name }}
                            </p>
                            <p v-if="activeConversation.other_user?.username" style="font-size: 11px; color: #009ac7; margin: 1px 0 0;">
                                @{{ activeConversation.other_user.username }}
                            </p>
                        </div>

                        <!-- Open profile button -->
                        <Link
                            v-if="activeConversation.other_user?.username"
                            :href="route('profile.show', activeConversation.other_user.username)"
                            style="
                                padding: 7px 16px;
                                border-radius: 99px;
                                border: 1.5px solid #009ac733;
                                color: #009ac7; font-size: 12px; font-weight: 700;
                                text-decoration: none;
                                background: rgba(0,154,199,0.06);
                                transition: all .2s;
                                white-space: nowrap;
                            "
                            @mouseenter="$event.currentTarget.style.background='rgba(0,154,199,0.14)'; $event.currentTarget.style.borderColor='#009ac7'"
                            @mouseleave="$event.currentTarget.style.background='rgba(0,154,199,0.06)'; $event.currentTarget.style.borderColor='#009ac733'"
                        >Ver perfil</Link>
                    </div>

                    <!-- Messages area -->
                    <div
                        ref="messagesEl"
                        style="
                            flex: 1;
                            overflow-y: auto;
                            padding: 24px 28px;
                            display: flex;
                            flex-direction: column;
                            gap: 4px;
                            scroll-behavior: smooth;
                        "
                    >
                        <!-- Empty state -->
                        <div
                            v-if="messages.length === 0"
                            style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; opacity: .6;"
                        >
                            <p style="font-size: 22px; margin: 0;">👋</p>
                            <p style="font-size: 13px; color: #8ba0b0; margin: 0;">Sem mensagens ainda. Diz olá!</p>
                        </div>

                        <!-- Message groups -->
                        <template v-for="(item, i) in groupedMessages()" :key="i">
                            <!-- Date separator -->
                            <div
                                v-if="item.type === 'date'"
                                style="
                                    display: flex; align-items: center; gap: 12px;
                                    margin: 18px 0 10px;
                                "
                            >
                                <div style="flex: 1; height: 1px; background: linear-gradient(to right, transparent, #009ac714);"></div>
                                <span style="font-size: 10px; font-weight: 700; color: #b0c0cc; letter-spacing: .06em; white-space: nowrap;">{{ item.label }}</span>
                                <div style="flex: 1; height: 1px; background: linear-gradient(to left, transparent, #009ac714);"></div>
                            </div>

                            <!-- Message bubble -->
                            <div
                                v-else
                                style="display: flex; flex-direction: column;"
                                :style="{ alignItems: item.is_own ? 'flex-end' : 'flex-start' }"
                            >
                                <div
                                    style="
                                        max-width: 68%;
                                        padding: 10px 14px;
                                        border-radius: 18px;
                                        font-size: 13.5px;
                                        line-height: 1.5;
                                        word-break: break-word;
                                        transition: transform .1s;
                                    "
                                    :style="item.is_own
                                        ? {
                                            background: item.image_url && !item.content ? 'transparent' : `linear-gradient(135deg, ${authUser?.avatar_color ?? '#009ac7'}, ${authUser?.avatar_color ?? '#009ac7'}cc)`,
                                            color: 'white',
                                            borderBottomRightRadius: '5px',
                                            boxShadow: item.image_url && !item.content ? 'none' : `0 4px 16px ${authUser?.avatar_color ?? '#009ac7'}44`,
                                            padding: item.image_url ? '0' : '10px 14px',
                                          }
                                        : {
                                            background: item.image_url && !item.content ? 'transparent' : 'rgba(255,255,255,0.78)',
                                            backdropFilter: item.image_url && !item.content ? 'none' : 'blur(12px)',
                                            color: '#1a3a4a',
                                            borderBottomLeftRadius: '5px',
                                            border: item.image_url && !item.content ? 'none' : '1px solid #009ac714',
                                            boxShadow: item.image_url && !item.content ? 'none' : '0 2px 8px #009ac70a',
                                            padding: item.image_url ? '0' : '10px 14px',
                                          }
                                    "
                                >
                                    <img
                                        v-if="item.image_url"
                                        :src="item.image_url"
                                        style="display: block; max-width: 260px; max-height: 320px; border-radius: 14px; width: 100%;"
                                    />
                                    <span v-if="item.content" :style="item.image_url ? { display: 'block', padding: '8px 14px 10px' } : {}">{{ item.content }}</span>
                                </div>

                                <span style="font-size: 10px; color: #b0c0cc; margin-top: 3px; padding: 0 3px;">
                                    {{ formatTime(item.created_at) }}
                                </span>
                            </div>
                        </template>
                    </div>

                    <!-- Input bar -->
                    <div style="
                        flex-shrink: 0;
                        padding: 14px 20px;
                        background: rgba(255,255,255,0.65);
                        backdrop-filter: blur(20px);
                        border-top: 1px solid #009ac712;
                        display: flex;
                        flex-direction: column;
                        gap: 8px;
                    ">
                        <!-- Image preview strip -->
                        <div v-if="imagePreview" style="display: flex; align-items: flex-start; gap: 8px;">
                            <div style="position: relative; display: inline-block;">
                                <img :src="imagePreview" style="max-height: 80px; max-width: 140px; border-radius: 10px; display: block; border: 1.5px solid #009ac730;" />
                                <button
                                    @click="removeImage"
                                    style="position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; border-radius: 50%; border: none; background: #e05555; color: white; font-size: 11px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1;"
                                >×</button>
                            </div>
                        </div>

                        <!-- Buttons + textarea row -->
                        <div style="display: flex; align-items: flex-end; gap: 10px;">

                        <!-- Image button -->
                        <label
                            :style="{
                                flexShrink: 0,
                                width: '40px', height: '40px',
                                borderRadius: '50%',
                                border: imagePreview ? '1.5px solid #009ac7' : '1.5px solid #c8d8e066',
                                background: imagePreview ? '#009ac714' : 'transparent',
                                cursor: 'pointer',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                color: imagePreview ? '#009ac7' : '#b0c0cc',
                                transition: 'all .2s',
                            }"
                        >
                            <input ref="imageInput" type="file" accept="image/*" style="display: none;" @change="onImageChange" />
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/>
                                <polyline points="21 15 16 10 5 21"/>
                            </svg>
                        </label>

                        <!-- Textarea -->
                        <textarea
                            v-model="msgForm.content"
                            placeholder="Escreve uma mensagem…"
                            rows="1"
                            @keydown="handleKeydown"
                            @input="$event.target.style.height = 'auto'; $event.target.style.height = Math.min($event.target.scrollHeight, 120) + 'px'"
                            style="
                                flex: 1;
                                resize: none;
                                border: 1.5px solid #009ac722;
                                border-radius: 20px;
                                padding: 10px 16px;
                                font-size: 13.5px;
                                font-family: inherit;
                                color: #1a3a4a;
                                background: rgba(255,255,255,0.85);
                                outline: none;
                                line-height: 1.5;
                                max-height: 120px;
                                overflow-y: auto;
                                transition: border-color .2s, box-shadow .2s;
                            "
                            @focus="$event.target.style.borderColor='#009ac7'; $event.target.style.boxShadow='0 0 0 3px #009ac714'"
                            @blur="$event.target.style.borderColor='#009ac722'; $event.target.style.boxShadow='none'"
                        />

                        <!-- Send button -->
                        <button
                            @click="send"
                            :disabled="(!msgForm.content.trim() && !msgForm.image) || msgForm.processing"
                            style="
                                flex-shrink: 0;
                                width: 40px; height: 40px;
                                border-radius: 50%;
                                border: none;
                                background: linear-gradient(135deg,#009ac7,#4ebcff);
                                color: white;
                                display: flex; align-items: center; justify-content: center;
                                cursor: pointer;
                                box-shadow: 0 3px 12px #009ac740;
                                transition: transform .2s, box-shadow .2s, opacity .2s;
                            "
                            :style="{ opacity: ((!msgForm.content.trim() && !msgForm.image) || msgForm.processing) ? '0.45' : '1' }"
                            @mouseenter="e => { if ((msgForm.content.trim() || msgForm.image) && !msgForm.processing) { e.currentTarget.style.transform='scale(1.1)'; e.currentTarget.style.boxShadow='0 6px 20px #009ac750' } }"
                            @mouseleave="e => { e.currentTarget.style.transform='scale(1)'; e.currentTarget.style.boxShadow='0 3px 12px #009ac740' }"
                        >
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                        </button>

                        </div><!-- end row -->
                    </div>

                </template>
            </main>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Scrollbar styling */
aside > div::-webkit-scrollbar,
[ref="messagesEl"]::-webkit-scrollbar {
    width: 4px;
}
aside > div::-webkit-scrollbar-track,
[ref="messagesEl"]::-webkit-scrollbar-track {
    background: transparent;
}
aside > div::-webkit-scrollbar-thumb,
[ref="messagesEl"]::-webkit-scrollbar-thumb {
    background: #009ac722;
    border-radius: 99px;
}
</style>
