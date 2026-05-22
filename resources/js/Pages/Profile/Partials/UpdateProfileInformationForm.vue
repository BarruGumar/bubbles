<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import ImageCropper from '@/Components/ImageCropper.vue';
import { useAudio } from '@/Composables/useAudio';

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});

const { playSfx } = useAudio();

const user = usePage().props.auth.user;
const COLORS = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b', '#e0a040', '#6b9bdf'];

// Separate upload forms — each sends only one file via POST
const avatarForm = useForm({ avatar: null });
const bannerForm = useForm({ banner: null });
const avatarInput = ref(null);
const bannerInput = ref(null);

// Local preview before upload (blob URL from File object)
const avatarPreview = ref(user.avatar ?? null);
const bannerPreview = ref(user.banner ?? null);

// Text fields form (name, email, username, bio, color)
const form = useForm({
    name: user.name,
    email: user.email,
    username: user.username ?? '',
    bio: user.bio ?? '',
    avatar_color: user.avatar_color ?? '#009ac7',
});

const initial = computed(() => (form.name || '?')[0].toUpperCase());

// ── Cropper ──
const cropperSrc = ref(null);
const cropperMode = ref(null); // 'avatar' | 'banner'

function onAvatarChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    cropperSrc.value = URL.createObjectURL(file);
    cropperMode.value = 'avatar';
    e.target.value = ''; // allow re-selecting same file
}

function onBannerChange(e) {
    const file = e.target.files[0];
    if (!file) return;

    // GIFs bypass the cropper — the cropper always outputs JPEG, which strips animation
    if (file.type === 'image/gif') {
        if (bannerPreview.value?.startsWith('blob:')) URL.revokeObjectURL(bannerPreview.value);
        bannerForm.banner = file;
        bannerPreview.value = URL.createObjectURL(file);
        e.target.value = '';
        return;
    }

    cropperSrc.value = URL.createObjectURL(file);
    cropperMode.value = 'banner';
    e.target.value = '';
}

function onCropConfirm(blob) {
    const isAvatar = cropperMode.value === 'avatar';
    const ext = blob.type === 'image/png' ? 'png' : 'jpg';
    const filename = isAvatar ? `avatar.${ext}` : `banner.${ext}`;
    const file = new File([blob], filename, { type: blob.type });
    const blobUrl = URL.createObjectURL(blob);

    if (isAvatar) {
        avatarForm.avatar = file;
        avatarPreview.value = blobUrl;
    } else {
        bannerForm.banner = file;
        bannerPreview.value = blobUrl;
    }

    cropperSrc.value = null;
    cropperMode.value = null;
}

function onCropCancel() {
    cropperSrc.value = null;
    cropperMode.value = null;
}

function submitAvatar() {
    playSfx('send');
    avatarForm.post(route('profile.avatar'), {
        forceFormData: true,
        onSuccess: () => {
            avatarForm.reset();
            // Inertia refreshes page props, so usePage().props.auth.user.avatar is now updated
        },
    });
}

function submitBanner() {
    playSfx('send');
    bannerForm.post(route('profile.banner'), {
        forceFormData: true,
        onSuccess: () => bannerForm.reset(),
    });
}

function submitProfile() {
    playSfx('send');
    form.patch(route('profile.update'));
}
</script>

