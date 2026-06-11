<script setup>
import { Link } from '@inertiajs/vue3';
import BackgroundAmbient from '@/Components/BackgroundAmbient.vue';
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

        <BackgroundAmbient />

        <!-- Logo -->
        <div style="margin-bottom: 28px; position: relative; z-index: 1">
            <Link href="/" style="text-decoration: none">
                <span style="font-weight: 900; font-size: 34px; color: #009ac7; letter-spacing: -2px">bubbles</span>
            </Link>
        </div>

        <!-- Glass card -->
        <div class="guest-card">
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

.guest-card {
    width: 100%;
    max-width: 420px;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 24px;
    padding: 36px;
    border: 1px solid rgba(78, 188, 255, 0.22);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.6),
        0 20px 60px rgba(0, 154, 199, 0.12),
        0 4px 16px rgba(0, 0, 0, 0.06);
    position: relative;
    z-index: 1;
    overflow: hidden;
}
.guest-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 44%;
    border-radius: 24px 24px 0 0;
    background: linear-gradient(to bottom, rgba(255, 255, 255, 0.18), transparent);
    pointer-events: none;
}

/* Auth inputs */
.auth-input {
    background: rgba(240, 248, 255, 0.8);
    border: 1.5px solid rgba(78, 188, 255, 0.28);
    border-radius: 99px;
    padding: 11px 18px;
    font-size: 13px;
    color: #3a6478;
    outline: none;
    font-family: inherit;
    transition: border-color 0.2s, box-shadow 0.2s;
    width: 100%;
    box-sizing: border-box;
}
.auth-input:focus {
    border-color: #009ac7;
    box-shadow: 0 0 0 3px rgba(0, 154, 199, 0.1);
}

/* Auth primary button */
.auth-btn-primary {
    width: 100%;
    padding: 13px;
    border-radius: 99px;
    border: 1px solid rgba(255, 255, 255, 0.45);
    background: linear-gradient(180deg, rgba(255,255,255,.22) 0%, rgba(255,255,255,.04) 50%, rgba(0,0,0,.06) 100%), linear-gradient(180deg, #4ebcff 0%, #009ac7 55%, #006d8e 100%);
    color: white;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 6px 24px rgba(0,154,199,.5), inset 0 1px 0 rgba(255,255,255,.35);
    transition: opacity 0.2s;
    margin-top: 4px;
    font-family: inherit;
}
.auth-btn-primary:hover { opacity: 0.88; }
.auth-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

/* Auth Google button */
.auth-btn-google {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px;
    border-radius: 99px;
    border: 1.5px solid rgba(78, 188, 255, 0.25);
    background: rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(8px);
    color: #3a6478;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    margin-top: 4px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.5);
}
.auth-btn-google:hover {
    border-color: rgba(0, 154, 199, 0.45);
    background: rgba(255, 255, 255, 0.82);
    box-shadow: 0 2px 12px rgba(0, 154, 199, 0.15), inset 0 1px 0 rgba(255,255,255,.5);
}
</style>
