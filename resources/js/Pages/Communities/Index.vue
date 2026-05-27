<script setup>
import { onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAudio } from '@/Composables/useAudio';

const { playBgm, playSfx } = useAudio();

defineProps({
    communities: Array,
});

onMounted(() => playBgm('communities'));
onUnmounted(() => {});

function leave(id) {
    playSfx('leave');
    router.delete(route('community.leave', id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Comunidades · bubbles" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px">
            <h1
                style="
                    font-size: 22px;
                    font-weight: 900;
                    color: #3a6478;
                    margin: 0 0 24px;
                    letter-spacing: -0.02em;
                "
            >
                Comunidades
            </h1>

            <!-- Lista de comunidades -->
            <div
                style="
                    background: rgba(255, 255, 255, 0.88);
                    backdrop-filter: blur(20px);
                    border-radius: 18px;
                    border: 1px solid #4ebcff22;
                    box-shadow: 0 4px 16px #009ac70a;
                    padding: 20px;
                "
            >
                <p
                    style="
                        font-size: 10px;
                        font-weight: 800;
                        color: #8ba0b0;
                        text-transform: uppercase;
                        letter-spacing: 0.1em;
                        margin: 0 0 16px;
                    "
                >
                    As tuas comunidades · {{ communities.length }}
                </p>

                <!-- Estado vazio -->
                <div
                    v-if="communities.length === 0"
                    style="text-align: center; padding: 40px 20px"
                >
                    <p style="font-size: 32px; margin: 0 0 12px">🫧</p>
                    <p style="font-size: 14px; color: #8ba0b0">Ainda não fazes parte de nenhuma comunidade.</p>
                    <p style="font-size: 12px; color: #b0c0cc; margin-top: 6px">
                        Explora as bolhas e junta-te a uma comunidade!
                    </p>
                    <Link
                        :href="route('bubbles')"
                        style="
                            display: inline-block;
                            margin-top: 16px;
                            padding: 9px 22px;
                            border-radius: 99px;
                            background: #009ac7;
                            color: white;
                            font-size: 13px;
                            font-weight: 700;
                            text-decoration: none;
                            box-shadow: 0 3px 12px #009ac730;
                            transition: opacity 0.2s;
                        "
                        @mouseenter="$event.currentTarget.style.opacity = '.85'"
                        @mouseleave="$event.currentTarget.style.opacity = '1'"
                    >
                        Explorar bolhas
                    </Link>
                </div>

                <div v-else style="display: flex; flex-direction: column; gap: 14px">
                    <div
                        v-for="c in communities"
                        :key="c.id"
                        style="display: flex; align-items: center; gap: 14px"
                    >
                        <!-- Bubble avatar -->
                        <Link
                            :href="route('community.show', c.id)"
                            style="text-decoration: none; flex-shrink: 0"
                        >
                            <div
                                :style="{
                                    width: '52px',
                                    height: '52px',
                                    borderRadius: '50%',
                                    backgroundImage: c.image
                                        ? `radial-gradient(circle at 38% 32%, ${c.color}55 0%, ${c.color}99 100%), url('${c.image}')`
                                        : `radial-gradient(circle at 38% 32%, ${c.color}ee 0%, ${c.color} 60%)`,
                                    backgroundSize: 'cover',
                                    backgroundPosition: 'center',
                                    position: 'relative',
                                    overflow: 'hidden',
                                    flexShrink: '0',
                                    boxShadow: `0 4px 14px ${c.color}44, 0 2px 6px ${c.color}22`,
                                    transition: 'transform .22s cubic-bezier(.34,1.56,.64,1)',
                                    cursor: 'pointer',
                                }"
                                @mouseenter="$event.currentTarget.style.transform = 'scale(1.1)'"
                                @mouseleave="$event.currentTarget.style.transform = 'scale(1)'"
                            >
                                <div
                                    style="
                                        position: absolute;
                                        top: 6px;
                                        left: 14%;
                                        width: 72%;
                                        height: 34%;
                                        border-radius: 50%;
                                        background: rgba(255,255,255,0.22);
                                        transform: rotate(-10deg);
                                        pointer-events: none;
                                    "
                                />
                                <span
                                    style="
                                        position: relative;
                                        font-size: 8px;
                                        font-weight: 800;
                                        color: white;
                                        text-align: center;
                                        padding: 0 4px;
                                        line-height: 1.2;
                                        text-shadow: 0 1px 3px rgba(0,0,0,.35);
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        height: 100%;
                                        word-break: break-word;
                                        max-width: 100%;
                                    "
                                >{{ c.label }}</span>
                            </div>
                        </Link>

                        <!-- Info + Ações -->
                        <div class="list-content">
                        <Link
                            :href="route('community.show', c.id)"
                            style="text-decoration: none; min-width: 0"
                        >
                            <p
                                style="
                                    font-size: 13px;
                                    font-weight: 700;
                                    color: #3a6478;
                                    margin: 0;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                "
                            >
                                {{ c.title }}
                            </p>
                            <p style="font-size: 11px; color: #009ac7; margin: 2px 0 0; font-weight: 600">
                                {{ c.members }} {{ c.members === 1 ? 'membro' : 'membros' }}
                            </p>
                        </Link>

                        <!-- Ações -->
                        <div class="list-actions">
                            <Link
                                :href="route('community.show', c.id)"
                                style="
                                    font-size: 12px;
                                    font-weight: 700;
                                    color: white;
                                    padding: 7px 18px;
                                    border-radius: 99px;
                                    border: none;
                                    background: linear-gradient(135deg, #009ac7, #4ebcff);
                                    cursor: pointer;
                                    white-space: nowrap;
                                    box-shadow: 0 3px 12px #009ac730;
                                    text-decoration: none;
                                    display: inline-block;
                                    transition: opacity 0.2s;
                                "
                                @mouseenter="$event.currentTarget.style.opacity = '.85'"
                                @mouseleave="$event.currentTarget.style.opacity = '1'"
                            >
                                Ver
                            </Link>
                            <button
                                @click="leave(c.id)"
                                style="
                                    padding: 7px 14px;
                                    border-radius: 99px;
                                    border: 1.5px solid #c8d8e0;
                                    background: transparent;
                                    color: #8ba0b0;
                                    font-size: 12px;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                "
                                @mouseenter="
                                    $event.currentTarget.style.borderColor = '#e05555';
                                    $event.currentTarget.style.color = '#e05555';
                                "
                                @mouseleave="
                                    $event.currentTarget.style.borderColor = '#c8d8e0';
                                    $event.currentTarget.style.color = '#8ba0b0';
                                "
                            >
                                Sair
                            </button>
                        </div><!-- /list-actions -->
                        </div><!-- /list-content -->
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.list-content {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
}
.list-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

@media (max-width: 640px) {
    .list-content {
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
    }
}
</style>
