<script setup>
import { computed, ref } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    community: Object,
    posts:     Array,
})

const authUser   = computed(() => usePage().props.auth?.user)
const postForm   = useForm({ content: '' })
const charCount  = computed(() => postForm.content.length)

function submitPost() {
    if (!postForm.content.trim()) return
    postForm.post(route('community.posts.store', props.community.id), {
        preserveScroll: true,
        onSuccess: () => postForm.reset('content'),
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

// Activity: derive from post count (clamp 0–1)
const activityLevel = computed(() => Math.min(1, props.posts.length / 20))
const activityPct   = computed(() => Math.round(activityLevel.value * 100))
</script>

<template>
    <Head :title="`${community.title} · bubbles`" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px;">

            <!-- Community hero -->
            <div
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 22px; border: 1px solid #4ebcff22; box-shadow: 0 8px 32px #009ac70e; padding: 36px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 28px;"
            >
                <!-- Avatar circle -->
                <div :style="{
                    width: '80px', height: '80px', borderRadius: '50%', flexShrink: 0,
                    background: community.color,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: '28px', fontWeight: '900', color: 'white',
                    boxShadow: `0 4px 24px ${community.color}55`,
                    letterSpacing: '-.02em',
                }">{{ community.label.replace('#', '').charAt(0).toUpperCase() }}</div>

                <!-- Info -->
                <div style="flex: 1; min-width: 0;">
                     <h1 style="font-size: 22px; font-weight: 900; color: #1a3a4a; margin: 0 0 4px; letter-spacing: -.02em;">{{ community.title }}</h1>
                    <p style="font-size: 12px; color: #5a7a8a; margin: 0 0 8px;">{{ community.tagline }}</p>
                    <p style="font-size: 13px; font-weight: 600; margin: 0 0 12px;" :style="{ color: community.color }">{{ community.members }} membros</p>
                    <p style="font-size: 12px; color: #6a8798; margin: 0 0 16px; line-height: 1.5;">{{ community.description }}</p>

                    <!-- Activity bar -->
                    <div style="margin-bottom: 18px;">
                        <p style="font-size: 10px; color: #8ba0b0; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; margin: 0 0 6px;">Atividade</p>
                        <div style="height: 5px; background: #e8f4fb; border-radius: 99px; overflow: hidden; width: 180px;">
                            <div
                                :style="{
                                    height: '100%',
                                    width: `${activityPct}%`,
                                    background: community.color,
                                    borderRadius: '99px',
                                    transition: 'width .5s ease',
                                    minWidth: posts.length ? '6px' : '0',
                                }"
                            />
                        </div>
                        <p style="font-size: 9px; color: #b0c0cc; margin: 3px 0 0;">{{ posts.length }} posts</p>
                    </div>

                    <!-- Join button (visual placeholder) -->
                    <button
                        :style="{
                            padding: '9px 24px', borderRadius: '99px', border: 'none',
                            background: community.color, color: 'white',
                            fontSize: '12px', fontWeight: '700', cursor: 'pointer',
                            boxShadow: `0 4px 14px ${community.color}44`,
                            transition: 'opacity .2s',
                        }"
                        @mouseenter="$event.target.style.opacity = '.82'"
                        @mouseleave="$event.target.style.opacity = '1'"
                    >Entrar na comunidade</button>
                </div>
            </div>

                        <div style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 18px; border: 1px solid #4ebcff22; box-shadow: 0 4px 16px #009ac70a; padding: 18px; margin-bottom: 16px;">
                <p style="font-size: 11px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .08em; margin: 0 0 8px;">Modelo base da comunidade</p>
                <ul style="margin: 0; padding-left: 18px; color: #2a4a5a; font-size: 13px; line-height: 1.6;">
                    <li v-for="(rule, index) in community.guidelines" :key="index">{{ rule }}</li>
                </ul>
            </div>

            <!-- Post creation box -->
            <div
                v-if="authUser"
                style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 18px; border: 1px solid #4ebcff22; box-shadow: 0 4px 16px #009ac70a; padding: 20px; margin-bottom: 16px;"
            >
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <!-- Mini avatar -->
                    <div :style="{
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
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px;">
                            <span style="font-size: 11px; color: #b0c0cc;">{{ charCount }}/1000 · Ctrl+Enter para publicar</span>
                            <button
                                @click="submitPost"
                                :disabled="postForm.processing || !postForm.content.trim()"
                                :style="{
                                    padding: '8px 20px', borderRadius: '99px',
                                    background: community.color, border: 'none',
                                    color: 'white', fontSize: '12px', fontWeight: '700',
                                    cursor: postForm.content.trim() ? 'pointer' : 'not-allowed',
                                    opacity: (postForm.processing || !postForm.content.trim()) ? 0.5 : 1,
                                    boxShadow: `0 3px 12px ${community.color}44`,
                                    transition: 'opacity .2s',
                                }"
                            >Publicar</button>
                        </div>
                        <p v-if="postForm.errors.content" style="font-size: 11px; color: #e05555; margin: 6px 0 0;">{{ postForm.errors.content }}</p>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="posts.length === 0" style="text-align: center; padding: 60px 20px;">
                <p style="font-size: 32px; margin: 0 0 12px;">🫧</p>
                <p style="font-size: 14px; color: #8ba0b0;">Ainda não há posts nesta comunidade. Sê o primeiro!</p>
            </div>

            <!-- Posts feed -->
            <div v-else style="display: flex; flex-direction: column; gap: 12px;">
                <div
                    v-for="post in posts"
                    :key="post.id"
                    style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid #4ebcff1a; box-shadow: 0 2px 12px #009ac708; padding: 20px;"
                >
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <!-- Author avatar -->
                        <div :style="{
                            width: '38px', height: '38px', borderRadius: '50%', flexShrink: 0,
                            background: post.author.avatar_color,
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: '15px', fontWeight: '800', color: 'white',
                        }">{{ formatInitial(post.author.name) }}</div>

                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <div>
                                    <span style="font-size: 13px; font-weight: 700; color: #1a3a4a;">{{ post.author.name }}</span>
                                    <span v-if="post.author.username" style="font-size: 11px; color: #009ac7; margin-left: 5px;">@{{ post.author.username }}</span>
                                    <span style="font-size: 11px; color: #8ba0b0; margin-left: 8px;">{{ post.created_at }}</span>
                                </div>
                                <button
                                    v-if="post.isOwn"
                                    @click="deletePost(post.id)"
                                    style="background: none; border: none; cursor: pointer; color: #c0c8d0; font-size: 16px; padding: 2px 4px; border-radius: 6px; line-height: 1; transition: color .2s;"
                                    @mouseenter="$event.target.style.color = '#e05555'"
                                    @mouseleave="$event.target.style.color = '#c0c8d0'"
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

<style scoped>
textarea::placeholder { color: #b0c8d8; }
textarea:focus { outline: none; }
</style>
