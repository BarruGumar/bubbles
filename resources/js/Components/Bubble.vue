<script setup>
import { computed } from 'vue';
import { clImg } from '@/Composables/useCloudinary';

const props = defineProps({
    bubble: { type: Object, required: true },
    isDragging: { type: Boolean, default: false },
    isHovered: { type: Boolean, default: false },
    anyHovered: { type: Boolean, default: false },
});

defineEmits(['mousedown', 'mouseenter', 'mouseleave', 'touchstart']);

const ARC_C = 276.46;

const optimizedImage = computed(() =>
    props.bubble.image ? clImg(props.bubble.image, 300, 300, 'fill') : null,
);

const activityDash = computed(() => {
    const filled = ARC_C * props.bubble.activity;
    return `${filled} ${ARC_C - filled}`;
});

// Position wrapper — translate() for GPU-composited movement, no layout recalculation
const posStyle = computed(() => {
    const { x, y, size, selected } = props.bubble;
    return {
        position: 'absolute',
        left: 0,
        top: 0,
        width: `${size}px`,
        height: `${size}px`,
        zIndex: props.isHovered && !selected ? 32 : 20,
        willChange: 'transform',
        transform: `translate(${x}px, ${y}px)`,
        pointerEvents: selected ? 'none' : 'auto',
        touchAction: 'none',
        cursor: selected ? 'default' : props.isDragging ? 'grabbing' : 'grab',
        opacity: selected ? 0 : props.anyHovered && !props.isHovered ? 0.52 : 1,
        transition: 'opacity .3s ease',
    };
});

// Visual bubble — scale transition for hover/spawn; position changes are on the wrapper
const visualStyle = computed(() => {
    const { color, spawnScale, selected, activity, breathScale } = props.bubble;
    // breathScale is precomputed in usePhysics.js — no trig in the render path
    const finalScale = spawnScale * (props.isHovered && !selected ? 1.07 : 1) * (breathScale ?? 1);

    let shadow;
    if (props.isHovered && !selected) {
        shadow = `0 14px 40px ${color}99, 0 4px 14px ${color}44, 0 0 ${18 + activity * 12}px ${color}44`;
    } else {
        shadow = `0 8px 26px ${color}44, 0 2px 8px ${color}22, 0 0 ${10 + activity * 8}px ${color}22`;
    }

    return {
        width: '100%',
        height: '100%',
        borderRadius: '50%',
        backgroundImage: optimizedImage.value
            ? `radial-gradient(circle at 38% 32%, ${color}55 0%, ${color}99 100%), url(${optimizedImage.value})`
            : `radial-gradient(circle at 38% 32%, ${color}ee 0%, ${color} 60%)`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '3px',
        overflow: 'hidden',
        boxShadow: shadow,
        transform: `scale(${finalScale.toFixed(4)})`,
        transition: props.isDragging
            ? 'none'
            : 'box-shadow .35s ease, transform .45s cubic-bezier(.22,.78,.26,1)',
    };
});
</script>

<template>
    <!-- Position wrapper: GPU-composited via translate(), no transition (physics drives it) -->
    <div
        :style="posStyle"
        @mousedown="$emit('mousedown', $event)"
        @mouseenter="$emit('mouseenter')"
        @mouseleave="$emit('mouseleave')"
        @touchstart="$emit('touchstart', $event)"
    >
        <!-- Visual bubble: scale transition for hover/spawn only -->
        <div :style="visualStyle">
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
        </div>

    </div>
</template>
