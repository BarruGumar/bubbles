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
        <p style="font-size: 11px; color: #8ba0b0; margin: 0 0 6px">A denúncia deve ter pelo menos 5 caracteres.</p>
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
                color: #3a6478;
                outline: none;
                font-family: inherit;
                resize: none;
                box-sizing: border-box;
            "
            @input="emit('update:text', $event.target.value)"
        />
        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 8px; gap: 6px">
            <span style="font-size: 10px; color: #b0c0cc">
                {{ text.trim().length }}/500
                <span v-if="text.trim().length > 0 && text.trim().length < 5" style="color: #e05555"> · mín. 5 caracteres</span>
            </span>
            <div style="display: flex; gap: 6px">
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
                    :disabled="text.trim().length < 5 || sending"
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
                    :style="{ opacity: text.trim().length < 5 || sending ? 0.5 : 1 }"
                >
                    {{ sending ? 'A enviar...' : 'Enviar' }}
                </button>
            </div>
        </div>
    </div>
</template>
