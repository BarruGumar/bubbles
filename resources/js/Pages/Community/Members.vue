<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { clImg } from '@/Composables/useCloudinary';

const props = defineProps({
    community: Object,
    members: Object,
    query: { type: String, default: '' },
    filter: { type: String, default: 'all' },
    myRole: { type: String, default: 'member' },
});

const search = ref(props.query ?? '');
const banModal  = ref(null);  // user to ban
const muteModal = ref(null);  // user to mute
const banForm   = ref({ reason: '', banned_until: '' });
const muteForm  = ref({ reason: '', muted_until: '' });

const roleLabel  = { owner: 'Criador', admin: 'Admin', moderator: 'Moderador', member: 'Membro', banned: 'Banido' };
const roleColor  = { owner: '#009ac7', admin: '#9b6bdf', moderator: '#2ea87e', member: '#8ba0b0', banned: '#e05555' };
const filterLabel = { all: 'Todos', staff: 'Staff', banned: 'Banidos', muted: 'Silenciados' };

let timer = null;

function doSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('community.members', props.community.id), {
            q: search.value,
            filter: props.filter,
        }, { preserveState: true, replace: true });
    }, 320);
}

function setFilter(f) {
    router.get(route('community.members', props.community.id), { q: search.value, filter: f }, { preserveState: true, replace: true });
}

function canActOn(member) {
    if (member.is_owner) return false;
    if (props.myRole === 'owner' || props.myRole === 'admin') return true;
    if (props.myRole === 'moderator') return member.role === 'member';
    return false;
}

const canPromote = props.myRole === 'owner' || props.myRole === 'admin';

function updateRole(member, role) {
    router.patch(route('community.members.role', [props.community.id, member.id]), { role }, { preserveScroll: true });
}

function submitBan() {
    if (!banModal.value || !banForm.value.reason) return;
    router.post(route('community.moderation.ban', props.community.id), {
        user_id: banModal.value.id,
        reason: banForm.value.reason,
        banned_until: banForm.value.banned_until || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { banModal.value = null; banForm.value = { reason: '', banned_until: '' }; },
    });
}

function submitMute() {
    if (!muteModal.value || !muteForm.value.reason) return;
    router.post(route('community.moderation.mute', props.community.id), {
        user_id: muteModal.value.id,
        reason: muteForm.value.reason,
        muted_until: muteForm.value.muted_until || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { muteModal.value = null; muteForm.value = { reason: '', muted_until: '' }; },
    });
}

function unban(member) {
    router.delete(route('community.moderation.unban', [props.community.id, member.id]), { preserveScroll: true });
}

