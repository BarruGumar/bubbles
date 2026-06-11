<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const pwChecks = computed(() => ({
    length:  form.password.length >= 8,
    letter:  /[a-zA-Z]/.test(form.password),
    number:  /[0-9]/.test(form.password),
    symbol:  /[^a-zA-Z0-9]/.test(form.password),
}));

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Criar conta" />

        <h2 style="font-weight: 800; font-size: 18px; color: #3a6478; margin-bottom: 4px">Cria a tua conta</h2>
        <p style="font-size: 12px; color: #8ba0b0; margin-bottom: 28px">Entra no mundo Bubbles</p>

        <form @submit.prevent="submit" style="display: flex; flex-direction: column; gap: 16px">
            <div style="display: flex; flex-direction: column; gap: 6px">
                <label class="auth-label">Nome</label>
                <input v-model="form.name" type="text" required autofocus autocomplete="name" placeholder="O teu nome" class="auth-input" />
                <p v-if="form.errors.name" style="font-size:11px;color:#e05555;margin:0">{{ form.errors.name }}</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px">
                <label class="auth-label">Email</label>
                <input v-model="form.email" type="email" required autocomplete="username" placeholder="email@exemplo.com" class="auth-input" />
                <p v-if="form.errors.email" style="font-size:11px;color:#e05555;margin:0">{{ form.errors.email }}</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px">
                <label class="auth-label">Password</label>
                <input v-model="form.password" type="password" required autocomplete="new-password" class="auth-input" />
                <div v-if="form.password.length > 0" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:2px">
                    <span
                        v-for="[key, label] in [['length','8 caracteres'],['letter','1 letra'],['number','1 número'],['symbol','1 símbolo']]"
                        :key="key"
                        :style="{
                            fontSize:'10px', fontWeight:'600', padding:'2px 8px',
                            borderRadius:'99px', transition:'all 0.2s',
                            background: pwChecks[key] ? '#edfdf4' : '#f0f4f8',
                            color: pwChecks[key] ? '#16a34a' : '#8ba0b0',
                            border: `1px solid ${pwChecks[key] ? '#16a34a44' : 'transparent'}`,
                        }"
                    >{{ pwChecks[key] ? '✓' : '·' }} {{ label }}</span>
                </div>
                <p v-if="form.errors.password" style="font-size:11px;color:#e05555;margin:0">{{ form.errors.password }}</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px">
                <label class="auth-label">Confirmar password</label>
                <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="auth-input" />
                <p v-if="form.errors.password_confirmation" style="font-size:11px;color:#e05555;margin:0">{{ form.errors.password_confirmation }}</p>
            </div>

            <button type="submit" class="auth-btn-primary" :disabled="form.processing" :style="{ opacity: form.processing ? 0.7 : 1 }">
                {{ form.processing ? 'A criar...' : 'Criar conta' }}
            </button>
        </form>

        <div style="display:flex;align-items:center;gap:12px;margin-top:20px">
            <div style="flex:1;height:1px;background:rgba(78,188,255,.2)" />
            <span style="font-size:11px;color:#b0c4d0;font-weight:600">ou</span>
            <div style="flex:1;height:1px;background:rgba(78,188,255,.2)" />
        </div>

        <a :href="route('auth.google.redirect')" class="auth-btn-google" style="margin-top:12px">
            <svg width="18" height="18" viewBox="0 0 48 48">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                <path fill="none" d="M0 0h48v48H0z"/>
            </svg>
            Continuar com Google
        </a>

        <p style="text-align:center;margin-top:20px;font-size:12px;color:#8ba0b0">
            Já tens conta?
            <Link :href="route('login')" style="color:#009ac7;font-weight:700;text-decoration:none">Entrar</Link>
        </p>
    </GuestLayout>
</template>

<style scoped>
.auth-label {
    font-size: 11px;
    font-weight: 700;
    color: #5a7a8a;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
</style>
