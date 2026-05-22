<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { clImg } from '@/Composables/useCloudinary';

const props = defineProps({
    reactorsRoute: { type: String, required: true },
});

const REACTIONS = [
    { type: 'like', emoji: '👍' },
    { type: 'love', emoji: '❤️' },
    { type: 'laugh', emoji: '😂' },
    { type: 'wow', emoji: '😮' },
    { type: 'sad', emoji: '😢' },
];

const isOpen = ref(false);
const loading = ref(false);
const reactors = ref([]);
const byType = ref({});
const activeTab = ref('all');

async function open() {
    isOpen.value = true;
    loading.value = true;
    activeTab.value = 'all';
    reactors.value = [];
    byType.value = {};
    try {
        const { data } = await axios.get(props.reactorsRoute);
        reactors.value = data.reactors;
        byType.value = data.by_type;
    } finally {
        loading.value = false;
    }
}

function close() {
    isOpen.value = false;
}

const availableTabs = computed(() => {
    const tabs = [{ key: 'all', emoji: null, count: reactors.value.length }];
    for (const r of REACTIONS) {
        const count = byType.value[r.type];
        if (count) tabs.push({ key: r.type, emoji: r.emoji, count });
    }
    return tabs;
});

const filteredReactors = computed(() => {
    if (activeTab.value === 'all') return reactors.value;
    return reactors.value.filter((r) => r.reaction_type === activeTab.value);
});

function reactionEmoji(type) {
    return REACTIONS.find((r) => r.type === type)?.emoji ?? '👍';
}

defineExpose({ open });
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="isOpen"
                @click.self="close"
                style="
                    position: fixed;
                    inset: 0;
                    z-index: 1000;
                    background: rgba(0, 0, 0, 0.45);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 16px;
                "
            >
                <div
                    class="modal-card"
                    style="
                        background: white;
                        border-radius: 16px;
                        width: 100%;
                        max-width: 400px;
                        max-height: 80vh;
                        display: flex;
                        flex-direction: column;
                        box-shadow: 0 8px 40px rgba(0, 0, 0, 0.18);
                    "
                >
                    <!-- Header -->
                    <div
                        style="
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            padding: 16px 20px;
                            border-bottom: 1px solid #eef2f8;
                            flex-shrink: 0;
                        "
                    >
                        <span style="font-size: 16px; font-weight: 700; color: #1a2433">
                            {{ loading ? 'Reações' : `${reactors.length} ${reactors.length === 1 ? 'Reação' : 'Reações'}` }}
                        </span>
                        <button
                            @click="close"
                            style="
                                background: none;
                                border: none;
                                cursor: pointer;
                                color: #8ba0b0;
                                font-size: 18px;
                                line-height: 1;
                                padding: 4px 8px;
                                border-radius: 50%;
                            "
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Tabs (só aparece quando há mais de um tipo de reação) -->
                    <div
                        v-if="!loading && availableTabs.length > 2"
                        style="
                            display: flex;
                            gap: 4px;
                            padding: 10px 16px 0;
                            overflow-x: auto;
                            scrollbar-width: none;
                            flex-shrink: 0;
                        "
                    >
                        <button
                            v-for="tab in availableTabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            :style="{
                                background: activeTab === tab.key ? '#f0f8ff' : 'none',
                                border: activeTab === tab.key ? '1.5px solid #009ac7' : '1.5px solid #eef2f8',
                                borderRadius: '99px',
                                cursor: 'pointer',
                                padding: '4px 12px',
                                fontSize: '13px',
                                fontWeight: '600',
                                color: activeTab === tab.key ? '#009ac7' : '#8ba0b0',
                                whiteSpace: 'nowrap',
                            }"
                        >
                            {{ tab.emoji ?? 'Todos' }} {{ tab.count }}
                        </button>
                    </div>

                    <!-- Lista -->
                    <div style="overflow-y: auto; padding: 12px 16px; flex: 1; min-height: 0">
                        <!-- Carregando -->
                        <div
                            v-if="loading"
                            style="display: flex; justify-content: center; align-items: center; padding: 40px"
                        >
                            <div class="spinner" />
                        </div>

                        <!-- Sem reações -->
                        <div
                            v-else-if="filteredReactors.length === 0"
                            style="text-align: center; color: #8ba0b0; padding: 40px; font-size: 14px"
                        >
                            Nenhuma reação
                        </div>

                        <!-- Lista de utilizadores -->
                        <div v-else style="display: flex; flex-direction: column; gap: 2px">
                            <div
                                v-for="r in filteredReactors"
                                :key="r.id"
                                style="
                                    display: flex;
                                    align-items: center;
                                    gap: 12px;
                                    padding: 8px;
                                    border-radius: 10px;
                                "
                            >
                                <!-- Avatar -->
                                <div style="position: relative; flex-shrink: 0">
                                    <img
                                        v-if="r.avatar"
                                        :src="clImg(r.avatar, 48, 48, 'fill', 'face')"
                                        loading="lazy"
                                        style="
                                            width: 40px;
                                            height: 40px;
                                            border-radius: 50%;
                                            object-fit: cover;
                                            display: block;
                                        "
                                        :style="{ border: `2px solid ${r.avatar_color}` }"
                                    />
                                    <div
                                        v-else
                                        style="
                                            width: 40px;
                                            height: 40px;
                                            border-radius: 50%;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            font-weight: 700;
                                            font-size: 15px;
                                            color: white;
                                        "
                                        :style="{ background: r.avatar_color }"
                                    >
                                        {{ r.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <!-- Emoji da reação -->
                                    <span
                                        style="
                                            position: absolute;
                                            bottom: -3px;
                                            right: -5px;
                                            font-size: 13px;
                                            line-height: 1;
                                        "
                                        >{{ reactionEmoji(r.reaction_type) }}</span
                                    >
                                </div>

                                <!-- Nome e username -->
                                <div>
                                    <div style="font-size: 14px; font-weight: 600; color: #1a2433">
                                        {{ r.name }}
                                    </div>
                                    <div style="font-size: 12px; color: #8ba0b0">@{{ r.username }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active {
    transition: opacity 0.2s ease;
}
.modal-leave-active {
    transition: opacity 0.15s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-active .modal-card {
    transition: transform 0.2s ease;
}
.modal-leave-active .modal-card {
    transition: transform 0.15s ease;
}
.modal-enter-from .modal-card {
    transform: scale(0.95);
}
.modal-leave-to .modal-card {
    transform: scale(0.97);
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
.spinner {
    width: 28px;
    height: 28px;
    border: 3px solid #eef2f8;
    border-top-color: #009ac7;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
</style>
