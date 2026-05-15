<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PostCard from '@/Components/PostCard.vue';

defineProps({
    feed: { type: Array, default: () => [] },
    hasFriends: { type: Boolean, default: false },
    hasCommunities: { type: Boolean, default: false },
});

const authUser = computed(() => usePage().props.auth?.user);
</script>

<template>
    <Head title="Feed · bubbles" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px">
            <!-- Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px">
                <h1 style="font-size: 22px; font-weight: 900; color: #1a3a4a; margin: 0; letter-spacing: -0.02em">
                    Feed
                </h1>
                <span style="font-size: 12px; color: #8ba0b0">Posts de amigos e comunidades</span>
            </div>

            <!-- Empty — no friends or communities -->
            <div
                v-if="!hasFriends && !hasCommunities && feed.length === 0"
                style="
                    text-align: center;
                    padding: 60px 20px;
                    background: rgba(255, 255, 255, 0.88);
                    border-radius: 18px;
                    border: 1px solid #4ebcff1a;
                "
            >
                <p style="font-size: 36px; margin: 0 0 14px">🫧</p>
                <p style="font-size: 15px; font-weight: 700; color: #1a3a4a; margin: 0 0 6px">O teu feed está vazio</p>
                <p style="font-size: 13px; color: #8ba0b0; margin: 0">
                    Entra em comunidades e adiciona amigos para ver o conteúdo deles aqui.
                </p>
            </div>

            <!-- Hint when we show recent instead -->
            <div
                v-else-if="!hasFriends && !hasCommunities && feed.length > 0"
                style="
                    background: rgba(0, 154, 199, 0.06);
                    border: 1px solid #009ac722;
                    border-radius: 12px;
                    padding: 12px 16px;
                    margin-bottom: 16px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                "
            >
                <span style="font-size: 18px">💡</span>
                <p style="font-size: 12px; color: #5a7a8a; margin: 0; line-height: 1.5">
                    A mostrar posts recentes. Adiciona amigos e junta-te a comunidades para personalizar o teu feed!
                </p>
            </div>

            <!-- Feed -->
            <div v-if="feed.length > 0" style="display: flex; flex-direction: column; gap: 12px">
                <PostCard
                    v-for="item in feed"
                    :key="`${item._type}-${item.id}`"
                    :post="item"
                    :author="item.author"
                    :auth-user="authUser"
                    :can-edit="item.can_edit"
                    :can-delete="item.can_delete"
                    :like-route="item.like_route"
                    :comment-route="item.comment_route"
                    :delete-route="item.delete_route"
                    :edit-route="item.can_edit ? item.edit_route : null"
                    :report-route="
                        !item.can_edit && authUser
                            ? item._type === 'post'
                                ? route('posts.report', item.id)
                                : route('community-posts.report', item.id)
                            : null
                    "
                    :community="item.community ?? null"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
