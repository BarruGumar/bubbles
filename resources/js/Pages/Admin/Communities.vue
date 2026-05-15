<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    communities: { type: Object, required: true },
    query: { type: String, default: '' },
});

const search = ref(props.query ?? '');
let timer = null;

function doSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.communities'), { q: search.value }, { preserveState: true, replace: true });
    }, 320);
}

function deleteCommunity(id) {
    if (!confirm('Tens a certeza? A comunidade e todos os posts serão eliminados.')) return;
    router.delete(route('admin.communities.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin · Comunidades" />

    <AdminLayout>
        <template #header>
            <h1 style="font-size: 16px; font-weight: 800; color: #1a3a4a; margin: 0">Comunidades</h1>
        </template>

        <div style="margin-bottom: 18px">
            <input
                v-model="search"
                @input="doSearch"
                placeholder="Pesquisar comunidades..."
                style="
                    width: 100%;
                    max-width: 360px;
                    background: white;
                    border: 1.5px solid #dde8f0;
                    border-radius: 10px;
                    padding: 10px 14px;
                    font-size: 13px;
                    color: #1a3a4a;
                    outline: none;
                    font-family: inherit;
                    box-sizing: border-box;
                    transition: border-color 0.2s;
                "
                @focus="$event.target.style.borderColor = '#009ac7'"
                @blur="$event.target.style.borderColor = '#dde8f0'"
            />
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
                            Comunidade
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
                            Membros
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
                            Criada
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
                        v-for="(c, i) in communities.data"
                        :key="c.id"
                        :style="{ borderTop: i > 0 ? '1px solid #f4f7fb' : 'none' }"
                    >
                        <td style="padding: 12px 16px">
                            <div style="display: flex; align-items: center; gap: 10px">
                                <div
                                    :style="{
                                        width: '32px',
                                        height: '32px',
                                        borderRadius: '50%',
                                        background: c.color,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        fontSize: '11px',
                                        fontWeight: '800',
                                        color: 'white',
                                        flexShrink: 0,
                                    }"
                                >
                                    {{ (c.label ?? '?')[0].toUpperCase() }}
                                </div>
                                <div>
                                    <p style="font-size: 13px; font-weight: 700; color: #1a3a4a; margin: 0">
                                        {{ c.title }}
                                    </p>
                                    <p style="font-size: 11px; color: #8ba0b0; margin: 0">{{ c.label }}</p>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 12px 16px; font-size: 12px; color: #5a7a8a">{{ c.members }}</td>
                        <td style="padding: 12px 16px; font-size: 12px; color: #5a7a8a">{{ c.posts_count }}</td>
                        <td style="padding: 12px 16px; font-size: 12px; color: #8ba0b0; white-space: nowrap">
                            {{ c.created_at }}
                        </td>
                        <td style="padding: 12px 16px; text-align: right">
                            <div style="display: flex; gap: 6px; justify-content: flex-end">
                                <Link
                                    :href="route('community.show', c.id)"
                                    style="
                                        font-size: 11px;
                                        color: #009ac7;
                                        border: 1px solid #009ac733;
                                        border-radius: 6px;
                                        padding: 4px 10px;
                                        font-weight: 600;
                                        text-decoration: none;
                                        transition: all 0.2s;
                                    "
                                    @mouseenter="$event.currentTarget.style.background = '#f0f8ff'"
                                    @mouseleave="$event.currentTarget.style.background = 'transparent'"
                                    >Ver</Link
                                >
                                <button
                                    @click="deleteCommunity(c.id)"
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
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                v-if="communities.last_page > 1"
                style="
                    padding: 14px 16px;
                    border-top: 1px solid #f0f4f8;
                    display: flex;
                    gap: 6px;
                    justify-content: center;
                "
            >
                <Link
                    v-for="link in communities.links"
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
