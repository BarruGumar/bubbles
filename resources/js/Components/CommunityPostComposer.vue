<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';
import { useClipboardImage } from '@/Composables/useClipboardImage';
import { compressImage } from '@/Composables/useImageCompressor';
import { useAudio } from '@/Composables/useAudio';
import { useDirectUpload } from '@/Composables/useDirectUpload';

const props = defineProps({
    authUser: { type: Object, required: true },
    community: { type: Object, required: true },
});

const { show: toast } = useToast();
const { playSfx } = useAudio();
const { upload } = useDirectUpload();

const postForm = useForm({ content: '', image_url: null, image_public_id: null, video: null });
const pendingImageFile = ref(null);
const postUploading = ref(false);
const uploadProgress = ref(0);
const uploadingServer = ref(false);
const charCount = computed(() => postForm.content.length);

const mediaInput = ref(null);
const mediaPreview = ref(null);
const isVideoMedia = ref(false);

async function onMediaChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (mediaPreview.value) URL.revokeObjectURL(mediaPreview.value);
    if (file.type.startsWith('video/')) {
        pendingImageFile.value = null;
        postForm.video = file;
        isVideoMedia.value = true;
        mediaPreview.value = URL.createObjectURL(file);
    } else {
        postForm.video = null;
        isVideoMedia.value = false;
        mediaPreview.value = URL.createObjectURL(file);
        pendingImageFile.value = await compressImage(file);
    }
}

function removeMedia() {
    if (mediaPreview.value) URL.revokeObjectURL(mediaPreview.value);
    pendingImageFile.value = null;
    postForm.video = null;
    mediaPreview.value = null;
    isVideoMedia.value = false;
    if (mediaInput.value) mediaInput.value.value = '';
}

async function setMediaFile(file) {
    if (mediaPreview.value) URL.revokeObjectURL(mediaPreview.value);
    postForm.video = null;
    isVideoMedia.value = false;
    mediaPreview.value = URL.createObjectURL(file);
    pendingImageFile.value = await compressImage(file);
}

const { handlePaste: handlePostPaste } = useClipboardImage({
    onImage: setMediaFile,
    maxKB: 4096,
    onError: (msg) => toast(msg, 'error'),
});

async function submitPost() {
    if (!postForm.content.trim()) return;
    playSfx('send');
    uploadProgress.value = 0;
    uploadingServer.value = false;

    if (pendingImageFile.value) {
        postUploading.value = true;
        try {
            const { url, public_id } = await upload(pendingImageFile.value, 'community_post');
            postForm.image_url = url;
            postForm.image_public_id = public_id;
        } catch {
            postUploading.value = false;
            toast('Erro ao carregar imagem. Tenta novamente.', 'error');
            return;
        }
        postUploading.value = false;
    }

    postForm.post(route('community.posts.store', props.community.id), {
        forceFormData: true,
        preserveScroll: true,
        onProgress: (p) => {
            uploadProgress.value = p?.percentage ?? 0;
            if (uploadProgress.value >= 100) uploadingServer.value = true;
        },
        onSuccess: () => {
            if (usePage().props.flash?.error) return;
            postForm.reset('content', 'image_url', 'image_public_id', 'video');
            pendingImageFile.value = null;
            removeMedia();
            uploadProgress.value = 0;
            uploadingServer.value = false;
            toast('Publicação criada com sucesso.');
        },
        onError: () => {
            uploadProgress.value = 0;
            uploadingServer.value = false;
            toast('Erro ao publicar. Tenta novamente.', 'error');
        },
        onFinish: () => {
            uploadProgress.value = 0;
            uploadingServer.value = false;
        },
    });
}

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}

onUnmounted(() => {
    if (mediaPreview.value) URL.revokeObjectURL(mediaPreview.value);
});
</script>

