<script setup>
import { useToast } from '@/Composables/useToast';

const { toasts, dismiss } = useToast();

function bg(type) {
    if (type === 'error') return 'linear-gradient(135deg,#c74a6b,#e07b9a)';
    if (type === 'warning') return 'linear-gradient(135deg,#d08a00,#e0b040)';
    if (type === 'success') return 'linear-gradient(135deg,#1a9e6b,#2ec98a)';
    return 'linear-gradient(135deg,#009ac7,#4ebcff)';
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
                        boxShadow: '0 8px 32px rgba(0,0,0,0.15)',
                        cursor: 'pointer',
                        backdropFilter: 'blur(12px)',
                        background: bg(toast.type),
                    }"
                    @click="dismiss(toast.id)"
                >
                    <span style="font-size: 15px; flex-shrink: 0; font-weight: 900">{{ icon(toast.type) }}</span>
                    <span style="flex: 1; line-height: 1.4">{{ toast.message }}</span>
                    <span style="font-size: 16px; opacity: 0.6; flex-shrink: 0; line-height: 1">×</span>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
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
