<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ToastContainer from '@/Components/ToastContainer.vue'
import { clImg } from '@/Composables/useCloudinary'

const page             = usePage()
const user             = computed(() => page.props.auth?.user)
const pendingFriends   = computed(() => page.props.auth?.pending_friends_count ?? 0)
const unreadMessages   = computed(() => page.props.auth?.unread_messages_count ?? 0)
const open             = ref(false)

function avatarInitial(name) {
    return (name ?? '?')[0].toUpperCase()
}
</script>

<template>
    <div
        class="min-h-screen"
        style="background: rgba(240,248,255,0.45); font-family: 'Segoe UI', system-ui, sans-serif;"
    >
        <!-- Topbar -->
        <nav style="position: sticky; top: 0; z-index: 40; background: rgba(255,255,255,0.75); backdrop-filter: blur(16px); border-bottom: 1px solid #009ac71a;">
            <div style="max-width: 1100px; margin: 0 auto; padding: 0 24px; height: 58px; display: flex; align-items: center; justify-content: space-between;">

                <!-- Left: logo + nav links -->
                <div style="display: flex; align-items: center; gap: 28px;">
                    <Link href="/bubbles" style="text-decoration: none;">
                        <span style="font-weight: 900; font-size: 20px; color: #009ac7; letter-spacing: -1px;">bubbles</span>
                    </Link>
                    <Link
                        href="/bubbles"
                        style="font-size: 13px; font-weight: 600; color: #5a7a8a; text-decoration: none; transition: color .2s;"
                        @mouseenter="$event.target.style.color='#009ac7'"
                        @mouseleave="$event.target.style.color='#5a7a8a'"
                    >Explorar</Link>
                    <Link
                        v-if="user"
                        :href="route('friends.index')"
                        style="font-size: 13px; font-weight: 600; color: #5a7a8a; text-decoration: none; transition: color .2s; position: relative; display: inline-flex; align-items: center; gap: 6px;"
                        @mouseenter="$event.currentTarget.style.color='#009ac7'"
                        @mouseleave="$event.currentTarget.style.color='#5a7a8a'"
                    >
                        Amigos
                        <span
                            v-if="pendingFriends > 0"
                            style="display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; background: #c74a6b; color: white; border-radius: 99px; font-size: 10px; font-weight: 800; line-height: 1;"
                        >{{ pendingFriends }}</span>
                    </Link>
                    <Link
                        v-if="user"
                        :href="route('conversations.index')"
                        style="font-size: 13px; font-weight: 600; color: #5a7a8a; text-decoration: none; transition: color .2s; position: relative; display: inline-flex; align-items: center; gap: 6px;"
                        @mouseenter="$event.currentTarget.style.color='#009ac7'"
                        @mouseleave="$event.currentTarget.style.color='#5a7a8a'"
                    >
                        Mensagens
                        <span
                            v-if="unreadMessages > 0"
                            style="display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; background: #009ac7; color: white; border-radius: 99px; font-size: 10px; font-weight: 800; line-height: 1;"
                        >{{ unreadMessages }}</span>
                    </Link>
                </div>

                <!-- Right: user avatar dropdown -->
                <div v-if="user" style="position: relative;">
                    <button
                        @click="open = !open"
                        style="display: flex; align-items: center; gap: 10px; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 99px; transition: background .2s;"
                        @mouseenter="$event.currentTarget.style.background='#009ac70c'"
                        @mouseleave="$event.currentTarget.style.background='transparent'"
                    >
                        <!-- Avatar -->
                        <img
                            v-if="user.avatar"
                            :src="clImg(user.avatar, 64, 64, 'fill', 'face')"
                            :style="{
                                width: '32px', height: '32px', borderRadius: '50%',
                                objectFit: 'cover',
                                border: `2px solid ${user.avatar_color ?? '#009ac7'}`,
                                boxShadow: `0 2px 8px ${user.avatar_color ?? '#009ac7'}44`,
                            }"
                        />
                        <div v-else :style="{
                            width: '32px', height: '32px', borderRadius: '50%',
                            background: user.avatar_color ?? '#009ac7',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: '13px', fontWeight: '800', color: 'white',
                            boxShadow: `0 2px 8px ${user.avatar_color ?? '#009ac7'}44`,
                        }">{{ avatarInitial(user.name) }}</div>
                        <span style="font-size: 13px; font-weight: 700; color: #1a3a4a;">{{ user.name }}</span>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" style="color: #8ba0b0;">
                            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <!-- Dropdown -->
                    <Transition name="dropdown">
                        <div
                            v-if="open"
                            style="position: absolute; right: 0; top: calc(100% + 8px); min-width: 180px; background: rgba(255,255,255,0.95); backdrop-filter: blur(16px); border-radius: 14px; border: 1px solid #4ebcff22; box-shadow: 0 8px 32px #009ac71a; overflow: hidden; z-index: 50;"
                            @click="open = false"
                        >
                            <!-- Profile header -->
                            <div style="padding: 12px 16px 10px; border-bottom: 1px solid #f0f4f8;">
                                <p style="font-size: 12px; font-weight: 700; color: #1a3a4a; margin: 0;">{{ user.name }}</p>
                                <p v-if="user.username" style="font-size: 11px; color: #8ba0b0; margin: 2px 0 0;">@{{ user.username }}</p>
                            </div>
                            <div style="padding: 6px;">
                                <Link
                                    v-if="user.username"
                                    :href="route('profile.show', user.username)"
                                    style="display: block; padding: 9px 12px; border-radius: 9px; font-size: 13px; color: #2a4a5a; text-decoration: none; transition: background .15s;"
                                    @mouseenter="$event.target.style.background='#f0f8ff'"
                                    @mouseleave="$event.target.style.background='transparent'"
                                >O meu perfil</Link>
                                <Link
                                    :href="route('profile.edit')"
                                    style="display: block; padding: 9px 12px; border-radius: 9px; font-size: 13px; color: #2a4a5a; text-decoration: none; transition: background .15s;"
                                    @mouseenter="$event.target.style.background='#f0f8ff'"
                                    @mouseleave="$event.target.style.background='transparent'"
                                >Definições</Link>
                                <div style="height: 1px; background: #f0f4f8; margin: 4px 0;" />
                                <Link
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    style="display: block; width: 100%; text-align: left; padding: 9px 12px; border-radius: 9px; font-size: 13px; color: #c74a6b; background: none; border: none; cursor: pointer; transition: background .15s;"
                                    @mouseenter="$event.target.style.background='#fef0f4'"
                                    @mouseleave="$event.target.style.background='transparent'"
                                >Sair</Link>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Not logged in -->
                <div v-else style="display: flex; gap: 10px;">
                    <Link
                        :href="route('login')"
                        style="font-size: 13px; font-weight: 600; color: #009ac7; text-decoration: none; padding: 7px 16px; border-radius: 99px; border: 1.5px solid #009ac7; transition: all .2s;"
                    >Entrar</Link>
                    <Link
                        :href="route('register')"
                        style="font-size: 13px; font-weight: 700; color: white; text-decoration: none; padding: 7px 16px; border-radius: 99px; background: #009ac7; box-shadow: 0 3px 12px #009ac740;"
                    >Registo</Link>
                </div>
            </div>
        </nav>

        <!-- Ambient orbs -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden" style="z-index: 0;">
            <div style="position:absolute;top:-60px;left:-60px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,#009ac714 0%,transparent 70%);" />
            <div style="position:absolute;bottom:-40px;right:-40px;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle,#4ebcff0e 0%,transparent 70%);" />
        </div>

        <!-- Page content -->
        <main style="position: relative; z-index: 1;">
            <slot />
        </main>

        <ToastContainer />
    </div>
</template>

<style scoped>
.dropdown-enter-active, .dropdown-leave-active { transition: opacity .18s ease, transform .22s cubic-bezier(.2,.8,.2,1) }
.dropdown-enter-from, .dropdown-leave-to       { opacity: 0; transform: translateY(-6px) scale(.97) }
</style>