function unmute(member) {
    router.delete(route('community.moderation.unmute', [props.community.id, member.id]), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${community.title} · Membros`" />

    <AuthenticatedLayout>
        <div style="max-width:860px; margin:0 auto; padding:32px 20px 80px;">

            <!-- Header -->
            <div style="display:flex; align-items:center; gap:14px; margin-bottom:24px;">
                <Link :href="route('community.show', community.id)"
                    style="font-size:12px; color:#8ba0b0; text-decoration:none;">&larr; Voltar à comunidade</Link>
                <div :style="{ width:'10px', height:'10px', borderRadius:'50%', background: community.color, flexShrink:0 }"></div>
                <h1 style="font-size:18px; font-weight:900; color:var(--text); margin:0;">{{ community.title }} · Membros</h1>
            </div>

            <!-- Toolbar -->
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; align-items:center;">
                <input v-model="search" @input="doSearch" placeholder="Pesquisar membro..."
                    style="background:var(--input-bg); border:1.5px solid var(--input-border); border-radius:10px; padding:9px 14px; font-size:13px; color:var(--input-text); outline:none; font-family:inherit; width:220px;"
                    @focus="$event.target.style.borderColor='#009ac7'" @blur="$event.target.style.borderColor=''" />

                <div style="display:flex; gap:6px;">
                    <button v-for="(label, key) in filterLabel" :key="key" @click="setFilter(key)"
                        :style="{ fontSize:'11px', fontWeight:'700', padding:'7px 13px', borderRadius:'99px', border:'none', cursor:'pointer',
                            background: filter===key ? community.color : '#f0f4f8', color: filter===key ? 'white' : '#5a7a8a' }">
                        {{ label }}
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div style="background:var(--card-bg); border-radius:16px; border:1px solid var(--card-border); overflow:hidden;">
                <div v-if="members.data.length === 0"
                    style="padding:48px; text-align:center; color:var(--text-3); font-size:13px;">
                    Sem membros para este filtro.
                </div>
                <div v-else>
                    <div v-for="(m, i) in members.data" :key="m.id"
                        :style="{ padding:'14px 20px', borderTop: i>0 ? '1px solid var(--card-border)' : 'none',
                            display:'flex', alignItems:'center', gap:'12px', flexWrap:'wrap' }">

                        <!-- Avatar -->
                        <img v-if="m.avatar" :src="clImg(m.avatar,72,72,'fill','face')"
                            :style="{ width:'38px', height:'38px', borderRadius:'50%', objectFit:'cover', border:`2px solid ${m.avatar_color}`, flexShrink:0 }" />
                        <div v-else :style="{ width:'38px', height:'38px', borderRadius:'50%', background:m.avatar_color,
                            display:'flex', alignItems:'center', justifyContent:'center', fontSize:'14px', fontWeight:'800', color:'white', flexShrink:0 }">
                            {{ (m.name ?? '?')[0].toUpperCase() }}
                        </div>

                        <!-- Info -->
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <Link :href="route('profile.show', m.username)"
                                    style="font-size:13px; font-weight:700; color:var(--text); text-decoration:none;">
                                    {{ m.name }}
                                </Link>
                                <span style="font-size:11px; color:var(--text-3);">@{{ m.username }}</span>
                                <span :style="{ fontSize:'10px', fontWeight:'800', padding:'2px 8px', borderRadius:'99px',
                                    background: roleColor[m.role]+'22', color: roleColor[m.role] }">
                                    {{ roleLabel[m.role] ?? m.role }}
                                </span>
                                <span v-if="m.status === 'banned'" style="font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; background:#fff0f0; color:#e05555;">
                                    BANIDO{{ m.banned_until ? ' até ' + new Date(m.banned_until).toLocaleDateString('pt-PT') : '' }}
                                </span>
                                <span v-if="m.status === 'muted'" style="font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; background:#f4eeff; color:#9b6bdf;">
                                    SILENCIADO{{ m.muted_until ? ' até ' + new Date(m.muted_until).toLocaleDateString('pt-PT') : '' }}
                                </span>
                            </div>
                            <p v-if="m.ban_reason || m.mute_reason" style="font-size:11px; color:var(--text-3); margin:2px 0 0; font-style:italic;">
                                Motivo: {{ m.ban_reason || m.mute_reason }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div v-if="canActOn(m)" style="display:flex; gap:6px; flex-wrap:wrap; flex-shrink:0;">

                            <!-- Role change (promote/demote) -->
                            <select v-if="canPromote && m.status === 'active'"
                                :value="m.role"
                                @change="updateRole(m, $event.target.value)"
                                style="font-size:11px; font-weight:700; background:transparent; border:1px solid var(--card-border); border-radius:8px; padding:5px 8px; cursor:pointer; font-family:inherit; color:var(--text-2);">
                                <option value="member">Membro</option>
                                <option value="moderator">Moderador</option>
                                <option value="admin">Admin</option>
                            </select>

                            <!-- Unban / Unmute -->
                            <button v-if="m.status === 'banned'" @click="unban(m)"
                                style="font-size:11px; font-weight:700; color:#2ea87e; background:none; border:1px solid #2ea87e44; border-radius:8px; padding:5px 10px; cursor:pointer; font-family:inherit;"
                                @mouseenter="$event.currentTarget.style.background='#f0fff8'"
                                @mouseleave="$event.currentTarget.style.background='transparent'">
                                Desbanir
                            </button>
                            <button v-else-if="m.status === 'muted'" @click="unmute(m)"
                                style="font-size:11px; font-weight:700; color:#9b6bdf; background:none; border:1px solid #9b6bdf44; border-radius:8px; padding:5px 10px; cursor:pointer; font-family:inherit;"
                                @mouseenter="$event.currentTarget.style.background='#f4eeff'"
                                @mouseleave="$event.currentTarget.style.background='transparent'">
                                Dessilenciar
                            </button>

                            <!-- Ban / Mute -->
                            <template v-if="m.status === 'active'">
                                <button @click="muteModal = m; muteForm = { reason:'', muted_until:'' }"
                                    style="font-size:11px; font-weight:700; color:#9b6bdf; background:none; border:1px solid #9b6bdf44; border-radius:8px; padding:5px 10px; cursor:pointer; font-family:inherit;"
                                    @mouseenter="$event.currentTarget.style.background='#f4eeff'"
                                    @mouseleave="$event.currentTarget.style.background='transparent'">
                                    Silenciar
                                </button>
                                <button @click="banModal = m; banForm = { reason:'', banned_until:'' }"
                                    style="font-size:11px; font-weight:700; color:#e05555; background:none; border:1px solid #e0555533; border-radius:8px; padding:5px 10px; cursor:pointer; font-family:inherit;"
                                    @mouseenter="$event.currentTarget.style.background='#fff0f0'"
                                    @mouseleave="$event.currentTarget.style.background='transparent'">
                                    Banir
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="members.last_page > 1"
                    style="padding:14px 16px; border-top:1px solid var(--card-border); display:flex; gap:6px; justify-content:center;">
                    <Link v-for="link in members.links" :key="link.label" :href="link.url ?? '#'"
                        :style="{ padding:'5px 11px', borderRadius:'7px', fontSize:'12px', fontWeight:'600', textDecoration:'none',
                            background: link.active ? community.color : '#f0f4f8', color: link.active ? 'white' : '#5a7a8a',
                            pointerEvents: link.url ? 'auto' : 'none', opacity: link.url ? 1 : 0.4 }">
                        <span v-html="link.label" />
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Ban modal -->
    <div v-if="banModal"
        style="position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px;"
        @click.self="banModal=null">
        <div style="background:white; border-radius:18px; padding:28px 32px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <h2 style="font-size:15px; font-weight:900; color:#3a6478; margin:0 0 6px;">Banir {{ banModal.name }}</h2>
            <p style="font-size:12px; color:#8ba0b0; margin:0 0 18px;">@{{ banModal.username }}</p>

            <div style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#5a7a8a; display:block; margin-bottom:6px;">Motivo *</label>
                <textarea v-model="banForm.reason" rows="3" placeholder="Motivo do banimento..."
                    style="width:100%; background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:10px; padding:10px 14px; font-size:13px; color:#3a6478; font-family:inherit; outline:none; resize:vertical; box-sizing:border-box;"
                    @focus="$event.target.style.borderColor='#e05555'" @blur="$event.target.style.borderColor='#dde8f0'" />
            </div>
            <div style="margin-bottom:22px;">
                <label style="font-size:12px; font-weight:700; color:#5a7a8a; display:block; margin-bottom:6px;">Banido até (vazio = permanente)</label>
                <input type="datetime-local" v-model="banForm.banned_until"
                    style="width:100%; background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:10px; padding:10px 14px; font-size:13px; color:#3a6478; font-family:inherit; outline:none; box-sizing:border-box;" />
            </div>
            <div style="display:flex; gap:10px;">
                <button @click="banModal=null"
                    style="flex:1; padding:11px; background:#f0f4f8; border:none; border-radius:10px; font-size:13px; font-weight:700; color:#5a7a8a; cursor:pointer; font-family:inherit;">
                    Cancelar
                </button>
                <button @click="submitBan" :disabled="!banForm.reason"
                    style="flex:2; padding:11px; background:#e05555; border:none; border-radius:10px; font-size:13px; font-weight:700; color:white; cursor:pointer; font-family:inherit;"
                    :style="{ opacity: !banForm.reason ? 0.5 : 1 }">
                    Confirmar Banimento
                </button>
            </div>
        </div>
    </div>

    <!-- Mute modal -->
    <div v-if="muteModal"
        style="position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px;"
        @click.self="muteModal=null">
        <div style="background:white; border-radius:18px; padding:28px 32px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <h2 style="font-size:15px; font-weight:900; color:#3a6478; margin:0 0 6px;">Silenciar {{ muteModal.name }}</h2>
            <p style="font-size:12px; color:#8ba0b0; margin:0 0 18px;">@{{ muteModal.username }}</p>

            <div style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#5a7a8a; display:block; margin-bottom:6px;">Motivo *</label>
                <textarea v-model="muteForm.reason" rows="3" placeholder="Motivo do silêncio..."
                    style="width:100%; background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:10px; padding:10px 14px; font-size:13px; color:#3a6478; font-family:inherit; outline:none; resize:vertical; box-sizing:border-box;"
                    @focus="$event.target.style.borderColor='#9b6bdf'" @blur="$event.target.style.borderColor='#dde8f0'" />
            </div>
            <div style="margin-bottom:22px;">
                <label style="font-size:12px; font-weight:700; color:#5a7a8a; display:block; margin-bottom:6px;">Silenciado até (vazio = permanente)</label>
                <input type="datetime-local" v-model="muteForm.muted_until"
                    style="width:100%; background:#f4f7fb; border:1.5px solid #dde8f0; border-radius:10px; padding:10px 14px; font-size:13px; color:#3a6478; font-family:inherit; outline:none; box-sizing:border-box;" />
            </div>
            <div style="display:flex; gap:10px;">
                <button @click="muteModal=null"
                    style="flex:1; padding:11px; background:#f0f4f8; border:none; border-radius:10px; font-size:13px; font-weight:700; color:#5a7a8a; cursor:pointer; font-family:inherit;">
                    Cancelar
                </button>
                <button @click="submitMute" :disabled="!muteForm.reason"
                    style="flex:2; padding:11px; background:#9b6bdf; border:none; border-radius:10px; font-size:13px; font-weight:700; color:white; cursor:pointer; font-family:inherit;"
                    :style="{ opacity: !muteForm.reason ? 0.5 : 1 }">
                    Confirmar Silêncio
                </button>
            </div>
        </div>
    </div>
</template>
