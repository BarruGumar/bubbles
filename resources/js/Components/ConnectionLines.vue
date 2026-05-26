<script setup>
import { computed } from 'vue';
import { clImg } from '@/Composables/useCloudinary';
import { resolveBadgePos } from '@/Composables/useBadgeLayout';

const props = defineProps({
    connections: { type: Array, required: true },
    friendConnections: { type: Array, default: () => [] },
    bubbles: { type: Array, required: true },
    badgePositions: { type: Array, default: () => [] },
});

const bubbleMap = computed(() => new Map(props.bubbles.map((b) => [b.id, b])));

const validFriendConnections = computed(() =>
    props.friendConnections.filter((c) => bubbleMap.value.has(c.from) && bubbleMap.value.has(c.to)),
);

const badgePosMap = computed(() =>
    new Map(props.badgePositions.map((p) => [`${p.from}-${p.to}`, p])),
);

function getBubble(id) {
    return bubbleMap.value.get(id);
}

function center(id) {
    const b = getBubble(id);
    return b ? { x: b.x + b.size / 2, y: b.y + b.size / 2 } : { x: 0, y: 0 };
}

function midpoint(fromId, toId) {
    const a = center(fromId);
    const b = center(toId);
    return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
}

function badgePosition(c) {
    const cached = badgePosMap.value.get(`${c.from}-${c.to}`);
    if (cached) return cached;
    const mid = midpoint(c.from, c.to);
    return resolveBadgePos(mid.x, mid.y, c.from, c.to, props.bubbles);
}

function badgeLabel(friends) {
    if (!friends?.length) return '?';
    return friends.length === 1 ? friends[0].name[0].toUpperCase() : friends.length;
}

function avatarPos(bubble, angle) {
    const rad = (angle * Math.PI) / 180;
    const dist = bubble.size / 2 + 10;
    return {
        x: bubble.x + bubble.size / 2 + Math.cos(rad) * dist,
        y: bubble.y + bubble.size / 2 + Math.sin(rad) * dist,
    };
}

function balloonPos(bubble, angle) {
    const head = avatarPos(bubble, angle);
    const rad = (angle * Math.PI) / 180;
    return { x: head.x + Math.cos(rad) * 38, y: head.y + Math.sin(rad) * 38 };
}

function balloonWidth(msg) {
    return msg.length * 5.5 + 16;
}

function balloonLeft(angle, msg) {
    return angle > 90 && angle < 270 ? -balloonWidth(msg) : 0;
}

function balloonTextX(angle, msg) {
    const w = balloonWidth(msg);
    return angle > 90 && angle < 270 ? -w / 2 : w / 2;
}
</script>

