<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { clImg } from '@/Composables/useCloudinary';
import { useSearch } from '@/Composables/useSearch';
import { useOnlineUsers } from '@/Composables/useOnlineUsers';

const props = defineProps({ open: Boolean });
const emit  = defineEmits(['close']);

const inputEl = ref(null);
const searchQuery    = ref('');
const activeSearchIdx = ref(-1);

const { onlineUsers } = useOnlineUsers();
const isOnline = (userId) => userId != null && onlineUsers.value.has(userId);

const { results: searchResults, loading: searchLoading, error: searchError, search: doSearch, clear: clearSearch } = useSearch();

const hasResults = computed(() =>
    searchResults.value &&
    (searchResults.value.users?.length || searchResults.value.communities?.length || searchResults.value.posts?.length),
);

const searchOffsets = computed(() => {
    const u = Math.min(searchResults.value?.users?.length ?? 0, 4);
    const c = Math.min(searchResults.value?.communities?.length ?? 0, 4);
    const p = Math.min(searchResults.value?.posts?.length ?? 0, 3);
    return { users: 0, communities: u, posts: u + c, all: u + c + p };
});

watch(searchQuery, (q) => {
    activeSearchIdx.value = -1;
    if (!q.trim()) { clearSearch(); return; }
    doSearch(q);
});

watch(() => props.open, (val) => {
    if (val) {
        nextTick(() => inputEl.value?.focus());
    } else {
        searchQuery.value = '';
        clearSearch();
        activeSearchIdx.value = -1;
    }
});

function close() { emit('close'); }

function goToResult(url) {
    close();
    router.visit(url);
}

function viewAllResults() {
    const q = searchQuery.value.trim();
    if (!q) return;
    close();
    router.visit(route('search.index', { q }));
}

function handleSearchKey(e) {
    if (!hasResults.value) return;
    const total = searchOffsets.value.all + 1;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeSearchIdx.value = Math.min(activeSearchIdx.value + 1, total - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeSearchIdx.value = Math.max(activeSearchIdx.value - 1, -1);
    } else if (e.key === 'Enter' && activeSearchIdx.value >= 0) {
        e.preventDefault();
        if (activeSearchIdx.value === searchOffsets.value.all) { viewAllResults(); return; }
        const urls = [
            ...(searchResults.value?.users?.slice(0, 4) ?? []).map(u =>
                u.username ? route('profile.show', u.username) : null),
            ...(searchResults.value?.communities?.slice(0, 4) ?? []).map(c =>
                route('community.show', c.id)),
            ...(searchResults.value?.posts?.slice(0, 3) ?? []).map(p =>
                p.author.username ? route('profile.show', p.author.username) : null),
        ];
        const url = urls[activeSearchIdx.value];
        if (url) goToResult(url);
    }
}
</script>

