<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    reports: { type: Object, required: true },
    statusFilter: { type: String, default: 'pending' },
});

const activeStatus = ref(props.statusFilter);

function changeStatus(s) {
    activeStatus.value = s;
    router.get(route('admin.reports'), { status: s }, { preserveState: true, replace: true });
}

const noteMap = ref({});

function resolveReport(id) {
    router.patch(route('admin.reports.resolve', id), { admin_note: noteMap.value[id] ?? '' }, { preserveScroll: true });
}

function dismissReport(id) {
    router.patch(route('admin.reports.dismiss', id), { admin_note: noteMap.value[id] ?? '' }, { preserveScroll: true });
}

const statusLabel = { pending: 'Pendente', resolved: 'Resolvido', dismissed: 'Dispensado' };
const statusColor = { pending: '#e09a00', resolved: '#2ea87e', dismissed: '#8ba0b0' };
const statusBg = { pending: '#fff8e6', resolved: '#f0fff8', dismissed: '#f4f7fb' };
</script>

<template>
    <Head title="Admin · Denúncias" />

    <AdminLayout>
        <template #header>
            <h1 style="font-size: 16px; font-weight: 800; color: #1a3a4a; margin: 0">Denúncias</h1>
        </template>

        <!-- Status tabs -->
        <div style="display: flex; gap: 8px; margin-bottom: 18px">
            <button
                v-for="s in ['pending', 'resolved', 'dismissed']"
                :key="s"
                @click="changeStatus(s)"
                :style="{
                    fontSize: '12px',
                    fontWeight: '700',
                    padding: '7px 16px',
                    borderRadius: '99px',
                    border: 'none',
                    cursor: 'pointer',
                    transition: 'all .15s',
                    background: activeStatus === s ? '#009ac7' : '#f0f4f8',
                    color: activeStatus === s ? 'white' : '#5a7a8a',
                }"
            >
                {{ statusLabel[s] }}
            </button>
        </div>

        <div
            style="
                background: white;
                border-radius: 14px;
                border: 1px solid #eef2f8;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
                overflow: hidden;
            "
        >
            <div
                v-if="reports.data.length === 0"
                style="padding: 48px; text-align: center; color: #8ba0b0; font-size: 13px"
            >
                Sem denúncias {{ statusLabel[activeStatus].toLowerCase() }}s. 🎉
            </div>

            <div v-else>
                <div
                    v-for="(r, i) in reports.data"
                    :key="r.id"
                    :style="{ padding: '18px 20px', borderTop: i > 0 ? '1px solid #f0f4f8' : 'none' }"
                >
                    <div
                        style="
                            display: flex;
                            align-items: flex-start;
                            justify-content: space-between;
                            gap: 12px;
                            flex-wrap: wrap;
                        "
                    >
                        <div style="min-width: 0; flex: 1">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px">
                                <span
                                    :style="{
                                        fontSize: '10px',
                                        fontWeight: '800',
                                        padding: '3px 8px',
                                        borderRadius: '99px',
                                        background: statusBg[r.status],
                                        color: statusColor[r.status],
                                    }"
                                    >{{ statusLabel[r.status] }}</span
                                >
                                <span style="font-size: 11px; color: #8ba0b0">{{ r.type }} · {{ r.created_at }}</span>
                            </div>

                            <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0 0 4px">
                                {{ r.reason }}
                            </p>

                            <div style="font-size: 12px; color: #5a7a8a; margin-bottom: 8px">
                                Denunciado por <strong>{{ r.reporter_name }}</strong>
                                <span v-if="r.reportable_author">
                                    · Autor: <strong>{{ r.reportable_author }}</strong></span
                                >
                            </div>

                            <div
                                v-if="r.reportable_content"
                                style="
                                    background: #f4f7fb;
                                    border-radius: 8px;
                                    padding: 10px 14px;
                                    font-size: 12px;
                                    color: #2a4a5a;
                                    margin-bottom: 10px;
                                    max-width: 560px;
                                    white-space: pre-wrap;
                                    word-break: break-word;
                                "
                            >
                                {{ r.reportable_content }}
                            </div>

                            <div
                                v-if="r.admin_note"
                                style="font-size: 11px; color: #8ba0b0; font-style: italic; margin-bottom: 8px"
                            >
                                Nota admin: {{ r.admin_note }}
                            </div>

                            <div
                                v-if="r.status === 'pending'"
                                style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 10px"
                            >
                                <input
                                    v-model="noteMap[r.id]"
                                    placeholder="Nota opcional..."
                                    style="
                                        flex: 1;
                                        min-width: 180px;
                                        max-width: 320px;
                                        background: white;
                                        border: 1.5px solid #dde8f0;
                                        border-radius: 8px;
                                        padding: 7px 12px;
                                        font-size: 12px;
                                        color: #1a3a4a;
                                        outline: none;
                                        font-family: inherit;
                                    "
                                    @focus="$event.target.style.borderColor = '#009ac7'"
                                    @blur="$event.target.style.borderColor = '#dde8f0'"
                                />
                                <button
                                    @click="resolveReport(r.id)"
                                    style="
                                        font-size: 11px;
                                        color: #2ea87e;
                                        background: none;
                                        border: 1px solid #2ea87e44;
                                        border-radius: 6px;
                                        padding: 6px 14px;
                                        cursor: pointer;
                                        font-weight: 700;
                                        transition: all 0.2s;
                                    "
                                    @mouseenter="$event.currentTarget.style.background = '#f0fff8'"
                                    @mouseleave="$event.currentTarget.style.background = 'transparent'"
                                >
                                    Resolver
                                </button>
                                <button
                                    @click="dismissReport(r.id)"
                                    style="
                                        font-size: 11px;
                                        color: #8ba0b0;
                                        background: none;
                                        border: 1px solid #8ba0b033;
                                        border-radius: 6px;
                                        padding: 6px 14px;
                                        cursor: pointer;
                                        font-weight: 700;
                                        transition: all 0.2s;
                                    "
                                    @mouseenter="$event.currentTarget.style.background = '#f4f7fb'"
                                    @mouseleave="$event.currentTarget.style.background = 'transparent'"
                                >
                                    Dispensar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="reports.last_page > 1"
                style="
                    padding: 14px 16px;
                    border-top: 1px solid #f0f4f8;
                    display: flex;
                    gap: 6px;
                    justify-content: center;
                "
            >
                <Link
                    v-for="link in reports.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :style="{
                        padding: '5px 11px',
                        borderRadius: '7px',
                        fontSize: '12px',
                        fontWeight: '600',
                        textDecoration: 'none',
                        background: link.active ? '#009ac7' : '#f0f4f8',
                        color: link.active ? 'white' : '#5a7a8a',
                        pointerEvents: link.url ? 'auto' : 'none',
                        opacity: link.url ? 1 : 0.4,
                    }"
                    ><span v-html="link.label"
                /></Link>
            </div>
        </div>
    </AdminLayout>
</template>
