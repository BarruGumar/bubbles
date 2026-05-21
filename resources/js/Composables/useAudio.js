import { ref } from 'vue';
import { BGM as BGM_MAP, SFX as SFX_MAP } from '@/Constants/audioMap';

// ── Module-level singleton ────────────────────────────────────────
// All state lives here so every component shares the same instance.

const bgmVolume  = ref(0.30);
const sfxVolume  = ref(0.30);
const bgmEnabled = ref(true);
const sfxEnabled = ref(true);
const muted      = ref(false);
const currentBgmKey = ref(null); // track we want to play (for UI + restart logic)

let bgmAudio      = null;   // the live <audio> element
let bgmPlayingKey = '';     // key that bgmAudio is playing
let pendingBgmKey = null;   // queued before first interaction
let hasInteracted = false;

let sfxCache   = {};        // key → <audio>
let clickState = 0;         // alternates click1/click2
let hoverLocked = false;    // cooldown flag for bubble hover

// ── localStorage ──────────────────────────────────────────────────
const LS_KEY = 'bubbles_audio_prefs';

function loadPrefs() {
    try {
        const p = JSON.parse(localStorage.getItem(LS_KEY) ?? 'null');
        if (!p) return;
        if (typeof p.bgmVolume  === 'number')  bgmVolume.value  = p.bgmVolume;
        if (typeof p.sfxVolume  === 'number')  sfxVolume.value  = p.sfxVolume;
        if (typeof p.bgmEnabled === 'boolean') bgmEnabled.value = p.bgmEnabled;
        if (typeof p.sfxEnabled === 'boolean') sfxEnabled.value = p.sfxEnabled;
        if (typeof p.muted      === 'boolean') muted.value      = p.muted;
    } catch { /* ignore */ }
}

function savePrefs() {
    try {
        localStorage.setItem(LS_KEY, JSON.stringify({
            bgmVolume:  bgmVolume.value,
            sfxVolume:  sfxVolume.value,
            bgmEnabled: bgmEnabled.value,
            sfxEnabled: sfxEnabled.value,
            muted:      muted.value,
        }));
    } catch { /* ignore */ }
}

loadPrefs();

// ── Autoplay unlock ───────────────────────────────────────────────
// Browsers block audio.play() until a real user interaction fires.
function onFirstInteraction() {
    if (hasInteracted) return;
    hasInteracted = true;
    ['click', 'touchstart', 'keydown'].forEach(evt =>
        document.removeEventListener(evt, onFirstInteraction, { capture: true }),
    );
    if (pendingBgmKey) {
        const key = pendingBgmKey;
        pendingBgmKey = null;
        _startBgm(key);
    }
}

if (typeof document !== 'undefined') {
    // capture: true fires before stopPropagation on child elements,
    // so buttons with @click.stop don't block the unlock.
    ['click', 'touchstart', 'keydown'].forEach(evt =>
        document.addEventListener(evt, onFirstInteraction, { passive: true, capture: true }),
    );
}

// ── Fade helpers ──────────────────────────────────────────────────
function effectiveVol(base) {
    return muted.value ? 0 : base;
}

function fadeOut(audio, ms, onDone) {
    if (!audio || audio.paused) { onDone?.(); return; }
    const startVol = audio.volume;
    const t0 = performance.now();
    function tick(now) {
        const p = Math.min((now - t0) / ms, 1);
        audio.volume = startVol * (1 - p);
        if (p < 1) requestAnimationFrame(tick);
        else { audio.pause(); audio.currentTime = 0; audio.volume = startVol; onDone?.(); }
    }
    requestAnimationFrame(tick);
}

