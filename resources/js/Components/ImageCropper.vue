<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    src: { type: String, required: true },
    aspectRatio: { type: Number, default: 1 }, // width / height
    circle: { type: Boolean, default: false }, // visual indicator only
    outputWidth: { type: Number, default: 400 },
    outputHeight: { type: Number, default: 400 },
});

const emit = defineEmits(['confirm', 'cancel']);

// ── internal image state (non-reactive plain vars) ──
let imgEl = null;
let imgNW = 0;
let imgNH = 0;
let baseScale = 1; // scale that makes the image "cover" the crop area

// ── reactive state ──
const displayW = ref(320);
const displayH = ref(320);
const scale = ref(1); // relative to baseScale (slider 1-4)
const ox = ref(0); // pan offset X (display px)
const oy = ref(0); // pan offset Y (display px)
const ready = ref(false);
const dragging = ref(false);
const canvasRef = ref(null);

// ── setup on mount ──
onMounted(() => {
    const maxW = Math.min(520, window.innerWidth - 48);
    const maxH = Math.min(400, window.innerHeight - 220);
    let w = maxW;
    let h = w / props.aspectRatio;
    if (h > maxH) {
        h = maxH;
        w = h * props.aspectRatio;
    }
    displayW.value = Math.round(w);
    displayH.value = Math.round(h);

    imgEl = new Image();
    imgEl.onload = () => {
        imgNW = imgEl.naturalWidth;
        imgNH = imgEl.naturalHeight;
        baseScale = Math.max(displayW.value / imgNW, displayH.value / imgNH);
        scale.value = 1;
        ox.value = 0;
        oy.value = 0;
        ready.value = true;
    };
    imgEl.src = props.src;

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
    window.addEventListener('touchmove', onTouchMove, { passive: false });
    window.addEventListener('touchend', onTouchEnd);
});

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
    window.removeEventListener('touchmove', onTouchMove);
    window.removeEventListener('touchend', onTouchEnd);
});

// ── helpers ──
function clamp(x, y) {
    const ds = baseScale * scale.value;
    const maxX = Math.max(0, (imgNW * ds - displayW.value) / 2);
    const maxY = Math.max(0, (imgNH * ds - displayH.value) / 2);
    return {
        x: Math.max(-maxX, Math.min(maxX, x)),
        y: Math.max(-maxY, Math.min(maxY, y)),
    };
}

// ── image CSS transform inside crop area ──
const imgTransform = computed(() => {
    if (!ready.value) return { display: 'none' };
    return {
        position: 'absolute',
        top: '50%',
        left: '50%',
        width: imgNW + 'px',
        height: imgNH + 'px',
        transform: `translate(calc(-50% + ${ox.value}px), calc(-50% + ${oy.value}px)) scale(${baseScale * scale.value})`,
        transformOrigin: 'center center',
        userSelect: 'none',
        pointerEvents: 'none',
        maxWidth: 'none',
    };
});

// ── drag ──
let dragSX = 0,
    dragSY = 0,
    dragSOX = 0,
    dragSOY = 0;

function startDrag(cx, cy) {
    dragging.value = true;
    dragSX = cx;
    dragSY = cy;
    dragSOX = ox.value;
    dragSOY = oy.value;
}

function moveDrag(cx, cy) {
    if (!dragging.value) return;
    const r = clamp(dragSOX + cx - dragSX, dragSOY + cy - dragSY);
    ox.value = r.x;
    oy.value = r.y;
}

function onPointerDown(e) {
    const c = e.touches ? e.touches[0] : e;
    startDrag(c.clientX, c.clientY);
    e.preventDefault();
}

function onMouseMove(e) {
    moveDrag(e.clientX, e.clientY);
}
function onMouseUp() {
    dragging.value = false;
}
function onTouchMove(e) {
    e.preventDefault();
    moveDrag(e.touches[0].clientX, e.touches[0].clientY);
}
function onTouchEnd() {
    dragging.value = false;
}

// ── zoom ──
function onWheel(e) {
    e.preventDefault();
    scale.value = Math.max(1, Math.min(4, scale.value + (e.deltaY > 0 ? -0.08 : 0.08)));
    const r = clamp(ox.value, oy.value);
    ox.value = r.x;
    oy.value = r.y;
}

function onSlider(e) {
    scale.value = Number(e.target.value) / 100;
    const r = clamp(ox.value, oy.value);
    ox.value = r.x;
    oy.value = r.y;
}

// ── export cropped image via canvas ──
function sampleHasAlpha(ctx, w, h) {
    const data = ctx.getImageData(0, 0, w, h).data; // 4 bytes per pixel (RGBA)
    const total = w * h;
    const step = Math.max(1, Math.floor(total / 600));
    for (let i = 0; i < total; i += step) {
        if (data[i * 4 + 3] < 255) return true;
    }
    return false;
}

