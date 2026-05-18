<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { clImg } from '@/Composables/useCloudinary';

const props = defineProps({
    community: { type: Object, required: true },
    isOwn: { type: Boolean, default: false },
    canModerate: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    isMember: { type: Boolean, default: false },
    authUser: { type: Object, default: null },
    imagePreview: { type: String, default: null },
    bannerPreview: { type: String, default: null },
});
const emit = defineEmits(['open-edit']);

const activityLevel = computed(() => {
    const n = props.community.recent_posts_count ?? 0;
    if (n === 0) return { pct: 0,  label: 'Inativa',        color: '#b0c0cc' };
    if (n <= 2)  return { pct: 33, label: 'Com atividade',  color: props.community.color };
    if (n <= 5)  return { pct: 66, label: 'Ativa',          color: props.community.color };
    return             { pct: 100, label: 'Muito ativa',    color: props.community.color };
});

function joinCommunity() {
    router.post(route('community.join', props.community.id), {}, { preserveScroll: true });
}

function leaveCommunity() {
    router.delete(route('community.leave', props.community.id), { preserveScroll: true });
}
</script>

<template>
    <div
        style="
            border-radius: 22px;
            overflow: visible;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px #009ac70e;
            position: relative;
        "
    >
        <!-- Banner -->
        <div
            :style="{
                height: '180px',
                position: 'relative',
                borderRadius: '22px 22px 0 0',
                background: bannerPreview
                    ? `url('${clImg(bannerPreview, 1400, 500, 'fill')}') center/cover no-repeat`
                    : `linear-gradient(135deg, ${community.color}dd 0%, ${community.cover_color ?? community.color} 100%)`,
            }"
        >
            <!-- Community avatar circle -->
            <div style="position: absolute; bottom: -42px; left: 32px; z-index: 5">
                <div style="position: relative; width: 86px; height: 86px">
                    <img
                        v-if="imagePreview"
                        :src="clImg(imagePreview, 200, 200, 'fill')"
                        :style="{
                            width: '86px',
                            height: '86px',
                            borderRadius: '50%',
                            objectFit: 'cover',
                            border: '4.5px solid white',
                            boxShadow: `0 4px 20px ${community.color}66`,
                            display: 'block',
                        }"
                    />
                    <div
                        v-else
                        :style="{
                            width: '86px',
                            height: '86px',
                            borderRadius: '50%',
                            background: community.color,
                            border: '4.5px solid white',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: '30px',
                            fontWeight: '900',
                            color: 'white',
                            boxShadow: `0 4px 20px ${community.color}66`,
                        }"
                    >
                        {{ community.label.replace('#', '').charAt(0).toUpperCase() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Card body -->
        <div
            style="
                background: rgba(255, 255, 255, 0.92);
                backdrop-filter: blur(20px);
                border: 1px solid #4ebcff22;
                border-top: none;
                border-radius: 0 0 22px 22px;
                padding: 58px 32px 28px;
                position: relative;
                z-index: 1;
            "
        >
            <div
                style="
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                    flex-wrap: wrap;
                "
            >
                <div style="flex: 1; min-width: 200px">
                    <h1
                        style="
                            font-size: 22px;
                            font-weight: 900;
                            color: #1a3a4a;
                            margin: 0 0 3px;
                            letter-spacing: -0.02em;
                        "
                    >
                        {{ community.title }}
                    </h1>
                    <p style="font-size: 12px; color: #5a7a8a; margin: 0 0 10px; font-style: italic">
                        {{ community.tagline }}
                    </p>
                    <p style="font-size: 13px; font-weight: 700; margin: 0" :style="{ color: community.color }">
                        {{ community.members }} membros · {{ community.posts_count }} posts
                    </p>

                    <div v-if="community.creator" style="display: flex; align-items: center; gap: 6px; margin-top: 8px">
                        <span style="font-size: 11px; color: #8ba0b0; font-weight: 600">Criado por</span>
                        <component
                            :is="community.creator.username ? Link : 'span'"
                            :href="
                                community.creator.username
                                    ? route('profile.show', community.creator.username)
                                    : undefined
                            "
                            style="display: flex; align-items: center; gap: 5px; text-decoration: none"
                        >
                            <img
                                v-if="community.creator.avatar"
                                :src="clImg(community.creator.avatar, 40, 40, 'fill', 'face')"
                                :style="{
                                    width: '18px',
                                    height: '18px',
                                    borderRadius: '50%',
                                    objectFit: 'cover',
                                    border: `1.5px solid ${community.color}`,
                                    display: 'block',
                                }"
                            />
                            <div
                                v-else
                                :style="{
                                    width: '18px',
                                    height: '18px',
                                    borderRadius: '50%',
                                    background: community.creator.avatar_color,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: '8px',
                                    fontWeight: '800',
                                    color: 'white',
                                }"
                            >
                                {{ community.creator.name[0] }}
                            </div>
                            <span :style="{ fontSize: '12px', fontWeight: '700', color: community.color }">{{
                                community.creator.name
                            }}</span>
                        </component>
                    </div>
                </div>

                <!-- Owner controls -->
                <div
                    v-if="authUser && isOwn"
                    style="display: flex; gap: 8px; align-items: center; flex-shrink: 0; margin-top: 4px"
                >
                    <div
                        :style="{
                            padding: '9px 18px',
                            borderRadius: '99px',
                            border: `1.5px solid ${community.color}44`,
                            background: community.color + '12',
                            color: community.color,
                            fontSize: '12px',
                            fontWeight: '700',
                            whiteSpace: 'nowrap',
                        }"
                    >
                        ✦ Criador
                    </div>
                    <Link
                        :href="route('community.members', community.id)"
                        style="
                            padding: 9px 16px;
                            border-radius: 99px;
                            border: none;
                            background: #f0f8ff;
                            color: #5a7a8a;
                            font-size: 12px;
                            font-weight: 700;
                            cursor: pointer;
                            white-space: nowrap;
                            text-decoration: none;
                            transition: all 0.2s;
                        "
                        @mouseenter="
                            $event.currentTarget.style.background = '#e0f0fc';
                            $event.currentTarget.style.color = '#009ac7';
                        "
                        @mouseleave="
                            $event.currentTarget.style.background = '#f0f8ff';
                            $event.currentTarget.style.color = '#5a7a8a';
                        "
                    >
                        👥 Membros
                    </Link>
                    <button
                        @click="emit('open-edit')"
                        style="
                            padding: 9px 16px;
                            border-radius: 99px;
                            border: none;
                            background: #f0f8ff;
                            color: #5a7a8a;
                            font-size: 12px;
                            font-weight: 700;
                            cursor: pointer;
                            white-space: nowrap;
                            transition: all 0.2s;
                        "
                        @mouseenter="
                            $event.currentTarget.style.background = '#e0f0fc';
                            $event.currentTarget.style.color = '#009ac7';
                        "
                        @mouseleave="
                            $event.currentTarget.style.background = '#f0f8ff';
                            $event.currentTarget.style.color = '#5a7a8a';
                        "
                    >
                        ⚙ Editar
                    </button>
                </div>

                <!-- Member join/leave -->
                <template v-else-if="authUser && !isOwn">
                    <!-- Owner/admin members link (non-owner path) -->
                    <Link
                        v-if="canManage && !isOwn"
                        :href="route('community.members', community.id)"
                        style="
                            padding: 9px 16px;
                            border-radius: 99px;
                            border: none;
                            background: #f0f8ff;
                            color: #5a7a8a;
                            font-size: 12px;
                            font-weight: 700;
                            cursor: pointer;
                            white-space: nowrap;
                            text-decoration: none;
                            align-self: flex-start;
                            margin-top: 4px;
                            transition: all 0.2s;
                        "
                        @mouseenter="
                            $event.currentTarget.style.background = '#e0f0fc';
                            $event.currentTarget.style.color = '#009ac7';
                        "
                        @mouseleave="
                            $event.currentTarget.style.background = '#f0f8ff';
                            $event.currentTarget.style.color = '#5a7a8a';
                        "
                    >
                        👥 Membros
                    </Link>
                    <button
                        v-if="!isMember"
                        :style="{
                            padding: '9px 22px',
                            borderRadius: '99px',
                            border: 'none',
                            background: community.color,
                            color: 'white',
                            fontSize: '12px',
                            fontWeight: '700',
                            cursor: 'pointer',
                            boxShadow: `0 4px 14px ${community.color}44`,
                            whiteSpace: 'nowrap',
                            transition: 'opacity .2s',
                            alignSelf: 'flex-start',
                            marginTop: '4px',
                        }"
                        @click="joinCommunity"
                        @mouseenter="$event.target.style.opacity = '.8'"
                        @mouseleave="$event.target.style.opacity = '1'"
                    >
                        Entrar
                    </button>
                    <button
                        v-else
                        :style="{
                            padding: '9px 22px',
                            borderRadius: '99px',
                            border: `1.5px solid ${community.color}66`,
                            background: 'transparent',
                            color: community.color,
                            fontSize: '12px',
                            fontWeight: '700',
                            cursor: 'pointer',
                            whiteSpace: 'nowrap',
                            transition: 'all .2s',
                            alignSelf: 'flex-start',
                            marginTop: '4px',
                        }"
                        @click="leaveCommunity"
                        @mouseenter="$event.currentTarget.style.background = community.color + '14'"
                        @mouseleave="$event.currentTarget.style.background = 'transparent'"
                    >
                        ✓ Membro
                    </button>
                </template>

                <!-- Guest — redirect to login -->
                <Link
                    v-else-if="!authUser"
                    :href="route('login')"
                    :style="{
                        padding: '9px 22px',
                        borderRadius: '99px',
                        border: 'none',
                        background: community.color,
                        color: 'white',
                        fontSize: '12px',
                        fontWeight: '700',
                        cursor: 'pointer',
                        boxShadow: `0 4px 14px ${community.color}44`,
                        whiteSpace: 'nowrap',
                        transition: 'opacity .2s',
                        alignSelf: 'flex-start',
                        marginTop: '4px',
                        textDecoration: 'none',
                        display: 'inline-block',
                    }"
                    @mouseenter="$event.currentTarget.style.opacity = '.8'"
                    @mouseleave="$event.currentTarget.style.opacity = '1'"
                    >Entrar</Link
                >
            </div>

            <p v-if="community.description" style="font-size: 13px; color: #4a6a7a; margin: 16px 0 0; line-height: 1.6">
                {{ community.description }}
            </p>

            <!-- Activity bar -->
            <div style="margin-top: 18px">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px">
                    <span
                        style="
                            font-size: 10px;
                            color: #8ba0b0;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: 0.08em;
                        "
                        >Atividade esta semana</span
                    >
                    <span
                        :style="{
                            fontSize: '10px',
                            fontWeight: '700',
                            color: activityLevel.color,
                        }"
                        >{{ activityLevel.label }}</span
                    >
                </div>
                <div style="height: 4px; background: #e8f4fb; border-radius: 99px; overflow: hidden">
                    <div
                        :style="{
                            height: '100%',
                            borderRadius: '99px',
                            background: activityLevel.color,
                            width: activityLevel.pct > 0 ? `${activityLevel.pct}%` : '0%',
                            transition: 'width .6s ease',
                        }"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
