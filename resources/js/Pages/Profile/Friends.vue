<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { clImg } from '@/Composables/useCloudinary';

defineProps({
    profileUser: Object,
    friends: Array,
});

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}
</script>

<template>
    <Head :title="`Amigos de ${profileUser.name} · bubbles`" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px">
            <div style="display:flex; align-items:center; gap:14px; margin-bottom:24px;">
                <Link :href="route('profile.show', profileUser.username)"
                    style="font-size:12px; color:#8ba0b0; text-decoration:none;">&larr; Voltar ao perfil</Link>
                <h1 style="font-size: 22px; font-weight: 900; color: #3a6478; margin: 0; letter-spacing: -0.02em">
                    Amigos de {{ profileUser.name }}
                </h1>
            </div>

            <div style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 18px; border: 1px solid #4ebcff22; box-shadow: 0 4px 16px #009ac70a; padding: 20px;">

                <p style="font-size: 10px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: 0.1em; margin: 0 0 16px;">
                    Amigos · {{ friends.length }}
                </p>

                <div v-if="friends.length === 0" style="text-align: center; padding: 40px 20px">
                    <p style="font-size: 32px; margin: 0 0 12px">🫧</p>
                    <p style="font-size: 14px; color: #8ba0b0">Ainda não tem amigos no bubbles.</p>
                </div>

                <div v-else style="display: flex; flex-direction: column; gap: 14px">
                    <div v-for="f in friends" :key="f.id" style="display: flex; align-items: center; gap: 14px">

                        <Link :href="route('profile.show', f.username)" style="text-decoration: none; flex-shrink: 0">
                            <img v-if="f.avatar" :src="clImg(f.avatar, 96, 96, 'fill', 'face')"
                                :style="{ width:'46px', height:'46px', borderRadius:'50%', objectFit:'cover', border:`2px solid ${f.avatar_color}`, boxShadow:`0 2px 8px ${f.avatar_color}44`, display:'block' }" />
                            <div v-else
                                :style="{ width:'46px', height:'46px', borderRadius:'50%', background:f.avatar_color, display:'flex', alignItems:'center', justifyContent:'center', fontSize:'17px', fontWeight:'800', color:'white', boxShadow:`0 2px 8px ${f.avatar_color}44` }">
                                {{ formatInitial(f.name) }}
                            </div>
                        </Link>

                        <div class="list-content">
                            <Link :href="route('profile.show', f.username)" style="text-decoration: none; min-width: 0; flex: 1">
                                <p style="font-size: 13px; font-weight: 700; color: #3a6478; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ f.name }}
                                </p>
                                <p style="font-size: 11px; color: #009ac7; margin: 2px 0 0">@{{ f.username }}</p>
                            </Link>

                            <div class="list-actions">
                                <Link :href="route('profile.show', f.username)"
                                    style="font-size: 12px; font-weight: 700; color: white; padding: 7px 18px; border-radius: 99px; border: none; background: linear-gradient(135deg, #009ac7, #4ebcff); cursor: pointer; white-space: nowrap; box-shadow: 0 3px 12px #009ac730; text-decoration: none; display: inline-block; transition: opacity 0.2s;"
                                    @mouseenter="$event.currentTarget.style.opacity = '.85'"
                                    @mouseleave="$event.currentTarget.style.opacity = '1'">
                                    Ver perfil
                                </Link>
                            </div>
                        </div>
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
