<script setup>
defineProps({
    text: { type: String, required: true },
    sending: { type: Boolean, default: false },
});

const emit = defineEmits(['update:text', 'submit', 'cancel']);
</script>

<template>
    <div
        style="
            margin-top: 12px;
            background: #fff8f8;
            border: 1px solid #e0555522;
            border-radius: 10px;
            padding: 12px;
        "
    >
        <p style="font-size: 12px; font-weight: 700; color: #e05555; margin: 0 0 8px">Denunciar post</p>
        <textarea
            :value="text"
            placeholder="Descreve o motivo da denúncia..."
            rows="2"
            maxlength="500"
            style="
                width: 100%;
                background: white;
                border: 1px solid #e0555533;
                border-radius: 8px;
                padding: 8px 10px;
                font-size: 12px;
                color: #1a3a4a;
                outline: none;
                font-family: inherit;
                resize: none;
                box-sizing: border-box;
            "
            @input="emit('update:text', $event.target.value)"
        />
        <div style="display: flex; gap: 6px; margin-top: 8px; justify-content: flex-end">
            <button
                @click="emit('cancel')"
                style="
                    padding: 5px 12px;
                    border-radius: 99px;
                    border: 1px solid #e0eef8;
                    background: #f0f8ff;
                    color: #8ba0b0;
                    font-size: 11px;
                    cursor: pointer;
                "
            >
                Cancelar
            </button>
            <button
                @click="emit('submit')"
                :disabled="!text.trim() || sending"
                style="
                    padding: 5px 14px;
                    border-radius: 99px;
                    border: none;
                    background: #e05555;
                    color: white;
                    font-size: 11px;
                    font-weight: 700;
                    cursor: pointer;
                "
                :style="{ opacity: !text.trim() || sending ? 0.5 : 1 }"
            >
                {{ sending ? 'A enviar...' : 'Enviar' }}
            </button>
        </div>
    </div>
</template>
