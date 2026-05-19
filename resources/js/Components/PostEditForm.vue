<script setup>
defineProps({
    content: { type: String, required: true },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:content', 'save', 'cancel']);
</script>

<template>
    <div style="margin-bottom: 10px">
        <textarea
            :value="content"
            maxlength="1000"
            rows="4"
            style="
                width: 100%;
                background: #f0f8ff;
                border: 1.5px solid #009ac744;
                border-radius: 10px;
                padding: 10px 12px;
                font-size: 14px;
                color: #3a6478;
                outline: none;
                font-family: inherit;
                resize: vertical;
                box-sizing: border-box;
                transition: border-color 0.2s;
            "
            @input="emit('update:content', $event.target.value)"
            @focus="$event.target.style.borderColor = '#009ac7'"
            @blur="$event.target.style.borderColor = '#009ac744'"
            @keydown.ctrl.enter="emit('save')"
            @keydown.esc="emit('cancel')"
        />
        <div style="display: flex; gap: 8px; margin-top: 8px; justify-content: flex-end">
            <button
                @click="emit('cancel')"
                style="
                    padding: 6px 14px;
                    border-radius: 99px;
                    border: 1.5px solid #e0eef8;
                    background: #f0f8ff;
                    color: #8ba0b0;
                    font-size: 12px;
                    font-weight: 600;
                    cursor: pointer;
                "
            >
                Cancelar
            </button>
            <button
                @click="emit('save')"
                :disabled="loading || !content.trim()"
                :style="{
                    padding: '6px 16px',
                    borderRadius: '99px',
                    border: 'none',
                    background: '#009ac7',
                    color: 'white',
                    fontSize: '12px',
                    fontWeight: '700',
                    cursor: loading ? 'not-allowed' : 'pointer',
                    opacity: loading ? 0.6 : 1,
                }"
            >
                {{ loading ? 'A guardar...' : 'Guardar' }}
            </button>
        </div>
    </div>
</template>
