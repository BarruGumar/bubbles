<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    comments: { type: Array, required: true },
    authUser: { type: Object, default: null },
    commentRoute: { type: String, required: true },
    accentColor: { type: String, default: '#009ac7' },
});

const commentText = ref('');

function submitComment() {
    const text = commentText.value.trim();
    if (!text) return;
    router.post(
        props.commentRoute,
        { content: text },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                commentText.value = '';
            },
        },
    );
}

function deleteComment(commentId) {
    router.delete(route('comments.destroy', commentId), {
        preserveScroll: true,
        preserveState: true,
    });
}

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}
</script>

<template>
    <div style="margin-top: 12px; border-top: 1px solid rgba(0, 154, 199, 0.06); padding-top: 12px">
        <div
            v-for="c in comments"
            :key="c.id"
            style="display: flex; gap: 8px; margin-bottom: 10px; align-items: flex-start"
        >
            <img
                v-if="c.author.avatar"
                :src="c.author.avatar"
                :style="{
                    width: '28px',
                    height: '28px',
                    borderRadius: '50%',
                    objectFit: 'cover',
                    border: `1.5px solid ${c.author.avatar_color}`,
                    flexShrink: 0,
                }"
            />
            <div
                v-else
                :style="{
                    width: '28px',
                    height: '28px',
                    borderRadius: '50%',
                    flexShrink: 0,
                    background: c.author.avatar_color ?? '#009ac7',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '11px',
                    fontWeight: '800',
                    color: 'white',
                }"
            >
                {{ formatInitial(c.author.name) }}
            </div>

            <div style="flex: 1; min-width: 0">
                <div style="background: rgba(240, 248, 255, 0.8); border-radius: 12px; padding: 8px 12px">
                    <Link
                        v-if="c.author.username"
                        :href="route('profile.show', c.author.username)"
                        style="font-size: 12px; font-weight: 700; color: #1a3a4a; text-decoration: none"
                        >{{ c.author.name }}</Link
                    >
                    <span v-else style="font-size: 12px; font-weight: 700; color: #1a3a4a">{{ c.author.name }}</span>
                    <p
                        style="
                            font-size: 13px;
                            color: #2a4a5a;
                            margin: 2px 0 0;
                            line-height: 1.5;
                            white-space: pre-wrap;
                        "
                    >
                        {{ c.content }}
                    </p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; margin-top: 3px; padding-left: 10px">
                    <span style="font-size: 11px; color: #b0c0cc">{{ c.created_at }}</span>
                    <button
                        v-if="c.is_own"
                        @click="deleteComment(c.id)"
                        style="
                            font-size: 11px;
                            color: #c0c8d0;
                            background: none;
                            border: none;
                            cursor: pointer;
                            padding: 0;
                            transition: color 0.2s;
                        "
                        @mouseenter="$event.target.style.color = '#e05555'"
                        @mouseleave="$event.target.style.color = '#c0c8d0'"
                    >
                        Apagar
                    </button>
                </div>
            </div>
        </div>

        <p
            v-if="comments.length === 0"
            style="font-size: 12px; color: #b0c0cc; text-align: center; padding: 2px 0 10px; font-style: italic"
        >
            Sê o primeiro a comentar!
        </p>

        <div v-if="authUser" style="display: flex; gap: 8px; align-items: center; margin-top: 4px">
            <img
                v-if="authUser.avatar"
                :src="authUser.avatar"
                :style="{
                    width: '28px',
                    height: '28px',
                    borderRadius: '50%',
                    objectFit: 'cover',
                    border: `1.5px solid ${authUser.avatar_color ?? '#009ac7'}`,
                    flexShrink: 0,
                }"
            />
            <div
                v-else
                :style="{
                    width: '28px',
                    height: '28px',
                    borderRadius: '50%',
                    flexShrink: 0,
                    background: authUser.avatar_color ?? '#009ac7',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '11px',
                    fontWeight: '800',
                    color: 'white',
                }"
            >
                {{ formatInitial(authUser.name) }}
            </div>

            <div style="flex: 1; display: flex; gap: 6px">
                <input
                    v-model="commentText"
                    @keydown.enter.prevent="submitComment"
                    placeholder="Escreve um comentário..."
                    style="
                        flex: 1;
                        min-width: 0;
                        background: rgba(240, 248, 255, 0.8);
                        border: 1.5px solid rgba(0, 154, 199, 0.15);
                        border-radius: 20px;
                        padding: 7px 14px;
                        font-size: 13px;
                        color: #1a3a4a;
                        outline: none;
                        font-family: inherit;
                        transition: border-color 0.2s;
                    "
                    @focus="$event.target.style.borderColor = accentColor"
                    @blur="$event.target.style.borderColor = 'rgba(0,154,199,0.15)'"
                />
                <button
                    @click="submitComment"
                    :style="{
                        padding: '7px 14px',
                        background: accentColor,
                        color: 'white',
                        border: 'none',
                        borderRadius: '20px',
                        fontSize: '12px',
                        fontWeight: '700',
                        cursor: 'pointer',
                        whiteSpace: 'nowrap',
                        flexShrink: 0,
                        transition: 'opacity .2s',
                    }"
                    @mouseenter="$event.target.style.opacity = '.8'"
                    @mouseleave="$event.target.style.opacity = '1'"
                >
                    Enviar
                </button>
            </div>
        </div>
    </div>
</template>
