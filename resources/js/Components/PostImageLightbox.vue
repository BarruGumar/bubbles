<script setup>
import { ref, watch, nextTick, onUnmounted } from 'vue';
import { clImg } from '@/Composables/useCloudinary';
import { useToast } from '@/Composables/useToast';

const props = defineProps({
    imageUrl: { type: String, required: true },
    open: { type: Boolean, required: true },
});
const emit = defineEmits(['close']);

const { show: toast } = useToast();

const zoom = ref(1);
const panX = ref(0);
const panY = ref(0);
const isDragging = ref(false);
const lightboxEl = ref(null);

function close() {
    emit('close');
}

function onWheel(e) {
    const delta = e.deltaY < 0 ? 0.15 : -0.15;
    const newZoom = Math.min(5, Math.max(0.5, zoom.value + delta));
    const cx = window.innerWidth / 2;
    const cy = window.innerHeight / 2;
    const dx = e.clientX - cx;
    const dy = e.clientY - cy;
    const ratio = newZoom / zoom.value;
    panX.value = dx * (1 - ratio) + panX.value * ratio;
    panY.value = dy * (1 - ratio) + panY.value * ratio;
    zoom.value = newZoom;
    if (zoom.value <= 1) {
        panX.value = 0;
        panY.value = 0;
    }
}

function zoomAtPoint(e) {
    const newZoom = zoom.value >= 2 ? 1 : 2.5;
    if (newZoom <= 1) {
        zoom.value = 1;
        panX.value = 0;
        panY.value = 0;
        return;
    }
    const cx = window.innerWidth / 2;
    const cy = window.innerHeight / 2;
    const ratio = newZoom / zoom.value;
    panX.value = (e.clientX - cx) * (1 - ratio) + panX.value * ratio;
    panY.value = (e.clientY - cy) * (1 - ratio) + panY.value * ratio;
    zoom.value = newZoom;
}

let _dragMove = null;
let _dragUp = null;

function startDrag(e) {
    const startX = e.clientX - panX.value;
    const startY = e.clientY - panY.value;
    let moved = false;
    _dragMove = (ev) => {
        if (!moved && (Math.abs(ev.clientX - e.clientX) > 3 || Math.abs(ev.clientY - e.clientY) > 3)) moved = true;
        if (!moved) return;
        isDragging.value = true;
        panX.value = ev.clientX - startX;
        panY.value = ev.clientY - startY;
    };
    _dragUp = () => {
        isDragging.value = false;
        document.removeEventListener('mousemove', _dragMove);
        document.removeEventListener('mouseup', _dragUp);
        _dragMove = null;
        _dragUp = null;
    };
    document.addEventListener('mousemove', _dragMove);
    document.addEventListener('mouseup', _dragUp);
}

onUnmounted(() => {
    if (_dragMove) document.removeEventListener('mousemove', _dragMove);
    if (_dragUp) document.removeEventListener('mouseup', _dragUp);
});

async function downloadImage() {
    try {
        const url = clImg(props.imageUrl, 2400, 0, 'limit');
        const resp = await fetch(url);
        const blob = await resp.blob();
        const blobUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = 'post-image.jpg';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(blobUrl);
    } catch {
        toast('Erro ao baixar imagem.', 'error');
    }
}

function handleKeydown(e) {
    if (e.key === 'Escape') close();
    if (e.key === '+' || e.key === '=') zoom.value = Math.min(5, zoom.value + 0.25);
    if (e.key === '-') {
        zoom.value = Math.max(0.5, zoom.value - 0.25);
        if (zoom.value <= 1) {
            panX.value = 0;
            panY.value = 0;
        }
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            zoom.value = 1;
            panX.value = 0;
            panY.value = 0;
            nextTick(() => lightboxEl.value?.focus());
            document.addEventListener('keydown', handleKeydown);
        } else {
            document.removeEventListener('keydown', handleKeydown);
        }
    },
);
</script>

