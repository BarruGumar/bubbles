<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    friends:  Array,
    received: Array,
    sent:     Array,
})

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase()
}

function accept(friendId) {
    router.patch(route('friends.accept', friendId), {}, { preserveScroll: true })
}

function reject(friendId) {
    router.delete(route('friends.reject', friendId), { preserveScroll: true })
}

function startConversation(friend) {
    router.post(route('conversations.store'), { recipient_id: friend.id })
}
</script>

<template>
    <Head title="Amigos · bubbles" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px;">

            <h1 style="font-size: 22px; font-weight: 900; color: #1a3a4a; margin: 0 0 24px; letter-spacing: -.02em;">Amigos</h1>

            <!-- Pedidos recebidos -->
            <div
                v-if="received.length"
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 18px; border: 1px solid #c74a6b22; box-shadow: 0 4px 16px #c74a6b08; padding: 20px; margin-bottom: 16px;"
            >
                <p style="font-size: 10px; font-weight: 800; color: #c74a6b; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 16px;">
                    Pedidos recebidos · {{ received.length }}
                </p>
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div v-for="req in received" :key="req.friendId" style="display: flex; align-items: center; gap: 14px;">
                        <!-- Avatar clicável -->
                        <Link :href="route('profile.show', req.username)" style="text-decoration: none; flex-shrink: 0;">
                            <img
                                v-if="req.avatar"
                                :src="req.avatar"
                                :style="{
                                    width: '46px', height: '46px', borderRadius: '50%',
                                    objectFit: 'cover', border: `2px solid ${req.avatar_color}`,
                                    boxShadow: `0 2px 8px ${req.avatar_color}44`, display: 'block',
                                }"
                            />
                            <div v-else :style="{
                                width: '46px', height: '46px', borderRadius: '50%',
                                background: req.avatar_color,
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                fontSize: '17px', fontWeight: '800', color: 'white',
                                boxShadow: `0 2px 8px ${req.avatar_color}44`,
                            }">{{ formatInitial(req.name) }}</div>
                        </Link>

                        <!-- Info -->
                        <Link :href="route('profile.show', req.username)" style="text-decoration: none; flex: 1; min-width: 0;">
                            <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ req.name }}</p>
                            <p v-if="req.username" style="font-size: 11px; color: #009ac7; margin: 2px 0 0;">@{{ req.username }}</p>
                        </Link>

                        <!-- Ações -->
                        <div style="display: flex; gap: 8px; flex-shrink: 0;">
                            <button
                                @click="accept(req.friendId)"
                                style="padding: 7px 16px; border-radius: 99px; border: none; background: #009ac7; color: white; font-size: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 3px 10px #009ac730; transition: opacity .2s;"
                                @mouseenter="$event.target.style.opacity='.8'"
                                @mouseleave="$event.target.style.opacity='1'"
                            >Aceitar</button>
                            <button
                                @click="reject(req.friendId)"
                                style="padding: 7px 14px; border-radius: 99px; border: 1.5px solid #c8d8e0; background: transparent; color: #8ba0b0; font-size: 12px; font-weight: 600; cursor: pointer; transition: all .2s;"
                                @mouseenter="$event.currentTarget.style.borderColor='#e05555'; $event.currentTarget.style.color='#e05555'"
                                @mouseleave="$event.currentTarget.style.borderColor='#c8d8e0'; $event.currentTarget.style.color='#8ba0b0'"
                            >Recusar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pedidos enviados -->
            <div
                v-if="sent.length"
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 18px; border: 1px solid #4ebcff22; box-shadow: 0 4px 16px #009ac70a; padding: 20px; margin-bottom: 16px;"
            >
                <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 16px;">
                    Pedidos enviados · {{ sent.length }}
                </p>
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div v-for="req in sent" :key="req.friendId" style="display: flex; align-items: center; gap: 14px;">
                        <Link :href="route('profile.show', req.username)" style="text-decoration: none; flex-shrink: 0;">
                            <img
                                v-if="req.avatar"
                                :src="req.avatar"
                                :style="{
                                    width: '46px', height: '46px', borderRadius: '50%',
                                    objectFit: 'cover', border: `2px solid ${req.avatar_color}`,
                                    boxShadow: `0 2px 8px ${req.avatar_color}44`, display: 'block',
                                }"
                            />
                            <div v-else :style="{
                                width: '46px', height: '46px', borderRadius: '50%',
                                background: req.avatar_color,
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                fontSize: '17px', fontWeight: '800', color: 'white',
                                boxShadow: `0 2px 8px ${req.avatar_color}44`,
                            }">{{ formatInitial(req.name) }}</div>
                        </Link>

                        <Link :href="route('profile.show', req.username)" style="text-decoration: none; flex: 1; min-width: 0;">
                            <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ req.name }}</p>
                            <p v-if="req.username" style="font-size: 11px; color: #009ac7; margin: 2px 0 0;">@{{ req.username }}</p>
                        </Link>

                        <button
                            @click="reject(req.friendId)"
                            style="padding: 7px 14px; border-radius: 99px; border: 1.5px solid #c8d8e0; background: transparent; color: #8ba0b0; font-size: 12px; font-weight: 600; cursor: pointer; transition: all .2s; flex-shrink: 0;"
                            @mouseenter="$event.currentTarget.style.borderColor='#e05555'; $event.currentTarget.style.color='#e05555'"
                            @mouseleave="$event.currentTarget.style.borderColor='#c8d8e0'; $event.currentTarget.style.color='#8ba0b0'"
                        >Cancelar</button>
                    </div>
                </div>
            </div>

            <!-- Lista de amigos aceites -->
            <div
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 18px; border: 1px solid #4ebcff22; box-shadow: 0 4px 16px #009ac70a; padding: 20px;"
            >
                <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 16px;">
                    Amigos · {{ friends.length }}
                </p>

                <!-- Estado vazio -->
                <div v-if="friends.length === 0 && received.length === 0 && sent.length === 0" style="text-align: center; padding: 40px 20px;">
                    <p style="font-size: 32px; margin: 0 0 12px;">🫧</p>
                    <p style="font-size: 14px; color: #8ba0b0;">Ainda não tens amigos no bubbles.</p>
                    <p style="font-size: 12px; color: #b0c0cc; margin-top: 6px;">Visita o perfil de alguém e envia um pedido de amizade.</p>
                </div>
                <div v-else-if="friends.length === 0" style="text-align: center; padding: 20px;">
                    <p style="font-size: 13px; color: #b0c0cc;">Nenhum amigo aceite ainda.</p>
                </div>

                <div v-else style="display: flex; flex-direction: column; gap: 14px;">
                    <div v-for="friend in friends" :key="friend.friendId" style="display: flex; align-items: center; gap: 14px;">
                        <Link :href="route('profile.show', friend.username)" style="text-decoration: none; flex-shrink: 0;">
                            <img
                                v-if="friend.avatar"
                                :src="friend.avatar"
                                :style="{
                                    width: '46px', height: '46px', borderRadius: '50%',
                                    objectFit: 'cover', border: `2px solid ${friend.avatar_color}`,
                                    boxShadow: `0 2px 8px ${friend.avatar_color}44`, display: 'block',
                                }"
                            />
                            <div v-else :style="{
                                width: '46px', height: '46px', borderRadius: '50%',
                                background: friend.avatar_color,
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                fontSize: '17px', fontWeight: '800', color: 'white',
                                boxShadow: `0 2px 8px ${friend.avatar_color}44`,
                            }">{{ formatInitial(friend.name) }}</div>
                        </Link>

                        <Link :href="route('profile.show', friend.username)" style="text-decoration: none; flex: 1; min-width: 0;">
                            <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ friend.name }}</p>
                            <p v-if="friend.username" style="font-size: 11px; color: #009ac7; margin: 2px 0 0;">@{{ friend.username }}</p>
                        </Link>
                                                        
                        <button
                                    @click="startConversation(friend)"
                                    style="font-size: 12px; font-weight: 700; color: white; padding: 7px 18px; border-radius: 99px; border: none; background: linear-gradient(135deg,#009ac7,#4ebcff); cursor: pointer; white-space: nowrap; box-shadow: 0 3px 12px #009ac730; transition: opacity .2s;"
                                    @mouseenter="$event.target.style.opacity='.85'"
                                    @mouseleave="$event.target.style.opacity='1'"
                                >💬 Mensagem</button>
                                
                        <button
                            @click="reject(friend.friendId)"
                            style="padding: 7px 14px; border-radius: 99px; border: 1.5px solid #c8d8e0; background: transparent; color: #8ba0b0; font-size: 12px; font-weight: 600; cursor: pointer; transition: all .2s; flex-shrink: 0;"
                            @mouseenter="$event.currentTarget.style.borderColor='#e05555'; $event.currentTarget.style.color='#e05555'"
                            @mouseleave="$event.currentTarget.style.borderColor='#c8d8e0'; $event.currentTarget.style.color='#8ba0b0'"
                        >Remover</button>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
