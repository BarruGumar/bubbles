<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useAudio } from '@/Composables/useAudio';

const {
    bgmVolume, sfxVolume, bgmEnabled, sfxEnabled, muted,
    toggleMuted, setBgmVolume, setSfxVolume, setBgmEnabled, setSfxEnabled,
} = useAudio();

const panelOpen = ref(false);
const btnEl     = ref(null);
const panelEl   = ref(null);

function toggle() { panelOpen.value = !panelOpen.value; }

function onDocClick(e) {
    if (!panelOpen.value) return;
    if (!btnEl.value?.contains(e.target) && !panelEl.value?.contains(e.target)) {
        panelOpen.value = false;
    }
}

onMounted(()   => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div style="position: relative">
        <!-- ── Trigger button ─────────────────────────────────── -->
        <button
            ref="btnEl"
            @click.stop="toggle"
            :title="muted ? 'Áudio silenciado' : 'Controlo de áudio'"
            :style="{
                width: '34px', height: '34px', borderRadius: '50%',
                border: '1.5px solid var(--nav-border)',
                background: panelOpen ? 'rgba(0,154,199,0.08)' : 'transparent',
                color: muted ? '#c74a6b' : panelOpen ? '#009ac7' : 'var(--text-3)',
                cursor: 'pointer', display: 'flex',
                alignItems: 'center', justifyContent: 'center',
                transition: 'all 0.2s', flexShrink: 0,
            }"
        >
            <!-- Muted icon -->
            <svg v-if="muted" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="1" y1="1" x2="23" y2="23"/>
                <path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/>
                <path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"/>
                <line x1="12" y1="19" x2="12" y2="23"/>
                <line x1="8" y1="23" x2="16" y2="23"/>
            </svg>
            <!-- Active icon -->
            <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
            </svg>
        </button>

        <!-- ── Panel ──────────────────────────────────────────── -->
        <Transition name="audio-panel">
            <div
                v-if="panelOpen"
                ref="panelEl"
                @click.stop
                style="
                    position: absolute; right: 0; top: calc(100% + 10px);
                    width: 230px;
                    background: var(--dropdown-bg);
                    backdrop-filter: blur(20px);
                    border: 1px solid var(--nav-border);
                    border-radius: 14px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
                    padding: 12px 14px 14px;
                    z-index: 60;
                "
            >
                <!-- Header row: mute toggle -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <span style="font-size:12px;font-weight:700;color:var(--text)">Áudio</span>
                    <button
                        @click="toggleMuted"
                        :style="{
                            padding: '4px 10px', borderRadius: '99px', border: 'none',
                            cursor: 'pointer', fontSize: '11px', fontWeight: '700',
                            background: muted ? '#c74a6b18' : '#009ac714',
                            color: muted ? '#c74a6b' : '#009ac7',
                            transition: 'all .15s',
                        }"
                    >{{ muted ? '🔇 Silenciado' : '🔊 Ativo' }}</button>
                </div>

                <!-- BGM row -->
                <div style="margin-bottom:10px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                        <label style="font-size:11px;font-weight:600;color:var(--text-2)">Música de fundo</label>
                        <!-- Toggle BGM -->
                        <button
                            @click="setBgmEnabled(!bgmEnabled)"
                            :style="{
                                width: '28px', height: '16px', borderRadius: '99px', border: 'none',
                                cursor: 'pointer', position: 'relative',
                                background: bgmEnabled ? '#009ac7' : '#cdd8e0',
                                transition: 'background .2s',
                            }"
                        >
                            <span :style="{
                                position: 'absolute', top: '2px',
                                left: bgmEnabled ? '14px' : '2px',
                                width: '12px', height: '12px', borderRadius: '50%',
                                background: 'white', transition: 'left .2s',
                                boxShadow: '0 1px 3px rgba(0,0,0,.2)',
                            }"/>
                        </button>
                    </div>
                    <input
                        type="range" min="0" max="0.30" step="0.01"
                        :value="bgmVolume"
                        @input="setBgmVolume(+$event.target.value)"
                        :disabled="!bgmEnabled || muted"
                        class="audio-slider"
                        :style="{ background: `linear-gradient(to right, #009ac7 0%, #009ac7 ${Math.round((bgmVolume/0.30)*100)}%, #eef2f8 0%)` }"
                    />
                    <div style="text-align:right;font-size:10px;color:var(--text-3);margin-top:2px">
                        {{ Math.round((bgmVolume / 0.30) * 100) }}%
                    </div>
                </div>

                <!-- SFX row -->
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                        <label style="font-size:11px;font-weight:600;color:var(--text-2)">Efeitos sonoros</label>
                        <!-- Toggle SFX -->
                        <button
                            @click="setSfxEnabled(!sfxEnabled)"
                            :style="{
                                width: '28px', height: '16px', borderRadius: '99px', border: 'none',
                                cursor: 'pointer', position: 'relative',
                                background: sfxEnabled ? '#009ac7' : '#cdd8e0',
                                transition: 'background .2s',
                            }"
                        >
                            <span :style="{
                                position: 'absolute', top: '2px',
                                left: sfxEnabled ? '14px' : '2px',
                                width: '12px', height: '12px', borderRadius: '50%',
                                background: 'white', transition: 'left .2s',
                                boxShadow: '0 1px 3px rgba(0,0,0,.2)',
                            }"/>
                        </button>
                    </div>
                    <input
                        type="range" min="0" max="0.30" step="0.01"
                        :value="sfxVolume"
                        @input="setSfxVolume(+$event.target.value)"
                        :disabled="!sfxEnabled || muted"
                        class="audio-slider"
                        :style="{ background: `linear-gradient(to right, #009ac7 0%, #009ac7 ${Math.round((sfxVolume/0.30)*100)}%, #eef2f8 0%)` }"
                    />
                    <div style="text-align:right;font-size:10px;color:var(--text-3);margin-top:2px">
                        {{ Math.round((sfxVolume / 0.30) * 100) }}%
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.audio-slider {
    width: 100%;
    height: 4px;
    border-radius: 99px;
    appearance: none;
    cursor: pointer;
    outline: none;
}
.audio-slider:disabled { opacity: 0.4; cursor: not-allowed; }
.audio-slider::-webkit-slider-thumb {
    appearance: none;
    width: 14px; height: 14px;
    border-radius: 50%;
    background: #009ac7;
    border: 2px solid white;
    box-shadow: 0 1px 4px rgba(0,154,199,.4);
    cursor: pointer;
}
.audio-slider::-moz-range-thumb {
    width: 14px; height: 14px;
    border-radius: 50%;
    background: #009ac7;
    border: 2px solid white;
    cursor: pointer;
}

.audio-panel-enter-active { transition: opacity 0.18s ease, transform 0.2s cubic-bezier(0.2,0.8,0.2,1); }
.audio-panel-leave-active { transition: opacity 0.14s ease, transform 0.16s ease; }
.audio-panel-enter-from, .audio-panel-leave-to { opacity: 0; transform: translateY(-6px) scale(0.97); }
</style>
