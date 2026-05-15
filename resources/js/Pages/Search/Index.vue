<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { clImg } from '@/Composables/useCloudinary';
import axios from 'axios';

const props = defineProps({
    query: { type: String, default: '' },
    results: { type: Object, default: null },
});

const localQuery = ref(props.query);
const localResults = ref(props.results);
const loading = ref(false);
const error = ref(false);

let debounceTimer = null;

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}

function doSearch(q) {
    if (!q.trim()) {
        localResults.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    error.value = false;

    axios
        .get(route('search.api'), { params: { q } })
        .then((res) => {
            localResults.value = res.data;
            loading.value = false;
        })
        .catch(() => {
            error.value = true;
            loading.value = false;
        });
}

watch(localQuery, (q) => {
    clearTimeout(debounceTimer);
    if (!q.trim()) {
        localResults.value = null;
        loading.value = false;
        return;
    }
    loading.value = true;
    debounceTimer = setTimeout(() => doSearch(q), 320);
});

const hasResults = computed(
    () =>
        localResults.value &&
        (localResults.value.users?.length ||
            localResults.value.communities?.length ||
            localResults.value.posts?.length),
);

const isEmpty = computed(
    () =>
        localResults.value &&
        !localResults.value.users?.length &&
        !localResults.value.communities?.length &&
        !localResults.value.posts?.length,
);
</script>

<template>
    <Head title="Pesquisa · bubbles" />

    <AuthenticatedLayout>
        <div style="max-width: 720px; margin: 0 auto; padding: 40px 20px 80px">
            <h1 style="font-size: 22px; font-weight: 900; color: #1a3a4a; margin: 0 0 20px; letter-spacing: -0.02em">
                Pesquisa
            </h1>

            <!-- Search input -->
            <div style="position: relative; margin-bottom: 28px">
                <svg
                    style="
                        position: absolute;
                        left: 16px;
                        top: 50%;
                        transform: translateY(-50%);
                        color: #8ba0b0;
                        pointer-events: none;
                    "
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input
                    v-model="localQuery"
                    type="text"
                    placeholder="Pesquisa pessoas, comunidades ou posts…"
                    autofocus
                    style="
                        width: 100%;
                        box-sizing: border-box;
                        background: rgba(255, 255, 255, 0.88);
                        border: 1.5px solid #009ac722;
                        border-radius: 14px;
                        padding: 13px 16px 13px 44px;
                        font-size: 14px;
                        color: #1a3a4a;
                        outline: none;
                        font-family: inherit;
                        transition:
                            border-color 0.2s,
                            box-shadow 0.2s;
                        backdrop-filter: blur(12px);
                    "
                    @focus="
                        $event.target.style.borderColor = '#009ac7';
                        $event.target.style.boxShadow = '0 0 0 3px #009ac714';
                    "
                    @blur="
                        $event.target.style.borderColor = '#009ac722';
                        $event.target.style.boxShadow = 'none';
                    "
                />
                <!-- Loading indicator -->
                <svg
                    v-if="loading"
                    style="
                        position: absolute;
                        right: 16px;
                        top: 50%;
                        transform: translateY(-50%);
                        color: #009ac7;
                        animation: spin 1s linear infinite;
                        transform-origin: center;
                    "
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                >
                    <path
                        d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"
                    />
                </svg>
            </div>

            <!-- Error -->
            <div
                v-if="error"
                style="text-align: center; padding: 32px; color: #e05555; font-size: 13px; font-weight: 600"
            >
                Erro ao pesquisar. Tenta novamente.
            </div>

            <!-- Empty -->
            <div
                v-else-if="isEmpty"
                style="
                    text-align: center;
                    padding: 48px 20px;
                    background: rgba(255, 255, 255, 0.88);
                    border-radius: 18px;
                    border: 1px solid #4ebcff1a;
                "
            >
                <p style="font-size: 28px; margin: 0 0 10px">🔍</p>
                <p style="font-size: 14px; font-weight: 700; color: #1a3a4a; margin: 0 0 4px">
                    Sem resultados para "{{ localQuery }}"
                </p>
                <p style="font-size: 12px; color: #8ba0b0; margin: 0">
                    Tenta termos diferentes ou verifica a ortografia.
                </p>
            </div>

            <!-- No query yet -->
            <div
                v-else-if="!localQuery.trim() && !localResults"
                style="text-align: center; padding: 48px 20px; opacity: 0.6"
            >
                <p style="font-size: 28px; margin: 0 0 10px">🔍</p>
                <p style="font-size: 14px; color: #8ba0b0; margin: 0">Escreve algo para pesquisar.</p>
            </div>

            <!-- Results -->
            <template v-else-if="hasResults">
                <!-- Users -->
                <div v-if="localResults.users?.length" style="margin-bottom: 24px">
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
                        Pessoas · {{ localResults.users.length }}
                    </p>
                    <div
                        style="
                            background: rgba(255, 255, 255, 0.88);
                            backdrop-filter: blur(20px);
                            border-radius: 18px;
                            border: 1px solid #4ebcff1a;
                            box-shadow: 0 4px 16px #009ac708;
                            overflow: hidden;
                        "
                    >
                        <Link
                            v-for="(u, i) in localResults.users"
                            :key="u.id"
                            :href="u.username ? route('profile.show', u.username) : '#'"
                            style="
                                display: flex;
                                align-items: center;
                                gap: 14px;
                                padding: 14px 20px;
                                text-decoration: none;
                                transition: background 0.15s;
                            "
                            :style="{ borderTop: i > 0 ? '1px solid #009ac70a' : 'none' }"
                            @mouseenter="$event.currentTarget.style.background = 'rgba(0,154,199,0.04)'"
                            @mouseleave="$event.currentTarget.style.background = 'transparent'"
                        >
                            <img
                                v-if="u.avatar"
                                :src="clImg(u.avatar, 96, 96, 'fill', 'face')"
                                :style="{
                                    width: '46px',
                                    height: '46px',
                                    borderRadius: '50%',
                                    objectFit: 'cover',
                                    border: `2px solid ${u.avatar_color}`,
                                    boxShadow: `0 2px 8px ${u.avatar_color}33`,
                                    flexShrink: '0',
                                }"
                            />
                            <div
                                v-else
                                :style="{
                                    width: '46px',
                                    height: '46px',
                                    borderRadius: '50%',
                                    flexShrink: '0',
                                    background: u.avatar_color,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: '17px',
                                    fontWeight: '800',
                                    color: 'white',
                                }"
                            >
                                {{ formatInitial(u.name) }}
                            </div>
                            <div style="flex: 1; min-width: 0">
                                <p
                                    style="
                                        font-size: 14px;
                                        font-weight: 700;
                                        color: #1a3a4a;
                                        margin: 0;
                                        overflow: hidden;
                                        text-overflow: ellipsis;
                                        white-space: nowrap;
                                    "
                                >
                                    {{ u.name }}
                                </p>
                                <p v-if="u.username" style="font-size: 12px; color: #009ac7; margin: 1px 0 0">
                                    @{{ u.username }}
                                </p>
                                <p
                                    v-if="u.bio"
                                    style="
                                        font-size: 11px;
                                        color: #8ba0b0;
                                        margin: 2px 0 0;
                                        overflow: hidden;
                                        text-overflow: ellipsis;
                                        white-space: nowrap;
                                    "
                                >
                                    {{ u.bio }}
                                </p>
                            </div>
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#c8d8e0"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <!-- Communities -->
                <div v-if="localResults.communities?.length" style="margin-bottom: 24px">
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
                        Comunidades · {{ localResults.communities.length }}
                    </p>
                    <div
                        style="
                            background: rgba(255, 255, 255, 0.88);
                            backdrop-filter: blur(20px);
                            border-radius: 18px;
                            border: 1px solid #4ebcff1a;
                            box-shadow: 0 4px 16px #009ac708;
                            overflow: hidden;
                        "
                    >
                        <Link
                            v-for="(c, i) in localResults.communities"
                            :key="c.id"
                            :href="route('community.show', c.id)"
                            style="
                                display: flex;
                                align-items: center;
                                gap: 14px;
                                padding: 14px 20px;
                                text-decoration: none;
                                transition: background 0.15s;
                            "
                            :style="{ borderTop: i > 0 ? '1px solid #009ac70a' : 'none' }"
                            @mouseenter="$event.currentTarget.style.background = 'rgba(0,154,199,0.04)'"
                            @mouseleave="$event.currentTarget.style.background = 'transparent'"
                        >
                            <!-- Community bubble icon -->
                            <div
                                :style="{
                                    width: '46px',
                                    height: '46px',
                                    borderRadius: '50%',
                                    flexShrink: '0',
                                    backgroundImage: c.image
                                        ? `radial-gradient(circle at 38% 32%, ${c.color}55 0%, ${c.color}99 100%), url('${c.image}')`
                                        : `radial-gradient(circle at 38% 32%, ${c.color}ee 0%, ${c.color} 60%)`,
                                    backgroundSize: 'cover',
                                    backgroundPosition: 'center',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    boxShadow: `0 4px 14px ${c.color}44`,
                                }"
                            >
                                <span
                                    v-if="!c.image"
                                    style="
                                        font-size: 11px;
                                        font-weight: 800;
                                        color: white;
                                        text-align: center;
                                        padding: 0 4px;
                                        line-height: 1.2;
                                    "
                                    >{{ c.label?.slice(0, 3) }}</span
                                >
                            </div>
                            <div style="flex: 1; min-width: 0">
                                <p
                                    style="
                                        font-size: 14px;
                                        font-weight: 700;
                                        color: #1a3a4a;
                                        margin: 0;
                                        overflow: hidden;
                                        text-overflow: ellipsis;
                                        white-space: nowrap;
                                    "
                                >
                                    {{ c.title }}
                                </p>
                                <p
                                    v-if="c.description"
                                    style="
                                        font-size: 11px;
                                        color: #8ba0b0;
                                        margin: 2px 0 0;
                                        overflow: hidden;
                                        text-overflow: ellipsis;
                                        white-space: nowrap;
                                    "
                                >
                                    {{ c.description }}
                                </p>
                                <p style="font-size: 10px; color: #b0c0cc; margin: 2px 0 0">
                                    {{ c.members }} {{ c.members === 1 ? 'membro' : 'membros' }}
                                </p>
                            </div>
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#c8d8e0"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <!-- Posts -->
                <div v-if="localResults.posts?.length">
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
                        Posts · {{ localResults.posts.length }}
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 10px">
                        <Link
                            v-for="p in localResults.posts"
                            :key="p.id"
                            :href="p.author.username ? route('profile.show', p.author.username) : '#'"
                            style="
                                background: rgba(255, 255, 255, 0.88);
                                backdrop-filter: blur(20px);
                                border-radius: 16px;
                                border: 1px solid #4ebcff1a;
                                box-shadow: 0 2px 10px #009ac706;
                                padding: 16px 20px;
                                text-decoration: none;
                                display: block;
                                transition: background 0.15s;
                            "
                            @mouseenter="$event.currentTarget.style.background = 'rgba(255,255,255,0.95)'"
                            @mouseleave="$event.currentTarget.style.background = 'rgba(255,255,255,0.88)'"
                        >
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px">
                                <img
                                    v-if="p.author.avatar"
                                    :src="clImg(p.author.avatar, 64, 64, 'fill', 'face')"
                                    :style="{
                                        width: '32px',
                                        height: '32px',
                                        borderRadius: '50%',
                                        objectFit: 'cover',
                                        border: `2px solid ${p.author.avatar_color}`,
                                        flexShrink: '0',
                                    }"
                                />
                                <div
                                    v-else
                                    :style="{
                                        width: '32px',
                                        height: '32px',
                                        borderRadius: '50%',
                                        flexShrink: '0',
                                        background: p.author.avatar_color,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        fontSize: '12px',
                                        fontWeight: '800',
                                        color: 'white',
                                    }"
                                >
                                    {{ formatInitial(p.author.name) }}
                                </div>
                                <div>
                                    <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0">
                                        {{ p.author.name }}
                                    </p>
                                    <p style="font-size: 10px; color: #b0c0cc; margin: 0">{{ p.created_at }}</p>
                                </div>
                            </div>
                            <p
                                style="
                                    font-size: 13px;
                                    color: #3a5a6a;
                                    margin: 0;
                                    line-height: 1.6;
                                    display: -webkit-box;
                                    -webkit-line-clamp: 3;
                                    -webkit-box-orient: vertical;
                                    overflow: hidden;
                                "
                            >
                                {{ p.content }}
                            </p>
                            <img
                                v-if="p.image"
                                :src="clImg(p.image, 600, 0, 'limit')"
                                style="
                                    margin-top: 10px;
                                    width: 100%;
                                    max-height: 180px;
                                    object-fit: cover;
                                    border-radius: 10px;
                                    display: block;
                                "
                            />
                        </Link>
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes spin {
    to {
        transform: translateY(-50%) rotate(360deg);
    }
}
</style>
