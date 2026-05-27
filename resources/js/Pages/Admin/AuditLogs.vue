<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    logs:         { type: Object, required: true },
    filters:      { type: Object, default: () => ({}) },
    isSiteOwner:  { type: Boolean, default: false },
});

const f = ref({
    user_id:      props.filters.userId ?? '',
    ip:           props.filters.ip ?? '',
    action:       props.filters.action ?? '',
    category:     props.filters.category ?? '',
    community_id: props.filters.communityId ?? '',
    from:         props.filters.from ?? '',
    to:           props.filters.to ?? '',
});

const detailLog = ref(null);

const categoryColor = {
    auth:        '#009ac7',
    moderation:  '#e05555',
    admin:       '#9b6bdf',
    content:     '#2ea87e',
    community:   '#e09a00',
    security:    '#c0152a',
    system:      '#8ba0b0',
};

let filterTimer = null;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(route('admin.audit-logs'), {
            user_id:      f.value.user_id || undefined,
            ip:           f.value.ip || undefined,
            action:       f.value.action || undefined,
            category:     f.value.category || undefined,
            community_id: f.value.community_id || undefined,
            from:         f.value.from || undefined,
            to:           f.value.to || undefined,
        }, { preserveState: true, replace: true });
    }, 400);
}

function clearFilters() {
    f.value = { user_id: '', ip: '', action: '', category: '', community_id: '', from: '', to: '' };
    router.get(route('admin.audit-logs'), {}, { preserveState: true, replace: true });
}

function deleteLog(id) {
    if (!confirm('Eliminar este log de auditoria? Esta ação é irreversível.')) return;
    router.delete(route('admin.audit-logs.destroy', id), {}, { preserveScroll: true });
}

