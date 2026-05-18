import { ref } from 'vue';
import axios from 'axios';

// Singleton reactive state shared across all components
const isDark = ref(
    typeof document !== 'undefined'
        ? document.documentElement.classList.contains('dark')
        : false,
);

export function applyTheme(theme) {
    const dark = theme === 'dark';
    isDark.value = dark;
    if (typeof document !== 'undefined') {
        document.documentElement.classList.toggle('dark', dark);
        try { localStorage.setItem('bubbles_theme', theme); } catch (_) {}
    }
}

export function useTheme() {
    async function toggle() {
        const next = isDark.value ? 'light' : 'dark';
        applyTheme(next);
        try {
            await axios.patch(route('profile.theme'), { theme: next });
        } catch (_) {}
    }

    return { isDark, toggle };
}
