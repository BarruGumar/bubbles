<script setup>
import { ref, computed } from 'vue';
import { useAnnouncements } from '@/Composables/useAnnouncements';

const { visible, dismiss } = useAnnouncements();
const closed = ref(false);

const showModal = computed(() => !closed.value && visible.value.length > 0);

const TYPE_STYLE = {
    info:        { bg: '#e8f7fd', border: '#009ac7', icon: 'ℹ️',  label: 'Info',        text: '#0069a0' },
    update:      { bg: '#edfdf4', border: '#16a34a', icon: '🔄', label: 'Atualização', text: '#166534' },
    warning:     { bg: '#fff8e8', border: '#d97706', icon: '⚠️', label: 'Aviso',       text: '#92400e' },
    maintenance: { bg: '#fef2f2', border: '#dc2626', icon: '🔧', label: 'Manutenção',  text: '#991b1b' },
};

function style(type) {
    return TYPE_STYLE[type] ?? TYPE_STYLE.info;
}

function fmtDate(iso) {
    return new Date(iso).toLocaleDateString('pt-PT', { day: '2-digit', month: 'short', year: 'numeric' });
}

function dismissAndClose(id) {
    dismiss(id);
}

function close() {
    closed.value = true;
}
</script>

<template>
    <Transition name="ann-overlay">
        <div
            v-if="showModal"
            style="
                position: fixed; inset: 0; z-index: 400;
                background: rgba(0,0,0,0.55);
                backdrop-filter: blur(4px);
                display: flex; align-items: center; justify-content: center;
                padding: 20px;
            "
            @click.self="close"
        >
            <Transition name="ann-pop" appear>
                <div
                    style="
                        background: white; border-radius: 20px;
                        width: 100%; max-width: 520px;
                        box-shadow: 0 24px 80px rgba(0,0,0,0.25);
                        overflow: hidden;
                        display: flex; flex-direction: column;
                        max-height: 80vh;
                    "
                >
                    <!-- Header -->
                    <div style="
                        padding: 18px 22px 14px;
                        border-bottom: 1px solid #e8f0f8;
                        display: flex; align-items: center; justify-content: space-between;
                        flex-shrink: 0;
                    ">
                        <div style="display: flex; align-items: center; gap: 10px">
                            <span style="font-size: 20px">📢</span>
                            <div>
                                <p style="font-size: 15px; font-weight: 800; color: #1a2a3a; margin: 0">Avisos do Site</p>
                                <p style="font-size: 11px; color: #8ba0b0; margin: 0">{{ visible.length }} aviso(s) por ler</p>
                            </div>
                        </div>
                        <button
                            @click="close"
                            style="
                                background: #f0f4f8; border: none; cursor: pointer;
                                width: 28px; height: 28px; border-radius: 50%;
                                display: flex; align-items: center; justify-content: center;
                                font-size: 16px; color: #5a7a8a; line-height: 1;
                            "
                        >×</button>
                    </div>

                    <!-- Announcement list -->
                    <div style="overflow-y: auto; flex: 1; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px">
                        <div
                            v-for="ann in visible"
                            :key="ann.id"
                            :style="{
                                borderRadius: '14px',
                                border: `1.5px solid ${style(ann.type).border}44`,
                                background: style(ann.type).bg,
                                padding: '14px 16px',
                                display: 'flex',
                                gap: '12px',
                                alignItems: 'flex-start',
                            }"
                        >
                            <span style="font-size: 20px; flex-shrink: 0; margin-top: 1px">{{ style(ann.type).icon }}</span>

                            <div style="flex: 1; min-width: 0">
                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 4px">
                                    <span
                                        :style="{
                                            fontSize: '10px', fontWeight: '800',
                                            textTransform: 'uppercase', letterSpacing: '0.07em',
                                            color: style(ann.type).border,
                                        }"
                                    >{{ style(ann.type).label }}</span>
                                    <span style="font-size: 11px; color: #8ba0b0">· {{ fmtDate(ann.created_at) }}</span>
                                </div>
                                <p style="font-size: 14px; font-weight: 700; color: #1a2a3a; margin: 0 0 4px">{{ ann.title }}</p>
                                <p style="font-size: 12px; color: #3a5068; margin: 0; line-height: 1.55; white-space: pre-wrap">{{ ann.body }}</p>
                            </div>

                            <button
                                @click="dismissAndClose(ann.id)"
                                :style="{
                                    flexShrink: 0,
                                    padding: '5px 11px',
                                    borderRadius: '8px',
                                    border: `1px solid ${style(ann.type).border}44`,
                                    background: 'white',
                                    color: style(ann.type).border,
                                    fontSize: '11px', fontWeight: '700',
                                    cursor: 'pointer',
                                    whiteSpace: 'nowrap',
                                }"
                            >Dispensar</button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div style="
                        padding: 12px 16px;
                        border-top: 1px solid #e8f0f8;
                        display: flex; justify-content: flex-end;
                        flex-shrink: 0;
                    ">
                        <button
                            @click="close"
                            style="
                                padding: 9px 22px; border-radius: 10px;
                                border: 1px solid #e8f0f8; background: white;
                                font-size: 13px; font-weight: 700;
                                cursor: pointer; color: #5a7a8a;
                            "
                        >Fechar</button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
.ann-overlay-enter-active, .ann-overlay-leave-active { transition: opacity 0.2s; }
.ann-overlay-enter-from, .ann-overlay-leave-to { opacity: 0; }

.ann-pop-enter-active { transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.2s; }
.ann-pop-leave-active { transition: transform 0.18s ease-in, opacity 0.15s; }
.ann-pop-enter-from { transform: scale(0.88) translateY(12px); opacity: 0; }
.ann-pop-leave-to   { transform: scale(0.92) translateY(8px); opacity: 0; }
</style>
