<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImageCropper from '@/Components/ImageCropper.vue';
import PostCard from '@/Components/PostCard.vue';
import PostCardSkeleton from '@/Components/PostCardSkeleton.vue';
import CommunityHeader from '@/Components/CommunityHeader.vue';
import CommunityPostComposer from '@/Components/CommunityPostComposer.vue';
import CommunitySettingsModal from '@/Components/CommunitySettingsModal.vue';
import { clImg } from '@/Composables/useCloudinary';

const props = defineProps({
    community: Object,
    posts: Array,
    nextCursor: String,
    hasMorePosts: Boolean,
    isOwn: Boolean,
    isMember: Boolean,
});

const authUser = computed(() => usePage().props.auth?.user);

// ── Pagination ────────────────────────────────────────────────────
const localPosts = ref([...props.posts]);
const currentCursor = ref(props.nextCursor);
const hasMore = ref(props.hasMorePosts);
const loadingMore = ref(false);

watch(
    () => props.posts,
    (newPosts) => {
        if (loadingMore.value) return;
        localPosts.value = [...newPosts];
        currentCursor.value = props.nextCursor;
        hasMore.value = props.hasMorePosts;
    },
);

function loadMore() {
    if (!hasMore.value || loadingMore.value) return;
    loadingMore.value = true;
    router.reload({
        data: { cursor: currentCursor.value },
        only: ['posts', 'nextCursor', 'hasMorePosts'],
        preserveScroll: true,
        onSuccess: () => {
            localPosts.value = [...localPosts.value, ...props.posts];
            currentCursor.value = props.nextCursor;
            hasMore.value = props.hasMorePosts;
            loadingMore.value = false;
        },
        onError: () => {
            loadingMore.value = false;
        },
    });
}

// ── Community image / banner (shared state for hero + modal) ──────
const communityImagePreview = ref(props.community.image ?? null);
const communityBannerPreview = ref(props.community.banner ?? null);
const communityImageForm = useForm({ image: null });
const communityBannerForm = useForm({ banner: null });

const cropperSrc = ref(null);
const cropperMode = ref(null); // 'image' | 'banner'

function openCropperWithFile(file, mode) {
    if (cropperSrc.value) URL.revokeObjectURL(cropperSrc.value);
    cropperSrc.value = URL.createObjectURL(file);
    cropperMode.value = mode;
}

