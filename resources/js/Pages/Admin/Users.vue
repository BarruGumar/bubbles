<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SiteOwnerBadge from '@/Components/SiteOwnerBadge.vue';
import { clImg } from '@/Composables/useCloudinary';

const props = defineProps({
    users: { type: Object, required: true },
    query: { type: String, default: '' },
    isSiteOwner: { type: Boolean, default: false },
});

const search = ref(props.query ?? '');

const baseRoles = { user: 'Utilizador', moderator: 'Moderador', admin: 'Admin', suspended: 'Suspenso', banned: 'Banido' };
const ownerRoles = { site_owner: '👑 Dono do Site', ...baseRoles };
const roleMap = props.isSiteOwner ? ownerRoles : baseRoles;

const roleColor = {
    site_owner: '#d4a017',
    user: '#5a7a8a',
    moderator: '#9b6bdf',
    admin: '#009ac7',
    suspended: '#e05555',
    banned: '#7f1d1d',
};

let timer = null;

function doSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.users'), { q: search.value }, { preserveState: true, replace: true });
    }, 320);
}

function setRole(userId, role, user) {
    if (!user.can_manage) return;
    if (role === 'site_owner' && !props.isSiteOwner) return;
    router.patch(route('admin.users.role', userId), { role }, { preserveScroll: true });
}

function deleteUser(u) {
    if (!u.can_manage) return;
    if (!confirm('Tens a certeza? Esta ação é irreversível.')) return;
    router.delete(route('admin.users.destroy', u.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin · Utilizadores" />

    <AdminLayout>
        <template #header>
            <h1 style="font-size: 16px; font-weight: 800; color: #3a6478; margin: 0">Utilizadores</h1>
        </template>

        <!-- Search -->
        <div style="margin-bottom: 18px">
            <input
                v-model="search"
                @input="doSearch"
                placeholder="Pesquisar utilizadores..."
                style="
                    width: 100%;
                    max-width: 360px;
                    background: white;
                    border: 1.5px solid #dde8f0;
                    border-radius: 10px;
                    padding: 10px 14px;
                    font-size: 13px;
                    color: #3a6478;
                    outline: none;
                    font-family: inherit;
                    box-sizing: border-box;
                    transition: border-color 0.2s;
                "
                @focus="$event.target.style.borderColor = '#009ac7'"
                @blur="$event.target.style.borderColor = '#dde8f0'"
            />
        </div>

        <!-- Table -->
        <div
            style="
                background: white;
                border-radius: 14px;
                border: 1px solid #eef2f8;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
                overflow: hidden;
            "
        >
            <table style="width: 100%; border-collapse: collapse">
                <thead>
                    <tr style="border-bottom: 1px solid #f0f4f8">
                        <th
                            style="
                                text-align: left;
                                padding: 12px 16px;
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.07em;
                            "
                        >
                            Utilizador
                        </th>
                        <th
                            style="
                                text-align: left;
                                padding: 12px 16px;
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.07em;
                            "
                        >
                            Email
                        </th>
                        <th
                            style="
                                text-align: left;
                                padding: 12px 16px;
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.07em;
                            "
                        >
                            Posts
                        </th>
                        <th
                            style="
                                text-align: left;
                                padding: 12px 16px;
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.07em;
                            "
                        >
                            Membro desde
                        </th>
                        <th
                            style="
                                text-align: left;
                                padding: 12px 16px;
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.07em;
                            "
                        >
                            Papel
                        </th>
                        <th
                            style="
                                text-align: right;
                                padding: 12px 16px;
                                font-size: 10px;
                                font-weight: 800;
                                color: #8ba0b0;
                                text-transform: uppercase;
                                letter-spacing: 0.07em;
                            "
                        >
                            Ações
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(u, i) in users.data"
                        :key="u.id"
                        :style="{ borderTop: i > 0 ? '1px solid #f4f7fb' : 'none' }"
                    >
                        <td style="padding: 12px 16px">
                            <div style="display: flex; align-items: center; gap: 10px">
                                <img
                                    v-if="u.avatar"
                                    :src="clImg(u.avatar, 56, 56, 'fill', 'face')"
                                    :style="{
                                        width: '32px',
                                        height: '32px',
                                        borderRadius: '50%',
                                        objectFit: 'cover',
                                        border: `2px solid ${u.avatar_color}`,
                                        flexShrink: 0,
                                    }"
                                />
                                <div
                                    v-else
                                    :style="{
                                        width: '32px',
                                        height: '32px',
                                        borderRadius: '50%',
                                        background: u.avatar_color,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        fontSize: '12px',
                                        fontWeight: '800',
                                        color: 'white',
                                        flexShrink: 0,
                                    }"
                                >
                                    {{ (u.name ?? '?')[0].toUpperCase() }}
                                </div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 5px">
                                        <p style="font-size: 13px; font-weight: 700; color: #3a6478; margin: 0">
                                            {{ u.name }}
                                        </p>
                                        <SiteOwnerBadge v-if="u.role === 'site_owner'" size="sm" />
                                    </div>
                                    <p style="font-size: 11px; color: #8ba0b0; margin: 0">@{{ u.username }}</p>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 12px 16px; font-size: 12px; color: #5a7a8a">{{ u.email }}</td>
                        <td style="padding: 12px 16px; font-size: 12px; color: #5a7a8a">{{ u.posts_count }}</td>
                        <td style="padding: 12px 16px; font-size: 12px; color: #5a7a8a">{{ u.created_at }}</td>
                        <td style="padding: 12px 16px">
                            <select
                                v-if="u.can_manage"
                                :value="u.role"
                                @change="setRole(u.id, $event.target.value, u)"
                                :style="{
                                    fontSize: '11px',
                                    fontWeight: '700',
                                    color: roleColor[u.role] ?? '#5a7a8a',
                                    background: 'transparent',
                                    border: 'none',
                                    cursor: 'pointer',
                                    fontFamily: 'inherit',
                                }"
                            >
                                <option v-for="(label, val) in roleMap" :key="val" :value="val">{{ label }}</option>
                            </select>
                            <span
                                v-else
                                :style="{ fontSize: '11px', fontWeight: '700', color: roleColor[u.role] ?? '#5a7a8a' }"
                            >
                                {{ roleMap[u.role] ?? u.role }}
                            </span>
                        </td>
                        <td style="padding: 12px 16px; text-align: right">
                            <button
                                v-if="u.can_manage"
                                @click="deleteUser(u)"
                                style="
                                    font-size: 11px;
                                    color: #e05555;
                                    background: none;
                                    border: 1px solid #e0555533;
                                    border-radius: 6px;
                                    padding: 4px 10px;
                                    cursor: pointer;
                                    font-weight: 600;
                                    transition: all 0.2s;
                                "
                                @mouseenter="
                                    $event.currentTarget.style.background = '#fff0f0';
                                    $event.currentTarget.style.borderColor = '#e05555';
                                "
                                @mouseleave="
                                    $event.currentTarget.style.background = 'transparent';
                                    $event.currentTarget.style.borderColor = '#e0555533';
                                "
                            >
                                Eliminar
                            </button>
                            <span v-else style="font-size: 11px; color: #b0c0cc">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div
                v-if="users.last_page > 1"
                style="
                    padding: 14px 16px;
                    border-top: 1px solid #f0f4f8;
                    display: flex;
                    gap: 6px;
                    justify-content: center;
                "
            >
                <Link
                    v-for="link in users.links"
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
