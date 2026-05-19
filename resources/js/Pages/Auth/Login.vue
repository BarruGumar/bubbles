<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Entrar" />

        <h2 style="font-weight: 800; font-size: 18px; color: #3a6478; margin-bottom: 4px">Bem-vindo de volta</h2>
        <p style="font-size: 12px; color: #8ba0b0; margin-bottom: 28px">Entra na tua conta Bubbles</p>

        <div
            v-if="status"
            class="status-success"
            style="
                background: #edfaf4;
                border: 1px solid #2ea87e44;
                border-radius: 10px;
                padding: 10px 14px;
                font-size: 12px;
                color: #2ea87e;
                margin-bottom: 20px;
            "
        >
            {{ status }}
        </div>

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
                    >Email</label
                >
                <input
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    style="
                        background: #f0f8ff;
                        border: 1.5px solid #4ebcff44;
                        border-radius: 11px;
                        padding: 11px 14px;
                        font-size: 13px;
                        color: #3a6478;
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
                <div style="display: flex; justify-content: space-between; align-items: center">
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
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        style="font-size: 11px; color: #009ac7; text-decoration: none; font-weight: 600"
                        >Esqueceste?</Link
                    >
                </div>
                <input
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    style="
                        background: #f0f8ff;
                        border: 1.5px solid #4ebcff44;
                        border-radius: 11px;
                        padding: 11px 14px;
                        font-size: 13px;
                        color: #3a6478;
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

            <label
                style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 12px; color: #8ba0b0"
            >
                <input type="checkbox" v-model="form.remember" style="accent-color: #009ac7" />
                Lembrar de mim
            </label>

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
                {{ form.processing ? 'A entrar...' : 'Entrar' }}
            </button>
        </form>

        <p style="text-align: center; margin-top: 24px; font-size: 12px; color: #8ba0b0">
            Ainda não tens conta?
            <Link :href="route('register')" style="color: #009ac7; font-weight: 700; text-decoration: none"
                >Regista-te</Link
            >
        </p>
    </GuestLayout>
</template>