function deleteAllLogs() {
    if (!confirm('Tens a certeza? Isto vai apagar TODOS os logs de auditoria permanentemente.')) return;
    if (!confirm('Última confirmação — esta ação não pode ser desfeita. Continuar?')) return;
    router.delete(route('admin.audit-logs.destroy-all'), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin · Audit Log" />

    <AdminLayout>
        <template #header>
            <h1 style="font-size: 16px; font-weight: 800; color: #3a6478; margin: 0">Audit Log</h1>
        </template>

        <!-- Filters + Apagar todos -->
        <div style="background:white; border-radius:14px; border:1px solid #eef2f8; padding:16px 20px; margin-bottom:18px; display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-size:10px; font-weight:700; color:#8ba0b0; text-transform:uppercase; letter-spacing:.05em;">User ID</label>
                <input v-model="f.user_id" @input="applyFilters" placeholder="ID do utilizador"
                    style="background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:8px; padding:7px 10px; font-size:12px; color:#3a6478; outline:none; font-family:inherit; width:130px;"
                    @focus="$event.target.style.borderColor='#009ac7'" @blur="$event.target.style.borderColor='#dde8f0'" />
            </div>
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-size:10px; font-weight:700; color:#8ba0b0; text-transform:uppercase; letter-spacing:.05em;">IP</label>
                <input v-model="f.ip" @input="applyFilters" placeholder="ex: 192.168.1.1"
                    style="background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:8px; padding:7px 10px; font-size:12px; color:#3a6478; outline:none; font-family:inherit; width:130px;"
                    @focus="$event.target.style.borderColor='#009ac7'" @blur="$event.target.style.borderColor='#dde8f0'" />
            </div>
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-size:10px; font-weight:700; color:#8ba0b0; text-transform:uppercase; letter-spacing:.05em;">Ação</label>
                <input v-model="f.action" @input="applyFilters" placeholder="ex: user.punish"
                    style="background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:8px; padding:7px 10px; font-size:12px; color:#3a6478; outline:none; font-family:inherit; width:140px;"
                    @focus="$event.target.style.borderColor='#009ac7'" @blur="$event.target.style.borderColor='#dde8f0'" />
            </div>
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-size:10px; font-weight:700; color:#8ba0b0; text-transform:uppercase; letter-spacing:.05em;">Categoria</label>
                <select v-model="f.category" @change="applyFilters"
                    style="background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:8px; padding:7px 10px; font-size:12px; color:#3a6478; font-family:inherit; outline:none; cursor:pointer; width:130px;">
                    <option value="">Todas</option>
                    <option v-for="c in ['auth','moderation','admin','content','community','security','system']" :key="c" :value="c">{{ c }}</option>
                </select>
            </div>
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-size:10px; font-weight:700; color:#8ba0b0; text-transform:uppercase; letter-spacing:.05em;">De</label>
                <input type="date" v-model="f.from" @change="applyFilters"
                    style="background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:8px; padding:7px 10px; font-size:12px; color:#3a6478; font-family:inherit; outline:none; width:130px;" />
            </div>
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-size:10px; font-weight:700; color:#8ba0b0; text-transform:uppercase; letter-spacing:.05em;">Até</label>
                <input type="date" v-model="f.to" @change="applyFilters"
                    style="background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:8px; padding:7px 10px; font-size:12px; color:#3a6478; font-family:inherit; outline:none; width:130px;" />
            </div>
            <button @click="clearFilters"
                style="background:#f0f4f8; border:none; border-radius:8px; padding:8px 14px; font-size:12px; font-weight:700; color:#5a7a8a; cursor:pointer; font-family:inherit;">
                Limpar
            </button>
            <div style="flex:1;"></div>
            <button v-if="isSiteOwner" @click="deleteAllLogs"
                style="background:#fef2f2; border:1.5px solid #fca5a5; border-radius:9px; padding:8px 14px; font-size:12px; font-weight:700; color:#dc2626; cursor:pointer; font-family:inherit;">
                Apagar todos
            </button>
        </div>

        <!-- Log table -->
        <div style="background:white; border-radius:14px; border:1px solid #eef2f8; box-shadow:0 2px 8px rgba(0,0,0,.04); overflow:hidden;">
            <div v-if="logs.data.length === 0" style="padding:48px; text-align:center; color:#8ba0b0; font-size:13px;">
                Sem logs para este filtro.
            </div>
            <div v-else style="overflow-x: auto">
            <table style="width:100%; border-collapse:collapse; min-width:660px;">
                <thead>
                    <tr style="border-bottom:1px solid #f0f4f8;">
                        <th v-for="h in ['Data','Actor','Ação','Categoria','Target','IP','']" :key="h"
                            style="text-align:left; padding:11px 14px; font-size:10px; font-weight:800; color:#8ba0b0; text-transform:uppercase; letter-spacing:.07em; white-space:nowrap;">
                            {{ h }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(l, i) in logs.data" :key="l.id"
                        :style="{ borderTop: i>0 ? '1px solid #f4f7fb' : 'none' }">
                        <td style="padding:10px 14px; font-size:11px; color:#8ba0b0; white-space:nowrap;">{{ l.created_at }}</td>
                        <td style="padding:10px 14px; font-size:12px; color:#3a6478;">
                            <span v-if="l.actor">{{ l.actor.name }}<br><span style="color:#8ba0b0;font-size:10px;">@{{ l.actor.username }}</span></span>
                            <span v-else style="color:#b0c0cc; font-style:italic;">Sistema</span>
                        </td>
                        <td style="padding:10px 14px;">
                            <code style="font-size:11px; background:#f4f7fb; padding:2px 6px; border-radius:4px; color:#3a6478;">{{ l.action }}</code>
                        </td>
                        <td style="padding:10px 14px;">
                            <span :style="{ fontSize:'10px', fontWeight:'800', padding:'2px 7px', borderRadius:'99px',
                                background: (categoryColor[l.category] ?? '#8ba0b0') + '22',
                                color: categoryColor[l.category] ?? '#8ba0b0' }">
                                {{ l.category }}
                            </span>
                        </td>
                        <td style="padding:10px 14px; font-size:12px; color:#5a7a8a;">
                            <span v-if="l.target_user">{{ l.target_user.name }}</span>
                            <span v-else-if="l.community" style="color:#e09a00">{{ l.community.label }}</span>
                            <span v-else-if="l.target_type" style="color:#b0c0cc; font-size:11px;">{{ l.target_type }}</span>
                        </td>
                        <td style="padding:10px 14px; font-size:11px; color:#8ba0b0; font-family:monospace;">{{ l.ip_address }}</td>
                        <td style="padding:10px 14px; text-align:right; white-space:nowrap;">
                            <div style="display:inline-flex; gap:6px; align-items:center;">
                                <button v-if="l.metadata && Object.keys(l.metadata).length"
                                    @click="detailLog = l"
                                    style="font-size:11px; color:#009ac7; background:none; border:1px solid #009ac733; border-radius:6px; padding:3px 9px; cursor:pointer; font-weight:600;">
                                    Detalhe
                                </button>
                                <button v-if="isSiteOwner" @click="deleteLog(l.id)"
                                    style="font-size:11px; color:#dc2626; background:none; border:1px solid #dc262633; border-radius:6px; padding:3px 9px; cursor:pointer; font-weight:600;">
                                    Apagar
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div><!-- /overflow-x wrapper -->

            <div v-if="logs.last_page > 1"
                style="padding:14px 16px; border-top:1px solid #f0f4f8; display:flex; gap:6px; justify-content:center;">
                <Link v-for="link in logs.links" :key="link.label" :href="link.url ?? '#'"
                    :style="{ padding:'5px 11px', borderRadius:'7px', fontSize:'12px', fontWeight:'600', textDecoration:'none',
                        background: link.active ? '#009ac7' : '#f0f4f8', color: link.active ? 'white' : '#5a7a8a',
                        pointerEvents: link.url ? 'auto' : 'none', opacity: link.url ? 1 : 0.4 }">
                    <span v-html="link.label" />
                </Link>
            </div>
        </div>

        <!-- Detail modal -->
        <div v-if="detailLog"
            style="position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px;"
            @click.self="detailLog=null">
            <div style="background:white; border-radius:18px; padding:28px 32px; width:100%; max-width:560px; max-height:80vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2);">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                    <h2 style="font-size:15px; font-weight:900; color:#3a6478; margin:0;">Metadata</h2>
                    <button @click="detailLog=null" style="background:none; border:none; cursor:pointer; font-size:18px; color:#8ba0b0;">✕</button>
                </div>
                <div style="margin-bottom:12px;">
                    <p style="font-size:11px; color:#8ba0b0; margin:0 0 4px;">Ação</p>
                    <code style="font-size:13px; color:#3a6478;">{{ detailLog.action }}</code>
                </div>
                <pre style="background:#f4f7fb; border-radius:10px; padding:14px; font-size:12px; color:#3a6478; overflow:auto; white-space:pre-wrap; word-break:break-word;">{{ JSON.stringify(detailLog.metadata, null, 2) }}</pre>
            </div>
        </div>
    </AdminLayout>
</template>
