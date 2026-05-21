<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import ReactorsModal from '@/Components/ReactorsModal.vue';
import { useAudio } from '@/Composables/useAudio';

const props = defineProps({
    post: { type: Object, required: true },
    authUser: { type: Object, default: null },
    likeRoute: { type: String, required: true },
    reactorsRoute: { type: String, default: null },
    accentColor: { type: String, default: '#009ac7' },
    commentsExpanded: { type: Boolean, default: false },
});
const emit = defineEmits(['toggle-comments']);

const reactorsModal = ref(null);
const { playClick } = useAudio();

function openReactorsModal() {
    if (!props.reactorsRoute || localLikeCount.value === 0) return;
    reactorsModal.value?.open();
}

const REACTIONS = [
    { type: 'like', emoji: '👍', label: 'Curtir' },
    { type: 'love', emoji: '❤️', label: 'Adorar' },
    { type: 'laugh', emoji: '😂', label: 'Haha' },
    { type: 'wow', emoji: '😮', label: 'Uau' },
    { type: 'sad', emoji: '😢', label: 'Triste' },
];

const localUserReaction = ref(props.post.user_reaction ?? null);
const localLikeCount = ref(props.post.likes_count ?? 0);
const likeLoading = ref(false);
const localIsLiked = computed(() => localUserReaction.value !== null);
const showReactionPicker = ref(false);
let hoverTimer = null;
let hideTimer = null;

watch(
    () => props.post.user_reaction,
    (v) => {
        if (!likeLoading.value) localUserReaction.value = v ?? null;
    },
);
watch(
    () => props.post.likes_count,
    (v) => {
        if (!likeLoading.value) localLikeCount.value = v ?? 0;
    },
);

function currentEmoji() {
    return REACTIONS.find((r) => r.type === localUserReaction.value)?.emoji ?? null;
}

function onLikeHoverStart() {
    if (!props.authUser) return;
    clearTimeout(hideTimer);
    if (!showReactionPicker.value)
        hoverTimer = setTimeout(() => {
            showReactionPicker.value = true;
        }, 450);
}

function onLikeHoverEnd() {
    clearTimeout(hoverTimer);
}

function scheduleHide() {
    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => {
        showReactionPicker.value = false;
    }, 220);
}

function cancelHide() {
    clearTimeout(hideTimer);
}

function setReaction(type) {
    if (!props.authUser || likeLoading.value) return;
    showReactionPicker.value = false;
    playClick();

    const prevReaction = localUserReaction.value;
    const isSame = prevReaction === type;
    const hadReaction = prevReaction !== null;

    if (isSame) {
        localUserReaction.value = null;
        localLikeCount.value = Math.max(0, localLikeCount.value - 1);
    } else if (!hadReaction) {
        localUserReaction.value = type;
        localLikeCount.value += 1;
    } else {
        localUserReaction.value = type;
    }

    likeLoading.value = true;
    router.post(
        props.likeRoute,
        { type },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                likeLoading.value = false;
            },
            onError: () => {
                localUserReaction.value = prevReaction;
                localLikeCount.value = props.post.likes_count;
            },
        },
    );
}

function toggleLike() {
    setReaction(localUserReaction.value ?? 'like');
}
</script>

<template>
    <div style="display: flex; margin-top: 12px; border-top: 1px solid rgba(0, 154, 199, 0.08); padding-top: 2px">
        <!-- Like / Reaction button -->
        <div style="flex: 1; position: relative" @mouseleave="scheduleHide">
            <Transition name="picker">
                <div
                    v-if="showReactionPicker"
                    @mouseenter="cancelHide"
                    @mouseleave="scheduleHide"
                    style="
                        position: absolute;
                        bottom: calc(100% + 8px);
                        left: 50%;
                        transform: translateX(-50%);
                        background: white;
                        border-radius: 99px;
                        border: 1px solid #eef2f8;
                        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.13);
                        display: flex;
                        gap: 2px;
                        padding: 5px 8px;
                        z-index: 50;
                    "
                >
                    <button
                        v-for="r in REACTIONS"
                        :key="r.type"
                        @click.stop="setReaction(r.type)"
                        :title="r.label"
                        :style="{
                            background: localUserReaction === r.type ? '#f0f8ff' : 'none',
                            border: 'none',
                            cursor: 'pointer',
                            padding: '5px 7px',
                            borderRadius: '8px',
                            fontSize: '20px',
                            lineHeight: '1',
                            transition: 'transform .15s',
                        }"
                        @mouseenter="$event.currentTarget.style.transform = 'scale(1.4)'"
                        @mouseleave="$event.currentTarget.style.transform = 'scale(1)'"
                    >
                        {{ r.emoji }}
                    </button>
                </div>
            </Transition>

            <button
                @click="toggleLike"
                @mouseenter="onLikeHoverStart"
                @mouseleave="onLikeHoverEnd"
                :style="{
                    width: '100%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '6px',
                    padding: '8px 0',
                    background: 'none',
                    border: 'none',
                    cursor: authUser ? 'pointer' : 'default',
                    fontSize: '13px',
                    fontWeight: '600',
                    borderRadius: '8px',
                    transition: 'all .2s',
                    color: localIsLiked ? '#e05f7a' : '#8ba0b0',
                }"
                @mouseenter.stop="authUser && ($event.currentTarget.style.background = 'rgba(224,95,122,0.07)')"
                @mouseleave.stop="$event.currentTarget.style.background = 'transparent'"
            >
                <span v-if="localIsLiked && currentEmoji()" style="font-size: 16px; line-height: 1">{{
                    currentEmoji()
                }}</span>
                <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none">
                    <path
                        :fill="localIsLiked ? '#e05f7a' : 'none'"
                        :stroke="localIsLiked ? '#e05f7a' : 'currentColor'"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                    />
                </svg>
                <span
                    v-if="reactorsRoute && localLikeCount > 0"
                    @click.stop="openReactorsModal"
                    :style="{
                        textDecoration: 'underline',
                        textUnderlineOffset: '2px',
                        cursor: 'pointer',
                    }"
                    >{{ localLikeCount }} {{ localLikeCount === 1 ? 'Reação' : 'Reações' }}</span
                >
                <span v-else>{{ localLikeCount }} {{ localLikeCount === 1 ? 'Reação' : 'Reações' }}</span>
            </button>

            <ReactorsModal v-if="reactorsRoute" ref="reactorsModal" :reactors-route="reactorsRoute" />
        </div>

        <div style="width: 1px; background: rgba(0, 154, 199, 0.08); margin: 6px 0" />

        <!-- Comment toggle button -->
        <button
            @click="emit('toggle-comments')"
            :style="{
                flex: 1,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '6px',
                padding: '8px 0',
                background: 'none',
                border: 'none',
                cursor: 'pointer',
                fontSize: '13px',
                fontWeight: '600',
                borderRadius: '8px',
                transition: 'all .2s',
                color: commentsExpanded ? accentColor : '#8ba0b0',
            }"
            @mouseenter="$event.currentTarget.style.background = 'rgba(0,154,199,0.07)'"
            @mouseleave="$event.currentTarget.style.background = 'transparent'"
        >
            <svg
                width="15"
                height="15"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            {{ post.comments.length }} {{ post.comments.length === 1 ? 'Comentário' : 'Comentários' }}
        </button>
    </div>
</template>

<style scoped>
.picker-enter-active {
    transition: all 0.18s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.picker-leave-active {
    transition: all 0.14s ease;
}
.picker-enter-from {
    opacity: 0;
    transform: translateX(-50%) translateY(6px) scale(0.92);
}
.picker-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(4px) scale(0.95);
}
</style>
