<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
    stats:         { type: Object, required: true },
    recentReports: { type: Array, default: () => [] },
})

const statCards = (stats) => [
    { label: 'Utilizadores', value: stats.users, color: '#009ac7', icon: '👤' },
    { label: 'Posts', value: stats.posts, color: '#2ea87e', icon: '📝' },
    { label: 'Comunidades', value: stats.communities, color: '#9b6bdf', icon: '🫧' },
    { label: 'Denúncias pendentes', value: stats.reports, color: '#e05555', icon: '⚑' },
]
</script>

<template>
    <Head title="Admin · Dashboard" />

    <AdminLayout>
        <template #header>
            <h1 style="font-size: 16px; font-weight: 800; color: #1a3a4a; margin: 0;">Dashboard</h1>
        </template>

        <!-- Stat cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px;">
            <div
                v-for="card in statCards(stats)"
                :key="card.label"
                style="background: white; border-radius: 14px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.04); border: 1px solid #eef2f8;"
            >
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                    <span style="font-size: 11px; font-weight: 700; color: #8ba0b0; text-transform: uppercase; letter-spacing: .06em;">{{ card.label }}</span>
                    <span style="font-size: 18px;">{{ card.icon }}</span>
                </div>
                <p :style="{ fontSize: '28px', fontWeight: '900', color: card.color, margin: 0, letterSpacing: '-.02em' }">{{ card.value.toLocaleString() }}</p>
            </div>
        </div>

        <!-- Recent reports -->
        <div style="background: white; border-radius: 14px; border: 1px solid #eef2f8; box-shadow: 0 2px 8px rgba(0,0,0,.04); overflow: hidden;">
            <div style="padding: 16px 20px; border-bottom: 1px solid #f0f4f8; display: flex; align-items: center; justify-content: space-between;">
                <p style="font-size: 13px; font-weight: 800; color: #1a3a4a; margin: 0;">Denúncias recentes</p>
                <Link :href="route('admin.reports')" style="font-size: 12px; color: #009ac7; font-weight: 600; text-decoration: none;">Ver todas →</Link>
            </div>
            <div v-if="recentReports.length === 0" style="padding: 32px; text-align: center; color: #8ba0b0; font-size: 13px;">
                Nenhuma denúncia pendente. 🎉
            </div>
            <div v-else>
                <div
                    v-for="(r, i) in recentReports"
                    :key="r.id"
                    :style="{ padding: '14px 20px', borderTop: i > 0 ? '1px solid #f0f4f8' : 'none', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '12px' }"
                >
                    <div style="min-width: 0;">
                        <p style="font-size: 13px; color: #1a3a4a; font-weight: 600; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ r.reason }}</p>
                        <p style="font-size: 11px; color: #8ba0b0; margin: 3px 0 0;">{{ r.type }} · por {{ r.reporter_name }} · {{ r.created_at }}</p>
                    </div>
                    <Link :href="route('admin.reports')" style="font-size: 11px; color: #009ac7; font-weight: 700; text-decoration: none; white-space: nowrap; flex-shrink: 0; padding: 5px 12px; border-radius: 99px; border: 1px solid #009ac733;">Ver</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
