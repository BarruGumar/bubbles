<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const punishment = computed(() => page.props.new_punishment ?? null);
const visible = ref(false);

const config = {
    warning:    { label: 'Aviso',          icon: '⚠️',  color: '#d97706' },
    mute:       { label: 'Silenciamento',  icon: '🔇',  color: '#ea580c' },
    suspension: { label: 'Conta Suspensa', icon: '🚫',  color: '#dc2626' },
    ban:        { label: 'Banimento',      icon: '⛔',  color: '#991b1b' },
};

const info = computed(() => config[punishment.value?.type] ?? config.warning);

function formatDate(iso) {
    if (!iso) return null;
    return new Date(iso).toLocaleDateString('pt-PT', {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

watch(punishment, (p) => { if (p) visible.value = true; }, { immediate: true });

function acknowledge() {
    visible.value = false;
    router.post(route('punishment.acknowledge', punishment.value.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Transition name="punishment-overlay">
        <div
            v-if="visible && punishment"
            style="position:fixed;inset:0;z-index:300;background:rgba(0,0,0,0.55);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;padding:20px"
        >
            <Transition name="punishment-pop" appear>
                <div style="background:var(--surface);border-radius:20px;max-width:420px;width:100%;box-shadow:0 24px 80px rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.08);overflow:hidden">
                    <div :style="{ background: info.color, padding: '20px 24px', display: 'flex', alignItems: 'center', gap: '14px' }">
                        <span style="font-size:32px;line-height:1">{{ info.icon }}</span>
                        <div>
                            <p style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.75);text-transform:uppercase;letter-spacing:0.08em;margin:0 0 2px">Punição recebida</p>
                            <h2 style="font-size:18px;font-weight:800;color:white;margin:0">{{ info.label }}</h2>
                        </div>
                    </div>
                    <div style="padding:24px">
                        <p style="font-size:11px;font-weight:700;color:#8ba0b0;text-transform:uppercase;letter-spacing:0.07em;margin:0 0 8px">Motivo</p>
                        <div style="background:rgba(0,0,0,0.06);border-radius:12px;padding:14px 16px;margin-bottom:16px">
                            <p style="font-size:14px;color:#3a6478;margin:0;line-height:1.55">
                                {{ punishment.reason || 'Sem motivo especificado.' }}
                            </p>
                        </div>
                        <p v-if="punishment.ends_at" style="font-size:12px;color:#8ba0b0;margin:0 0 20px;text-align:center">
                            Ativo até <strong style="color:#3a6478">{{ formatDate(punishment.ends_at) }}</strong>
                        </p>
                        <p v-else-if="punishment.type !== 'warning'" style="font-size:12px;color:#8ba0b0;margin:0 0 20px;text-align:center">
                            Duração: <strong style="color:#3a6478">Permanente</strong>
                        </p>
                        <button
                            @click="acknowledge"
                            :style="{ width: '100%', padding: '13px', borderRadius: '12px', background: info.color, border: 'none', color: 'white', fontSize: '14px', fontWeight: '700', cursor: 'pointer', boxShadow: `0 4px 16px ${info.color}55` }"
                        >
                            Entendido
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