<template>
    <!-- Cropper modal — rendered at body level to avoid z-index issues -->
    <Teleport to="body">
        <ImageCropper
            v-if="cropperSrc"
            :src="cropperSrc"
            :aspect-ratio="cropperMode === 'banner' ? 3 : 1"
            :circle="cropperMode === 'avatar'"
            :output-width="cropperMode === 'banner' ? 1200 : 400"
            :output-height="cropperMode === 'banner' ? 400 : 400"
            @confirm="onCropConfirm"
            @cancel="onCropCancel"
        />
    </Teleport>

    <section>
        <header style="margin-bottom: 24px">
            <h2 style="font-size: 15px; font-weight: 800; color: var(--text); margin: 0 0 4px">Informação de perfil</h2>
            <p style="font-size: 12px; color: var(--text-3); margin: 0">Nome, username, bio, avatar e banner.</p>
        </header>

        <!-- ── BANNER ── -->
        <div style="margin-bottom: 28px">
            <p
                style="
                    font-size: 11px;
                    font-weight: 700;
                    color: var(--text-2);
                    text-transform: uppercase;
                    letter-spacing: 0.06em;
                    margin: 0 0 8px;
                "
            >
                Banner do perfil
            </p>

            <div
                :style="{
                    height: '120px',
                    borderRadius: '14px',
                    cursor: 'pointer',
                    background: bannerPreview
                        ? `url('${bannerPreview}') center/cover no-repeat`
                        : `linear-gradient(135deg, ${form.avatar_color}33 0%, ${form.avatar_color}88 100%)`,
                    border: '1.5px dashed #4ebcff66',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    transition: 'opacity .2s',
                    position: 'relative',
                    overflow: 'hidden',
                }"
                @click="bannerInput.click()"
                @mouseenter="$event.currentTarget.style.opacity = '.8'"
                @mouseleave="$event.currentTarget.style.opacity = '1'"
            >
                <span
                    v-if="!bannerPreview"
                    style="
                        font-size: 12px;
                        color: var(--text-3);
                        background: rgba(255, 255, 255, 0.75);
                        padding: 5px 16px;
                        border-radius: 99px;
                        backdrop-filter: blur(4px);
                    "
                >
                    Clica para adicionar banner
                </span>
                <span
                    v-else
                    style="
                        font-size: 12px;
                        color: white;
                        background: rgba(0, 0, 0, 0.38);
                        padding: 5px 16px;
                        border-radius: 99px;
                    "
                >
                    Alterar banner
                </span>
            </div>
            <input ref="bannerInput" type="file" accept="image/*" style="display: none" @change="onBannerChange" />

            <div v-if="bannerForm.banner" style="display: flex; align-items: center; gap: 10px; margin-top: 10px">
                <button
                    type="button"
                    :disabled="bannerForm.processing"
                    @click="submitBanner"
                    :style="{
                        padding: '8px 20px',
                        borderRadius: '10px',
                        border: 'none',
                        background: '#009ac7',
                        color: 'white',
                        fontSize: '12px',
                        fontWeight: '700',
                        cursor: 'pointer',
                        opacity: bannerForm.processing ? 0.6 : 1,
                        transition: 'opacity .2s',
                    }"
                >
                    {{ bannerForm.processing ? 'A enviar...' : 'Guardar banner' }}
                </button>
                <Transition name="fade">
                    <span v-if="status === 'banner-updated'" style="font-size: 11px; color: #2ea87e; font-weight: 600"
                        >✓ Guardado</span
                    >
                </Transition>
            </div>
        </div>

        <!-- ── AVATAR ── -->
        <div style="display: flex; align-items: flex-start; gap: 20px; margin-bottom: 28px">
            <!-- Clickable avatar circle -->
            <div
                style="position: relative; flex-shrink: 0; cursor: pointer"
                @click="avatarInput.click()"
                title="Alterar foto"
            >
                <img
                    v-if="avatarPreview"
                    :src="avatarPreview"
                    :style="{
                        width: '64px',
                        height: '64px',
                        borderRadius: '50%',
                        objectFit: 'cover',
                        border: `3px solid ${form.avatar_color}`,
                        boxShadow: `0 4px 18px ${form.avatar_color}44`,
                    }"
                />
                <div
                    v-else
                    :style="{
                        width: '64px',
                        height: '64px',
                        borderRadius: '50%',
                        background: form.avatar_color,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontSize: '26px',
                        fontWeight: '900',
                        color: 'white',
                        boxShadow: `0 4px 18px ${form.avatar_color}44`,
                        transition: 'background .3s',
                    }"
                >
                    {{ initial }}
                </div>

                <!-- Camera overlay on hover -->
                <div
                    style="
                        position: absolute;
                        inset: 0;
                        border-radius: 50%;
                        background: rgba(0, 0, 0, 0.35);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        opacity: 0;
                        transition: opacity 0.2s;
                    "
                    @mouseenter="$event.currentTarget.style.opacity = '1'"
                    @mouseleave="$event.currentTarget.style.opacity = '0'"
                >
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path
                            d="M9 1.5v9M5.5 5 9 1.5 12.5 5"
                            stroke="white"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <path d="M3 12v3.5h12V12" stroke="white" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </div>
            </div>
            <input ref="avatarInput" type="file" accept="image/*" style="display: none" @change="onAvatarChange" />

            <div style="flex: 1">
                <p
                    style="
                        font-size: 11px;
                        font-weight: 700;
                        color: var(--text-2);
                        text-transform: uppercase;
                        letter-spacing: 0.06em;
                        margin: 0 0 8px;
                    "
                >
                    Cor do perfil
                </p>

                <!-- Upload button appears after file is selected -->
                <div v-if="avatarForm.avatar" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px">
                    <button
                        type="button"
                        :disabled="avatarForm.processing"
                        @click="submitAvatar"
                        :style="{
                            padding: '7px 18px',
                            borderRadius: '10px',
                            border: 'none',
                            background: '#009ac7',
                            color: 'white',
                            fontSize: '12px',
                            fontWeight: '700',
                            cursor: 'pointer',
                            opacity: avatarForm.processing ? 0.6 : 1,
                        }"
                    >
                        {{ avatarForm.processing ? 'A enviar...' : 'Guardar foto' }}
                    </button>
                    <Transition name="fade">
                        <span
                            v-if="status === 'avatar-updated'"
                            style="font-size: 11px; color: #2ea87e; font-weight: 600"
                            >✓ Guardado</span
                        >
                    </Transition>
                </div>

                <!-- Color swatches + custom picker -->
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
                    <button
                        v-for="c in COLORS"
                        :key="c"
                        type="button"
                        @click="form.avatar_color = c"
                        :style="{
                            width: '24px',
                            height: '24px',
                            borderRadius: '50%',
                            background: c,
                            border: 'none',
                            cursor: 'pointer',
                            boxShadow: form.avatar_color === c ? `0 0 0 3px white, 0 0 0 5px ${c}` : 'none',
                            transition: 'box-shadow .2s',
                        }"
                    />
                    <!-- Custom color picker -->
                    <label
                        title="Cor personalizada"
                        :style="{
                            width: '24px',
                            height: '24px',
                            borderRadius: '50%',
                            cursor: 'pointer',
                            background: COLORS.includes(form.avatar_color) ? '#f0f4f8' : form.avatar_color,
                            border: '2px dashed #4ebcff88',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            boxShadow: !COLORS.includes(form.avatar_color)
                                ? `0 0 0 3px white, 0 0 0 5px ${form.avatar_color}`
                                : 'none',
                            transition: 'box-shadow .2s',
                            flexShrink: 0,
                        }"
                    >
                        <svg
                            v-if="COLORS.includes(form.avatar_color)"
                            width="12"
                            height="12"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#009ac7"
                            stroke-width="2.5"
                            stroke-linecap="round"
                        >
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        <input
                            type="color"
                            :value="form.avatar_color"
                            @input="form.avatar_color = $event.target.value"
                            style="opacity: 0; position: absolute; width: 0; height: 0"
                        />
                    </label>
                </div>
                <p v-if="!avatarPreview" style="font-size: 10px; color: var(--text-4); margin: 6px 0 0">
                    Ou clica na imagem acima para fazer upload de uma foto
                </p>
            </div>
        </div>

        <!-- ── TEXT FIELDS ── -->
        <form
            @submit.prevent="submitProfile"
            style="display: flex; flex-direction: column; gap: 18px"
        >
            <div style="display: flex; flex-direction: column; gap: 6px">
                <label
                    style="
                        font-size: 11px;
                        font-weight: 700;
                        color: var(--text-2);
                        text-transform: uppercase;
                        letter-spacing: 0.06em;
                    "
                    >Nome</label
                >
                <input
                    v-model="form.name"
                    type="text"
                    required
                    autocomplete="name"
                    style="
                        background: var(--input-bg);
                        border: 1.5px solid #4ebcff44;
                        border-radius: 11px;
                        padding: 10px 14px;
                        font-size: 13px;
                        color: var(--input-text);
                        outline: none;
                        font-family: inherit;
                        width: 100%;
                        box-sizing: border-box;
                        transition: border-color 0.2s;
                    "
                    @focus="$event.target.style.borderColor = '#009ac7'"
                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                />
                <p v-if="form.errors.name" style="font-size: 11px; color: #e05555; margin: 0">{{ form.errors.name }}</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px">
                <label
                    style="
                        font-size: 11px;
                        font-weight: 700;
                        color: var(--text-2);
                        text-transform: uppercase;
                        letter-spacing: 0.06em;
                    "
                    >Username</label
                >
                <div style="position: relative">
                    <span
                        style="
                            position: absolute;
                            left: 13px;
                            top: 50%;
                            transform: translateY(-50%);
                            font-size: 13px;
                            color: #009ac7;
                            font-weight: 700;
                        "
                        >@</span
                    >
                    <input
                        v-model="form.username"
                        type="text"
                        autocomplete="off"
                        placeholder="o_teu_username"
                        style="
                            background: var(--input-bg);
                            border: 1.5px solid #4ebcff44;
                            border-radius: 11px;
                            padding: 10px 14px 10px 28px;
                            font-size: 13px;
                            color: var(--input-text);
                            outline: none;
                            font-family: inherit;
                            width: 100%;
                            box-sizing: border-box;
                            transition: border-color 0.2s;
                        "
                        @focus="$event.target.style.borderColor = '#009ac7'"
                        @blur="$event.target.style.borderColor = '#4ebcff44'"
                    />
                </div>
                <p style="font-size: 10px; color: var(--text-4); margin: 0">Só letras minúsculas, números e underscores.</p>
                <p v-if="form.errors.username" style="font-size: 11px; color: #e05555; margin: 0">
                    {{ form.errors.username }}
                </p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px">
                <label
                    style="
                        font-size: 11px;
                        font-weight: 700;
                        color: var(--text-2);
                        text-transform: uppercase;
                        letter-spacing: 0.06em;
                    "
                    >Email</label
                >
                <input
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    style="
                        background: var(--input-bg);
                        border: 1.5px solid #4ebcff44;
                        border-radius: 11px;
                        padding: 10px 14px;
                        font-size: 13px;
                        color: var(--input-text);
                        outline: none;
                        font-family: inherit;
                        width: 100%;
                        box-sizing: border-box;
                        transition: border-color 0.2s;
                    "
                    @focus="$event.target.style.borderColor = '#009ac7'"
                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                />
                <p v-if="form.errors.email" style="font-size: 11px; color: #e05555; margin: 0">
                    {{ form.errors.email }}
                </p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px">
                <div style="display: flex; justify-content: space-between; align-items: center">
                    <label
                        style="
                            font-size: 11px;
                            font-weight: 700;
                            color: var(--text-2);
                            text-transform: uppercase;
                            letter-spacing: 0.06em;
                        "
                        >Bio</label
                    >
                    <span style="font-size: 10px; color: #b0c0cc">{{ form.bio.length }}/300</span>
                </div>
                <textarea
                    v-model="form.bio"
                    maxlength="300"
                    rows="3"
                    placeholder="Conta um pouco sobre ti..."
                    style="
                        background: var(--input-bg);
                        border: 1.5px solid #4ebcff44;
                        border-radius: 11px;
                        padding: 10px 14px;
                        font-size: 13px;
                        color: var(--input-text);
                        outline: none;
                        font-family: inherit;
                        width: 100%;
                        box-sizing: border-box;
                        resize: vertical;
                        transition: border-color 0.2s;
                    "
                    @focus="$event.target.style.borderColor = '#009ac7'"
                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                />
                <p v-if="form.errors.bio" style="font-size: 11px; color: #e05555; margin: 0">{{ form.errors.bio }}</p>
            </div>

            <div
                v-if="mustVerifyEmail && user.email_verified_at === null"
                style="
                    background: #fff9ec;
                    border: 1px solid #e0a04044;
                    border-radius: 10px;
                    padding: 12px 14px;
                    font-size: 12px;
                    color: #8a6020;
                "
            >
                O teu email não está verificado.
                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    style="
                        color: #009ac7;
                        font-weight: 700;
                        background: none;
                        border: none;
                        cursor: pointer;
                        font-size: 12px;
                    "
                >
                    Reenviar verificação.
                </Link>
                <div
                    v-if="status === 'verification-link-sent'"
                    style="margin-top: 6px; color: #2ea87e; font-weight: 600"
                >
                    Link enviado!
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 14px">
                <button
                    type="submit"
                    :disabled="form.processing"
                    style="
                        padding: 11px 28px;
                        border-radius: 12px;
                        background: #009ac7;
                        border: none;
                        color: white;
                        font-size: 13px;
                        font-weight: 700;
                        cursor: pointer;
                        box-shadow: 0 4px 14px #009ac730;
                        transition: opacity 0.2s;
                    "
                    :style="{ opacity: form.processing ? 0.7 : 1 }"
                >
                    Guardar
                </button>
                <Transition name="fade">
                    <span v-if="status === 'profile-updated'" style="font-size: 12px; color: #2ea87e; font-weight: 600"
                        >✓ Guardado</span
                    >
                </Transition>
            </div>
        </form>
    </section>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
