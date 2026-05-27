<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    status: { type: String },
});

const form = useForm({});
const cooldown    = ref(0);
const justVerified = ref(false);
const redirecting  = ref(false);

let cooldownTimer = null;
let pollTimer     = null;

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
const canResend = computed(() => !form.processing && cooldown.value === 0);

async function checkVerification() {
    try {
        const { data } = await axios.get(route('verification.status'));
        if (data.verified) {
            clearInterval(pollTimer);
            pollTimer = null;
            justVerified.value = true;
            redirecting.value  = true;
            setTimeout(() => router.visit(route('dashboard')), 2000);
        }
    } catch { /* ignore network errors */ }
}

function submit() {
    form.post(route('verification.send'), {
        onSuccess: () => {
            cooldown.value = 30;
            cooldownTimer = setInterval(() => {
                cooldown.value--;
                if (cooldown.value <= 0) {
                    clearInterval(cooldownTimer);
                    cooldownTimer = null;
                }
            }, 1000);
        },
    });
}

onMounted(() => {
    pollTimer = setInterval(checkVerification, 4000);
});

onUnmounted(() => {
    if (cooldownTimer) clearInterval(cooldownTimer);
    if (pollTimer)     clearInterval(pollTimer);
});
</script>

<template>
    <GuestLayout>
        <Head title="Verificação de Email" />

        <!-- Verified feedback -->
        <div v-if="justVerified" class="mb-4 rounded-md bg-green-50 p-4 text-sm font-medium text-green-700">
            Email verificado! A redirecionar…
        </div>

        <template v-else>
            <div class="mb-4 text-sm text-gray-800">
                Obrigado por te registares! Antes de começares, verifica o teu endereço de email clicando no link que
                acabámos de te enviar. Se não recebeste o email, podemos enviar outro.
            </div>

            <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                Abre o email de verificação <strong>no mesmo dispositivo</strong> em que fizeste o registo. Clicar o link noutro dispositivo pode resultar em erro.
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

            <div class="mt-4 border-t pt-4">
                <button
                    type="button"
                    @click="checkVerification"
                    class="text-sm font-medium text-indigo-600 underline hover:text-indigo-800 focus:outline-none"
                >
                    Já verifiquei o meu email
                </button>
            </div>
        </template>
    </GuestLayout>
</template>
