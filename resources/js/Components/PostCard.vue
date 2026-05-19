<script setup>
import { ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

const emit = defineEmits(['deleted']);
import axios from 'axios';
import { clImg } from '@/Composables/useCloudinary';
import { useToast } from '@/Composables/useToast';
import PostImageLightbox from '@/Components/PostImageLightbox.vue';
import PostReactionBar from '@/Components/PostReactionBar.vue';
import PostComments from '@/Components/PostComments.vue';
import PostEditForm from '@/Components/PostEditForm.vue';
import PostReportForm from '@/Components/PostReportForm.vue';
import SiteOwnerBadge from '@/Components/SiteOwnerBadge.vue';

const props = defineProps({
    post: { type: Object, required: true },
    author: { type: Object, required: true },
    authUser: { type: Object, default: null },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    isCreator: { type: Boolean, default: false },
    likeRoute: { type: String, required: true },
    commentRoute: { type: String, required: true },
    deleteRoute: { type: String, required: true },
    editRoute: { type: String, default: null },
    reportRoute: { type: String, default: null },
    accentColor: { type: String, default: '#009ac7' },
    community: { type: Object, default: null },
});

const { show: toast } = useToast();

// ── Edit ──────────────────────────────────────────────────────────
const localContent = ref(props.post.content);
const editMode = ref(false);
const editContent = ref('');
const editLoading = ref(false);

watch(
    () => props.post.content,
    (v) => {
        if (!editMode.value) localContent.value = v;
    },
);

function startEdit() {
    editContent.value = localContent.value;
    editMode.value = true;
}
function cancelEdit() {
    editMode.value = false;
}

async function saveEdit() {
    const trimmed = editContent.value.trim();
    if (!trimmed || editLoading.value) return;
    if (trimmed === localContent.value) {
        cancelEdit();
        return;
    }
    editLoading.value = true;
    try {
        await axios.patch(props.editRoute, { content: trimmed });
        localContent.value = trimmed;
        editMode.value = false;
        toast('Post atualizado.', 'success');
    } catch {
        toast('Erro ao atualizar. Tenta novamente.', 'error');
    } finally {
        editLoading.value = false;
    }
}

// ── Delete ────────────────────────────────────────────────────────
const confirmDelete = ref(false);

async function doDelete() {
    emit('deleted', props.post.id);
    try {
        await axios.delete(props.deleteRoute);
    } catch {
        toast('Erro ao apagar. Tenta novamente.', 'error');
    }
}

// ── Report ────────────────────────────────────────────────────────
const showReport = ref(false);
const reportText = ref('');
const reportSending = ref(false);

async function submitReport() {
    const text = reportText.value.trim();
    if (!text || reportSending.value) return;
    reportSending.value = true;
    try {
        await axios.post(props.reportRoute, { reason: text });
        showReport.value = false;
        reportText.value = '';
        toast('Denúncia enviada.');
    } catch (e) {
        const msg = e?.response?.data?.errors?.reason?.[0]
            ?? e?.response?.data?.message
            ?? 'Erro ao enviar denúncia.';
        toast(msg, 'error');
    } finally {
        reportSending.value = false;
    }
}

function handleReportCancel() {
    showReport.value = false;
    reportText.value = '';
}

// ── Comments expanded ─────────────────────────────────────────────
const expandedComments = ref(false);

// ── Lightbox ──────────────────────────────────────────────────────
const lightboxOpen = ref(false);

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}
</script>

