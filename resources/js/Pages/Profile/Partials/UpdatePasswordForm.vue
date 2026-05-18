<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    status: String,
});

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Alterar Password</h2>
            <p class="mt-1 text-sm text-gray-600">
                Usa uma password longa e aleatória para manteres a conta segura.
            </p>
        </header>

        <!-- Email sent notice -->
        <div
            v-if="status === 'password-email-sent'"
            style="
                margin-top: 16px;
                padding: 14px 18px;
                background: #e8f7fd;
                border: 1px solid #009ac733;
                border-radius: 12px;
                font-size: 13px;
                color: #007aa0;
                line-height: 1.5;
            "
        >
            📧 <strong>Verifica o teu email.</strong> Enviámos um link de confirmação. Clica nele para aplicar a nova password. O link expira em 15 minutos.
        </div>

        <!-- Password changed success -->
        <div
            v-if="status === 'password-changed'"
            style="
                margin-top: 16px;
                padding: 14px 18px;
                background: #eafaf1;
                border: 1px solid #2ea87e44;
                border-radius: 12px;
                font-size: 13px;
                color: #1e7a57;
                line-height: 1.5;
            "
        >
            ✅ <strong>Password alterada com sucesso!</strong>
        </div>

        <!-- Link expired notice -->
        <div
            v-if="status === 'password-link-expired'"
            style="
                margin-top: 16px;
                padding: 14px 18px;
                background: #fdf3f3;
                border: 1px solid #e0535344;
                border-radius: 12px;
                font-size: 13px;
                color: #b03030;
                line-height: 1.5;
            "
        >
            ⚠️ <strong>Link expirado ou inválido.</strong> Submete o formulário novamente para receber um novo email.
        </div>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-6">
            <div>
                <InputLabel for="current_password" value="Password Atual" />
                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                />
                <InputError :message="form.errors.current_password" class="mt-2" />
            </div>

            <div>
                <InputLabel for="password" value="Nova Password" />
                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password" class="mt-2" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirmar Password" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password_confirmation" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Enviar confirmação por email</PrimaryButton>
            </div>
        </form>
    </section>
</template>
