<script setup>
import { reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    community: { type: Object, required: true },
    communityImagePreview: { type: String, default: null },
    communityBannerPreview: { type: String, default: null },
});
const emit = defineEmits(['update:modelValue', 'image-file-selected', 'banner-file-selected']);

const PALETTE = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b', '#e0a040', '#6b9bdf'];

const editData = reactive({
    label: '',
    title: '',
    tagline: '',
    description: '',
    color: '',
    guidelines: '',
});
const confirmDel = ref(false);
const editSaving = ref(false);
const editDeleting = ref(false);

const imageInputRef = ref(null);
const bannerInputRef = ref(null);

function resetEditData() {
    editData.label = props.community.label;
    editData.title = props.community.title;
    editData.tagline = props.community.tagline;
    editData.description = props.community.description;
    editData.color = props.community.color;
    editData.guidelines = (props.community.guidelines || []).join('\n');
    confirmDel.value = false;
}

watch(
    () => props.modelValue,
    (open) => {
        if (open) resetEditData();
    },
);

function close() {
    emit('update:modelValue', false);
}

function onImageChange(e) {
    const file = e.target.files[0];
    if (file) emit('image-file-selected', file);
    e.target.value = '';
}

function onBannerChange(e) {
    const file = e.target.files[0];
    if (file) emit('banner-file-selected', file);
    e.target.value = '';
}

function saveSettings() {
    editSaving.value = true;
    router.put(
        route('community.update', props.community.id),
        {
            label: editData.label,
            community_title: editData.title,
            community_tagline: editData.tagline,
            community_description: editData.description,
            color: editData.color,
            community_guidelines: editData.guidelines
                .split('\n')
                .map((g) => g.trim())
                .filter(Boolean)
                .slice(0, 5),
        },
        {
            preserveScroll: true,
            onSuccess: () => close(),
            onFinish: () => {
                editSaving.value = false;
            },
        },
    );
}

function deleteCommunity() {
    editDeleting.value = true;
    router.delete(route('community.delete', props.community.id), {
        onFinish: () => {
            editDeleting.value = false;
        },
    });
}
</script>

