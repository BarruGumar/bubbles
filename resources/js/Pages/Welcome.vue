<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});

const authUser = computed(() => usePage().props.auth?.user);
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
                            padding: '8px 16px',
                            borderRadius: '10px',
                            background: '#009ac7',
                            color: 'white',
                            textDecoration: 'none',
                            fontSize: '13px',
                            fontWeight: '600',
                            transition: 'background .15s, box-shadow .15s',
                            cursor: 'pointer',
                            display: 'inline-block',
                            boxShadow: '0 4px 12px #009ac722',
                        }"
                        @mouseenter="
                            $event.currentTarget.style.background = '#0088a8';
                            $event.currentTarget.style.boxShadow = '0 6px 16px #009ac744';
                        "
                        @mouseleave="
                            $event.currentTarget.style.background = '#009ac7';
                            $event.currentTarget.style.boxShadow = '0 4px 12px #009ac722';
                        "
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
                            padding: '12px 32px',
                            borderRadius: '12px',
                            background: '#009ac7',
                            color: 'white',
                            textDecoration: 'none',
                            fontSize: '14px',
                            fontWeight: '700',
                            transition: 'background .2s, box-shadow .2s',
                            cursor: 'pointer',
                            display: 'inline-block',
                            boxShadow: '0 6px 20px #009ac733',
                            border: 'none',
                        }"
                        @mouseenter="
                            $event.currentTarget.style.background = '#0088a8';
                            $event.currentTarget.style.boxShadow = '0 8px 28px #009ac755';
                        "
                        @mouseleave="
                            $event.currentTarget.style.background = '#009ac7';
                            $event.currentTarget.style.boxShadow = '0 6px 20px #009ac733';
                        "
                    >
                        Entrar
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        :style="{
                            padding: '12px 32px',
                            borderRadius: '12px',
                            background: 'rgba(255,255,255,0.85)',
                            color: '#009ac7',
                            textDecoration: 'none',
                            fontSize: '14px',
                            fontWeight: '700',
                            transition: 'background .2s, box-shadow .2s',
                            cursor: 'pointer',
                            display: 'inline-block',
                            boxShadow: '0 2px 10px rgba(0,0,0,0.08)',
                            border: '1px solid rgba(0,154,199,0.2)',
                        }"
                        @mouseenter="
                            $event.currentTarget.style.background = 'white';
                            $event.currentTarget.style.boxShadow = '0 4px 16px rgba(0,0,0,0.12)';
                        "
                        @mouseleave="
                            $event.currentTarget.style.background = 'rgba(255,255,255,0.85)';
                            $event.currentTarget.style.boxShadow = '0 2px 10px rgba(0,0,0,0.08)';
                        "
                    >
                        Criar conta
                    </Link>
                </div>

                <!-- Authenticated message -->
                <div v-else-if="authUser" style="pointer-events: all">
                    <Link
                        :href="route('bubbles')"
                        :style="{
                            padding: '12px 32px',
                            borderRadius: '12px',
                            background: '#009ac7',
                            color: 'white',
                            textDecoration: 'none',
                            fontSize: '14px',
                            fontWeight: '700',
                            transition: 'background .2s, box-shadow .2s',
                            cursor: 'pointer',
                            display: 'inline-block',
                            boxShadow: '0 6px 20px #009ac733',
                            border: 'none',
                        }"
                        @mouseenter="
                            $event.currentTarget.style.background = '#0088a8';
                            $event.currentTarget.style.boxShadow = '0 8px 28px #009ac755';
                        "
                        @mouseleave="
                            $event.currentTarget.style.background = '#009ac7';
                            $event.currentTarget.style.boxShadow = '0 6px 20px #009ac733';
                        "
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
</template>

<style>
/* Welcome page always shows the light theme regardless of html.dark */
html.dark body:has(.welcome-page)::before {
    background-image: url('/images/realistic-style-soap-bubbles-background.png') !important;
    opacity: 0.22 !important;
}
html.dark body:has(.welcome-page) {
    background: linear-gradient(160deg, #f0f8ff 0%, #daeef9 50%, #c5e5f5 100%) !important;
}
</style>