<template>
    <div
        style="
            background: var(--card-bg, rgba(255, 255, 255, 0.88));
            backdrop-filter: blur(20px);
            border-radius: 18px;
            border: 1px solid var(--card-border, rgba(78, 188, 255, 0.22));
            box-shadow: 0 4px 16px rgba(0, 154, 199, 0.08);
            padding: 20px;
            margin-bottom: 16px;
        "
    >
        <div style="display: flex; gap: 14px; align-items: flex-start">
            <img
                v-if="authUser.avatar"
                :src="authUser.avatar"
                :style="{
                    width: '36px',
                    height: '36px',
                    borderRadius: '50%',
                    flexShrink: 0,
                    objectFit: 'cover',
                    border: `2px solid ${authUser.avatar_color ?? '#009ac7'}`,
                }"
            />
            <div
                v-else
                :style="{
                    width: '36px',
                    height: '36px',
                    borderRadius: '50%',
                    flexShrink: 0,
                    background: authUser.avatar_color ?? '#009ac7',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '14px',
                    fontWeight: '800',
                    color: 'white',
                }"
            >
                {{ formatInitial(authUser.name) }}
            </div>

            <div style="flex: 1">
                <textarea
                    v-model="postForm.content"
                    placeholder="Escreve algo para a comunidade..."
                    maxlength="1000"
                    rows="3"
                    style="
                        width: 100%;
                        background: var(--input-bg, #f0f8ff);
                        border: 1.5px solid var(--input-border, rgba(78, 188, 255, 0.20));
                        border-radius: 12px;
                        padding: 12px 14px;
                        font-size: 14px;
                        color: var(--input-text, #1a3a4a);
                        outline: none;
                        font-family: inherit;
                        resize: vertical;
                        transition: border-color 0.2s;
                        box-sizing: border-box;
                    "
                    @focus="$event.target.style.borderColor = community.color"
                    @blur="$event.target.style.borderColor = 'var(--input-border, rgba(78, 188, 255, 0.20))'"
                    @keydown.ctrl.enter="submitPost"
                    @paste="handlePostPaste"
                />

                <!-- Media preview -->
                <div v-if="mediaPreview" style="margin-top: 10px; position: relative; display: inline-block">
                    <video
                        v-if="isVideoMedia"
                        :src="mediaPreview"
                        style="
                            max-height: 200px;
                            max-width: 100%;
                            border-radius: 10px;
                            display: block;
                            border: 1px solid #4ebcff22;
                        "
                        controls
                        preload="metadata"
                    />
                    <img
                        v-else
                        :src="mediaPreview"
                        style="
                            max-height: 160px;
                            max-width: 100%;
                            border-radius: 10px;
                            object-fit: cover;
                            border: 1px solid #4ebcff22;
                        "
                    />
                    <button
                        @click="removeMedia"
                        style="
                            position: absolute;
                            top: 6px;
                            right: 6px;
                            background: rgba(0, 0, 0, 0.45);
                            border: none;
                            border-radius: 50%;
                            width: 22px;
                            height: 22px;
                            cursor: pointer;
                            color: white;
                            font-size: 14px;
                            line-height: 1;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        "
                    >
                        ×
                    </button>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px">
                    <div style="display: flex; align-items: center; gap: 8px">
                        <span style="font-size: 11px; color: var(--text-3, #b0c0cc)">{{ charCount }}/1000 · Ctrl+Enter</span>
                        <button
                            type="button"
                            @click="mediaInput.click()"
                            :style="{
                                background: 'none',
                                border: 'none',
                                cursor: 'pointer',
                                color: pendingImageFile || postForm.video ? community.color : 'var(--text-3, #b0c0cc)',
                                padding: '3px',
                                borderRadius: '6px',
                                transition: 'color .2s',
                                display: 'flex',
                                alignItems: 'center',
                            }"
                            title="Adicionar imagem ou vídeo"
                        >
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <rect
                                    x="1.5"
                                    y="2.5"
                                    width="13"
                                    height="11"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.3"
                                />
                                <circle cx="5.5" cy="6" r="1.2" fill="currentColor" />
                                <path
                                    d="M1.5 11l3.5-3 3 3 2-2 3.5 3.5"
                                    stroke="currentColor"
                                    stroke-width="1.3"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </button>
                        <input
                            ref="mediaInput"
                            type="file"
                            accept="image/*,video/mp4,video/webm,video/quicktime"
                            style="display: none"
                            @change="onMediaChange"
                        />
                    </div>
                    <button
                        @click="submitPost"
                        :disabled="postForm.processing || postUploading || !postForm.content.trim()"
                        :style="{
                            padding: '8px 20px',
                            borderRadius: '99px',
                            background: community.color,
                            border: 'none',
                            color: 'white',
                            fontSize: '12px',
                            fontWeight: '700',
                            cursor: postForm.content.trim() ? 'pointer' : 'not-allowed',
                            opacity: postForm.processing || postUploading || !postForm.content.trim() ? 0.5 : 1,
                            transition: 'opacity .2s',
                        }"
                    >
                        {{
                            postUploading
                                ? 'A carregar imagem...'
                                : postForm.processing
                                    ? uploadingServer
                                        ? 'A processar...'
                                        : `A carregar... ${uploadProgress}%`
                                    : 'Publicar'
                        }}
                    </button>
                </div>

                <!-- Video upload progress bar -->
                <div v-if="postForm.processing && postForm.video" style="margin-top: 8px">
                    <div style="height: 4px; background: var(--surface, #e8f4fb); border-radius: 99px; overflow: hidden">
                        <div
                            :style="{
                                width: uploadingServer ? '100%' : uploadProgress + '%',
                                height: '100%',
                                background: community.color,
                                borderRadius: '99px',
                                transition: uploadingServer ? 'none' : 'width .3s ease',
                            }"
                        />
                    </div>
                    <p style="font-size: 10px; color: var(--text-3, #8ba0b0); margin: 4px 0 0">
                        {{
                            uploadingServer
                                ? 'A guardar no servidor... pode demorar alguns segundos.'
                                : 'A enviar vídeo...'
                        }}
                    </p>
                </div>

                <p v-if="postForm.errors.content" style="font-size: 11px; color: #e05555; margin: 6px 0 0">
                    {{ postForm.errors.content }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
textarea::placeholder {
    color: var(--input-placeholder, #b0c8d8);
}
</style>
