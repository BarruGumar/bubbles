<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    profileUser: Object,
    posts:       Array,
    isOwn:       Boolean,
})

const authUser = computed(() => usePage().props.auth?.user)

const postForm = useForm({ content: '' })
const charCount = computed(() => postForm.content.length)

function submitPost() {
    if (!postForm.content.trim()) return
    postForm.post(route('posts.store'), {
        preserveScroll: true,
        onSuccess: () => postForm.reset('content'),
    })
}

function deletePost(id) {
    router.delete(route('posts.destroy', id), { preserveScroll: true })
}

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase()
}
</script>

<template>
    <Head :title="`@${profileUser.username} · bubbles`" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px;">

            <!-- Profile hero -->
            <div style="border-radius: 22px; overflow: visible; margin-bottom: 10px; box-shadow: 0 8px 32px #009ac70e; position: relative;">

                <!-- Banner -->
                <div :style="{
                    height: '180px', borderRadius: '22px 22px 0 0', position: 'relative',
                    background: profileUser.banner
                        ? `url('${profileUser.banner}') center/cover no-repeat`
                        : `linear-gradient(135deg, ${profileUser.avatar_color}cc 0%, ${profileUser.avatar_color} 100%)`,
                }">
                    <!-- Avatar overlapping -->
                    <div style="position: absolute; bottom: -42px; left: 32px; z-index: 5;">
                        <img
                            v-if="profileUser.avatar"
                            :src="profileUser.avatar"
                            :style="{
                                width: '86px', height: '86px', borderRadius: '50%',
                                objectFit: 'cover', border: '5px solid white',
                                boxShadow: `0 4px 20px ${profileUser.avatar_color}66`,
                                display: 'block',
                            }"
                        />
                        <div v-else :style="{
                            width: '86px', height: '86px', borderRadius: '50%',
                            background: profileUser.avatar_color,
                            border: '5px solid white',
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
                        <Link
                            v-if="isOwn"
                            :href="route('profile.edit')"
                            style="font-size: 12px; font-weight: 700; color: #009ac7; text-decoration: none; padding: 7px 18px; border-radius: 99px; border: 1.5px solid #009ac744; background: #009ac708; transition: all .2s; white-space: nowrap;"
                        >Editar perfil</Link>
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
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px;">
                            <span style="font-size: 11px; color: #b0c0cc;">{{ charCount }}/1000 · Ctrl+Enter para publicar</span>
                            <button
                                @click="submitPost"
                                :disabled="postForm.processing || !postForm.content.trim()"
                                style="padding: 8px 20px; border-radius: 99px; background: #009ac7; border: none; color: white; font-size: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 3px 12px #009ac730; transition: opacity .2s;"
                                :style="{ opacity: postForm.processing || !postForm.content.trim() ? 0.5 : 1, cursor: !postForm.content.trim() ? 'not-allowed' : 'pointer' }"
                            >Publicar</button>
                        </div>
                        <p v-if="postForm.errors.content" style="font-size: 11px; color: #e05555; margin: 6px 0 0;">{{ postForm.errors.content }}</p>
                    </div>
                </div>
            </div>

            <!-- Posts feed -->
            <div v-if="posts.length === 0" style="text-align: center; padding: 60px 20px;">
                <p style="font-size: 32px; margin: 0 0 12px;">🫧</p>
                <p style="font-size: 14px; color: #8ba0b0;">{{ isOwn ? 'Ainda não publicaste nada. Começa agora!' : 'Ainda não há posts aqui.' }}</p>
            </div>

            <div v-else style="display: flex; flex-direction: column; gap: 12px;">
                <div
                    v-for="post in posts"
                    :key="post.id"
                    style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid #4ebcff1a; box-shadow: 0 2px 12px #009ac708; padding: 20px;"
                >
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <!-- Avatar -->
                        <img
                            v-if="profileUser.avatar"
                            :src="profileUser.avatar"
                            :style="{
                                width: '38px', height: '38px', borderRadius: '50%', flexShrink: 0,
                                objectFit: 'cover', border: `2px solid ${profileUser.avatar_color}`,
                            }"
                        />
                        <div v-else :style="{
                            width: '38px', height: '38px', borderRadius: '50%', flexShrink: 0,
                            background: profileUser.avatar_color,
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: '15px', fontWeight: '800', color: 'white',
                        }">{{ formatInitial(profileUser.name) }}</div>

                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <div>
                                    <span style="font-size: 13px; font-weight: 700; color: #1a3a4a;">{{ profileUser.name }}</span>
                                    <span style="font-size: 11px; color: #8ba0b0; margin-left: 8px;">{{ post.created_at }}</span>
                                </div>
                                <button
                                    v-if="isOwn"
                                    @click="deletePost(post.id)"
                                    style="background: none; border: none; cursor: pointer; color: #c0c8d0; font-size: 16px; padding: 2px 4px; border-radius: 6px; line-height: 1; transition: color .2s;"
                                    @mouseenter="$event.target.style.color='#e05555'"
                                    @mouseleave="$event.target.style.color='#c0c8d0'"
                                    title="Apagar post"
                                >×</button>
                            </div>
                            <p style="font-size: 14px; color: #2a4a5a; line-height: 1.65; margin: 0; white-space: pre-wrap;">{{ post.content }}</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
