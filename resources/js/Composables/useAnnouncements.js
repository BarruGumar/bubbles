import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const LS_KEY = 'bubbles_dismissed_announcements';

const dismissed = ref((() => {
    try { return JSON.parse(localStorage.getItem(LS_KEY) ?? '[]'); } catch { return []; }
})());

export function useAnnouncements() {
    const page = usePage();

    const visible = computed(() =>
        (page.props.active_announcements ?? []).filter(a => !dismissed.value.includes(a.id))
    );

    function dismiss(id) {
        if (dismissed.value.includes(id)) return;
        dismissed.value = [...dismissed.value, id];
        try { localStorage.setItem(LS_KEY, JSON.stringify(dismissed.value)); } catch {}
    }

    return { visible, dismiss };
}
