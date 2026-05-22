<script setup>
import { ref, computed } from 'vue';
import { useAudio } from '@/Composables/useAudio';

const emit = defineEmits(['create', 'cancel']);

const { playSfx } = useAudio();

const PALETTE = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b', '#e0a040', '#6b9bdf'];

const step = ref(1);
const label = ref('');
const title = ref('');
const tagline = ref('');
const description = ref('');
const guidelines = ref('');
const color = ref('#009ac7');
const creating = ref(false);
const labelError = ref('');

const STEPS = 3;

const previewSize = 100;

const titleDisplay = computed(() => title.value.trim() || label.value.trim() || 'Nome da bolha');
const labelDisplay = computed(() => label.value.trim() || '#bolha');

function validateStep1() {
    labelError.value = '';
    const l = label.value.trim();
    if (!l) {
        labelError.value = 'O nome da bolha é obrigatório.';
        return false;
    }
    if (l.length < 2) {
        labelError.value = 'Mínimo 2 caracteres.';
        return false;
    }
    if (l.length > 40) {
        labelError.value = 'Máximo 40 caracteres.';
        return false;
    }
    return true;
}

function next() {
    if (step.value === 1 && !validateStep1()) return;
    if (step.value < STEPS) {
        playSfx('pageChange');
        step.value++;
    }
}

function prev() {
    if (step.value > 1) {
        playSfx('pageChange');
        step.value--;
    }
}

function submit() {
    if (!validateStep1()) {
        step.value = 1;
        return;
    }
    creating.value = true;
    emit('create', {
        label: label.value.trim(),
        title: title.value.trim(),
        tagline: tagline.value.trim(),
        description: description.value.trim(),
        guidelines: guidelines.value
            .split('\n')
            .map((g) => g.trim())
            .filter(Boolean)
            .slice(0, 5),
        color: color.value,
    });
}

function cancel() {
    playSfx('off');
    emit('cancel');
}

