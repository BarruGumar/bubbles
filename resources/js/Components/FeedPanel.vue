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
            :style="{
                position: 'absolute',
                left: isMobile ? '8px' : '16px',
                top: '70px',
                zIndex: 38,
                width: isMobile ? 'calc(100vw - 16px)' : '380px',
                height: 'calc(100vh - 86px)',
                background: 'var(--card-bg)',
                backdropFilter: 'blur(16px)',
                borderRadius: '18px',
                border: '1px solid var(--card-border)',
                boxShadow: '0 4px 20px #009ac70c',
                display: 'flex',
                flexDirection: 'column',
            }"
            @click.stop
            @mousedown.stop
        >
            <!-- Header -->
            <div
                style="
                    padding: 14px 16px 10px;
                    border-bottom: 1px solid var(--dropdown-sep);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    flex-shrink: 0;
                "
            >
                <p
                    style="
                        font-size: 10px;
                        font-weight: 800;
                        color: var(--text-3);
                        text-transform: uppercase;
                        letter-spacing: 0.1em;
                        margin: 0;
                    "
                >
                    Feed
                </p>
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
