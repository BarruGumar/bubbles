<script setup>
import { Link } from '@inertiajs/vue3';
import PostCard from '@/Components/PostCard.vue';
import { useAudio } from '@/Composables/useAudio';

const { playSfx } = useAudio();

defineProps({
    open: { type: Boolean, default: false },
    feed: { type: Array, default: () => [] },
    authUser: { type: Object, default: null },
    isMobile: { type: Boolean, default: false },
    hasMore: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['load-more']);
</script>

<template>
    <Transition name="slide-left">
        <div
            v-if="open"
            class="feed-panel"
            :style="{
                position: 'absolute',
                left: isMobile ? '8px' : '16px',
                top: '70px',
                zIndex: 38,
                width: isMobile ? 'calc(100vw - 16px)' : '380px',
                height: 'calc(100vh - 86px)',
                display: 'flex',
                flexDirection: 'column',
            }"
            @click.stop
            @mousedown.stop
        >
            <!-- Header -->
            <div
                class="feed-header"
                style="
                    padding: 14px 16px 10px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    flex-shrink: 0;
                "
            >
                <p class="feed-title">Feed</p>
                <Link
                    :href="route('feed.index')"
                    style="font-size: 11px; font-weight: 700; color: #009ac7; text-decoration: none"
                    @click="playSfx('verFeed')"
                    >Ver tudo →</Link
                >
            </div>

            <!-- Content -->
            <div style="padding: 10px 10px 20px; flex: 1; overflow-y: auto">
                <div v-if="feed.length === 0" style="text-align: center; padding: 40px 16px">
                    <p style="font-size: 28px; margin: 0 0 10px">🫧</p>
                    <p style="font-size: 13px; font-weight: 700; color: var(--text); margin: 0 0 6px">Feed vazio</p>
                    <p style="font-size: 11px; color: var(--text-3); margin: 0; line-height: 1.5">
                        Junta-te a comunidades e adiciona amigos para ver posts aqui.
                    </p>
                </div>
                <div v-else style="display: flex; flex-direction: column; gap: 8px">
                    <PostCard
                        v-for="item in feed"
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
                    <div style="text-align: center; padding: 8px 0 4px">
                        <button
                            v-if="hasMore"
                            :disabled="loading"
                            style="
                                background: rgba(0, 154, 199, 0.08);
                                border: 1.5px solid #009ac730;
                                border-radius: 16px;
                                padding: 7px 20px;
                                font-size: 12px;
                                font-weight: 700;
                                color: #009ac7;
                                cursor: pointer;
                                width: 100%;
                                transition: background 0.15s, opacity 0.15s;
                            "
                            :style="loading ? 'opacity: 0.6; cursor: default' : ''"
                            @click="emit('load-more')"
                        >
                            {{ loading ? 'A carregar…' : 'Carregar mais' }}
                        </button>
                        <p
                            v-else-if="feed.length > 0"
                            style="font-size: 11px; color: var(--text-3); margin: 0"
                        >
                            Fim do feed
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.feed-panel {
    background: rgba(255, 255, 255, .85);
    border: 1px solid rgba(255, 255, 255, .95);
    border-radius: 18px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: 0 8px 32px rgba(0, 154, 199, .1), inset 0 1px 0 rgba(255, 255, 255, .95);
}
html.dark .feed-panel {
    background: rgba(255, 255, 255, .06);
    border-top: 1px solid rgba(255, 255, 255, .3);
    border-left: 1px solid rgba(255, 255, 255, .15);
    border-right: 1px solid rgba(255, 255, 255, .06);
    border-bottom: 1px solid rgba(255, 255, 255, .04);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, .3), inset 0 1px 0 rgba(255, 255, 255, .2);
}

.feed-header {
    border-bottom: 1px solid rgba(0, 0, 0, .06);
}
html.dark .feed-header {
    border-bottom: 1px solid rgba(255, 255, 255, .06);
}

.feed-title {
    font-size: 10px;
    font-weight: 800;
    color: #009ac7;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin: 0;
}
html.dark .feed-title { color: #4ebcff; }

.slide-left-enter-active {
    transition:
        opacity 0.28s ease,
        transform 0.32s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.slide-left-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.24s ease;
}
.slide-left-enter-from {
    opacity: 0;
    transform: translateX(-20px);
}
.slide-left-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}
</style>
