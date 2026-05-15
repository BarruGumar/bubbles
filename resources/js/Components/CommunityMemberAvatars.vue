<script setup>
import { Link } from '@inertiajs/vue3';
import { clImg } from '@/Composables/useCloudinary';

defineProps({
    members: { type: Array, required: true },
});

function formatInitial(name) {
    return (name ?? '?')[0].toUpperCase();
}
</script>

<template>
    <div
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
                v-for="member in members"
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
</template>
