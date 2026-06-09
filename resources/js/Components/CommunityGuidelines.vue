<script setup>
defineProps({
    guidelines: { type: Array, required: true },
});
</script>

<template>
    <div
        style="
            background: var(--card-bg, rgba(17, 24, 39, 0.88));
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid var(--card-border, rgba(148, 163, 184, 0.18));
            box-shadow: 0 2px 12px var(--shadow-color, rgba(15, 23, 42, 0.28));
            padding: 18px 22px;
            margin-bottom: 16px;
        "
    >
        <p
            style="
                font-size: 10px;
                font-weight: 800;
                color: var(--text-3, #a8b8c5);
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin: 0 0 10px;
            "
        >
            Regras
        </p>
        <ol
            style="
                margin: 0;
                padding-left: 18px;
                color: var(--text-2, #dbe7f5);
                font-size: 13px;
                line-height: 1.8;
            "
        >
            <li v-for="(rule, i) in guidelines" :key="i">{{ rule }}</li>
        </ol>
    </div>
</template>