function onCropConfirm(blob) {
    const isImage = cropperMode.value === 'image';
    const ext = blob.type === 'image/png' ? 'png' : 'jpg';
    const filename = isImage ? `community_image.${ext}` : `community_banner.${ext}`;
    const file = new File([blob], filename, { type: blob.type });
    const blobUrl = URL.createObjectURL(blob);

    if (isImage) {
        if (communityImagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(communityImagePreview.value);
        communityImagePreview.value = blobUrl;
        communityImageForm.image = file;
        communityImageForm.post(route('community.image', props.community.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => communityImageForm.reset(),
        });
    } else {
        if (communityBannerPreview.value?.startsWith('blob:')) URL.revokeObjectURL(communityBannerPreview.value);
        communityBannerPreview.value = blobUrl;
        communityBannerForm.banner = file;
        communityBannerForm.post(route('community.banner', props.community.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => communityBannerForm.reset(),
        });
    }

    if (cropperSrc.value) URL.revokeObjectURL(cropperSrc.value);
    cropperSrc.value = null;
    cropperMode.value = null;
}

function onCropCancel() {
    if (cropperSrc.value) URL.revokeObjectURL(cropperSrc.value);
    cropperSrc.value = null;
    cropperMode.value = null;
}

onUnmounted(() => {
    if (communityImagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(communityImagePreview.value);
    if (communityBannerPreview.value?.startsWith('blob:')) URL.revokeObjectURL(communityBannerPreview.value);
    if (cropperSrc.value) URL.revokeObjectURL(cropperSrc.value);
});

// ── Settings modal ────────────────────────────────────────────────
const showEdit = ref(false);

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}
</script>

<template>
    <Head :title="`${community.title} · bubbles`" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px">
            <CommunityHeader
                :community="community"
                :is-own="isOwn"
                :is-member="isMember"
                :auth-user="authUser"
                :image-preview="communityImagePreview"
                :banner-preview="communityBannerPreview"
                @open-edit="showEdit = true"
            />

            <!-- Member avatars -->
            <div
                v-if="community.member_avatars && community.member_avatars.length"
                style="
                    background: rgba(255, 255, 255, 0.88);
                    backdrop-filter: blur(20px);
                    border-radius: 16px;
                    border: 1px solid #4ebcff1a;
                    box-shadow: 0 2px 12px #009ac708;
                    padding: 16px 22px;
                    margin-bottom: 16px;
                "
            >
                <p
                    style="
                        font-size: 10px;
                        font-weight: 800;
                        color: #8ba0b0;
                        text-transform: uppercase;
                        letter-spacing: 0.1em;
                        margin: 0 0 12px;
                    "
                >
                    Membros
                </p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap">
                    <component
                        :is="member.username ? Link : 'div'"
                        v-for="member in community.member_avatars"
                        :key="member.username ?? member.name"
                        :href="member.username ? route('profile.show', member.username) : undefined"
                        style="
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            gap: 5px;
                            text-decoration: none;
                        "
                        :title="member.name"
                    >
                        <img
                            v-if="member.avatar"
                            :src="clImg(member.avatar, 80, 80, 'fill', 'face')"
                            loading="lazy"
                            :style="{
                                width: '38px',
                                height: '38px',
                                borderRadius: '50%',
                                objectFit: 'cover',
                                border: `2px solid ${member.avatar_color}`,
                                boxShadow: `0 2px 8px ${member.avatar_color}44`,
                                transition: 'transform .2s',
                            }"
                            @mouseenter="$event.target.style.transform = 'scale(1.08)'"
                            @mouseleave="$event.target.style.transform = 'scale(1)'"
                        />
                        <div
                            v-else
                            :style="{
                                width: '38px',
                                height: '38px',
                                borderRadius: '50%',
                                background: member.avatar_color,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontSize: '15px',
                                fontWeight: '800',
                                color: 'white',
                                boxShadow: `0 2px 8px ${member.avatar_color}44`,
                                transition: 'transform .2s',
                            }"
                            @mouseenter="$event.currentTarget.style.transform = 'scale(1.08)'"
                            @mouseleave="$event.currentTarget.style.transform = 'scale(1)'"
                        >
                            {{ formatInitial(member.name) }}
                        </div>
                        <span
                            style="
                                font-size: 10px;
                                color: #8ba0b0;
                                font-weight: 600;
                                max-width: 48px;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                white-space: nowrap;
                            "
                        >
                            {{ member.username ? '@' + member.username : member.name }}
                        </span>
                    </component>
                </div>
            </div>

            <!-- Community guidelines -->
            <div
                v-if="community.guidelines && community.guidelines.length"
                style="
                    background: rgba(255, 255, 255, 0.88);
                    backdrop-filter: blur(20px);
                    border-radius: 16px;
                    border: 1px solid #4ebcff1a;
                    box-shadow: 0 2px 12px #009ac708;
                    padding: 18px 22px;
                    margin-bottom: 16px;
                "
            >
                <p
                    style="
                        font-size: 10px;
                        font-weight: 800;
                        color: #8ba0b0;
                        text-transform: uppercase;
                        letter-spacing: 0.1em;
                        margin: 0 0 10px;
                    "
                >
                    Regras
                </p>
                <ol style="margin: 0; padding-left: 18px; color: #2a4a5a; font-size: 13px; line-height: 1.8">
                    <li v-for="(rule, i) in community.guidelines" :key="i">{{ rule }}</li>
                </ol>
            </div>

            <!-- Post composer -->
            <CommunityPostComposer v-if="authUser" :auth-user="authUser" :community="community" />

            <!-- Empty state -->
            <div v-if="localPosts.length === 0" style="text-align: center; padding: 60px 20px">
                <p style="font-size: 32px; margin: 0 0 12px">🫧</p>
                <p style="font-size: 14px; color: #8ba0b0">Ainda não há posts. Sê o primeiro!</p>
            </div>

            <!-- Post feed -->
            <div v-else style="display: flex; flex-direction: column; gap: 12px">
                <PostCard
                    v-for="post in localPosts"
                    :key="post.id"
                    :post="post"
                    :author="post.author"
                    :auth-user="authUser"
                    :can-edit="post.isOwn"
                    :can-delete="post.isOwn || isOwn"
                    :is-creator="post.isCreator"
                    :accent-color="community.color"
                    :like-route="route('community-posts.like', post.id)"
                    :comment-route="route('community-posts.comments.store', post.id)"
                    :delete-route="route('community.posts.destroy', [community.id, post.id])"
                    :edit-route="post.isOwn ? route('community.posts.update', [community.id, post.id]) : null"
                    :report-route="!post.isOwn && authUser ? route('community-posts.report', post.id) : null"
                />
            </div>

            <!-- Skeleton while loading more -->
            <div v-if="loadingMore" style="display: flex; flex-direction: column; gap: 12px; margin-top: 12px">
                <PostCardSkeleton v-for="n in 3" :key="n" />
            </div>

            <!-- Load more -->
            <div v-if="hasMore && !loadingMore" style="text-align: center; margin-top: 8px">
                <button
                    @click="loadMore"
                    style="
                        padding: 10px 28px;
                        border-radius: 99px;
                        border: 1.5px solid #4ebcff44;
                        background: rgba(255, 255, 255, 0.85);
                        color: #009ac7;
                        font-size: 13px;
                        font-weight: 700;
                        cursor: pointer;
                        transition: all 0.2s;
                        backdrop-filter: blur(10px);
                    "
                    @mouseenter="$event.currentTarget.style.background = '#e8f7ff'"
                    @mouseleave="$event.currentTarget.style.background = 'rgba(255,255,255,0.85)'"
                >
                    Carregar mais
                </button>
            </div>
        </div>

        <CommunitySettingsModal
            v-model="showEdit"
            :community="community"
            :community-image-preview="communityImagePreview"
            :community-banner-preview="communityBannerPreview"
            @image-file-selected="openCropperWithFile($event, 'image')"
            @banner-file-selected="openCropperWithFile($event, 'banner')"
        />
    </AuthenticatedLayout>

    <Teleport to="body">
        <ImageCropper
            v-if="cropperSrc"
            :src="cropperSrc"
            :aspect-ratio="cropperMode === 'banner' ? 3 : 1"
            :circle="cropperMode === 'image'"
            :output-width="cropperMode === 'banner' ? 1400 : 300"
            :output-height="cropperMode === 'banner' ? 500 : 300"
            @confirm="onCropConfirm"
            @cancel="onCropCancel"
        />
    </Teleport>
</template>

<style scoped>
/* Badge "Criador" nos posts */
.creator-badge {
    display: inline-flex;
    align-items: center;
    padding: 1px 7px;
    border-radius: 99px;
    border: 1px solid;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    vertical-align: middle;
}
</style>
