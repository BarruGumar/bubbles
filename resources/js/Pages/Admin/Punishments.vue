<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    punishments: { type: Object, required: true },
    query: { type: String, default: '' },
    typeFilter: { type: String, default: '' },
    statusFilter: { type: String, default: 'active' },
});

const search     = ref(props.query ?? '');
const showModal  = ref(false);
const revokeModal = ref(null); // punishment object to revoke
const revokeReason = ref('');
const form = ref({ user_id: '', type: 'warning', reason: '', notes: '', ends_at: '' });
const userSearch = ref('');
const userResults = ref([]);
let userTimer = null;

const typeLabel  = { warning: 'Aviso', mute: 'Silêncio', suspension: 'Suspensão', ban: 'Banimento' };
const typeColor  = { warning: '#e09a00', mute: '#9b6bdf', suspension: '#e05555', ban: '#c0152a' };
const typeBg     = { warning: '#fff8e6', mute: '#f4eeff', suspension: '#fff0f0', ban: '#ffe6e8' };
const statusLabel = { active: 'Ativo', expired: 'Expirado', revoked: 'Revogado', all: 'Todos' };

let filterTimer = null;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(route('admin.punishments'), {
            q: search.value,
            status: props.statusFilter,
            type: props.typeFilter,
        }, { preserveState: true, replace: true });
    }, 320);
}

function setStatus(s) {
    router.get(route('admin.punishments'), { q: search.value, status: s, type: props.typeFilter }, { preserveState: true, replace: true });
}

function setType(t) {
    router.get(route('admin.punishments'), { q: search.value, status: props.statusFilter, type: t }, { preserveState: true, replace: true });
}

function searchUsers() {
    clearTimeout(userTimer);
    if (!userSearch.value.trim()) { userResults.value = []; return; }
    userTimer = setTimeout(async () => {
        const res = await fetch(route('search.api') + '?q=' + encodeURIComponent(userSearch.value) + '&type=users');
        const json = await res.json();
        userResults.value = (json.users ?? []).slice(0, 6);
    }, 300);
}

function selectUser(u) {
    form.value.user_id = u.id;
    userSearch.value = `${u.name} (@${u.username})`;
    userResults.value = [];
}

function submitPunishment() {
    if (!form.value.user_id || !form.value.reason) return;
    router.post(route('admin.punishments.store'), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.value = { user_id: '', type: 'warning', reason: '', notes: '', ends_at: '' };
            userSearch.value = '';
        },
    });
}

function openRevoke(p) {
    revokeModal.value = p;
    revokeReason.value = '';
}

function submitRevoke() {
    if (!revokeModal.value) return;
    router.patch(route('admin.punishments.revoke', revokeModal.value.id), { revoked_reason: revokeReason.value }, {
        preserveScroll: true,
        onSuccess: () => { revokeModal.value = null; },
    });
}
</script>

