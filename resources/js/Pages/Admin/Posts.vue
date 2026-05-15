<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    posts: { type: Object, required: true },
    query: { type: String, default: '' },
});

const search = ref(props.query ?? '');
let timer = null;

function doSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.posts'), { q: search.value }, { preserveState: true, replace: true });
    }, 320);
}

function forceDelete(postId) {
    if (!confirm('Apagar permanentemente? Esta ação é irreversível.')) return;
    router.delete(route('admin.posts.destroy', postId), { preserveScroll: true });
}

function restore(postId) {
    router.post(route('admin.posts.restore', postId), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin · Posts" />

    <AdminLayout>
        <template #header>
            <h1 style="font-size: 16px; font-weight: 800; color: #1a3a4a; margin: 0">Posts</h1>
        </template>

        <div style="margin-bottom: 18px">
            <input
                v-model="search"
                @input="doSearch"
                placeholder="Pesquisar posts..."
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
                            Conteúdo
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
                            Autor
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
                            Data
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
                            Estado
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
                        v-for="(p, i) in posts.data"
                        :key="p.id"
                        :style="{ borderTop: i > 0 ? '1px solid #f4f7fb' : 'none', opacity: p.deleted ? 0.55 : 1 }"
                    >
                        <td style="padding: 12px 16px; max-width: 340px">
                            <p
                                :style="{
                                    fontSize: '13px',
                                    color: '#2a4a5a',
                                    margin: 0,
                                    fontStyle: p.deleted ? 'italic' : 'normal',
                                }"
                            >
                                {{ p.content }}
                            </p>
                        </td>
                        <td style="padding: 12px 16px; font-size: 12px; color: #5a7a8a; white-space: nowrap">
                            <span v-if="p.author">{{ p.author.name }}</span>
                            <span v-else style="color: #b0c0cc">–</span>
                        </td>
                        <td style="padding: 12px 16px; font-size: 12px; color: #8ba0b0; white-space: nowrap">
                            {{ p.created_at }}
                        </td>
                        <td style="padding: 12px 16px">
                            <span
                                :style="{
                                    fontSize: '10px',
                                    fontWeight: '800',
                                    padding: '3px 8px',
                                    borderRadius: '99px',
                                    background: p.deleted ? '#fff0f0' : '#f0fff8',
                                    color: p.deleted ? '#e05555' : '#2ea87e',
                                }"
                                >{{ p.deleted ? 'Apagado' : 'Ativo' }}</span
                            >
                        </td>
                        <td style="padding: 12px 16px; text-align: right; white-space: nowrap">
                            <button
                                v-if="p.deleted"
                                @click="restore(p.id)"
                                style="
                                    font-size: 11px;
                                    color: #2ea87e;
                                    background: none;
                                    border: 1px solid #2ea87e33;
                                    border-radius: 6px;
                                    padding: 4px 10px;
                                    cursor: pointer;
                                    font-weight: 600;
                                    margin-right: 6px;
                                    transition: all 0.2s;
                                "
                                @mouseenter="$event.currentTarget.style.background = '#f0fff8'"
                                @mouseleave="$event.currentTarget.style.background = 'transparent'"
                            >
                                Restaurar
                            </button>
                            <button
                                @click="forceDelete(p.id)"
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
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                v-if="posts.last_page > 1"
                style="
                    padding: 14px 16px;
                    border-top: 1px solid #f0f4f8;
                    display: flex;
                    gap: 6px;
                    justify-content: center;
                "
            >
                <Link
                    v-for="link in posts.links"
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
