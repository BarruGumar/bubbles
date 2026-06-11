<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    canLogin:         { type: Boolean },
    canRegister:      { type: Boolean },
    punishmentModal:  { type: Object, default: null },
});

const authUser = computed(() => usePage().props.auth?.user);
const banModalVisible = ref(!!props.punishmentModal);
</script>

<template>
    <Head title="Bubbles" />
    <div
        class="welcome-page w-screen h-screen overflow-hidden relative select-none"
        style="
            background-color: #daeef9;
            background-image:
                linear-gradient(
                    160deg,
                    rgba(240, 248, 255, 0.72) 0%,
                    rgba(218, 238, 249, 0.68) 50%,
                    rgba(197, 229, 245, 0.7) 100%
                ),
                url('/images/realistic-style-soap-bubbles-background.png');
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            font-family: 'Segoe UI', system-ui, sans-serif;
        "
    >
        <!-- BACKGROUND GRID -->
        <svg class="absolute inset-0 w-full h-full pointer-events-none" style="opacity: 0.04">
            <defs>
                <pattern id="bg-grid" width="44" height="44" patternUnits="userSpaceOnUse">
                    <path d="M 44 0 L 0 0 0 44" fill="none" stroke="#009ac7" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#bg-grid)" />
        </svg>

        <!-- AMBIENT ORBS -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div
                style="
                    position: absolute;
                    top: -80px;
                    left: -80px;
                    width: 320px;
                    height: 320px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #009ac718 0%, transparent 70%);
                "
            />
            <div
                style="
                    position: absolute;
                    bottom: -60px;
                    right: -60px;
                    width: 280px;
                    height: 280px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #4ebcff14 0%, transparent 70%);
                "
            />
            <div
                style="
                    position: absolute;
                    top: 40%;
                    left: 65%;
                    width: 200px;
                    height: 200px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #9b6bdf0c 0%, transparent 70%);
                "
            />
        </div>

        <!-- TOP BAR -->
        <div
            class="absolute top-0 left-0 right-0 z-40 flex items-center justify-between px-6"
            style="
                background: rgba(255, 255, 255, 0.72);
                backdrop-filter: blur(16px);
                border-bottom: 1px solid #009ac71a;
                height: 58px;
            "
        >
            <span style="font-weight: 900; font-size: 22px; color: #009ac7; letter-spacing: -1px; user-select: none"
                >bubbles</span
            >

            <nav v-if="canLogin" style="display: flex; align-items: center; gap: 4px">
                <Link
                    v-if="authUser"
                    :href="route('bubbles')"
                    :style="{
                        padding: '8px 16px',
                        borderRadius: '10px',
                        background: '#009ac714',
                        color: '#009ac7',
                        textDecoration: 'none',
                        fontSize: '13px',
                        fontWeight: '600',
                        transition: 'background .15s',
                        cursor: 'pointer',
                        display: 'inline-block',
                    }"
                    @mouseenter="$event.currentTarget.style.background = '#009ac726'"
                    @mouseleave="$event.currentTarget.style.background = '#009ac714'"
                >
                    Comunidades
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        :style="{
                            padding: '8px 16px',
                            borderRadius: '10px',
                            background: 'transparent',
                            color: '#5a7a8a',
                            textDecoration: 'none',
                            fontSize: '13px',
                            fontWeight: '600',
                            transition: 'background .15s',
                            cursor: 'pointer',
                            display: 'inline-block',
                        }"
                        @mouseenter="$event.currentTarget.style.background = '#009ac714'"
                        @mouseleave="$event.currentTarget.style.background = 'transparent'"
                    >
                        Entrar
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        :style="{
                            padding: '7px 16px',
                            borderRadius: '99px',
                            background: 'linear-gradient(160deg, rgba(100,205,255,.72) 0%, rgba(0,154,199,.65) 55%, rgba(0,100,140,.7) 100%)',
                            backdropFilter: 'blur(16px)',
                            webkitBackdropFilter: 'blur(16px)',
                            color: 'white',
                            textDecoration: 'none',
                            fontSize: '13px',
                            fontWeight: '700',
                            transition: 'opacity .2s',
                            cursor: 'pointer',
                            display: 'inline-block',
                            boxShadow: 'inset 0 1px 0 rgba(255,255,255,.45), 0 2px 10px rgba(0,154,199,.25)',
                            border: '1px solid rgba(255,255,255,.35)',
                            textShadow: '0 1px 3px rgba(0,60,100,.3)',
                        }"
                        @mouseenter="$event.currentTarget.style.opacity = '.85'"
                        @mouseleave="$event.currentTarget.style.opacity = '1'"
                    >
                        Criar conta
                    </Link>
                </template>
            </nav>
        </div>

        <!-- HERO SECTION -->
        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none" style="z-index: 20">
            <div style="text-align: center; max-width: 640px; padding: 0 24px">
                <!-- Tagline -->
                <div style="margin-bottom: 32px">
                    <p
                        style="
                            font-size: 28px;
                            font-weight: 900;
                            color: #3a6478;
                            margin: 0 0 16px;
                            letter-spacing: -0.5px;
                            line-height: 1.2;
                        "
                    >
                        🫧 Bubbles — onde as ideias não ficam presas, elas flutuam.
                    </p>
                    <p style="font-size: 15px; color: #5a7a8a; margin: 0 0 12px; line-height: 1.6; font-weight: 400">
                        Explora comunidades, entra em conversas e descobre conexões que surgem naturalmente à tua volta.
                    </p>
                    <p style="font-size: 15px; color: #7a8f9a; margin: 0; line-height: 1.6; font-weight: 400">
                        Sem feeds infinitos — aqui tudo é mais leve, mais visual… quase como bolhas.
                    </p>
                </div>

                <!-- CTA Buttons -->
                <div
                    v-if="canLogin && !authUser"
                    style="display: flex; gap: 12px; justify-content: center; pointer-events: all"
                >
                    <Link
                        :href="route('login')"
                        :style="{
                            padding: '13px 36px',
                            borderRadius: '99px',
                            background: 'linear-gradient(160deg, rgba(100,205,255,.78) 0%, rgba(0,154,199,.7) 55%, rgba(0,100,140,.75) 100%)',
                            backdropFilter: 'blur(18px)',
                            webkitBackdropFilter: 'blur(18px)',
                            color: 'white',
                            textDecoration: 'none',
                            fontSize: '14px',
                            fontWeight: '700',
                            transition: 'opacity .2s',
                            cursor: 'pointer',
                            display: 'inline-block',
                            boxShadow: 'inset 0 1px 0 rgba(255,255,255,.48), inset 0 -1px 0 rgba(0,60,100,.22), 0 4px 20px rgba(0,154,199,.32)',
                            border: '1px solid rgba(255,255,255,.4)',
                            textShadow: '0 1px 3px rgba(0,60,100,.3)',
                        }"
                        @mouseenter="$event.currentTarget.style.opacity = '.85'"
                        @mouseleave="$event.currentTarget.style.opacity = '1'"
                    >
                        Entrar
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        :style="{
                            padding: '13px 36px',
                            borderRadius: '99px',
                            background: 'linear-gradient(160deg, rgba(255,255,255,.75) 0%, rgba(210,240,255,.62) 100%)',
                            backdropFilter: 'blur(18px)',
                            webkitBackdropFilter: 'blur(18px)',
                            color: '#2a5a72',
                            textDecoration: 'none',
                            fontSize: '14px',
                            fontWeight: '700',
                            transition: 'opacity .2s',
                            cursor: 'pointer',
                            display: 'inline-block',
                            boxShadow: 'inset 0 1px 0 rgba(255,255,255,.72), 0 2px 12px rgba(0,0,0,.08)',
                            border: '1px solid rgba(255,255,255,.55)',
                        }"
                        @mouseenter="$event.currentTarget.style.opacity = '.85'"
                        @mouseleave="$event.currentTarget.style.opacity = '1'"
                    >
                        Criar conta
                    </Link>
                </div>

                <!-- Authenticated message -->
                <div v-else-if="authUser" style="pointer-events: all">
                    <Link
                        :href="route('bubbles')"
                        :style="{
                            padding: '13px 36px',
                            borderRadius: '99px',
                            background: 'linear-gradient(160deg, rgba(100,205,255,.78) 0%, rgba(0,154,199,.7) 55%, rgba(0,100,140,.75) 100%)',
                            backdropFilter: 'blur(18px)',
                            webkitBackdropFilter: 'blur(18px)',
                            color: 'white',
                            textDecoration: 'none',
                            fontSize: '14px',
                            fontWeight: '700',
                            transition: 'opacity .2s',
                            cursor: 'pointer',
                            display: 'inline-block',
                            boxShadow: 'inset 0 1px 0 rgba(255,255,255,.48), inset 0 -1px 0 rgba(0,60,100,.22), 0 4px 20px rgba(0,154,199,.32)',
                            border: '1px solid rgba(255,255,255,.4)',
                            textShadow: '0 1px 3px rgba(0,60,100,.3)',
                        }"
                        @mouseenter="$event.currentTarget.style.opacity = '.85'"
                        @mouseleave="$event.currentTarget.style.opacity = '1'"
                    >
                        Explorar Comunidades
                    </Link>
                </div>
            </div>
        </div>

        <!-- FLOOR GRADIENT -->
        <div
            style="
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 100px;
                background: linear-gradient(to top, #9dcee8 0%, transparent 100%);
                pointer-events: none;
                z-index: 1;
            "
        />
    </div>

    <!-- Ban modal -->
    <Transition name="ban-overlay">
        <div
            v-if="banModalVisible && punishmentModal"
            style="position:fixed;inset:0;z-index:300;background:rgba(0,0,0,0.6);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;padding:20px"
        >
            <Transition name="ban-pop" appear>
                <div style="background:white;border-radius:20px;max-width:400px;width:100%;box-shadow:0 24px 80px rgba(0,0,0,0.25);overflow:hidden">
                    <div style="background:#991b1b;padding:22px 24px;display:flex;align-items:center;gap:14px">
                        <span style="font-size:32px;line-height:1">⛔</span>
                        <div>
                            <p style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:0.08em;margin:0 0 2px">Acesso negado</p>
                            <h2 style="font-size:18px;font-weight:800;color:white;margin:0">Conta banida</h2>
                        </div>
                    </div>
                    <div style="padding:24px">
                        <p style="font-size:13px;color:#64748b;margin:0 0 16px;line-height:1.5">
                            A tua conta foi <strong style="color:#991b1b">permanentemente banida</strong> da plataforma e não podes iniciar sessão.
                        </p>
                        <p style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.07em;margin:0 0 8px">Motivo</p>
                        <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:12px;padding:14px 16px;margin-bottom:20px">
                            <p style="font-size:14px;color:#374151;margin:0;line-height:1.55">
                                {{ punishmentModal.reason || 'Sem motivo especificado.' }}
                            </p>
                        </div>
                        <button
                            @click="banModalVisible = false"
                            style="width:100%;padding:13px;border-radius:12px;background:#991b1b;border:none;color:white;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 16px #991b1b55"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style>
.ban-overlay-enter-active, .ban-overlay-leave-active { transition: opacity 0.2s ease; }
.ban-overlay-enter-from, .ban-overlay-leave-to { opacity: 0; }
.ban-pop-enter-active { transition: opacity 0.25s ease, transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); }
.ban-pop-leave-active { transition: opacity 0.18s ease, transform 0.2s ease; }
.ban-pop-enter-from, .ban-pop-leave-to { opacity: 0; transform: scale(0.9) translateY(12px); }

/* Welcome page always shows the light theme regardless of html.dark */
html.dark body:has(.welcome-page)::before {
    background-image: url('/images/realistic-style-soap-bubbles-background.png') !important;
    opacity: 0.22 !important;
}
html.dark body:has(.welcome-page) {
    background: linear-gradient(160deg, #f0f8ff 0%, #daeef9 50%, #c5e5f5 100%) !important;
}
</style>
