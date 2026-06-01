<script setup>
import { ref, watch, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PostCard from '@/Components/PostCard.vue';

const props = defineProps({
    feed: { type: Array, default: () => [] },
    hasFriends: { type: Boolean, default: false },
    hasCommunities: { type: Boolean, default: false },
    hasMore: { type: Boolean, default: false },
    nextCursor: { type: Number, default: null },
});

const authUser = computed(() => usePage().props.auth?.user);

const items = ref([...props.feed]);
const cursor = ref(props.nextCursor);
const moreAvailable = ref(props.hasMore);
const loading = ref(false);

watch(
    () => props.feed,
    (fresh) => {
        items.value = [...fresh];
        cursor.value = props.nextCursor;
        moreAvailable.value = props.hasMore;
    },
);

function loadMore() {
    if (loading.value || !moreAvailable.value) return;
    loading.value = true;

    router.get(
        route('feed.index'),
        { cursor: cursor.value },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['feed', 'hasMore', 'nextCursor'],
            onSuccess: (page) => {
                items.value.push(...page.props.feed);
                cursor.value = page.props.nextCursor;
                moreAvailable.value = page.props.hasMore;
            },
            onFinish: () => { loading.value = false; },
        },
    );
}
</script>

<template>
    <Head title="Feed · bubbles" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px">
            <!-- Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px">
                <h1 style="font-size: 22px; font-weight: 900; color: #3a6478; margin: 0; letter-spacing: -0.02em">
                    Feed
                </h1>
                <span style="font-size: 12px; color: #8ba0b0">Posts de amigos e comunidades</span>
            </div>

            <!-- Empty — no friends or communities -->
            <div
                v-if="!hasFriends && !hasCommunities && items.length === 0"
                style="
                    text-align: center;
                    padding: 60px 20px;
                    background: rgba(255, 255, 255, 0.88);
                    border-radius: 18px;
                    border: 1px solid #4ebcff1a;
                "
            >
                <p style="font-size: 36px; margin: 0 0 14px">🫧</p>
                <p style="font-size: 15px; font-weight: 700; color: #3a6478; margin: 0 0 6px">O teu feed está vazio</p>
                <p style="font-size: 13px; color: #8ba0b0; margin: 0">
                    Entra em comunidades e adiciona amigos para ver o conteúdo deles aqui.
                </p>
            </div>

            <!-- Hint when we show recent instead -->
            <div
                v-else-if="!hasFriends && !hasCommunities && items.length > 0"
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
            <div v-if="items.length > 0" style="display: flex; flex-direction: column; gap: 12px">
                <PostCard
                    v-for="item in items"
                    :key="`${item._type}-${item.id}`"
                    :post="item"
                    :author="item.author"
                    :auth-user="authUser"
                    :can-edit="item.can_edit"
                    :can-delete="item.can_delete"
                    :like-route="item.like_route"
                    :reactors-route="item._type === 'post' ? route('posts.reactors', item.id) : route('community-posts.reactors', item.id)"
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

                <!-- Load more -->
                <div style="text-align: center; padding: 16px 0 0">
                    <button
                        v-if="moreAvailable"
                        :disabled="loading"
                        style="
                            background: rgba(0, 154, 199, 0.08);
                            border: 1.5px solid #009ac730;
                            border-radius: 20px;
                            padding: 10px 28px;
                            font-size: 13px;
                            font-weight: 700;
                            color: #009ac7;
                            cursor: pointer;
                            transition: background 0.15s, opacity 0.15s;
                        "
                        :style="loading ? 'opacity: 0.6; cursor: default' : ''"
                        @click="loadMore"
                    >
                        {{ loading ? 'A carregar…' : 'Carregar mais' }}
                    </button>
                    <p
                        v-else
                        style="font-size: 12px; color: #8ba0b0; margin: 0"
                    >
                        Chegaste ao fim do feed
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