<template>
    <Transition name="fade">
        <div
            v-if="modelValue"
            style="
                position: fixed;
                inset: 0;
                z-index: 200;
                background: rgba(10, 30, 40, 0.55);
                backdrop-filter: blur(6px);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            "
            @click.self="close"
        >
            <div
                style="
                    width: min(520px, 100%);
                    max-height: calc(100vh - 60px);
                    overflow-y: auto;
                    background: white;
                    border-radius: 22px;
                    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.22);
                    padding: 28px;
                "
            >
                <!-- Header -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px">
                    <p style="font-size: 16px; font-weight: 900; color: #1a3a4a; margin: 0; letter-spacing: -0.02em">
                        Editar comunidade
                    </p>
                    <button
                        @click="close"
                        style="
                            width: 30px;
                            height: 30px;
                            border-radius: 50%;
                            border: none;
                            background: #f0f8ff;
                            color: #5a7a8a;
                            font-size: 18px;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            transition: background 0.15s;
                        "
                        @mouseenter="$event.currentTarget.style.background = '#e0eef8'"
                        @mouseleave="$event.currentTarget.style.background = '#f0f8ff'"
                    >
                        ×
                    </button>
                </div>

                <!-- Banner + Image preview -->
                <div style="margin-bottom: 22px; border-radius: 16px; overflow: visible; position: relative">
                    <input
                        ref="bannerInputRef"
                        type="file"
                        accept="image/*"
                        style="display: none"
                        @change="onBannerChange"
                    />
                    <div
                        class="edit-banner-area"
                        :style="{
                            height: '110px',
                            borderRadius: '16px 16px 0 0',
                            cursor: 'pointer',
                            position: 'relative',
                            background: communityBannerPreview
                                ? `url('${communityBannerPreview}') center/cover no-repeat`
                                : `linear-gradient(135deg, ${editData.color}dd 0%, ${editData.color} 100%)`,
                        }"
                        @click="bannerInputRef.click()"
                    >
                        <div
                            class="edit-banner-overlay"
                            style="
                                position: absolute;
                                inset: 0;
                                border-radius: 16px 16px 0 0;
                                background: rgba(0, 0, 0, 0);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                transition: background 0.2s;
                            "
                        >
                            <span
                                class="edit-banner-label"
                                style="
                                    font-size: 11px;
                                    color: white;
                                    background: rgba(0, 0, 0, 0.5);
                                    padding: 5px 14px;
                                    border-radius: 99px;
                                    opacity: 0;
                                    transition: opacity 0.2s;
                                    pointer-events: none;
                                "
                                >Alterar banner</span
                            >
                        </div>
                    </div>
                    <div
                        :style="{
                            height: '36px',
                            background: '#f8fafc',
                            borderRadius: '0 0 16px 16px',
                            border: '1px solid #e8f0f8',
                            borderTop: 'none',
                        }"
                    />
                    <div style="position: absolute; bottom: -2px; left: 20px; z-index: 5">
                        <input
                            ref="imageInputRef"
                            type="file"
                            accept="image/*"
                            style="display: none"
                            @change="onImageChange"
                        />
                        <div
                            class="edit-image-area"
                            style="position: relative; width: 64px; height: 64px; cursor: pointer"
                            @click="imageInputRef.click()"
                        >
                            <img
                                v-if="communityImagePreview"
                                :src="communityImagePreview"
                                :style="{
                                    width: '64px',
                                    height: '64px',
                                    borderRadius: '50%',
                                    objectFit: 'cover',
                                    border: '4px solid white',
                                    boxShadow: `0 3px 14px ${editData.color}55`,
                                    display: 'block',
                                }"
                            />
                            <div
                                v-else
                                :style="{
                                    width: '64px',
                                    height: '64px',
                                    borderRadius: '50%',
                                    background: editData.color,
                                    border: '4px solid white',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: '22px',
                                    fontWeight: '900',
                                    color: 'white',
                                    boxShadow: `0 3px 14px ${editData.color}55`,
                                }"
                            >
                                {{ community.label.replace('#', '').charAt(0).toUpperCase() }}
                            </div>
                            <div
                                class="edit-image-overlay"
                                style="
                                    position: absolute;
                                    inset: 0;
                                    border-radius: 50%;
                                    background: rgba(0, 0, 0, 0);
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    transition: background 0.2s;
                                "
                            >
                                <svg
                                    class="edit-cam-icon"
                                    width="16"
                                    height="16"
                                    viewBox="0 0 18 18"
                                    fill="none"
                                    style="opacity: 0; transition: opacity 0.2s"
                                >
                                    <path
                                        d="M9 1.5v9M5.5 5 9 1.5 12.5 5"
                                        stroke="white"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="M3 12v3.5h12V12"
                                        stroke="white"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Color palette -->
                <div style="margin-bottom: 18px">
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
                        Cor
                    </p>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
                        <button
                            v-for="c in PALETTE"
                            :key="c"
                            type="button"
                            @click="editData.color = c"
                            :style="{
                                width: '24px',
                                height: '24px',
                                borderRadius: '50%',
                                background: c,
                                border: 'none',
                                cursor: 'pointer',
                                flexShrink: 0,
                                boxShadow: editData.color === c ? `0 0 0 2px white, 0 0 0 4px ${c}` : 'none',
                                transition: 'box-shadow .15s',
                            }"
                        />
                        <label
                            :style="{
                                width: '24px',
                                height: '24px',
                                borderRadius: '50%',
                                cursor: 'pointer',
                                border: '2px dashed #b0c8d8',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                                position: 'relative',
                                overflow: 'hidden',
                                boxShadow: !PALETTE.includes(editData.color)
                                    ? `0 0 0 2px white, 0 0 0 4px ${editData.color}`
                                    : 'none',
                                background: !PALETTE.includes(editData.color) ? editData.color : 'transparent',
                            }"
                            title="Cor personalizada"
                        >
                            <input
                                type="color"
                                v-model="editData.color"
                                style="position: absolute; width: 200%; height: 200%; opacity: 0; cursor: pointer"
                            />
                            <span
                                v-if="PALETTE.includes(editData.color)"
                                style="font-size: 12px; color: #8ba0b0; position: relative; pointer-events: none"
                                >+</span
                            >
                        </label>
                    </div>
                </div>

                <!-- Text fields -->
                <div style="display: flex; flex-direction: column; gap: 12px">
                    <div>
                        <label
                            style="
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.1em;
                                display: block;
                                margin-bottom: 6px;
                            "
                            >Hashtag</label
                        >
                        <input
                            v-model="editData.label"
                            placeholder="#hashtag"
                            style="
                                width: 100%;
                                background: #f0f8ff;
                                border: 1.5px solid #4ebcff33;
                                border-radius: 10px;
                                padding: 10px 14px;
                                font-size: 13px;
                                color: #1a3a4a;
                                outline: none;
                                font-family: inherit;
                                box-sizing: border-box;
                                transition: border-color 0.2s;
                            "
                            @focus="$event.target.style.borderColor = editData.color"
                            @blur="$event.target.style.borderColor = '#4ebcff33'"
                        />
                    </div>
                    <div>
                        <label
                            style="
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.1em;
                                display: block;
                                margin-bottom: 6px;
                            "
                            >Título</label
                        >
                        <input
                            v-model="editData.title"
                            placeholder="Título da comunidade"
                            style="
                                width: 100%;
                                background: #f0f8ff;
                                border: 1.5px solid #4ebcff33;
                                border-radius: 10px;
                                padding: 10px 14px;
                                font-size: 13px;
                                color: #1a3a4a;
                                outline: none;
                                font-family: inherit;
                                box-sizing: border-box;
                                transition: border-color 0.2s;
                            "
                            @focus="$event.target.style.borderColor = editData.color"
                            @blur="$event.target.style.borderColor = '#4ebcff33'"
                        />
                    </div>
                    <div>
                        <label
                            style="
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.1em;
                                display: block;
                                margin-bottom: 6px;
                            "
                            >Tagline</label
                        >
                        <input
                            v-model="editData.tagline"
                            placeholder="Tagline curta"
                            style="
                                width: 100%;
                                background: #f0f8ff;
                                border: 1.5px solid #4ebcff33;
                                border-radius: 10px;
                                padding: 10px 14px;
                                font-size: 13px;
                                color: #1a3a4a;
                                outline: none;
                                font-family: inherit;
                                box-sizing: border-box;
                                transition: border-color 0.2s;
                            "
                            @focus="$event.target.style.borderColor = editData.color"
                            @blur="$event.target.style.borderColor = '#4ebcff33'"
                        />
                    </div>
                    <div>
                        <label
                            style="
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.1em;
                                display: block;
                                margin-bottom: 6px;
                            "
                            >Descrição</label
                        >
                        <textarea
                            v-model="editData.description"
                            placeholder="Descrição da comunidade"
                            rows="3"
                            style="
                                width: 100%;
                                background: #f0f8ff;
                                border: 1.5px solid #4ebcff33;
                                border-radius: 10px;
                                padding: 10px 14px;
                                font-size: 13px;
                                color: #1a3a4a;
                                outline: none;
                                font-family: inherit;
                                resize: vertical;
                                box-sizing: border-box;
                                transition: border-color 0.2s;
                            "
                            @focus="$event.target.style.borderColor = editData.color"
                            @blur="$event.target.style.borderColor = '#4ebcff33'"
                        />
                    </div>
                    <div>
                        <label
                            style="
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.1em;
                                display: block;
                                margin-bottom: 6px;
                            "
                        >
                            Regras
                            <span style="font-weight: 500; text-transform: none; letter-spacing: 0"
                                >(uma por linha · máx. 5)</span
                            >
                        </label>
                        <textarea
                            v-model="editData.guidelines"
                            placeholder="Regra 1&#10;Regra 2&#10;..."
                            rows="4"
                            style="
                                width: 100%;
                                background: #f0f8ff;
                                border: 1.5px solid #4ebcff33;
                                border-radius: 10px;
                                padding: 10px 14px;
                                font-size: 13px;
                                color: #1a3a4a;
                                outline: none;
                                font-family: inherit;
                                resize: vertical;
                                box-sizing: border-box;
                                transition: border-color 0.2s;
                            "
                            @focus="$event.target.style.borderColor = editData.color"
                            @blur="$event.target.style.borderColor = '#4ebcff33'"
                        />
                    </div>
                </div>

                <!-- Actions -->
                <div style="display: flex; gap: 10px; margin-top: 20px">
                    <button
                        @click="close"
                        style="
                            flex: 1;
                            padding: 11px;
                            border-radius: 10px;
                            background: #f0f8ff;
                            border: 1.5px solid #e0eef8;
                            color: #8ba0b0;
                            font-size: 13px;
                            font-weight: 600;
                            cursor: pointer;
                            transition: background 0.2s;
                        "
                        @mouseenter="$event.currentTarget.style.background = '#e0eef8'"
                        @mouseleave="$event.currentTarget.style.background = '#f0f8ff'"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="saveSettings"
                        :disabled="editSaving"
                        :style="{
                            flex: 1,
                            padding: '11px',
                            borderRadius: '10px',
                            border: 'none',
                            background: editData.color,
                            color: 'white',
                            fontSize: '13px',
                            fontWeight: '700',
                            cursor: editSaving ? 'not-allowed' : 'pointer',
                            opacity: editSaving ? 0.7 : 1,
                            transition: 'opacity .2s',
                        }"
                    >
                        {{ editSaving ? 'A guardar...' : 'Guardar alterações' }}
                    </button>
                </div>

                <!-- Danger zone -->
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #f0f4f8">
                    <p
                        style="
                            font-size: 10px;
                            font-weight: 800;
                            color: #e05555;
                            text-transform: uppercase;
                            letter-spacing: 0.1em;
                            margin: 0 0 12px;
                        "
                    >
                        Zona de perigo
                    </p>
                    <div v-if="!confirmDel">
                        <button
                            @click="confirmDel = true"
                            style="
                                padding: 9px 18px;
                                border-radius: 10px;
                                border: 1.5px solid #e0555533;
                                background: transparent;
                                color: #e05555;
                                font-size: 12px;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.2s;
                            "
                            @mouseenter="
                                $event.currentTarget.style.background = '#fff0f0';
                                $event.currentTarget.style.borderColor = '#e05555';
                            "
                            @mouseleave="
                                $event.currentTarget.style.background = 'transparent';
                                $event.currentTarget.style.borderColor = '#e0555533';
                            "
                        >
                            Excluir comunidade
                        </button>
                    </div>
                    <div
                        v-else
                        style="
                            background: #fff8f8;
                            border: 1.5px solid #e0555533;
                            border-radius: 12px;
                            padding: 14px 16px;
                            display: flex;
                            flex-direction: column;
                            gap: 10px;
                        "
                    >
                        <p style="font-size: 13px; color: #e05555; margin: 0; font-weight: 600">
                            Tens a certeza? Esta ação é irreversível.
                        </p>
                        <div style="display: flex; gap: 8px">
                            <button
                                @click="confirmDel = false"
                                style="
                                    flex: 1;
                                    padding: 9px;
                                    border-radius: 9px;
                                    background: #f0f8ff;
                                    border: 1px solid #e0eef8;
                                    color: #8ba0b0;
                                    font-size: 12px;
                                    cursor: pointer;
                                "
                            >
                                Cancelar
                            </button>
                            <button
                                @click="deleteCommunity"
                                :disabled="editDeleting"
                                style="
                                    flex: 1;
                                    padding: 9px;
                                    border-radius: 9px;
                                    border: none;
                                    background: #e05555;
                                    color: white;
                                    font-size: 12px;
                                    font-weight: 700;
                                    cursor: pointer;
                                    transition: opacity 0.2s;
                                "
                                :style="{ opacity: editDeleting ? 0.7 : 1 }"
                            >
                                {{ editDeleting ? 'A excluir...' : 'Confirmar exclusão' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
textarea::placeholder {
    color: #b0c8d8;
}
.edit-banner-area:hover .edit-banner-overlay {
    background: rgba(0, 0, 0, 0.32) !important;
}
.edit-banner-area:hover .edit-banner-label {
    opacity: 1 !important;
}
.edit-image-area:hover .edit-image-overlay {
    background: rgba(0, 0, 0, 0.38) !important;
}
.edit-image-area:hover .edit-cam-icon {
    opacity: 1 !important;
}
</style>
