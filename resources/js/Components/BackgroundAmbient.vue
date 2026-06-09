<script setup>
import { onMounted, ref } from 'vue';

const bubbles = ref([]);

function generateBubbles() {
    return Array.from({ length: 14 }, (_, index) => {
        const size = 14 + Math.round(Math.random() * 28 + (index % 4 === 0 ? 8 : 0));
        const left = `${(3 + Math.random() * 94).toFixed(1)}%`;
        const opacity = Number((0.18 + Math.random() * 0.42).toFixed(2));
        const duration = 11 + Math.round(Math.random() * 14);
        const delay = -Math.random() * 16;

        return {
            id: `ambient-${index + 1}`,
            size: `${size}px`,
            left,
            opacity,
            duration: `${duration}s`,
            delay: `${delay.toFixed(1)}s`,
        };
    });
}

onMounted(() => {
    bubbles.value = generateBubbles();
});
</script>

<template>
    <div class="ambient-layer" aria-hidden="true">
        <div
            v-for="bubble in bubbles"
            :key="bubble.id"
            class="ambient-bubble"
            :style="{
                left: bubble.left,
                width: bubble.size,
                height: bubble.size,
                opacity: bubble.opacity,
                animationDuration: bubble.duration,
                animationDelay: bubble.delay,
            }"
        />
    </div>
</template>

<style scoped>
.ambient-layer {
    position: fixed;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: 0;
}

.ambient-bubble {
    position: absolute;
    bottom: -80px;
    border-radius: 9999px;
    background: radial-gradient(circle at 38% 32%, rgba(255,255,255,0.38) 0%, rgba(78,188,255,0.18) 58%, rgba(78,188,255,0.06) 82%, transparent 100%);
    border: 1px solid rgba(78, 188, 255, 0.24);
    box-shadow: 0 0 14px rgba(78,188,255,0.12), inset 0 0 10px rgba(255,255,255,0.12);
    filter: saturate(1.05);
    animation: ambient-rise linear infinite;
    will-change: transform;
}

@keyframes ambient-rise {
    0% { transform: translateY(0) scale(1); }
    100% { transform: translateY(calc(-100vh - 110px)) scale(1.08); }
}
</style>
