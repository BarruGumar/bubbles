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
            background: 'rgba(255,255,255,0.88)',
            backdropFilter: 'blur(16px)',
            borderRadius: '18px',
            border: '1px solid #4ebcff22',
            boxShadow: '0 4px 20px #009ac70c',
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
                    color: #8ba0b0;
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
                    stroke="#8ba0b0"
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
                @mouseenter="$event.currentTarget.style.background = '#f0f8ff'"
                @mouseleave="$event.currentTarget.style.background = 'transparent'"
            >
                <span
                    style="
                        font-size: 10px;
                        font-weight: 700;
                        color: #c0ccd4;
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
                            color: #3a6478;
                            margin: 0;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        "
                    >
                        {{ b.label }}
                    </p>
                    <p style="font-size: 9px; color: #8ba0b0; margin: 0">{{ b.members }} membros</p>
                </div>
            </div>
        </div>
    </div>
</template>
