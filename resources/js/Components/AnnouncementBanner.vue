<script setup>
import { useAnnouncements } from '@/Composables/useAnnouncements';

const { visible, dismiss } = useAnnouncements();

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
</script>

<template>
    <TransitionGroup name="banner" tag="div">
        <div
            v-for="ann in visible"
            :key="ann.id"
            :style="{
                background: style(ann.type).bg,
                borderBottom: `2px solid ${style(ann.type).border}`,
                padding: '10px 20px',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '12px',
                position: 'relative',
            }"
        >
            <span style="font-size: 16px; flex-shrink: 0; margin-top: 1px">{{ style(ann.type).icon }}</span>

            <div style="flex: 1; min-width: 0">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 2px">
                    <span
                        :style="{
                            fontSize: '10px',
                            fontWeight: '800',
                            textTransform: 'uppercase',
                            letterSpacing: '0.06em',
                            color: style(ann.type).border,
                        }"
                    >{{ style(ann.type).label }}</span>
                    <span style="font-size: 13px; font-weight: 700; color: #1a2a3a">{{ ann.title }}</span>
                    <span style="font-size: 11px; color: #8ba0b0">· {{ fmtDate(ann.created_at) }}</span>
                </div>
                <p style="font-size: 12px; color: #3a5068; margin: 0; line-height: 1.5; overflow-wrap: break-word; word-break: break-word">{{ ann.body }}</p>
            </div>

            <button
                @click="dismiss(ann.id)"
                :style="{
                    flexShrink: 0,
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    color: '#8ba0b0',
                    fontSize: '16px',
                    lineHeight: '1',
                    padding: '0 2px',
                    marginTop: '1px',
                }"
                title="Dispensar"
            >×</button>
        </div>
    </TransitionGroup>
</template>

<style scoped>
.banner-enter-active { transition: max-height 0.25s ease, opacity 0.2s ease; overflow: hidden; }
.banner-leave-active { transition: max-height 0.2s ease, opacity 0.15s ease; overflow: hidden; }
.banner-enter-from, .banner-leave-to { max-height: 0; opacity: 0; }
.banner-enter-to, .banner-leave-from { max-height: 120px; opacity: 1; }
</style>