<template>
    <!-- Layer 1: lines + member avatars — behind the bubbles -->
    <svg class="absolute inset-0 w-full h-full pointer-events-none" style="z-index: 2">
        <!-- Friend connection lines (amigos em comum) -->
        <g v-for="(c, i) in validFriendConnections" :key="'fc-' + i">
            <line
                :x1="center(c.from).x"
                :y1="center(c.from).y"
                :x2="center(c.to).x"
                :y2="center(c.to).y"
                stroke="#9b6bdf"
                stroke-width="10"
                stroke-linecap="round"
                opacity="0.07"
            />
            <line
                :x1="center(c.from).x"
                :y1="center(c.from).y"
                :x2="center(c.to).x"
                :y2="center(c.to).y"
                stroke="#9b6bdf"
                stroke-width="1.8"
                stroke-dasharray="5 4"
                opacity="0.4"
                class="friend-line"
            />
        </g>

        <!-- Manual connection lines -->
        <line
            v-for="(c, i) in connections"
            :key="'conn-' + i"
            :x1="center(c.from).x"
            :y1="center(c.from).y"
            :x2="center(c.to).x"
            :y2="center(c.to).y"
            :stroke="getBubble(c.from)?.color ?? '#009ac7'"
            stroke-width="2"
            stroke-dasharray="6 5"
            opacity="0.3"
            class="conn-line"
        />

        <!-- Member avatars + speech bubbles per bubble -->
        <g v-for="bubble in bubbles" :key="'av-' + bubble.id">
            <g v-for="av in bubble.avatars" :key="av.id">
                <circle
                    :cx="avatarPos(bubble, av.angle).x"
                    :cy="avatarPos(bubble, av.angle).y + 2"
                    r="19"
                    fill="rgba(0,0,0,0.1)"
                />
                <circle
                    :cx="avatarPos(bubble, av.angle).x"
                    :cy="avatarPos(bubble, av.angle).y"
                    r="19"
                    :fill="bubble.color"
                />
                <circle
                    :cx="avatarPos(bubble, av.angle).x"
                    :cy="avatarPos(bubble, av.angle).y"
                    r="16"
                    fill="white"
                    opacity="0.92"
                />
                <text
                    :x="avatarPos(bubble, av.angle).x"
                    :y="avatarPos(bubble, av.angle).y + 5"
                    text-anchor="middle"
                    font-size="13"
                    font-weight="700"
                    font-family="Segoe UI, system-ui, sans-serif"
                    :fill="bubble.color"
                >
                    {{ av.name[0] }}
                </text>
                <g :transform="`translate(${balloonPos(bubble, av.angle).x}, ${balloonPos(bubble, av.angle).y})`">
                    <rect
                        :x="balloonLeft(av.angle, av.msg)"
                        y="-14"
                        :width="balloonWidth(av.msg)"
                        height="24"
                        rx="8"
                        fill="white"
                        :stroke="bubble.color"
                        stroke-width="1.2"
                        opacity="0.96"
                    />
                    <text
                        :x="balloonTextX(av.angle, av.msg)"
                        y="2"
                        text-anchor="middle"
                        font-size="10"
                        font-family="Segoe UI, system-ui, sans-serif"
                        fill="#2a4a5a"
                    >
                        {{ av.msg }}
                    </text>
                </g>
            </g>
        </g>
    </svg>

    <!-- Layer 2: friend midpoint badges — above the bubbles -->
    <svg class="absolute inset-0 w-full h-full pointer-events-none" style="z-index: 25">
        <g
            v-for="(c, i) in validFriendConnections"
            :key="'badge-' + i"
            :transform="`translate(${badgePosition(c).x}, ${badgePosition(c).y})`"
            style="pointer-events: auto; cursor: default"
        >
            <title v-if="c.friends?.length">{{ c.friends.map((f) => f.name).join(', ') }}</title>
            <circle r="13" fill="white" stroke="#9b6bdf" stroke-width="1.6" opacity="0.97" />
            <circle r="12" :fill="c.friends?.[0]?.avatar_color ?? '#9b6bdf'" />
            <text
                text-anchor="middle"
                dominant-baseline="central"
                font-size="9"
                font-weight="800"
                font-family="Segoe UI, system-ui, sans-serif"
                fill="white"
            >
                {{ badgeLabel(c.friends) }}
            </text>
            <foreignObject v-if="c.friends?.[0]?.avatar" x="-12" y="-12" width="24" height="24">
                <img
                    xmlns="http://www.w3.org/1999/xhtml"
                    :src="clImg(c.friends[0].avatar, 48, 48, 'fill', 'face')"
                    style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; display: block"
                    @error="$event.target.style.display = 'none'"
                />
            </foreignObject>
            <g v-if="c.friends?.length > 1" transform="translate(8, -8)">
                <circle r="6" fill="#9b6bdf" stroke="white" stroke-width="1.2" />
                <text
                    text-anchor="middle"
                    dominant-baseline="central"
                    font-size="7"
                    font-weight="800"
                    font-family="Segoe UI, system-ui, sans-serif"
                    fill="white"
                >
                    +{{ c.friends.length - 1 }}
                </text>
            </g>
        </g>
    </svg>
</template>

<style scoped>
.conn-line {
    animation: dashFlow 2.4s linear infinite;
}

.friend-line {
    animation: dashFlow 3.2s linear infinite reverse;
}

@keyframes dashFlow {
    to {
        stroke-dashoffset: -11;
    }
}
</style>
