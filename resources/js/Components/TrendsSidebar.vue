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
        :style="{
            position: 'absolute',
            right: isMobile ? '8px' : '16px',
            top: isMobile ? 'auto' : '70px',
            bottom: isMobile ? '120px' : 'auto',
            zIndex: 38,
            width: isMobile ? '158px' : '192px',
            background: 'linear-gradient(145deg, rgba(255,255,255,0.82), rgba(238,243,248,0.42))',
            backdropFilter: 'blur(18px)',
            borderRadius: '18px',
            border: '1px solid rgba(255,255,255,0.72)',
            boxShadow: '0 10px 30px rgba(8,15,28,0.14)',
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
            <p
                style="
                    font-size: 10px;
                    font-weight: 800;
                    color: rgba(72,88,108,0.96);
                    text-transform: uppercase;
                    letter-spacing: 0.1em;
                    margin: 0;
                "
            >
                Global Trends
            </p>
            <svg
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
                    stroke="rgba(72,88,108,0.92)"
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
                @click.stop="emit('select', b.id)"
                style="
                    display: flex;
                    align-items: center;
                    gap: 9px;
                    padding: 7px 8px;
                    border-radius: 10px;
                    cursor: pointer;
                    transition: background 0.15s;
                "
                @mouseenter="$event.currentTarget.style.background = 'rgba(255,255,255,0.16)'"
                @mouseleave="$event.currentTarget.style.background = 'transparent'"
            >
                <span
                    style="
                        font-size: 10px;
                        font-weight: 700;
                        color: rgba(78,95,114,0.92);
                        width: 12px;
                        flex-shrink: 0;
                        text-align: right;
                    "
                    >{{ i + 1 }}</span
                >
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
                    <p
                        style="
                            font-size: 12px;
                            font-weight: 700;
                            color: rgba(33,46,60,0.98);
                            margin: 0;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        "
                    >
                        {{ b.label }}
                    </p>
                    <p style="font-size: 9px; color: rgba(88,104,124,0.92); margin: 0">{{ b.members }} membros</p>
                </div>
            </div>
        </div>
    </div>
</template>
