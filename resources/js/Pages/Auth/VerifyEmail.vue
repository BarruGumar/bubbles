<script setup>
import { ref, computed, onUnmounted } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});
const cooldown = ref(0);
let timer = null;

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');

const canResend = computed(() => !form.processing && cooldown.value === 0);

function submit() {
    form.post(route('verification.send'), {
        onSuccess: () => {
            cooldown.value = 30;
            timer = setInterval(() => {
                cooldown.value--;
                if (cooldown.value <= 0) {
                    clearInterval(timer);
                    timer = null;
                }
            }, 1000);
        },
    });
}

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <GuestLayout>
        <Head title="Verificação de Email" />

        <div class="mb-4 text-sm text-gray-600">
            Obrigado por te registares! Antes de começares, verifica o teu endereço de email clicando no link que
            acabámos de te enviar. Se não recebeste o email, podemos enviar outro.
        </div>

        <div class="mb-4 text-sm font-medium text-green-600" v-if="verificationLinkSent">
            Um novo link de verificação foi enviado para o teu endereço de email.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton :disabled="!canResend" :class="{ 'opacity-25': !canResend }">
                    <span v-if="cooldown > 0">Reenviar em {{ cooldown }}s</span>
                    <span v-else>Enviar Email de Verificação</span>
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Sair
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
