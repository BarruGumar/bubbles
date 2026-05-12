<script setup>
import { useToast } from '@/Composables/useToast'

const { toasts, dismiss } = useToast()
</script>

<template>
    <Teleport to="body">
        <div style="
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            display: flex; flex-direction: column; gap: 10px;
            pointer-events: none;
        ">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    style="
                        pointer-events: all;
                        display: flex; align-items: center; gap: 12px;
                        padding: 12px 18px;
                        border-radius: 14px;
                        font-size: 13.5px; font-weight: 600;
                        color: white;
                        min-width: 220px; max-width: 360px;
                        box-shadow: 0 8px 32px rgba(0,0,0,0.15);
                        cursor: pointer;
                        backdrop-filter: blur(12px);
                    "
                    :style="{
                        background: toast.type === 'error'
                            ? 'linear-gradient(135deg,#c74a6b,#e07b9a)'
                            : toast.type === 'warning'
                                ? 'linear-gradient(135deg,#e0a040,#f0c060)'
                                : 'linear-gradient(135deg,#009ac7,#4ebcff)',
                    }"
                    @click="dismiss(toast.id)"
                >
                    <span style="font-size: 16px; flex-shrink: 0;">
                        {{ toast.type === 'error' ? '✕' : toast.type === 'warning' ? '⚠' : '✓' }}
                    </span>
                    <span style="flex: 1; line-height: 1.4;">{{ toast.message }}</span>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active { transition: all .28s cubic-bezier(.2,.8,.2,1) }
.toast-leave-active { transition: all .22s ease }
.toast-enter-from   { opacity: 0; transform: translateY(16px) scale(.95) }
.toast-leave-to     { opacity: 0; transform: translateX(24px) scale(.95) }
</style>