<template>
    <div
        style="
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid #4ebcff1a;
            box-shadow: 0 2px 12px #009ac708;
            padding: 20px;
        "
    >
        <!-- Community badge -->
        <Link
            v-if="community"
            :href="route('community.show', community.id)"
            style="display: inline-flex; align-items: center; gap: 6px; margin-bottom: 10px; text-decoration: none"
        >
            <div
                :style="{
                    width: '18px',
                    height: '18px',
                    borderRadius: '50%',
                    background: community.color,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '8px',
                    fontWeight: '800',
                    color: 'white',
                    flexShrink: '0',
                }"
            >
                {{ (community.label ?? '?')[0].toUpperCase() }}
            </div>
            <span :style="{ fontSize: '11px', fontWeight: '700', color: community.color }">{{ community.title }}</span>
        </Link>

        <div style="display: flex; gap: 14px; align-items: flex-start">
            <!-- Avatar -->
            <component
                :is="author.username ? Link : 'div'"
                :href="author.username ? route('profile.show', author.username) : undefined"
                style="flex-shrink: 0; display: block; text-decoration: none"
            >
                <img
                    v-if="author.avatar"
                    :src="clImg(author.avatar, 80, 80, 'fill', 'face')"
                    loading="lazy"
                    :style="{
                        width: '38px',
                        height: '38px',
                        borderRadius: '50%',
                        objectFit: 'cover',
                        display: 'block',
                        border: isCreator ? `2px solid ${accentColor}` : `2px solid ${author.avatar_color}`,
                        boxShadow: isCreator ? `0 0 0 3px ${accentColor}30, 0 2px 10px ${accentColor}50` : 'none',
                    }"
                />
                <div
                    v-else
                    :style="{
                        width: '38px',
                        height: '38px',
                        borderRadius: '50%',
                        flexShrink: 0,
                        background: author.avatar_color ?? '#009ac7',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontSize: '15px',
                        fontWeight: '800',
                        color: 'white',
                        border: isCreator ? `2px solid ${accentColor}` : 'none',
                    }"
                >
                    {{ formatInitial(author.name) }}
                </div>
            </component>

            <div style="flex: 1; min-width: 0">
                <!-- Header row -->
                <div
                    style="
                        display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        margin-bottom: 8px;
                        gap: 8px;
                    "
                >
                    <div style="display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px">
                        <component
                            :is="author.username ? Link : 'span'"
                            :href="author.username ? route('profile.show', author.username) : undefined"
                            :style="{
                                fontSize: '13px',
                                fontWeight: '800',
                                textDecoration: 'none',
                                color: isCreator ? accentColor : '#3a6478',
                            }"
                        >
                            <span v-if="isCreator" style="margin-right: 2px">✦</span>{{ author.name }}
                        </component>
                        <SiteOwnerBadge v-if="author.role === 'site_owner'" size="sm" />
                        <span
                            v-if="isCreator"
                            :style="{
                                display: 'inline-flex',
                                alignItems: 'center',
                                padding: '1px 7px',
                                borderRadius: '99px',
                                border: `1px solid ${accentColor}55`,
                                background: accentColor + '14',
                                color: accentColor,
                                fontSize: '9px',
                                fontWeight: '800',
                                textTransform: 'uppercase',
                                letterSpacing: '.04em',
                            }"
                            >Criador</span
                        >
                        <span
                            v-if="author.username"
                            :style="{ fontSize: '11px', color: isCreator ? accentColor + 'bb' : '#009ac7' }"
                            >@{{ author.username }}</span
                        >
                        <span style="font-size: 11px; color: #8ba0b0">{{ post.created_at }}</span>
                        <span
                            v-if="localContent !== post.content"
                            style="font-size: 10px; color: #b0c0cc; font-style: italic"
                            >(editado)</span
                        >
                    </div>

                    <!-- Actions menu -->
                    <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0">
                        <button
                            v-if="canEdit && !editMode && !confirmDelete"
                            @click="startEdit"
                            style="
                                background: none;
                                border: none;
                                cursor: pointer;
                                color: #b0c0cc;
                                font-size: 12px;
                                padding: 3px 7px;
                                border-radius: 6px;
                                transition: all 0.2s;
                                font-weight: 600;
                            "
                            @mouseenter="
                                $event.currentTarget.style.color = '#009ac7';
                                $event.currentTarget.style.background = '#f0f8ff';
                            "
                            @mouseleave="
                                $event.currentTarget.style.color = '#b0c0cc';
                                $event.currentTarget.style.background = 'transparent';
                            "
                            title="Editar post"
                        >
                            ✎
                        </button>

                        <button
                            v-if="!canEdit && authUser && reportRoute && !confirmDelete && !showReport"
                            @click="showReport = !showReport"
                            style="
                                background: none;
                                border: none;
                                cursor: pointer;
                                color: #c0c8d0;
                                font-size: 12px;
                                padding: 3px 7px;
                                border-radius: 6px;
                                transition: all 0.2s;
                            "
                            @mouseenter="
                                $event.currentTarget.style.color = '#e05555';
                                $event.currentTarget.style.background = '#fff0f0';
                            "
                            @mouseleave="
                                $event.currentTarget.style.color = '#c0c8d0';
                                $event.currentTarget.style.background = 'transparent';
                            "
                            title="Denunciar"
                        >
                            ⚑
                        </button>

                        <template v-if="canDelete && confirmDelete">
                            <span style="font-size: 11px; color: #e05555; font-weight: 600; white-space: nowrap"
                                >Tens a certeza?</span
                            >
                            <button
                                @click="confirmDelete = false"
                                style="
                                    padding: 3px 9px;
                                    border-radius: 6px;
                                    border: 1px solid #dde8f0;
                                    background: #f0f8ff;
                                    color: #5a7a8a;
                                    font-size: 11px;
                                    font-weight: 600;
                                    cursor: pointer;
                                "
                            >
                                Não
                            </button>
                            <button
                                @click="doDelete"
                                style="
                                    padding: 3px 9px;
                                    border-radius: 6px;
                                    border: none;
                                    background: #e05555;
                                    color: white;
                                    font-size: 11px;
                                    font-weight: 700;
                                    cursor: pointer;
                                "
                            >
                                Apagar
                            </button>
                        </template>

                        <button
                            v-else-if="canDelete && !editMode"
                            @click="confirmDelete = true"
                            style="
                                background: none;
                                border: none;
                                cursor: pointer;
                                color: #c0c8d0;
                                font-size: 16px;
                                padding: 2px 4px;
                                border-radius: 6px;
                                line-height: 1;
                                transition: color 0.2s;
                            "
                            @mouseenter="$event.target.style.color = '#e05555'"
                            @mouseleave="$event.target.style.color = '#c0c8d0'"
                            title="Apagar post"
                        >
                            ×
                        </button>
                    </div>
                </div>

                <!-- Edit form -->
                <PostEditForm
                    v-if="editMode"
                    v-model:content="editContent"
                    :loading="editLoading"
                    @save="saveEdit"
                    @cancel="cancelEdit"
                />

                <!-- Post content -->
                <p v-else style="font-size: 14px; color: #2a4a5a; line-height: 1.65; margin: 0; white-space: pre-wrap">
                    {{ localContent }}
                </p>

                <!-- Image -->
                <img
                    v-if="post.image"
                    :src="clImg(post.image, 800, 0, 'limit')"
                    loading="lazy"
                    style="
                        margin-top: 12px;
                        max-width: 100%;
                        border-radius: 12px;
                        object-fit: cover;
                        max-height: 400px;
                        display: block;
                        border: 1px solid #4ebcff1a;
                        cursor: zoom-in;
                    "
                    @click.stop="lightboxOpen = true"
                    title="Clica para ampliar"
                />

                <!-- Video -->
                <video
                    v-if="post.video"
                    :src="post.video"
                    controls
                    preload="metadata"
                    style="
                        margin-top: 12px;
                        max-width: 100%;
                        border-radius: 12px;
                        display: block;
                        border: 1px solid #4ebcff1a;
                        max-height: 480px;
                        background: #000;
                        outline: none;
                    "
                />

                <!-- Report form -->
                <PostReportForm
                    v-if="showReport"
                    v-model:text="reportText"
                    :sending="reportSending"
                    @submit="submitReport"
                    @cancel="handleReportCancel"
                />
            </div>
        </div>

        <PostReactionBar
            :post="post"
            :auth-user="authUser"
            :like-route="likeRoute"
            :accent-color="accentColor"
            :comments-expanded="expandedComments"
            @toggle-comments="expandedComments = !expandedComments"
        />

        <PostComments
            v-if="expandedComments"
            :comments="post.comments"
            :auth-user="authUser"
            :comment-route="commentRoute"
            :accent-color="accentColor"
        />
    </div>

    <PostImageLightbox v-if="post.image" :image-url="post.image" :open="lightboxOpen" @close="lightboxOpen = false" />
</template>
