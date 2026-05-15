<script setup>
defineProps({
    guidelines: { type: Array, required: true },
});
</script>

<template>
    <div
        style="
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid #4ebcff1a;
            box-shadow: 0 2px 12px #009ac708;
            padding: 18px 22px;
            margin-bottom: 16px;
        "
    >
        <p
            style="
                font-size: 10px;
                font-weight: 800;
                color: #8ba0b0;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin: 0 0 10px;
            "
        >
            Regras
        </p>
        <ol style="margin: 0; padding-left: 18px; color: #2a4a5a; font-size: 13px; line-height: 1.8">
            <li v-for="(rule, i) in guidelines" :key="i">{{ rule }}</li>
        </ol>
    </div>
</template>
