<script setup>
defineProps({
    trends: { type: Array, required: true },
    isMobile: { type: Boolean, default: false },
    open: { type: Boolean, default: true },
});

const emit = defineEmits(['toggle', 'select']);
</script>

<template>
    <div
        class="trends-panel"
        :style="{
            position: 'absolute',
            right: isMobile ? '8px' : '16px',
            top: isMobile ? 'auto' : '70px',
            bottom: isMobile ? '120px' : 'auto',
            zIndex: 38,
            width: isMobile ? '158px' : '192px',
            padding: isMobile ? '10px 10px' : '14px 12px',
            display: 'flex',
            flexDirection: 'column',
            gap: '2px',
        }"
        @click.stop
    >
        <!-- Header — clicável em mobile para abrir/fechar -->
        <div
            style="
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 6px;
                margin-bottom: 8px;
                cursor: pointer;
                user-select: none;
            "
            @click="emit('toggle')"
            @touchend.prevent="emit('toggle')"
        >
            <p class="trends-title">Global Trends</p>
            <svg
                class="trends-chevron"
                width="12"
                height="12"
                viewBox="0 0 12 12"
                fill="none"
                :style="{
                    transform: open ? 'rotate(0deg)' : 'rotate(180deg)',
                    transition: 'transform .25s',
                    flexShrink: 0,
                }"
            >
                <path
                    d="M2 8L6 4l4 4"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </div>

        <!-- Lista colapsável -->
        <div
            :style="{
                overflow: 'hidden',
                maxHeight: open ? '400px' : '0px',
                transition: 'max-height .3s cubic-bezier(.4,0,.2,1)',
                display: 'flex',
                flexDirection: 'column',
                gap: '2px',
            }"
        >
            <div
                v-for="(b, i) in trends"
                :key="b.id"
                class="trend-row"
                @click.stop="emit('select', b.id)"
            >
                <span class="trend-rank">{{ i + 1 }}</span>
                <div
                    :style="{
                        width: '8px',
                        height: '8px',
                        borderRadius: '50%',
                        background: b.color,
                        flexShrink: 0,
                        boxShadow: `0 0 5px ${b.color}88`,
                    }"
                />
                <div style="flex: 1; min-width: 0">
                    <p class="trend-name">{{ b.label }}</p>
                    <p class="trend-count">{{ b.members }} membros</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.trends-panel {
    background: rgba(255, 255, 255, .65);
    border: 1px solid rgba(255, 255, 255, .95);
    border-radius: 18px;
    backdrop-filter: blur(20px) saturate(130%);
    -webkit-backdrop-filter: blur(20px) saturate(130%);
    box-shadow: 0 8px 32px rgba(0, 154, 199, .1), inset 0 1px 0 rgba(255, 255, 255, .95);
}
html.dark .trends-panel {
    background: rgba(255, 255, 255, .12);
    border-top: 1px solid rgba(255, 255, 255, .3);
    border-left: 1px solid rgba(255, 255, 255, .15);
    border-right: 1px solid rgba(255, 255, 255, .06);
    border-bottom: 1px solid rgba(255, 255, 255, .04);
    backdrop-filter: blur(24px) saturate(130%);
    -webkit-backdrop-filter: blur(24px) saturate(130%);
    box-shadow: 0 12px 40px rgba(0, 0, 0, .3), inset 0 1px 0 rgba(255, 255, 255, .2);
}

.trends-title {
    font-size: 10px;
    font-weight: 800;
    color: #009ac7;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin: 0;
}
html.dark .trends-title { color: #4ebcff; }

.trends-chevron { color: #009ac7; }
html.dark .trends-chevron { color: #4ebcff; }

.trend-row {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 7px 8px;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid rgba(0, 0, 0, .05);
}
html.dark .trend-row { border-bottom: 1px solid rgba(255, 255, 255, .06); }
.trend-row:last-child { border-bottom: none; }
.trend-row:hover { background: rgba(0, 154, 199, .07); }
html.dark .trend-row:hover { background: rgba(255, 255, 255, .06); }

.trend-rank {
    font-size: 10px;
    font-weight: 700;
    color: rgba(0, 154, 199, .55);
    width: 12px;
    flex-shrink: 0;
    text-align: right;
}
html.dark .trend-rank { color: rgba(255, 255, 255, .35); }

.trend-name {
    font-size: 12px;
    font-weight: 700;
    color: rgba(0, 0, 0, .8);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
html.dark .trend-name { color: white; }

.trend-count {
    font-size: 9px;
    color: rgba(0, 0, 0, .35);
    margin: 0;
}
html.dark .trend-count { color: rgba(255, 255, 255, .35); }
</style>