function fadeIn(audio, targetVol, ms) {
    audio.volume = 0;
    const t0 = performance.now();
    function tick(now) {
        const p = Math.min((now - t0) / ms, 1);
        audio.volume = targetVol * p;
        if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}

// ── BGM ───────────────────────────────────────────────────────────
function _startBgm(key) {
    const src = BGM_MAP[key];
    if (!src) return;

    // Same track already playing — just correct volume
    if (bgmAudio && !bgmAudio.paused && bgmPlayingKey === key) {
        bgmAudio.volume = effectiveVol(bgmVolume.value);
        return;
    }

    // Fade out whatever is playing
    if (bgmAudio) {
        const old = bgmAudio;
        bgmAudio = null;
        bgmPlayingKey = '';
        fadeOut(old, 600, () => { old.src = ''; });
    }

    const audio = new Audio(src);
    audio.loop = true;
    audio.preload = 'auto';
    bgmAudio = audio;
    bgmPlayingKey = key;

    audio.play()
        .then(() => fadeIn(audio, effectiveVol(bgmVolume.value), 800))
        .catch(() => {
            // Autoplay blocked — queue to retry on first user interaction
            bgmAudio = null;
            bgmPlayingKey = '';
            pendingBgmKey = key;
        });
}

function playBgm(key) {
    if (!key) return;
    currentBgmKey.value = key;
    if (!bgmEnabled.value) return;
    _startBgm(key);
}

function stopBgm() {
    currentBgmKey.value = null;
    pendingBgmKey = null;
    if (bgmAudio) {
        const old = bgmAudio;
        bgmAudio = null;
        bgmPlayingKey = '';
        fadeOut(old, 500, () => { old.src = ''; });
    }
}

// ── SFX ───────────────────────────────────────────────────────────
function playSfx(key) {
    if (!sfxEnabled.value || muted.value) return;
    const src = SFX_MAP[key];
    if (!src) { console.warn('[audio] Unknown SFX key:', key); return; }
    if (!sfxCache[key]) {
        const a = new Audio(src);
        a.preload = 'auto';
        sfxCache[key] = a;
    }
    const audio = sfxCache[key];
    audio.volume = sfxVolume.value;
    audio.currentTime = 0;
    audio.play().catch(() => {});
}

// Alternates between click1 and click2 to avoid repetition.
function playClick() {
    playSfx(clickState++ % 2 === 0 ? 'click1' : 'click2');
}

// Hover sound with cooldown to avoid spam on fast mouse movement.
function playHoverBubble() {
    if (hoverLocked) return;
    playSfx('passMouseOnBubble');
    hoverLocked = true;
    setTimeout(() => { hoverLocked = false; }, 180);
}

// ── Controls ──────────────────────────────────────────────────────
function setBgmVolume(v) {
    bgmVolume.value = Math.max(0, Math.min(0.30, v));
    if (bgmAudio) bgmAudio.volume = effectiveVol(bgmVolume.value);
    savePrefs();
}

function setSfxVolume(v) {
    sfxVolume.value = Math.max(0, Math.min(0.30, v));
    savePrefs();
}

function setMuted(v) {
    muted.value = !!v;
    if (bgmAudio) bgmAudio.volume = effectiveVol(bgmVolume.value);
    savePrefs();
}

function toggleMuted() { setMuted(!muted.value); }

function setBgmEnabled(v) {
    bgmEnabled.value = !!v;
    if (!v) {
        // Stop audio but remember which track was requested
        if (bgmAudio) {
            const old = bgmAudio;
            bgmAudio = null;
            bgmPlayingKey = '';
            fadeOut(old, 500, () => { old.src = ''; });
        }
    } else if (currentBgmKey.value && hasInteracted) {
        _startBgm(currentBgmKey.value);
    }
    savePrefs();
}

function setSfxEnabled(v) {
    sfxEnabled.value = !!v;
    savePrefs();
}

// ── Composable export ─────────────────────────────────────────────
export function useAudio() {
    return {
        // Reactive state (read-only for consumers)
        bgmVolume,
        sfxVolume,
        bgmEnabled,
        sfxEnabled,
        muted,
        currentBgmKey,
        // BGM
        playBgm,
        stopBgm,
        // SFX
        playSfx,
        playClick,
        playHoverBubble,
        // Controls
        setBgmVolume,
        setSfxVolume,
        setMuted,
        toggleMuted,
        setBgmEnabled,
        setSfxEnabled,
    };
}
