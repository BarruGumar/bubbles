<script setup>
defineProps({
    size: { type: String, default: 'md' }, // 'sm' | 'md' | 'lg'
    tooltip: { type: Boolean, default: true },
});
</script>

<template>
    <span
        class="site-owner-badge"
        :class="`site-owner-badge--${size}`"
        :title="tooltip ? 'Fundador e Dono do bubbles' : undefined"
        aria-label="Dono do Site"
    >
        <span class="site-owner-badge__crown">👑</span>
        <span class="site-owner-badge__label">Dono</span>
    </span>
</template>

<style scoped>
.site-owner-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: linear-gradient(135deg, #f5d060 0%, #d4a017 50%, #c084fc 100%);
    border-radius: 99px;
    font-weight: 800;
    color: #fff;
    white-space: nowrap;
    letter-spacing: 0.01em;
    box-shadow: 0 2px 8px rgba(212, 160, 23, 0.35);
    cursor: default;
    user-select: none;
}

.site-owner-badge--sm {
    padding: 1px 7px;
    font-size: 9px;
}
.site-owner-badge--md {
    padding: 2px 9px;
    font-size: 10px;
}
.site-owner-badge--lg {
    padding: 4px 13px;
    font-size: 12px;
}

.site-owner-badge__crown {
    font-size: 1.1em;
    line-height: 1;
}
</style>