<template>
    <Transition name="overlay">
        <div v-if="open" class="search-overlay" @click="close">
            <div @click.stop style="width:100%;max-width:560px;margin:0 20px">
                <form @submit.prevent="viewAllResults" style="position:relative">
                    <svg style="position:absolute;left:18px;top:50%;transform:translateY(-50%);color:#8ba0b0;pointer-events:none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input
                        ref="inputEl"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Pesquisa pessoas, comunidades ou posts…"
                        class="search-input"
                        @keydown="handleSearchKey"
                    />
                    <kbd class="search-kbd">Esc</kbd>
                </form>

                <div
                    v-if="searchQuery.trim() && (hasResults || searchResults || searchLoading)"
                    style="margin-top:8px;background:var(--dropdown-bg);border-radius:16px;box-shadow:0 16px 48px rgba(0,0,0,0.18);overflow:hidden;max-height:420px;overflow-y:auto"
                    @mouseleave="activeSearchIdx = -1"
                >
                    <div v-if="searchError" style="padding:24px;text-align:center;color:#e05555;font-size:13px;font-weight:600">
                        Erro de rede. Tenta novamente.
                    </div>
                    <div v-else-if="searchResults && !hasResults && !searchLoading" style="padding:24px;text-align:center;color:var(--text-3);font-size:13px">
                        Sem resultados para "{{ searchQuery }}"
                    </div>
                    <template v-if="hasResults">
                        <!-- Pessoas -->
                        <template v-if="searchResults.users?.length">
                            <p style="font-size:10px;font-weight:800;color:var(--text-3);text-transform:uppercase;letter-spacing:0.1em;margin:0;padding:12px 16px 6px">Pessoas</p>
                            <div
                                v-for="(u, i) in searchResults.users.slice(0, 4)"
                                :key="'u' + u.id"
                                @click="goToResult(u.username ? route('profile.show', u.username) : '#')"
                                :style="{ display:'flex', alignItems:'center', gap:'12px', padding:'10px 16px', cursor:'pointer', transition:'background 0.12s', background: activeSearchIdx === searchOffsets.users + i ? 'var(--item-hover)' : 'transparent' }"
                                @mouseenter="activeSearchIdx = searchOffsets.users + i"
                            >
                                <div style="position:relative;flex-shrink:0">
                                    <img v-if="u.avatar" :src="clImg(u.avatar, 72, 72, 'fill', 'face')" :style="{ width:'36px', height:'36px', borderRadius:'50%', objectFit:'cover', border:`2px solid ${u.avatar_color}` }" />
                                    <div v-else :style="{ width:'36px', height:'36px', borderRadius:'50%', background:u.avatar_color, display:'flex', alignItems:'center', justifyContent:'center', fontSize:'13px', fontWeight:'800', color:'white' }">
                                        {{ (u.name ?? '?')[0].toUpperCase() }}
                                    </div>
                                    <span v-if="isOnline(u.id)" class="search-online-dot"></span>
                                </div>
                                <div style="flex:1;min-width:0">
                                    <p style="font-size:13px;font-weight:700;color:var(--text);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ u.name }}</p>
                                    <p v-if="u.username" style="font-size:11px;color:#009ac7;margin:0">@{{ u.username }}</p>
                                </div>
                            </div>
                        </template>
                        <!-- Comunidades -->
                        <template v-if="searchResults.communities?.length">
                            <div style="height:1px;background:var(--dropdown-sep);margin:4px 0" />
                            <p style="font-size:10px;font-weight:800;color:var(--text-3);text-transform:uppercase;letter-spacing:0.1em;margin:0;padding:8px 16px 6px">Comunidades</p>
                            <div
                                v-for="(c, i) in searchResults.communities.slice(0, 4)"
                                :key="'c' + c.id"
                                @click="goToResult(route('community.show', c.id))"
                                :style="{ display:'flex', alignItems:'center', gap:'12px', padding:'10px 16px', cursor:'pointer', transition:'background 0.12s', background: activeSearchIdx === searchOffsets.communities + i ? 'var(--item-hover)' : 'transparent' }"
                                @mouseenter="activeSearchIdx = searchOffsets.communities + i"
                            >
                                <div :style="{ width:'36px', height:'36px', borderRadius:'50%', flexShrink:'0', overflow:'hidden', boxShadow:`0 3px 10px ${c.color}44`, background:`radial-gradient(circle at 38% 32%, ${c.color}ee 0%, ${c.color} 60%)`, display:'flex', alignItems:'center', justifyContent:'center' }">
                                    <img v-if="c.image" :src="c.image" style="width:100%;height:100%;object-fit:cover;display:block" />
                                    <span v-else style="font-size:9px;font-weight:800;color:white">{{ c.label?.slice(0, 3) }}</span>
                                </div>
                                <div style="flex:1;min-width:0">
                                    <p style="font-size:13px;font-weight:700;color:var(--text);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ c.title }}</p>
                                    <p style="font-size:11px;color:var(--text-3);margin:0">{{ c.members }} membros</p>
                                </div>
                            </div>
                        </template>
                        <!-- Posts -->
                        <template v-if="searchResults.posts?.length">
                            <div style="height:1px;background:var(--dropdown-sep);margin:4px 0" />
                            <p style="font-size:10px;font-weight:800;color:var(--text-3);text-transform:uppercase;letter-spacing:0.1em;margin:0;padding:8px 16px 6px">Posts</p>
                            <div
                                v-for="(p, i) in searchResults.posts.slice(0, 3)"
                                :key="'p' + p.id"
                                @click="goToResult(p.author.username ? route('profile.show', p.author.username) : '#')"
                                :style="{ display:'flex', alignItems:'flex-start', gap:'10px', padding:'10px 16px', cursor:'pointer', transition:'background 0.12s', background: activeSearchIdx === searchOffsets.posts + i ? 'var(--item-hover)' : 'transparent' }"
                                @mouseenter="activeSearchIdx = searchOffsets.posts + i"
                            >
                                <img v-if="p.author.avatar" :src="clImg(p.author.avatar, 48, 48, 'fill', 'face')" :style="{ width:'28px', height:'28px', borderRadius:'50%', objectFit:'cover', border:`1.5px solid ${p.author.avatar_color}`, flexShrink:'0', marginTop:'1px' }" />
                                <div v-else :style="{ width:'28px', height:'28px', borderRadius:'50%', background:p.author.avatar_color, flexShrink:'0', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'10px', fontWeight:'800', color:'white', marginTop:'1px' }">
                                    {{ (p.author.name ?? '?')[0].toUpperCase() }}
                                </div>
                                <div style="flex:1;min-width:0">
                                    <p style="font-size:12px;font-weight:700;color:var(--text);margin:0 0 2px">{{ p.author.name }}</p>
                                    <p style="font-size:11px;color:var(--text-2);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ p.content }}</p>
                                </div>
                            </div>
                        </template>
                        <!-- Ver todos -->
                        <div
                            @click="viewAllResults"
                            :style="{ padding:'12px 16px', textAlign:'center', fontSize:'12px', fontWeight:'700', color:'#009ac7', cursor:'pointer', borderTop:'1px solid var(--dropdown-sep)', transition:'background 0.12s', background: activeSearchIdx === searchOffsets.all ? 'var(--item-hover)' : 'transparent' }"
                            @mouseenter="activeSearchIdx = searchOffsets.all"
                        >
                            Ver todos os resultados →
                        </div>
                    </template>
                </div>

                <p v-if="!searchQuery.trim()" style="font-size:11px;color:rgba(255,255,255,0.7);margin:10px 0 0;text-align:center">
                    Enter para pesquisar · Esc para fechar
                </p>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.search-overlay {
    position: fixed; inset: 0; z-index: 100;
    background: rgba(0, 0, 0, 0.32);
    backdrop-filter: blur(4px);
    display: flex; align-items: flex-start; justify-content: center;
    padding-top: clamp(16px, 10svh, 80px);
}
.search-input {
    width: 100%; box-sizing: border-box;
    background: var(--search-input-bg);
    border: none; border-radius: 16px;
    padding: 16px 16px 16px 50px;
    font-size: 15px; color: var(--text);
    outline: none; font-family: inherit;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.25);
}
.search-kbd {
    position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
    font-size: 11px; color: var(--text-3); background: var(--kbd-bg);
    border-radius: 6px; padding: 2px 7px; font-family: inherit;
}
.search-online-dot {
    position: absolute; bottom: 0; right: 0;
    width: 10px; height: 10px; border-radius: 50%;
    background: #22c55e;
    border: 2px solid var(--dropdown-bg, #fff);
}
.overlay-enter-active, .overlay-leave-active { transition: opacity 0.2s ease; }
.overlay-enter-from, .overlay-leave-to { opacity: 0; }
</style>
