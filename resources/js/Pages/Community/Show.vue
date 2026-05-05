<script setup>
import { computed, ref } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import BaseCommunityPage from '@/Components/BaseCommunityPage.vue'

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

</script>

<template>
    <Head :title="`${community.title} · bubbles`" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px;">

            <BaseCommunityPage :community="community" :posts-count="posts.length" />

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