<template>
    <Teleport to="body">
        <Transition name="lb">
            <div
                v-if="open"
                ref="lightboxEl"
                tabindex="-1"
                style="
                    position: fixed;
                    inset: 0;
                    z-index: 9999;
                    background: rgba(0, 0, 0, 0.93);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    outline: none;
                "
                @click.self="close"
                @wheel.prevent="onWheel"
            >
                <!-- Top toolbar -->
                <div
                    style="
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        padding: 14px 18px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        background: linear-gradient(to bottom, rgba(0, 0, 0, 0.55), transparent);
                        z-index: 1;
                    "
                >
                    <div style="display: flex; align-items: center; gap: 6px">
                        <button
                            @click.stop="
                                zoom = Math.max(0.5, zoom - 0.25);
                                if (zoom <= 1) {
                                    panX = 0;
                                    panY = 0;
                                }
                            "
                            style="
                                width: 32px;
                                height: 32px;
                                border-radius: 8px;
                                border: none;
                                background: rgba(255, 255, 255, 0.15);
                                color: white;
                                font-size: 18px;
                                cursor: pointer;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                transition: background 0.15s;
                                font-weight: 300;
                                line-height: 1;
                            "
                            @mouseenter="$event.currentTarget.style.background = 'rgba(255,255,255,0.25)'"
                            @mouseleave="$event.currentTarget.style.background = 'rgba(255,255,255,0.15)'"
                        >
                            −
                        </button>
                        <span
                            style="
                                color: rgba(255, 255, 255, 0.75);
                                font-size: 12px;
                                font-weight: 700;
                                min-width: 42px;
                                text-align: center;
                                font-variant-numeric: tabular-nums;
                            "
                            >{{ Math.round(zoom * 100) }}%</span
                        >
                        <button
                            @click.stop="zoom = Math.min(5, zoom + 0.25)"
                            style="
                                width: 32px;
                                height: 32px;
                                border-radius: 8px;
                                border: none;
                                background: rgba(255, 255, 255, 0.15);
                                color: white;
                                font-size: 18px;
                                cursor: pointer;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                transition: background 0.15s;
                                font-weight: 300;
                                line-height: 1;
                            "
                            @mouseenter="$event.currentTarget.style.background = 'rgba(255,255,255,0.25)'"
                            @mouseleave="$event.currentTarget.style.background = 'rgba(255,255,255,0.15)'"
                        >
                            +
                        </button>
                        <button
                            v-if="zoom !== 1"
                            @click.stop="
                                zoom = 1;
                                panX = 0;
                                panY = 0;
                            "
                            style="
                                padding: 0 10px;
                                height: 32px;
                                border-radius: 8px;
                                border: none;
                                background: rgba(255, 255, 255, 0.12);
                                color: rgba(255, 255, 255, 0.7);
                                font-size: 11px;
                                cursor: pointer;
                                font-weight: 600;
                                transition: background 0.15s;
                            "
                            @mouseenter="$event.currentTarget.style.background = 'rgba(255,255,255,0.22)'"
                            @mouseleave="$event.currentTarget.style.background = 'rgba(255,255,255,0.12)'"
                        >
                            Reset
                        </button>
                    </div>
                    <div style="display: flex; gap: 8px">
                        <button
                            @click.stop="downloadImage"
                            style="
                                width: 36px;
                                height: 36px;
                                border-radius: 10px;
                                border: none;
                                background: rgba(255, 255, 255, 0.15);
                                color: white;
                                cursor: pointer;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                transition: background 0.15s;
                            "
                            @mouseenter="$event.currentTarget.style.background = 'rgba(0,154,199,0.5)'"
                            @mouseleave="$event.currentTarget.style.background = 'rgba(255,255,255,0.15)'"
                            title="Baixar imagem"
                        >
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                <polyline points="7 10 12 15 17 10" />
                                <line x1="12" y1="15" x2="12" y2="3" />
                            </svg>
                        </button>
                        <button
                            @click.stop="close"
                            style="
                                width: 36px;
                                height: 36px;
                                border-radius: 10px;
                                border: none;
                                background: rgba(255, 255, 255, 0.15);
                                color: white;
                                font-size: 20px;
                                cursor: pointer;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                transition: background 0.15s;
                                line-height: 1;
                            "
                            @mouseenter="$event.currentTarget.style.background = 'rgba(224,85,85,0.5)'"
                            @mouseleave="$event.currentTarget.style.background = 'rgba(255,255,255,0.15)'"
                            title="Fechar (Esc)"
                        >
                            ×
                        </button>
                    </div>
                </div>

                <img
                    :src="clImg(imageUrl, 2400, 0, 'limit')"
                    draggable="false"
                    :style="{
                        maxWidth: '90vw',
                        maxHeight: '90vh',
                        objectFit: 'contain',
                        borderRadius: zoom > 1 ? '4px' : '12px',
                        transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
                        transition: isDragging ? 'none' : 'transform .2s cubic-bezier(.2,.8,.2,1)',
                        cursor: isDragging ? 'grabbing' : 'grab',
                        userSelect: 'none',
                        boxShadow: '0 8px 60px rgba(0,0,0,0.6)',
                    }"
                    @dblclick.stop="zoomAtPoint"
                    @mousedown.stop="startDrag"
                />

                <p
                    style="
                        position: absolute;
                        bottom: 18px;
                        left: 50%;
                        transform: translateX(-50%);
                        color: rgba(255, 255, 255, 0.35);
                        font-size: 11px;
                        white-space: nowrap;
                        pointer-events: none;
                    "
                >
                    Duplo clique para zoom · Scroll para zoom · Arrastar para mover · Esc para fechar
                </p>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.lb-enter-active {
    transition: opacity 0.22s ease;
}
.lb-leave-active {
    transition: opacity 0.18s ease;
}
.lb-enter-from {
    opacity: 0;
}
.lb-leave-to {
    opacity: 0;
}
</style>
