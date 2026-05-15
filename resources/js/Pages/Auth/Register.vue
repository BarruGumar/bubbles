<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Criar conta" />

        <h2 style="font-weight: 800; font-size: 18px; color: #1a3a4a; margin-bottom: 4px">Cria a tua conta</h2>
        <p style="font-size: 12px; color: #8ba0b0; margin-bottom: 28px">Entra no mundo Bubbles</p>

        <form @submit.prevent="submit" style="display: flex; flex-direction: column; gap: 16px">
            <div style="display: flex; flex-direction: column; gap: 6px">
                <label
                    style="
                        font-size: 11px;
                        font-weight: 700;
                        color: #5a7a8a;
                        text-transform: uppercase;
                        letter-spacing: 0.06em;
                    "
                    >Nome</label
                >
                <input
                    v-model="form.name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="O teu nome"
                    style="
                        background: #f0f8ff;
                        border: 1.5px solid #4ebcff44;
                        border-radius: 11px;
                        padding: 11px 14px;
                        font-size: 13px;
                        color: #1a3a4a;
                        outline: none;
                        font-family: inherit;
                        transition: border-color 0.2s;
                    "
                    @focus="$event.target.style.borderColor = '#009ac7'"
                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                />
                <p v-if="form.errors.name" style="font-size: 11px; color: #e05555; margin: 0">{{ form.errors.name }}</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px">
                <label
                    style="
                        font-size: 11px;
                        font-weight: 700;
                        color: #5a7a8a;
                        text-transform: uppercase;
                        letter-spacing: 0.06em;
                    "
                    >Email</label
                >
                <input
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    placeholder="email@exemplo.com"
                    style="
                        background: #f0f8ff;
                        border: 1.5px solid #4ebcff44;
                        border-radius: 11px;
                        padding: 11px 14px;
                        font-size: 13px;
                        color: #1a3a4a;
                        outline: none;
                        font-family: inherit;
                        transition: border-color 0.2s;
                    "
                    @focus="$event.target.style.borderColor = '#009ac7'"
                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                />
                <p v-if="form.errors.email" style="font-size: 11px; color: #e05555; margin: 0">
                    {{ form.errors.email }}
                </p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px">
                <label
                    style="
                        font-size: 11px;
                        font-weight: 700;
                        color: #5a7a8a;
                        text-transform: uppercase;
                        letter-spacing: 0.06em;
                    "
                    >Password</label
                >
                <input
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    style="
                        background: #f0f8ff;
                        border: 1.5px solid #4ebcff44;
                        border-radius: 11px;
                        padding: 11px 14px;
                        font-size: 13px;
                        color: #1a3a4a;
                        outline: none;
                        font-family: inherit;
                        transition: border-color 0.2s;
                    "
                    @focus="$event.target.style.borderColor = '#009ac7'"
                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                />
                <p v-if="form.errors.password" style="font-size: 11px; color: #e05555; margin: 0">
                    {{ form.errors.password }}
                </p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px">
                <label
                    style="
                        font-size: 11px;
                        font-weight: 700;
                        color: #5a7a8a;
                        text-transform: uppercase;
                        letter-spacing: 0.06em;
                    "
                    >Confirmar password</label
                >
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    style="
                        background: #f0f8ff;
                        border: 1.5px solid #4ebcff44;
                        border-radius: 11px;
                        padding: 11px 14px;
                        font-size: 13px;
                        color: #1a3a4a;
                        outline: none;
                        font-family: inherit;
                        transition: border-color 0.2s;
                    "
                    @focus="$event.target.style.borderColor = '#009ac7'"
                    @blur="$event.target.style.borderColor = '#4ebcff44'"
                />
                <p v-if="form.errors.password_confirmation" style="font-size: 11px; color: #e05555; margin: 0">
                    {{ form.errors.password_confirmation }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                style="
                    padding: 13px;
                    border-radius: 12px;
                    background: #009ac7;
                    border: none;
                    color: white;
                    font-size: 14px;
                    font-weight: 700;
                    cursor: pointer;
                    box-shadow: 0 4px 18px #009ac740;
                    transition: opacity 0.2s;
                    margin-top: 4px;
                "
                :style="{ opacity: form.processing ? 0.7 : 1 }"
            >
                {{ form.processing ? 'A criar...' : 'Criar conta' }}
            </button>
        </form>

        <p style="text-align: center; margin-top: 24px; font-size: 12px; color: #8ba0b0">
            Já tens conta?
            <Link :href="route('login')" style="color: #009ac7; font-weight: 700; text-decoration: none">Entrar</Link>
        </p>
    </GuestLayout>
</template>
