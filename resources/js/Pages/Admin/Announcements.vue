<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    announcements: { type: Array, required: true },
});

const showModal = ref(false);
const form = ref({ title: '', body: '', type: 'info', expires_at: '' });

const TYPE_LABELS = {
    info:        { label: 'Info',        color: '#009ac7', bg: '#e8f7fd' },
    update:      { label: 'Atualização', color: '#16a34a', bg: '#edfdf4' },
    warning:     { label: 'Aviso',       color: '#d97706', bg: '#fff8e8' },
    maintenance: { label: 'Manutenção',  color: '#dc2626', bg: '#fef2f2' },
};

function openModal() {
    form.value = { title: '', body: '', type: 'info', expires_at: '' };
    showModal.value = true;
}

function submit() {
    router.post(route('admin.announcements.store'), form.value, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; },
    });
}

function toggle(id) {
    router.patch(route('admin.announcements.toggle', id), {}, { preserveScroll: true });
}

function destroy(id) {
    if (!confirm('Eliminar este aviso?')) return;
    router.delete(route('admin.announcements.destroy', id), { preserveScroll: true });
}

function fmtDate(iso) {
    return new Date(iso).toLocaleDateString('pt-PT', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function isExpired(iso) {
    return iso && new Date(iso) < new Date();
}
</script>

<template>
    <Head title="Admin · Avisos" />
    <AdminLayout>
        <template #header>
            <h1 style="font-size:16px;font-weight:800;color:#3a6478;margin:0">📢 Avisos Globais</h1>
        </template>

        <!-- Toolbar -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <p style="font-size:13px;color:#8ba0b0;margin:0">
                {{ announcements.length }} aviso(s) no total
            </p>
            <button
                @click="openModal"
                style="
                    padding: 8px 18px; border-radius: 99px; border: 1px solid rgba(255,255,255,.45);
                    background: rgba(0,154,199,.45); backdrop-filter: blur(16px) saturate(160%); -webkit-backdrop-filter: blur(16px) saturate(160%); color: white;
                    text-shadow: 0 1px 4px rgba(0,60,100,.6); font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 6px 20px rgba(0,154,199,.4), inset 0 1px 0 rgba(255,255,255,.4);
                "
            >+ Novo aviso</button>
        </div>

        <!-- Empty state -->
        <div
            v-if="announcements.length === 0"
            style="text-align:center;padding:60px 20px;background:white;border-radius:16px;border:1px solid #e8f0f8"
        >
            <p style="font-size:32px;margin:0 0 12px">📢</p>
            <p style="font-size:14px;color:#8ba0b0">Nenhum aviso criado ainda.</p>
        </div>

        <!-- List -->
        <div v-else style="display:flex;flex-direction:column;gap:10px">
            <div
                v-for="ann in announcements"
                :key="ann.id"
                :style="{
                    background: 'white',
                    border: `1px solid ${ann.is_active && !isExpired(ann.expires_at) ? TYPE_LABELS[ann.type]?.color + '44' : '#e8f0f8'}`,
                    borderRadius: '14px',
                    padding: '16px 20px',
                    opacity: ann.is_active && !isExpired(ann.expires_at) ? 1 : 0.6,
                }"
            >
                <div style="display:flex;align-items:flex-start;gap:12px">
                    <div style="flex:1;min-width:0">
                        <!-- Header row -->
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
                            <span
                                :style="{
                                    display: 'inline-block',
                                    padding: '2px 8px',
                                    borderRadius: '99px',
                                    fontSize: '10px',
                                    fontWeight: '800',
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.06em',
                                    background: TYPE_LABELS[ann.type]?.bg,
                                    color: TYPE_LABELS[ann.type]?.color,
                                }"
                            >{{ TYPE_LABELS[ann.type]?.label }}</span>

                            <span
                                :style="{
                                    display: 'inline-block',
                                    padding: '2px 8px',
                                    borderRadius: '99px',
                                    fontSize: '10px',
                                    fontWeight: '700',
                                    background: ann.is_active && !isExpired(ann.expires_at) ? '#edfdf4' : '#f5f5f5',
                                    color: ann.is_active && !isExpired(ann.expires_at) ? '#16a34a' : '#8ba0b0',
                                }"
                            >{{ ann.is_active && !isExpired(ann.expires_at) ? '● Ativo' : isExpired(ann.expires_at) ? '⏱ Expirado' : '○ Inativo' }}</span>

                            <span style="font-size:11px;color:#8ba0b0">
                                por {{ ann.creator.name }} · {{ fmtDate(ann.created_at) }}
                            </span>

                            <span v-if="ann.expires_at" style="font-size:11px;color:#8ba0b0">
                                · expira {{ fmtDate(ann.expires_at) }}
                            </span>
                        </div>

                        <p style="font-size:14px;font-weight:700;color:#1a2a3a;margin:0 0 4px">{{ ann.title }}</p>
                        <p style="font-size:12px;color:#5a7a8a;margin:0;line-height:1.5;white-space:pre-wrap">{{ ann.body }}</p>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex;gap:6px;flex-shrink:0">
                        <button
                            @click="toggle(ann.id)"
                            :style="{
                                padding: '6px 12px', borderRadius: '8px', border: '1px solid #e8f0f8',
                                background: 'white', fontSize: '11px', fontWeight: '700',
                                cursor: 'pointer',
                                color: ann.is_active ? '#d97706' : '#16a34a',
                            }"
                        >{{ ann.is_active ? 'Desativar' : 'Ativar' }}</button>

                        <button
                            @click="destroy(ann.id)"
                            style="
                                padding: 6px 12px; border-radius: 8px; border: 1px solid #fecaca;
                                background: white; font-size: 11px; font-weight: 700;
                                cursor: pointer; color: #dc2626;
                            "
                        >Eliminar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Transition name="modal">
            <div
                v-if="showModal"
                style="
                    position: fixed; inset: 0; z-index: 100;
                    background: rgba(0,0,0,0.45);
                    display: flex; align-items: center; justify-content: center;
                    padding: 20px;
                "
                @click.self="showModal = false"
            >
                <div style="
                    background: white; border-radius: 18px;
                    padding: 28px; width: 100%; max-width: 500px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
                ">
                    <h2 style="font-size:16px;font-weight:800;color:#1a2a3a;margin:0 0 20px">Novo aviso global</h2>

                    <!-- Type -->
                    <div style="margin-bottom:14px">
                        <label style="font-size:11px;font-weight:700;color:#5a7a8a;display:block;margin-bottom:6px">Tipo</label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button
                                v-for="(meta, key) in TYPE_LABELS"
                                :key="key"
                                @click="form.type = key"
                                :style="{
                                    padding: '6px 14px', borderRadius: '99px', fontSize: '12px', fontWeight: '700',
                                    cursor: 'pointer', border: '2px solid',
                                    borderColor: form.type === key ? meta.color : '#e8f0f8',
                                    background: form.type === key ? meta.bg : 'white',
                                    color: form.type === key ? meta.color : '#8ba0b0',
                                    transition: 'all .15s',
                                }"
                            >{{ meta.label }}</button>
                        </div>
                    </div>

                    <!-- Title -->
                    <div style="margin-bottom:14px">
                        <label style="font-size:11px;font-weight:700;color:#5a7a8a;display:block;margin-bottom:6px">Título *</label>
                        <input
                            v-model="form.title"
                            type="text"
                            maxlength="200"
                            placeholder="Título do aviso"
                            style="
                                width: 100%; border: 1.5px solid #e8f0f8; border-radius: 10px;
                                padding: 10px 12px; font-size: 13px; color: #1a2a3a;
                                outline: none; box-sizing: border-box; font-family: inherit;
                            "
                        />
                    </div>

                    <!-- Body -->
                    <div style="margin-bottom:14px">
                        <label style="font-size:11px;font-weight:700;color:#5a7a8a;display:block;margin-bottom:6px">Mensagem *</label>
                        <textarea
                            v-model="form.body"
                            maxlength="2000"
                            rows="4"
                            placeholder="Detalhe do aviso..."
                            style="
                                width: 100%; border: 1.5px solid #e8f0f8; border-radius: 10px;
                                padding: 10px 12px; font-size: 13px; color: #1a2a3a;
                                outline: none; resize: vertical; box-sizing: border-box; font-family: inherit;
                            "
                        />
                        <p style="font-size:10px;color:#b0c0cc;margin:3px 0 0;text-align:right">{{ form.body.length }}/2000</p>
                    </div>

                    <!-- Expires at -->
                    <div style="margin-bottom:22px">
                        <label style="font-size:11px;font-weight:700;color:#5a7a8a;display:block;margin-bottom:6px">Expira em (opcional)</label>
                        <input
                            v-model="form.expires_at"
                            type="datetime-local"
                            style="
                                width: 100%; border: 1.5px solid #e8f0f8; border-radius: 10px;
                                padding: 10px 12px; font-size: 13px; color: #1a2a3a;
                                outline: none; box-sizing: border-box; font-family: inherit;
                            "
                        />
                        <p style="font-size:10px;color:#b0c0cc;margin:3px 0 0">Deixa em branco para aviso permanente.</p>
                    </div>

                    <!-- Buttons -->
                    <div style="display:flex;gap:10px;justify-content:flex-end">
                        <button
                            @click="showModal = false"
                            style="
                                padding: 9px 20px; border-radius: 10px; border: 1px solid #e8f0f8;
                                background: white; font-size: 13px; font-weight: 600;
                                cursor: pointer; color: #5a7a8a;
                            "
                        >Cancelar</button>
                        <button
                            @click="submit"
                            :disabled="!form.title.trim() || !form.body.trim()"
                            style="
                                padding: 9px 22px; border-radius: 10px; border: 1px solid rgba(255,255,255,.45);
                                background: rgba(0,154,199,.45); backdrop-filter: blur(16px) saturate(160%); -webkit-backdrop-filter: blur(16px) saturate(160%); color: white;
                                text-shadow: 0 1px 4px rgba(0,60,100,.6); font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 6px 20px rgba(0,154,199,.4), inset 0 1px 0 rgba(255,255,255,.4);
                            "
                            :style="{ opacity: !form.title.trim() || !form.body.trim() ? 0.5 : 1 }"
                        >Publicar aviso</button>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