<template>
    <Head title="Admin · Punições" />

    <AdminLayout>
        <template #header>
            <h1 style="font-size: 16px; font-weight: 800; color: #3a6478; margin: 0">Punições Globais</h1>
        </template>

        <!-- Toolbar -->
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; align-items: center; overflow-x: auto; padding-bottom: 4px">
            <input
                v-model="search" @input="applyFilters"
                placeholder="Pesquisar utilizador..."
                style="background: white; border: 1.5px solid #dde8f0; border-radius: 10px; padding: 9px 14px; font-size: 13px; color: #3a6478; outline: none; font-family: inherit; width: 220px;"
                @focus="$event.target.style.borderColor='#009ac7'" @blur="$event.target.style.borderColor='#dde8f0'"
            />

            <!-- Status tabs -->
            <div style="display: flex; gap: 6px;">
                <button v-for="s in ['active','expired','revoked','all']" :key="s"
                    @click="setStatus(s)"
                    :style="{ fontSize:'11px', fontWeight:'700', padding:'7px 13px', borderRadius:'99px', border:'none', cursor:'pointer',
                        background: statusFilter===s ? '#009ac7' : '#f0f4f8', color: statusFilter===s ? 'white' : '#5a7a8a' }">
                    {{ statusLabel[s] }}
                </button>
            </div>

            <!-- Type filter -->
            <select @change="setType($event.target.value)" :value="typeFilter"
                style="background: white; border: 1.5px solid #dde8f0; border-radius: 10px; padding: 8px 12px; font-size: 12px; color: #3a6478; font-family: inherit; cursor: pointer; outline: none;">
                <option value="">Todos os tipos</option>
                <option v-for="(label, val) in typeLabel" :key="val" :value="val">{{ label }}</option>
            </select>

            <button @click="showModal = true"
                style="margin-left: auto; background: #009ac7; color: white; border: none; border-radius: 10px; padding: 9px 18px; font-size: 12px; font-weight: 700; cursor: pointer; font-family: inherit;">
                + Nova Punição
            </button>
        </div>

        <!-- Table -->
        <div style="background: white; border-radius: 14px; border: 1px solid #eef2f8; box-shadow: 0 2px 8px rgba(0,0,0,.04); overflow: hidden;">
            <div v-if="punishments.data.length === 0" style="padding: 48px; text-align: center; color: #8ba0b0; font-size: 13px;">
                Sem punições para este filtro.
            </div>
            <div v-else style="overflow-x: auto">
            <table style="width: 100%; border-collapse: collapse; min-width: 640px;">
                <thead>
                    <tr style="border-bottom: 1px solid #f0f4f8;">
                        <th v-for="h in ['Utilizador','Tipo','Motivo','Duração','Estado','Emitido por','Ações']" :key="h"
                            style="text-align:left; padding:11px 14px; font-size:10px; font-weight:800; color:#8ba0b0; text-transform:uppercase; letter-spacing:.07em;">
                            {{ h }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, i) in punishments.data" :key="p.id"
                        :style="{ borderTop: i>0 ? '1px solid #f4f7fb' : 'none' }">
                        <td style="padding: 11px 14px;">
                            <p style="font-size:13px; font-weight:700; color:#3a6478; margin:0">{{ p.user.name }}</p>
                            <p style="font-size:11px; color:#8ba0b0; margin:0">@{{ p.user.username }}</p>
                        </td>
                        <td style="padding: 11px 14px;">
                            <span :style="{ fontSize:'11px', fontWeight:'800', padding:'3px 9px', borderRadius:'99px',
                                background: typeBg[p.type], color: typeColor[p.type] }">
                                {{ typeLabel[p.type] }}
                            </span>
                        </td>
                        <td style="padding:11px 14px; font-size:12px; color:#2a4a5a; max-width:220px; word-break:break-word;">
                            {{ p.reason }}
                        </td>
                        <td style="padding:11px 14px; font-size:11px; color:#5a7a8a; white-space:nowrap;">
                            {{ p.ends_at ? 'até ' + p.ends_at : 'Permanente' }}
                        </td>
                        <td style="padding:11px 14px;">
                            <span :style="{ fontSize:'10px', fontWeight:'800', padding:'3px 8px', borderRadius:'99px',
                                background: p.is_active ? '#e8fff4' : '#f4f7fb',
                                color: p.is_active ? '#2ea87e' : '#8ba0b0' }">
                                {{ p.is_active ? 'Ativo' : p.revoked_at ? 'Revogado' : 'Expirado' }}
                            </span>
                        </td>
                        <td style="padding:11px 14px; font-size:12px; color:#5a7a8a;">{{ p.issued_by.name }}</td>
                        <td style="padding:11px 14px; text-align:right;">
                            <button v-if="p.is_active"
                                @click="openRevoke(p)"
                                style="font-size:11px; color:#e05555; background:none; border:1px solid #e0555533; border-radius:6px; padding:4px 10px; cursor:pointer; font-weight:600; transition:all .2s;"
                                @mouseenter="$event.currentTarget.style.background='#fff0f0'"
                                @mouseleave="$event.currentTarget.style.background='transparent'">
                                Revogar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div><!-- /overflow-x wrapper -->

            <!-- Pagination -->
            <div v-if="punishments.last_page > 1"
                style="padding:14px 16px; border-top:1px solid #f0f4f8; display:flex; gap:6px; justify-content:center;">
                <Link v-for="link in punishments.links" :key="link.label" :href="link.url ?? '#'"
                    :style="{ padding:'5px 11px', borderRadius:'7px', fontSize:'12px', fontWeight:'600', textDecoration:'none',
                        background: link.active ? '#009ac7' : '#f0f4f8', color: link.active ? 'white' : '#5a7a8a',
                        pointerEvents: link.url ? 'auto' : 'none', opacity: link.url ? 1 : 0.4 }">
                    <span v-html="link.label" />
                </Link>
            </div>
        </div>

        <!-- Create punishment modal -->
        <div v-if="showModal"
            style="position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px;"
            @click.self="showModal=false">
            <div style="background:white; border-radius:18px; padding:28px 32px; width:100%; max-width:480px; box-shadow:0 20px 60px rgba(0,0,0,.2);">
                <h2 style="font-size:16px; font-weight:900; color:#3a6478; margin:0 0 20px;">Nova Punição</h2>

                <!-- User search -->
                <div style="margin-bottom:14px; position:relative;">
                    <label style="font-size:12px; font-weight:700; color:#5a7a8a; display:block; margin-bottom:6px;">Utilizador</label>
                    <input v-model="userSearch" @input="searchUsers" placeholder="Pesquisar pelo nome ou username..."
                        style="width:100%; background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:10px; padding:10px 14px; font-size:13px; color:#3a6478; outline:none; font-family:inherit; box-sizing:border-box;"
                        @focus="$event.target.style.borderColor='#009ac7'" @blur="setTimeout(()=>userResults=[],200)" />
                    <div v-if="userResults.length" style="position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #dde8f0; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.1); z-index:10; overflow:hidden;">
                        <div v-for="u in userResults" :key="u.id"
                            @click="selectUser(u)"
                            style="padding:10px 14px; font-size:13px; cursor:pointer; color:#3a6478;"
                            @mouseenter="$event.currentTarget.style.background='#f0f8ff'"
                            @mouseleave="$event.currentTarget.style.background='white'">
                            <strong>{{ u.name }}</strong> <span style="color:#8ba0b0">@{{ u.username }}</span>
                        </div>
                    </div>
                </div>

                <!-- Type -->
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:700; color:#5a7a8a; display:block; margin-bottom:6px;">Tipo</label>
                    <select v-model="form.type"
                        style="width:100%; background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:10px; padding:10px 14px; font-size:13px; color:#3a6478; font-family:inherit; outline:none; cursor:pointer;">
                        <option v-for="(label, val) in typeLabel" :key="val" :value="val">{{ label }}</option>
                    </select>
                </div>

                <!-- Reason -->
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:700; color:#5a7a8a; display:block; margin-bottom:6px;">Motivo *</label>
                    <textarea v-model="form.reason" rows="3" placeholder="Descreve o motivo da punição..."
                        style="width:100%; background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:10px; padding:10px 14px; font-size:13px; color:#3a6478; font-family:inherit; outline:none; resize:vertical; box-sizing:border-box;"
                        @focus="$event.target.style.borderColor='#009ac7'" @blur="$event.target.style.borderColor='#dde8f0'" />
                </div>

                <!-- Duration -->
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:700; color:#5a7a8a; display:block; margin-bottom:6px;">Termina em (deixa vazio para permanente)</label>
                    <input type="datetime-local" v-model="form.ends_at"
                        style="width:100%; background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:10px; padding:10px 14px; font-size:13px; color:#3a6478; font-family:inherit; outline:none; box-sizing:border-box;"
                        @focus="$event.target.style.borderColor='#009ac7'" @blur="$event.target.style.borderColor='#dde8f0'" />
                </div>

                <!-- Notes -->
                <div style="margin-bottom:22px;">
                    <label style="font-size:12px; font-weight:700; color:#5a7a8a; display:block; margin-bottom:6px;">Notas internas (só admins veem)</label>
                    <textarea v-model="form.notes" rows="2" placeholder="Notas opcionais..."
                        style="width:100%; background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:10px; padding:10px 14px; font-size:13px; color:#3a6478; font-family:inherit; outline:none; resize:vertical; box-sizing:border-box;"
                        @focus="$event.target.style.borderColor='#009ac7'" @blur="$event.target.style.borderColor='#dde8f0'" />
                </div>

                <div style="display:flex; gap:10px;">
                    <button @click="showModal=false"
                        style="flex:1; padding:11px; background:#f0f4f8; border:none; border-radius:10px; font-size:13px; font-weight:700; color:#5a7a8a; cursor:pointer; font-family:inherit;">
                        Cancelar
                    </button>
                    <button @click="submitPunishment"
                        :disabled="!form.user_id || !form.reason"
                        style="flex:2; padding:11px; background:#e05555; border:none; border-radius:10px; font-size:13px; font-weight:700; color:white; cursor:pointer; font-family:inherit;"
                        :style="{ opacity: (!form.user_id || !form.reason) ? 0.5 : 1 }">
                        Aplicar Punição
                    </button>
                </div>
            </div>
        </div>

        <!-- Revoke modal -->
        <div v-if="revokeModal"
            style="position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px;"
            @click.self="revokeModal=null">
            <div style="background:white; border-radius:18px; padding:28px 32px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.2);">
                <h2 style="font-size:15px; font-weight:900; color:#3a6478; margin:0 0 6px;">Revogar Punição</h2>
                <p style="font-size:12px; color:#8ba0b0; margin:0 0 18px;">
                    {{ typeLabel[revokeModal.type] }} de {{ revokeModal.user.name }}
                </p>
                <div style="margin-bottom:18px;">
                    <label style="font-size:12px; font-weight:700; color:#5a7a8a; display:block; margin-bottom:6px;">Motivo da revogação (opcional)</label>
                    <input v-model="revokeReason" placeholder="ex: apelação aceite..."
                        style="width:100%; background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:10px; padding:10px 14px; font-size:13px; color:#3a6478; font-family:inherit; outline:none; box-sizing:border-box;" />
                </div>
                <div style="display:flex; gap:10px;">
                    <button @click="revokeModal=null"
                        style="flex:1; padding:11px; background:#f0f4f8; border:none; border-radius:10px; font-size:13px; font-weight:700; color:#5a7a8a; cursor:pointer; font-family:inherit;">
                        Cancelar
                    </button>
                    <button @click="submitRevoke"
                        style="flex:2; padding:11px; background:#2ea87e; border:none; border-radius:10px; font-size:13px; font-weight:700; color:white; cursor:pointer; font-family:inherit;">
                        Confirmar Revogação
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
