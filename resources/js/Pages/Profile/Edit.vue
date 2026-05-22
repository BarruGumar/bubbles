<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import { Head } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});

const { isDark, toggle: toggleTheme } = useTheme();
</script>

<template>
    <Head title="Definições" />

    <AuthenticatedLayout>
        <div style="max-width:680px;margin:0 auto;padding:40px 20px 80px;display:flex;flex-direction:column;gap:16px">

            <h1 style="font-size:20px;font-weight:900;color:var(--text);margin:0 0 8px;letter-spacing:-0.5px">
                Definições
            </h1>

            <!-- Aparência card -->
            <div class="settings-card">
                <h2 class="card-title">Aparência</h2>
                <p class="card-desc">Escolhe o tema da aplicação. A preferência é guardada na tua conta.</p>
                <div class="theme-options">
                    <button
                        @click="isDark && toggleTheme()"
                        class="theme-btn"
                        :class="{ 'theme-btn-active': !isDark }"
                    >
                        <span class="theme-icon">☀️</span>
                        <span class="theme-label">Claro</span>
                        <span v-if="!isDark" class="theme-check">✓</span>
                    </button>
                    <button
                        @click="!isDark && toggleTheme()"
                        class="theme-btn"
                        :class="{ 'theme-btn-active': isDark }"
                    >
                        <span class="theme-icon">🌙</span>
                        <span class="theme-label">Escuro</span>
                        <span v-if="isDark" class="theme-check">✓</span>
                    </button>
                </div>
            </div>

            <!-- Profile info card -->
            <div class="settings-card">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            </div>

            <!-- Password card -->
            <div class="settings-card">
                <UpdatePasswordForm :status="status" />
            </div>

            <!-- Danger zone card -->
            <div class="settings-card settings-card-danger">
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.settings-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid var(--card-border);
    box-shadow: 0 4px 20px rgba(0, 154, 199, 0.05);
    padding: 28px 32px;
}
.settings-card-danger { border-color: rgba(224, 85, 85, 0.15); }

.card-title {
    font-size: 15px; font-weight: 800; color: var(--text);
    margin: 0 0 6px; letter-spacing: -0.3px;
}
.card-desc { font-size: 13px; color: var(--text-3); margin: 0 0 20px; line-height: 1.5; }

/* Theme picker */
.theme-options { display: flex; gap: 12px; }
.theme-btn {
    flex: 1; display: flex; align-items: center; gap: 10px;
    padding: 14px 18px; border-radius: 14px;
    border: 2px solid var(--card-border);
    background: transparent; cursor: pointer;
    transition: all 0.2s; font-family: inherit;
    color: var(--text-2);
}
.theme-btn:hover { border-color: rgba(0, 154, 199, 0.35); background: var(--item-hover); }
.theme-btn-active {
    border-color: #009ac7;
    background: rgba(0, 154, 199, 0.08);
    color: var(--text);
    box-shadow: 0 0 0 3px rgba(0, 154, 199, 0.12);
}
.theme-icon { font-size: 20px; flex-shrink: 0; }
.theme-label { font-size: 14px; font-weight: 700; flex: 1; text-align: left; }
.theme-check {
    flex-shrink: 0; width: 20px; height: 20px; border-radius: 50%;
    background: #009ac7; color: white; font-size: 11px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
}
</style>
