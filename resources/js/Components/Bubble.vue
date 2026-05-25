<script setup>
import { computed } from 'vue';
import { clImg } from '@/Composables/useCloudinary';

const props = defineProps({
    bubble: { type: Object, required: true },
    isDragging: { type: Boolean, default: false },
    isHovered: { type: Boolean, default: false },
    anyHovered: { type: Boolean, default: false },
    isConnectSource: { type: Boolean, default: false },
});

defineEmits(['mousedown', 'mouseenter', 'mouseleave', 'contextmenu', 'touchstart']);

const ARC_C = 276.46;

const activityDash = computed(() => {
    const filled = ARC_C * props.bubble.activity;
    return `${filled} ${ARC_C - filled}`;
});

const containerStyle = computed(() => {
    const { x, y, size, color, spawnScale, selected, activity, phase } = props.bubble;
    const breathScale = 1 + Math.sin(phase || 0) * 0.008 * (0.4 + activity);
    const finalScale = spawnScale * (props.isHovered && !selected ? 1.07 : 1) * breathScale;

    let shadow;
    if (props.isConnectSource) {
        shadow = `0 0 0 4px white, 0 0 0 7px #009ac7, 0 0 24px #009ac744`;
    } else if (props.isHovered && !selected) {
        shadow = `0 14px 40px ${color}99, 0 4px 14px ${color}44, 0 0 ${18 + activity * 12}px ${color}44`;
    } else {
        shadow = `0 8px 26px ${color}44, 0 2px 8px ${color}22, 0 0 ${10 + activity * 8}px ${color}22`;
    }

    const { image } = props.bubble;
    const optimizedImage = image ? clImg(image, 300, 300, 'fill') : null;

    return {
        position: 'absolute',
        zIndex: props.isHovered && !selected ? 32 : 20,
        left: `${x}px`,
        top: `${y}px`,
        width: `${size}px`,
        height: `${size}px`,
        borderRadius: '50%',
        backgroundImage: optimizedImage
            ? `radial-gradient(circle at 38% 32%, ${color}55 0%, ${color}99 100%), url(${optimizedImage})`
            : `radial-gradient(circle at 38% 32%, ${color}ee 0%, ${color} 60%)`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        cursor: selected ? 'default' : props.isDragging ? 'grabbing' : 'grab',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '3px',
        opacity: selected ? 0 : props.anyHovered && !props.isHovered ? 0.52 : 1,
        pointerEvents: selected ? 'none' : 'auto',
        boxShadow: shadow,
        transform: `scale(${finalScale.toFixed(4)})`,
        transition: props.isDragging
            ? 'none'
            : 'box-shadow .35s ease, transform .45s cubic-bezier(.22,.78,.26,1), opacity .3s ease',
        overflow: 'hidden',
    };
});
</script>

<template>
    <div
        :style="containerStyle"
        @mousedown="$emit('mousedown', $event)"
        @mouseenter="$emit('mouseenter')"
        @mouseleave="$emit('mouseleave')"
        @contextmenu="$emit('contextmenu', $event)"
        @touchstart="$emit('touchstart', $event)"
    >
        <!-- Glass highlight -->
        <div
            :style="{
                position: 'absolute',
                top: '7px',
                left: '14%',
                width: '72%',
                height: '36%',
                borderRadius: '50%',
                background: 'rgba(255,255,255,0.24)',
                pointerEvents: 'none',
                transform: 'rotate(-10deg)',
            }"
        />

        <!-- Activity ring -->
        <svg
            viewBox="0 0 100 100"
            :style="{ position: 'absolute', inset: 0, width: '100%', height: '100%', pointerEvents: 'none' }"
        >
            <circle cx="50" cy="50" r="44" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="3.5" />
            <circle
                cx="50"
                cy="50"
                r="44"
                fill="none"
                stroke="rgba(255,255,255,0.5)"
                stroke-width="3.5"
                stroke-linecap="round"
                :stroke-dasharray="activityDash"
                stroke-dashoffset="69"
                transform="rotate(-90 50 50)"
            />
        </svg>

        <!-- Label -->
        <span
            :style="{
                fontSize: `${Math.max(9, Math.min(12, bubble.size / 9))}px`,
                fontWeight: '800',
                color: 'white',
                letterSpacing: '.02em',
                textShadow: '0 1px 4px rgba(0,0,0,.28)',
                textAlign: 'center',
                padding: '0 10px',
                lineHeight: 1.2,
                position: 'relative',
                zIndex: 1,
            }"
            >{{ bubble.label }}</span
        >

        <span
            :style="{
                fontSize: '9px',
                color: 'rgba(255,255,255,.72)',
                fontWeight: '600',
                position: 'relative',
                zIndex: 1,
            }"
            >{{ bubble.members }} membros</span
        >

        <!-- Connect source badge -->
        <div
            v-if="isConnectSource"
            :style="{
                position: 'absolute',
                top: '-8px',
                right: '-8px',
                background: '#009ac7',
                color: 'white',
                borderRadius: '99px',
                fontSize: '8px',
                padding: '2px 7px',
                border: '2px solid white',
                fontWeight: '700',
                zIndex: 31,
            }"
        >
            origem
        </div>
    </div>
</template>
