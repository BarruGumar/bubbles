<script setup>
import { useToast } from '@/Composables/useToast';

const { toasts, dismiss } = useToast();

function borderCol(type) {
    if (type === 'error')   return 'rgba(199,74,107,.4)';
    if (type === 'warning') return 'rgba(208,138,0,.4)';
    if (type === 'success') return 'rgba(30,160,107,.4)';
    return 'rgba(0,154,199,.4)';
}

function glow(type) {
    if (type === 'error')   return '0 4px 20px rgba(199,74,107,.25)';
    if (type === 'warning') return '0 4px 20px rgba(208,138,0,.25)';
    if (type === 'success') return '0 4px 20px rgba(30,160,107,.25)';
    return '0 4px 20px rgba(0,154,199,.25)';
}

function iconCol(type) {
    if (type === 'error')   return '#e87a9a';
    if (type === 'warning') return '#e0c060';
    if (type === 'success') return '#4fffaa';
    return '#4ebcff';
}

function icon(type) {
    if (type === 'error') return '✕';
    if (type === 'warning') return '⚠';
    return '✓';
}
</script>

<template>
    <Teleport to="body">
        <div
            role="region"
            aria-label="Notificações"
            aria-live="polite"
            style="
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
            "
        >
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :role="toast.type === 'error' ? 'alert' : 'status'"
                    class="toast-item"
                    :style="{
                        pointerEvents: 'all',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '12px',
                        padding: '12px 18px',
                        borderRadius: '14px',
                        fontSize: '13.5px',
                        fontWeight: '600',
                        color: 'white',
                        minWidth: '220px',
                        maxWidth: '360px',
                        cursor: 'pointer',
                        position: 'relative',
                        overflow: 'hidden',
                        background: 'rgba(13,27,42,.88)',
                        border: `1px solid ${borderCol(toast.type)}`,
                        boxShadow: `inset 0 1px 0 rgba(255,255,255,.1), ${glow(toast.type)}`,
                        backdropFilter: 'blur(16px)',
                    }"
                    @click="dismiss(toast.id)"
                >
                    <span :style="{ fontSize: '15px', flexShrink: '0', fontWeight: '900', color: iconCol(toast.type) }">{{ icon(toast.type) }}</span>
                    <span style="flex: 1; line-height: 1.4">{{ toast.message }}</span>
                    <span style="font-size: 16px; opacity: 0.4; flex-shrink: 0; line-height: 1">×</span>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-item::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 50%;
    border-radius: 14px 14px 0 0;
    background: linear-gradient(to bottom, rgba(255,255,255,.06), transparent);
    pointer-events: none;
    z-index: 1;
}
.toast-enter-active {
    transition: all 0.28s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.toast-leave-active {
    transition: all 0.22s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateY(16px) scale(0.95);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(24px) scale(0.95);
}
</style>