function confirm() {
    if (!ready.value) return;

    const canvas = canvasRef.value;
    canvas.width = props.outputWidth;
    canvas.height = props.outputHeight;
    const ctx = canvas.getContext('2d');

    const ds = baseScale * scale.value;
    const imgTLX = displayW.value / 2 + ox.value - (imgNW * ds) / 2;
    const imgTLY = displayH.value / 2 + oy.value - (imgNH * ds) / 2;

    // Source region in original image pixel coordinates
    const srcX = -imgTLX / ds;
    const srcY = -imgTLY / ds;
    const srcW = displayW.value / ds;
    const srcH = displayH.value / ds;

    ctx.drawImage(imgEl, srcX, srcY, srcW, srcH, 0, 0, props.outputWidth, props.outputHeight);

    if (sampleHasAlpha(ctx, props.outputWidth, props.outputHeight)) {
        canvas.toBlob((blob) => emit('confirm', blob), 'image/png');
    } else {
        canvas.toBlob((blob) => emit('confirm', blob), 'image/jpeg', 0.93);
    }
}
</script>

<template>
    <!-- Backdrop -->
    <div
        style="
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(10, 20, 35, 0.88);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        "
        @click.self="emit('cancel')"
    >
        <!-- Modal card -->
        <div
            style="
                background: white;
                border-radius: 22px;
                overflow: hidden;
                box-shadow: 0 32px 96px rgba(0, 0, 0, 0.45);
                display: flex;
                flex-direction: column;
                max-width: 100%;
            "
        >
            <!-- Header -->
            <div style="padding: 18px 24px 12px">
                <p style="font-size: 14px; font-weight: 800; color: #3a6478; margin: 0 0 2px">Ajusta a imagem</p>
                <p style="font-size: 11px; color: #8ba0b0; margin: 0">
                    Arrasta para posicionar · scroll ou slider para zoom
                </p>
            </div>

            <!-- Crop stage (dark bg makes the crop area pop) -->
            <div
                style="
                    background: #141e2e;
                    padding: 20px 24px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                "
            >
                <div
                    :style="{
                        width: displayW + 'px',
                        height: displayH + 'px',
                        position: 'relative',
                        overflow: 'hidden',
                        borderRadius: circle ? '50%' : '10px',
                        cursor: dragging ? 'grabbing' : 'grab',
                        outline: '2.5px solid rgba(0,154,199,.6)',
                        outlineOffset: '2px',
                        background: '#0d1520',
                    }"
                    @mousedown="onPointerDown"
                    @touchstart.prevent="onPointerDown"
                    @wheel.prevent="onWheel"
                >
                    <img v-if="ready" :src="src" :style="imgTransform" alt="" />

                    <!-- Loading spinner -->
                    <div
                        v-else
                        style="
                            position: absolute;
                            inset: 0;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        "
                    >
                        <div
                            class="spin"
                            style="
                                width: 30px;
                                height: 30px;
                                border-radius: 50%;
                                border: 3px solid rgba(0, 154, 199, 0.2);
                                border-top-color: #009ac7;
                            "
                        />
                    </div>
                </div>
            </div>

            <!-- Zoom slider -->
            <div style="padding: 12px 24px 4px; display: flex; align-items: center; gap: 10px">
                <!-- minus icon -->
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#b0c0cc"
                    stroke-width="2.5"
                    stroke-linecap="round"
                >
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    <line x1="8" y1="11" x2="14" y2="11" />
                </svg>
                <input
                    type="range"
                    min="100"
                    max="400"
                    :value="Math.round(scale * 100)"
                    @input="onSlider"
                    style="flex: 1; accent-color: #009ac7; cursor: pointer; height: 4px"
                />
                <!-- plus icon -->
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#b0c0cc"
                    stroke-width="2.5"
                    stroke-linecap="round"
                >
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    <line x1="8" y1="11" x2="14" y2="11" />
                    <line x1="11" y1="8" x2="11" y2="14" />
                </svg>
                <span style="font-size: 11px; color: #009ac7; font-weight: 700; min-width: 36px; text-align: right"
                    >{{ Math.round(scale * 100) }}%</span
                >
            </div>

            <!-- Actions -->
            <div style="padding: 10px 24px 20px; display: flex; gap: 8px; justify-content: flex-end">
                <button
                    @click="emit('cancel')"
                    style="
                        padding: 9px 20px;
                        border-radius: 10px;
                        border: 1.5px solid #d0e4ee;
                        background: transparent;
                        color: #5a7a8a;
                        font-size: 12px;
                        font-weight: 700;
                        cursor: pointer;
                        transition: background 0.15s;
                    "
                    @mouseenter="$event.target.style.background = '#f0f8ff'"
                    @mouseleave="$event.target.style.background = 'transparent'"
                >
                    Cancelar
                </button>
                <button
                    @click="confirm"
                    style="
                        padding: 9px 22px;
                        border-radius: 10px;
                        border: none;
                        background: #009ac7;
                        color: white;
                        font-size: 12px;
                        font-weight: 700;
                        cursor: pointer;
                        box-shadow: 0 3px 12px #009ac730;
                        transition: opacity 0.2s;
                    "
                    @mouseenter="$event.target.style.opacity = '.85'"
                    @mouseleave="$event.target.style.opacity = '1'"
                >
                    Confirmar
                </button>
            </div>
        </div>
    </div>

    <canvas ref="canvasRef" style="display: none" />
</template>

<style scoped>
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
.spin {
    animation: spin 0.7s linear infinite;
}
</style>