const guidelineLines = computed(() =>
    guidelines.value
        .split('\n')
        .filter((l) => l.trim())
        .slice(0, 5),
);
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <div
            style="
                position: fixed;
                inset: 0;
                z-index: 200;
                background: rgba(0, 0, 0, 0.4);
                backdrop-filter: blur(6px);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            "
            @click.self="cancel"
        >
            <!-- Modal -->
            <div
                style="
                    background: white;
                    border-radius: 24px;
                    box-shadow: 0 24px 80px rgba(0, 154, 199, 0.18);
                    width: 100%;
                    max-width: 520px;
                    max-height: 90vh;
                    overflow-y: auto;
                    position: relative;
                "
                @click.stop
            >
                <!-- Header -->
                <div style="padding: 28px 28px 0">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px">
                        <h2
                            style="
                                font-size: 18px;
                                font-weight: 900;
                                color: #3a6478;
                                margin: 0;
                                letter-spacing: -0.02em;
                            "
                        >
                            Nova bolha
                        </h2>
                        <button
                            @click="cancel"
                            style="
                                width: 30px;
                                height: 30px;
                                border-radius: 50%;
                                border: none;
                                background: #f0f4f8;
                                color: #8ba0b0;
                                font-size: 16px;
                                cursor: pointer;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            "
                        >
                            ×
                        </button>
                    </div>

                    <!-- Step indicator -->
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 24px">
                        <div
                            v-for="s in STEPS"
                            :key="s"
                            style="height: 3px; border-radius: 99px; flex: 1; transition: background 0.3s"
                            :style="{ background: s <= step ? color : '#e8f0f5' }"
                        />
                        <span style="font-size: 11px; color: #b0c0cc; white-space: nowrap; margin-left: 4px"
                            >{{ step }}/{{ STEPS }}</span
                        >
                    </div>
                </div>

                <div style="padding: 0 28px 28px">
                    <!-- ── Step 1: Identidade ── -->
                    <template v-if="step === 1">
                        <p
                            style="
                                font-size: 11px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.1em;
                                margin: 0 0 18px;
                            "
                        >
                            Identidade da bolha
                        </p>

                        <!-- Live preview -->
                        <div style="display: flex; justify-content: center; margin-bottom: 24px">
                            <div
                                :style="{
                                    width: previewSize + 'px',
                                    height: previewSize + 'px',
                                    borderRadius: '50%',
                                    background: `radial-gradient(circle at 38% 32%, ${color}ee 0%, ${color} 60%)`,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    boxShadow: `0 8px 28px ${color}55, 0 0 0 4px white, 0 0 0 6px ${color}33`,
                                    transition: 'background .3s, box-shadow .3s',
                                    position: 'relative',
                                    overflow: 'hidden',
                                }"
                            >
                                <!-- Glass highlight -->
                                <div
                                    style="
                                        position: absolute;
                                        top: 8px;
                                        left: 14%;
                                        width: 72%;
                                        height: 36%;
                                        border-radius: 50%;
                                        background: rgba(255, 255, 255, 0.24);
                                        transform: rotate(-10deg);
                                        pointer-events: none;
                                    "
                                />
                                <span
                                    style="
                                        font-size: 12px;
                                        font-weight: 800;
                                        color: white;
                                        text-shadow: 0 1px 4px rgba(0, 0, 0, 0.28);
                                        text-align: center;
                                        padding: 0 10px;
                                        line-height: 1.2;
                                        position: relative;
                                        z-index: 1;
                                        word-break: break-all;
                                    "
                                    >{{ labelDisplay }}</span
                                >
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 14px">
                            <div>
                                <label
                                    style="
                                        font-size: 11px;
                                        font-weight: 700;
                                        color: #5a7a8a;
                                        text-transform: uppercase;
                                        letter-spacing: 0.06em;
                                        display: block;
                                        margin-bottom: 6px;
                                    "
                                    >Nome da bolha *</label
                                >
                                <input
                                    v-model="label"
                                    placeholder="#designers, #música, #jogos…"
                                    maxlength="40"
                                    style="
                                        width: 100%;
                                        box-sizing: border-box;
                                        background: #f0f8ff;
                                        border: 1.5px solid #4ebcff44;
                                        border-radius: 12px;
                                        padding: 11px 14px;
                                        font-size: 14px;
                                        color: #3a6478;
                                        outline: none;
                                        font-family: inherit;
                                        transition: border-color 0.2s;
                                    "
                                    @focus="$event.target.style.borderColor = '#009ac7'"
                                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                                    @keydown.enter.prevent="next"
                                />
                                <p
                                    v-if="labelError"
                                    style="font-size: 11px; color: #e05555; margin: 5px 0 0; font-weight: 600"
                                >
                                    {{ labelError }}
                                </p>
                                <p v-else style="font-size: 10px; color: #b0c0cc; margin: 5px 0 0">
                                    {{ label.length }}/40
                                </p>
                            </div>

                            <div>
                                <label
                                    style="
                                        font-size: 11px;
                                        font-weight: 700;
                                        color: #5a7a8a;
                                        text-transform: uppercase;
                                        letter-spacing: 0.06em;
                                        display: block;
                                        margin-bottom: 8px;
                                    "
                                    >Cor</label
                                >
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
                                    <button
                                        v-for="c in PALETTE"
                                        :key="c"
                                        type="button"
                                        @click="color = c"
                                        :style="{
                                            width: '26px',
                                            height: '26px',
                                            borderRadius: '50%',
                                            background: c,
                                            border: 'none',
                                            cursor: 'pointer',
                                            flexShrink: 0,
                                            boxShadow: color === c ? `0 0 0 3px white, 0 0 0 5px ${c}` : 'none',
                                            transition: 'box-shadow .2s',
                                        }"
                                    />
                                    <label
                                        :style="{
                                            width: '26px',
                                            height: '26px',
                                            borderRadius: '50%',
                                            cursor: 'pointer',
                                            border: '2px dashed #b0c8d8',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            flexShrink: 0,
                                            position: 'relative',
                                            overflow: 'hidden',
                                            boxShadow: !PALETTE.includes(color)
                                                ? `0 0 0 3px white, 0 0 0 5px ${color}`
                                                : 'none',
                                            background: !PALETTE.includes(color) ? color : 'transparent',
                                        }"
                                        title="Cor personalizada"
                                    >
                                        <input
                                            type="color"
                                            v-model="color"
                                            style="
                                                position: absolute;
                                                width: 200%;
                                                height: 200%;
                                                opacity: 0;
                                                cursor: pointer;
                                                border: none;
                                                padding: 0;
                                            "
                                        />
                                        <span
                                            v-if="PALETTE.includes(color)"
                                            style="
                                                font-size: 12px;
                                                color: #8ba0b0;
                                                position: relative;
                                                pointer-events: none;
                                            "
                                            >+</span
                                        >
                                    </label>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ── Step 2: Detalhes ── -->
                    <template v-else-if="step === 2">
                        <p
                            style="
                                font-size: 11px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.1em;
                                margin: 0 0 18px;
                            "
                        >
                            Detalhes da comunidade
                        </p>

                        <div style="display: flex; flex-direction: column; gap: 14px">
                            <div>
                                <label
                                    style="
                                        font-size: 11px;
                                        font-weight: 700;
                                        color: #5a7a8a;
                                        text-transform: uppercase;
                                        letter-spacing: 0.06em;
                                        display: block;
                                        margin-bottom: 6px;
                                    "
                                    >Título
                                    <span style="font-weight: 400; text-transform: none; color: #b0c0cc"
                                        >(opcional)</span
                                    ></label
                                >
                                <input
                                    v-model="title"
                                    placeholder="Nome completo da comunidade"
                                    maxlength="80"
                                    style="
                                        width: 100%;
                                        box-sizing: border-box;
                                        background: #f0f8ff;
                                        border: 1.5px solid #4ebcff44;
                                        border-radius: 12px;
                                        padding: 11px 14px;
                                        font-size: 13px;
                                        color: #3a6478;
                                        outline: none;
                                        font-family: inherit;
                                        transition: border-color 0.2s;
                                    "
                                    @focus="$event.target.style.borderColor = '#009ac7'"
                                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                                />
                            </div>
                            <div>
                                <label
                                    style="
                                        font-size: 11px;
                                        font-weight: 700;
                                        color: #5a7a8a;
                                        text-transform: uppercase;
                                        letter-spacing: 0.06em;
                                        display: block;
                                        margin-bottom: 6px;
                                    "
                                    >Tagline
                                    <span style="font-weight: 400; text-transform: none; color: #b0c0cc"
                                        >(opcional)</span
                                    ></label
                                >
                                <input
                                    v-model="tagline"
                                    placeholder="Uma frase curta e memorável"
                                    maxlength="100"
                                    style="
                                        width: 100%;
                                        box-sizing: border-box;
                                        background: #f0f8ff;
                                        border: 1.5px solid #4ebcff44;
                                        border-radius: 12px;
                                        padding: 11px 14px;
                                        font-size: 13px;
                                        color: #3a6478;
                                        outline: none;
                                        font-family: inherit;
                                        transition: border-color 0.2s;
                                    "
                                    @focus="$event.target.style.borderColor = '#009ac7'"
                                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                                />
                            </div>
                            <div>
                                <div
                                    style="
                                        display: flex;
                                        justify-content: space-between;
                                        align-items: center;
                                        margin-bottom: 6px;
                                    "
                                >
                                    <label
                                        style="
                                            font-size: 11px;
                                            font-weight: 700;
                                            color: #5a7a8a;
                                            text-transform: uppercase;
                                            letter-spacing: 0.06em;
                                        "
                                        >Descrição
                                        <span style="font-weight: 400; text-transform: none; color: #b0c0cc"
                                            >(opcional)</span
                                        ></label
                                    >
                                    <span style="font-size: 10px; color: #b0c0cc">{{ description.length }}/500</span>
                                </div>
                                <textarea
                                    v-model="description"
                                    placeholder="Do que trata esta comunidade? Quem deve entrar?"
                                    maxlength="500"
                                    rows="4"
                                    style="
                                        width: 100%;
                                        box-sizing: border-box;
                                        background: #f0f8ff;
                                        border: 1.5px solid #4ebcff44;
                                        border-radius: 12px;
                                        padding: 11px 14px;
                                        font-size: 13px;
                                        color: #3a6478;
                                        outline: none;
                                        font-family: inherit;
                                        resize: vertical;
                                        transition: border-color 0.2s;
                                    "
                                    @focus="$event.target.style.borderColor = '#009ac7'"
                                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                                />
                            </div>
                        </div>
                    </template>

                    <!-- ── Step 3: Regras + Preview ── -->
                    <template v-else>
                        <p
                            style="
                                font-size: 11px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.1em;
                                margin: 0 0 18px;
                            "
                        >
                            Regras e confirmação
                        </p>

                        <div style="margin-bottom: 20px">
                            <label
                                style="
                                    font-size: 11px;
                                    font-weight: 700;
                                    color: #5a7a8a;
                                    text-transform: uppercase;
                                    letter-spacing: 0.06em;
                                    display: block;
                                    margin-bottom: 6px;
                                "
                                >Regras da comunidade
                                <span style="font-weight: 400; text-transform: none; color: #b0c0cc"
                                    >(opcional, máx 5)</span
                                ></label
                            >
                            <textarea
                                v-model="guidelines"
                                placeholder="Uma regra por linha&#10;Ex: Seja respeitoso&#10;Ex: Sem spam"
                                rows="5"
                                style="
                                    width: 100%;
                                    box-sizing: border-box;
                                    background: #f0f8ff;
                                    border: 1.5px solid #4ebcff44;
                                    border-radius: 12px;
                                    padding: 11px 14px;
                                    font-size: 13px;
                                    color: #3a6478;
                                    outline: none;
                                    font-family: inherit;
                                    resize: vertical;
                                    transition: border-color 0.2s;
                                "
                                @focus="$event.target.style.borderColor = '#009ac7'"
                                @blur="$event.target.style.borderColor = '#4ebcff44'"
                            />
                        </div>

                        <!-- Preview card -->
                        <div
                            :style="{
                                background: `linear-gradient(135deg, ${color}11, ${color}08)`,
                                border: `1.5px solid ${color}33`,
                                borderRadius: '16px',
                                padding: '20px',
                            }"
                        >
                            <p
                                style="
                                    font-size: 10px;
                                    font-weight: 800;
                                    color: #8ba0b0;
                                    text-transform: uppercase;
                                    letter-spacing: 0.1em;
                                    margin: 0 0 14px;
                                "
                            >
                                Preview
                            </p>
                            <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 12px">
                                <div
                                    :style="{
                                        width: '52px',
                                        height: '52px',
                                        borderRadius: '50%',
                                        flexShrink: 0,
                                        background: `radial-gradient(circle at 38% 32%, ${color}ee 0%, ${color} 60%)`,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        boxShadow: `0 4px 14px ${color}55`,
                                        position: 'relative',
                                        overflow: 'hidden',
                                    }"
                                >
                                    <div
                                        style="
                                            position: absolute;
                                            top: 6px;
                                            left: 14%;
                                            width: 72%;
                                            height: 36%;
                                            border-radius: 50%;
                                            background: rgba(255, 255, 255, 0.22);
                                            transform: rotate(-10deg);
                                        "
                                    />
                                    <span
                                        style="
                                            font-size: 9px;
                                            font-weight: 800;
                                            color: white;
                                            text-align: center;
                                            padding: 0 6px;
                                            position: relative;
                                            z-index: 1;
                                            word-break: break-all;
                                        "
                                        >{{ labelDisplay }}</span
                                    >
                                </div>
                                <div style="flex: 1; min-width: 0">
                                    <p
                                        style="
                                            font-size: 15px;
                                            font-weight: 800;
                                            color: #3a6478;
                                            margin: 0 0 2px;
                                            overflow: hidden;
                                            text-overflow: ellipsis;
                                            white-space: nowrap;
                                        "
                                    >
                                        {{ titleDisplay }}
                                    </p>
                                    <p
                                        v-if="tagline.trim()"
                                        style="
                                            font-size: 12px;
                                            color: #5a7a8a;
                                            margin: 0;
                                            overflow: hidden;
                                            text-overflow: ellipsis;
                                            white-space: nowrap;
                                        "
                                    >
                                        {{ tagline }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="description.trim()"
                                style="
                                    font-size: 12px;
                                    color: #5a7a8a;
                                    margin: 0 0 12px;
                                    line-height: 1.5;
                                    display: -webkit-box;
                                    -webkit-line-clamp: 2;
                                    -webkit-box-orient: vertical;
                                    overflow: hidden;
                                "
                            >
                                {{ description }}
                            </p>
                            <div v-if="guidelineLines.length" style="display: flex; flex-direction: column; gap: 5px">
                                <div
                                    v-for="(g, i) in guidelineLines"
                                    :key="i"
                                    style="display: flex; align-items: flex-start; gap: 8px"
                                >
                                    <span :style="{ color, fontWeight: '800', fontSize: '11px', flexShrink: 0 }"
                                        >{{ i + 1 }}.</span
                                    >
                                    <span style="font-size: 11px; color: #3a5a6a">{{ g }}</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Navigation buttons -->
                    <div style="display: flex; gap: 10px; margin-top: 24px">
                        <button
                            v-if="step > 1"
                            @click="prev"
                            style="
                                padding: 11px 20px;
                                border-radius: 12px;
                                border: 1.5px solid #c8d8e0;
                                background: transparent;
                                color: #5a7a8a;
                                font-size: 13px;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.2s;
                            "
                            @mouseenter="$event.currentTarget.style.borderColor = '#8ba0b0'"
                            @mouseleave="$event.currentTarget.style.borderColor = '#c8d8e0'"
                        >
                            ← Anterior
                        </button>
                        <div style="flex: 1" />
                        <button
                            @click="cancel"
                            style="
                                padding: 11px 20px;
                                border-radius: 12px;
                                border: 1.5px solid #c8d8e0;
                                background: transparent;
                                color: #8ba0b0;
                                font-size: 13px;
                                font-weight: 600;
                                cursor: pointer;
                            "
                        >
                            Cancelar
                        </button>
                        <button
                            v-if="step < STEPS"
                            @click="next"
                            :style="{
                                padding: '11px 24px',
                                borderRadius: '12px',
                                border: 'none',
                                background: color,
                                color: 'white',
                                fontSize: '13px',
                                fontWeight: '700',
                                cursor: 'pointer',
                                boxShadow: `0 4px 14px ${color}44`,
                                transition: 'opacity .2s',
                            }"
                            @mouseenter="$event.currentTarget.style.opacity = '.9'"
                            @mouseleave="$event.currentTarget.style.opacity = '1'"
                        >
                            Próximo →
                        </button>
                        <button
                            v-else
                            @click="submit"
                            :disabled="creating"
                            :style="{
                                padding: '11px 24px',
                                borderRadius: '12px',
                                border: 'none',
                                background: color,
                                color: 'white',
                                fontSize: '13px',
                                fontWeight: '700',
                                cursor: creating ? 'not-allowed' : 'pointer',
                                boxShadow: `0 4px 14px ${color}44`,
                                transition: 'opacity .2s',
                                opacity: creating ? '.6' : '1',
                            }"
                        >
                            {{ creating ? 'A criar…' : 'Criar bolha' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
