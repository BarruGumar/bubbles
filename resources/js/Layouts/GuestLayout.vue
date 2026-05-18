<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="guest-page min-h-screen flex flex-col items-center justify-center relative overflow-hidden"
        style="
            background-color: #daeef9;
            background-image:
                linear-gradient(
                    160deg,
                    rgba(240, 248, 255, 0.55) 0%,
                    rgba(218, 238, 249, 0.48) 50%,
                    rgba(197, 229, 245, 0.5) 100%
                ),
                url('/images/realistic-style-soap-bubbles-background.png');
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            font-family: 'Segoe UI', system-ui, sans-serif;
        "
    >
        <!-- Grid -->
        <svg class="absolute inset-0 w-full h-full pointer-events-none" style="opacity: 0.04">
            <defs>
                <pattern id="guest-grid" width="44" height="44" patternUnits="userSpaceOnUse">
                    <path d="M 44 0 L 0 0 0 44" fill="none" stroke="#009ac7" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#guest-grid)" />
        </svg>

        <!-- Ambient orbs -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div
                style="
                    position: absolute;
                    top: -100px;
                    left: -100px;
                    width: 380px;
                    height: 380px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #009ac71a 0%, transparent 70%);
                "
            />
            <div
                style="
                    position: absolute;
                    bottom: -80px;
                    right: -80px;
                    width: 340px;
                    height: 340px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #4ebcff12 0%, transparent 70%);
                "
            />
            <div
                style="
                    position: absolute;
                    top: 30%;
                    right: 10%;
                    width: 200px;
                    height: 200px;
                    border-radius: 50%;
                    background: radial-gradient(circle, #9b6bdf0a 0%, transparent 70%);
                "
            />
        </div>

        <!-- Logo -->
        <div style="margin-bottom: 28px; position: relative; z-index: 1">
            <Link href="/" style="text-decoration: none">
                <span style="font-weight: 900; font-size: 34px; color: #009ac7; letter-spacing: -2px">bubbles</span>
            </Link>
        </div>

        <!-- Glass card -->
        <div
            style="
                width: 100%;
                max-width: 420px;
                background: rgba(255, 255, 255, 0.88);
                backdrop-filter: blur(20px);
                border-radius: 22px;
                padding: 36px;
                border: 1px solid #4ebcff2a;
                box-shadow: 0 16px 56px #009ac71a;
                position: relative;
                z-index: 1;
            "
        >
            <slot />
        </div>

        <!-- Footer hint -->
        <p style="margin-top: 24px; font-size: 11px; color: #009ac780; position: relative; z-index: 1">
            um mundo vivo de comunidades
        </p>
    </div>
</template>

<style>
/* Guest pages always show the light theme regardless of html.dark */
html.dark .guest-page {
    background-color: #daeef9 !important;
}
/* Suppress the dark body::before bubble overlay on guest pages */
html.dark body:has(.guest-page)::before {
    background-image: url('/images/realistic-style-soap-bubbles-background.png') !important;
    opacity: 0.22 !important;
}
html.dark body:has(.guest-page) {
    background: linear-gradient(160deg, #f0f8ff 0%, #daeef9 50%, #c5e5f5 100%) !important;
}
</style>
